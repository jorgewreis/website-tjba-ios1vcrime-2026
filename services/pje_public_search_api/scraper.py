import os
import re
import time
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urljoin

from playwright.async_api import Page, TimeoutError as PlaywrightTimeoutError, async_playwright

from schemas import Documento, MetaConsulta, ProcessoResponse

# Limites para diagnostico em modo raw (evitar respostas gigantes).
_DIAG_TEXT_SNIPPET_MAX = 2800
_DIAG_INPUTS_MAX = 35
_DIAG_LINKS_MAX = 45
_DIAG_FORMS_MAX = 12
_DIAG_BODY_HTML_MAX = 4500
_DIAG_ONCLICK_MAX = 400

_INSTITUTIONAL_DOC_URL_RES = [
    re.compile(r"cnj\.jus\.br", re.I),
    re.compile(r"/atos-normativos", re.I),
    re.compile(r"atos-normativos\?", re.I),
    re.compile(r"conselho\s+nacional\s+de\s+just", re.I),
]
_INSTITUTIONAL_DOC_TITLE_RES = [
    re.compile(r"resolu[cç][aã]o\s+n[ºo°]\s*121", re.I),
    re.compile(r"conselho\s+nacional\s+de\s+just", re.I),
]


DEFAULT_TJBA_URLS = [
    # Host dedicado costuma ser o destino apos redirect e o detalhe do processo.
    "https://consultapublicapje.tjba.jus.br/pje/ConsultaPublica/listView.seam",
    "https://pje.tjba.jus.br/pje/ConsultaPublica/listView.seam",
    "https://pje2g.tjba.jus.br/pje/ConsultaPublica/listView.seam",
]


def only_digits(value: str) -> str:
    return re.sub(r"\D+", "", value or "")


def format_cnj(digits: str) -> str:
    return "{}-{}.{}.{}.{}.{}".format(
        digits[0:7],
        digits[7:9],
        digits[9:13],
        digits[13:14],
        digits[14:16],
        digits[16:20],
    )


def validate_cnj(value: str) -> str:
    digits = only_digits(value)
    if len(digits) != 20:
        raise ValueError("Numero CNJ invalido. Informe 20 digitos.")
    return digits


def get_target_urls() -> List[str]:
    explicit_urls = os.getenv("PJE_PUBLIC_BASE_URLS", "").strip()
    single_url = os.getenv("PJE_PUBLIC_BASE_URL", "").strip()

    raw_urls = explicit_urls or single_url
    if raw_urls:
        urls = [item.strip() for item in raw_urls.split(",") if item.strip()]
        if urls:
            return urls

    return DEFAULT_TJBA_URLS


def get_timeout_ms() -> int:
    raw = os.getenv("PJE_PUBLIC_TIMEOUT_SECONDS", "20").strip()
    try:
        seconds = max(5, int(raw))
    except ValueError:
        seconds = 20
    return seconds * 1000


def _is_institutional_documento(item: Dict[str, Any]) -> bool:
    url = (item.get("url") or "").strip()
    title = (item.get("title") or "").strip()
    blob = f"{url} {title}"
    if any(rx.search(blob) for rx in _INSTITUTIONAL_DOC_URL_RES):
        return True
    if any(rx.search(title) for rx in _INSTITUTIONAL_DOC_TITLE_RES):
        return True
    return False


def _filter_institutional_documentos(documentos: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return [d for d in documentos if not _is_institutional_documento(d)]


def _resumo_tem_dados_processuais(resumo: Dict[str, Any]) -> bool:
    for key, value in (resumo or {}).items():
        if key == "numero_consultado" or key == "resultado":
            continue
        if str(value or "").strip():
            return True
    return False


def _resultado_reconhecido(resumo: Dict[str, Any], partes: List[Any], andamentos: List[Any], documentos: List[Any]) -> bool:
    if partes or andamentos or documentos:
        return True
    return _resumo_tem_dados_processuais(resumo)


def _avisos_indicam_pagina_nao_reconhecida(avisos: List[str]) -> bool:
    needle = "nenhum bloco processual reconhecido"
    return any(needle in (a or "").lower() for a in (avisos or []))


async def _collect_diagnostico_pagina(page: Page) -> Dict[str, Any]:
    """Estrutura enxuta para depurar listView/redirect/captcha sem vazar HTML inteiro."""
    return await page.evaluate(
        """(limits) => {
            const clean = (value) => (value || '').replace(/\\s+/g, ' ').trim();
            const clip = (value, max) => {
                const text = clean(value);
                if (!max || text.length <= max) return text;
                return text.slice(0, max) + '…';
            };

            const visibleish = (el) => {
                if (!el) return false;
                const style = window.getComputedStyle(el);
                if (style && (style.visibility === 'hidden' || style.display === 'none')) return false;
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0;
            };

            const inputs = Array.from(document.querySelectorAll('input'))
                .filter(visibleish)
                .slice(0, limits.inputsMax)
                .map((input) => ({
                    id: input.id || null,
                    name: input.name || null,
                    type: (input.getAttribute('type') || 'text').toLowerCase(),
                    placeholder: clip(input.getAttribute('placeholder') || '', 120),
                    value: clip(input.value || '', 160),
                }));

            const linkLike = Array.from(document.querySelectorAll('a, button, input[type="submit"], input[type="button"]'))
                .filter(visibleish)
                .slice(0, limits.linksMax)
                .map((node) => {
                    const tag = node.tagName.toLowerCase();
                    const text = clip(node.textContent || '', 140);
                    const href = node.getAttribute('href') || null;
                    const onclick = clip(node.getAttribute('onclick') || '', limits.onclickMax);
                    const value = tag === 'input' ? clip(node.getAttribute('value') || '', 120) : null;
                    const label = text || value || clip(href || onclick || '', 140);
                    return { tag, text: text || value, href, onclick: onclick || null };
                })
                .filter((row) => row.text || row.href || row.onclick);

            const forms = Array.from(document.querySelectorAll('form')).slice(0, limits.formsMax).map((form) => ({
                id: form.id || null,
                name: form.name || null,
                action: form.getAttribute('action') || null,
                method: (form.getAttribute('method') || 'get').toLowerCase(),
            }));

            return {
                final_url: window.location.href,
                title: document.title || '',
                text_snippet: clip(document.body ? document.body.innerText : '', limits.textMax),
                inputs,
                links_botoes: linkLike,
                forms,
                body_html_snippet: clip(document.body ? document.body.innerHTML : '', limits.htmlMax),
            };
        }""",
        {
            "textMax": _DIAG_TEXT_SNIPPET_MAX,
            "inputsMax": _DIAG_INPUTS_MAX,
            "linksMax": _DIAG_LINKS_MAX,
            "formsMax": _DIAG_FORMS_MAX,
            "htmlMax": _DIAG_BODY_HTML_MAX,
            "onclickMax": _DIAG_ONCLICK_MAX,
        },
    )


async def check_browser() -> Dict[str, Any]:
    start = time.perf_counter()
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        await browser.close()

    return {
        "ok": True,
        "browser": "chromium",
        "duracao_ms": int((time.perf_counter() - start) * 1000),
        "targets": get_target_urls(),
    }


async def consultar_processo(numero: str, include_raw: bool = False) -> ProcessoResponse:
    digits = validate_cnj(numero)
    formatted = format_cnj(digits)
    timeout_ms = get_timeout_ms()
    errors: List[str] = []

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(
            headless=True,
            args=["--disable-dev-shm-usage", "--no-sandbox"],
        )
        try:
            context = await browser.new_context(
                locale="pt-BR",
                viewport={"width": 1366, "height": 900},
            )
            page = await context.new_page()
            page.set_default_timeout(timeout_ms)

            for target_url in get_target_urls():
                start = time.perf_counter()
                try:
                    return await _consultar_em_url(
                        page=page,
                        target_url=target_url,
                        digits=digits,
                        formatted=formatted,
                        started_at=start,
                        include_raw=include_raw,
                    )
                except Exception as exc:
                    errors.append(f"{target_url}: {exc}")

            meta = MetaConsulta(
                url_consultada=get_target_urls()[0] if get_target_urls() else None,
                duracao_ms=0,
                avisos=errors or ["Nenhuma base de consulta publica retornou dados."],
            )
            return ProcessoResponse(
                resumo={"numero_consultado": formatted, "resultado": "Nao encontrado"},
                meta_consulta=meta,
                dados_brutos={"erros": errors} if include_raw else {},
            )
        finally:
            await browser.close()


async def _consultar_em_url(
    page: Page,
    target_url: str,
    digits: str,
    formatted: str,
    started_at: float,
    include_raw: bool,
) -> ProcessoResponse:
    await page.goto(target_url, wait_until="domcontentloaded")
    await _fill_process_number(page, formatted, digits)
    await _submit_search(page)
    await _open_first_result_if_available(page, page.url)

    await page.wait_for_load_state("domcontentloaded")
    await _settle(page)

    page_url = page.url
    parsed = await _parse_process_page(page, page_url)
    avisos = list(parsed.pop("avisos", []) or [])
    resumo = dict(parsed.get("resumo", {}) or {})
    resumo.setdefault("numero_consultado", formatted)

    documentos_raw = list(parsed.get("documentos", []) or [])
    documentos_filtrados = _filter_institutional_documentos(documentos_raw)
    parsed["documentos"] = documentos_filtrados

    reconhecido = _resultado_reconhecido(resumo, parsed.get("partes", []), parsed.get("andamentos", []), documentos_filtrados)
    if not reconhecido:
        resumo["resultado"] = "Nao encontrado ou pagina de resultado nao reconhecida"
        if not _avisos_indicam_pagina_nao_reconhecida(avisos):
            avisos.append("Nenhum bloco processual reconhecido na pagina publica.")
        avisos.append(
            "Possiveis causas: numero inexistente, captcha/bloqueio, mudanca de layout do PJe ou permanencia na tela de listagem."
        )

    raw_payload: Dict[str, Any] = dict(parsed) if include_raw else {}
    if include_raw:
        raw_payload["resumo"] = dict(resumo)
        if len(documentos_raw) != len(documentos_filtrados):
            raw_payload["documentos_detectados_brutos"] = documentos_raw
    if include_raw and not reconhecido:
        try:
            raw_payload["diagnostico_pagina"] = await _collect_diagnostico_pagina(page)
        except Exception as exc:
            raw_payload["diagnostico_pagina"] = {"erro": f"Falha ao coletar diagnostico: {exc}"}

    meta = MetaConsulta(
        url_consultada=target_url,
        url_resultado=page_url,
        duracao_ms=int((time.perf_counter() - started_at) * 1000),
        avisos=avisos,
    )

    return ProcessoResponse(
        resumo=resumo,
        partes=parsed.get("partes", []),
        andamentos=parsed.get("andamentos", []),
        documentos=[Documento(**item) for item in documentos_filtrados],
        meta_consulta=meta,
        dados_brutos=raw_payload,
    )


async def _fill_process_number(page: Page, formatted: str, digits: str) -> None:
    selectors = [
        ".campoProcessoConsultaPublica>.propertyView:first-of-type input",
        "input[id*='numeroProcesso']",
        "input[name*='numeroProcesso']",
        "input[id*='processo']",
        "input[name*='processo']",
        "input[type='text']",
    ]

    for selector in selectors:
        locator = page.locator(selector).first
        try:
            if await locator.count() > 0 and await locator.is_visible():
                await locator.fill(formatted)
                return
        except PlaywrightTimeoutError:
            continue

    filled = await page.evaluate(
        """([formatted, digits]) => {
            const inputs = Array.from(document.querySelectorAll('input'))
                .filter((input) => {
                    const type = (input.getAttribute('type') || 'text').toLowerCase();
                    const text = `${input.id || ''} ${input.name || ''}`.toLowerCase();
                    return !input.disabled
                        && !input.readOnly
                        && ['text', 'search', 'tel', ''].includes(type)
                        && (text.includes('processo') || text.includes('numero'));
                });
            if (!inputs.length) return false;
            inputs[0].value = formatted;
            inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
            inputs[0].dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }""",
        [formatted, digits],
    )
    if not filled:
        raise RuntimeError("Campo de numero do processo nao encontrado na consulta publica.")


async def _submit_search(page: Page) -> None:
    selectors = [
        ".consulta-filtro .btn",
        "input[type='submit']",
        "button[type='submit']",
        "button:has-text('Consultar')",
        "input[value*='Consultar']",
        ".btn",
    ]

    for selector in selectors:
        locator = page.locator(selector).first
        try:
            if await locator.count() > 0 and await locator.is_visible():
                await locator.click()
                await _settle(page)
                return
        except PlaywrightTimeoutError:
            continue

    raise RuntimeError("Botao de consulta nao encontrado na consulta publica.")


async def _open_first_result_if_available(page: Page, base_url: str) -> None:
    candidates = [
        "a[href*='DetalheProcessoConsultaPublica']",
        "a[href*='detalheProcessoConsultaPublica']",
        "a[href*='ConsultaPublica'][href*='Detalhe']",
        "a[onclick*='DetalheProcessoConsultaPublica']",
        "a[onclick*='detalheProcessoConsultaPublica']",
        ".rich-panel.col-sm-9 .btn",
        ".rich-panel .btn",
        "a[onclick*='ConsultaPublica']",
        "button[onclick*='ConsultaPublica']",
        "input[onclick*='ConsultaPublica']",
    ]

    for selector in candidates:
        locator = page.locator(selector).first
        try:
            if await locator.count() == 0 or not await locator.is_visible():
                continue
            onclick = await locator.get_attribute("onclick")
            href = await locator.get_attribute("href")
            extracted = _extract_url_from_js(onclick or "")
            target = extracted or href
            if target and target != "#":
                await page.goto(urljoin(base_url, target), wait_until="domcontentloaded")
            else:
                await locator.click()
            await _settle(page)
            return
        except PlaywrightTimeoutError:
            continue


async def _parse_process_page(page: Page, base_url: str) -> Dict[str, Any]:
    payload = await page.evaluate(
        """(baseUrl) => {
            const clean = (value) => (value || '').replace(/\\s+/g, ' ').trim();
            const byText = (selector) => Array.from(document.querySelectorAll(selector))
                .map((node) => clean(node.textContent))
                .filter(Boolean);

            const resumo = {};
            const partes = [];
            const avisos = [];

            const bolds = byText('.rich-tabpanel-content-position .rich-tabpanel-content > div span.text-bold, span.text-bold');
            if (bolds[0]) partes.push({ campo: 'Polo Ativo', valor: bolds[0] });
            if (bolds[1]) partes.push({ campo: 'Polo Passivo', valor: bolds[1] });

            document.querySelectorAll('table tr').forEach((row) => {
                row.querySelectorAll('td').forEach((cell) => {
                    const label = clean((cell.querySelector('div.name label, label, .name') || {}).textContent);
                    const value = clean((cell.querySelector('div.value, .value') || {}).textContent);
                    if (label && value && !resumo[label]) resumo[label] = value;
                });
            });

            const andamentos = byText(
                '.rich-tabpanel-content-position .rich-tabpanel-content > div:nth-of-type(5) table tbody tr td:nth-of-type(1) span, ' +
                'table tbody tr td span'
            ).filter((text) => /\\d{2}\\/\\d{2}\\/\\d{4}/.test(text));

            const institutional = (href, text) => {
                const blob = `${href} ${text}`.toLowerCase();
                return blob.includes('cnj.jus.br')
                    || blob.includes('atos-normativos')
                    || /resolu[cç][aã]o\\s+n[ºo°]?\\s*121/.test(text)
                    || blob.includes('conselho nacional de just');
            };

            const documentos = [];
            document.querySelectorAll('a').forEach((link) => {
                const text = clean(link.textContent).replace('Visualizar documentos', '').trim();
                const onclick = link.getAttribute('onclick') || '';
                const href = link.getAttribute('href') || '';
                if (!text || institutional(href, text)) return;

                const haystack = `${text} ${onclick} ${href}`.toLowerCase();
                const pjeLike = /consultapublica|pje\\/|seam|documento|popup|richfaces|download|anexo|pdf|visualizar/i.test(haystack);
                const looksLikeDoc = /documento|visualizar|download|anexo|pdf|popup|seam/i.test(haystack);
                if (!looksLikeDoc || (!pjeLike && !/\\.seam|idDocumento|id_documento/i.test(haystack))) return;

                const match = onclick.match(/['\"]([^'\"]*(?:Documento|documento|download|Download|about:blank)[^'\"]*)['\"]/);
                let url = match ? match[1] : href;
                if (url && url !== 'about:blank') {
                    url = new URL(url, baseUrl).toString();
                }
                if (institutional(url || '', text)) return;
                documentos.push({ title: text, url: url || null });
            });

            if (!Object.keys(resumo).length && !partes.length && !andamentos.length && !documentos.length) {
                avisos.push('Nenhum bloco processual reconhecido na pagina publica.');
            }

            return { resumo, partes, andamentos, documentos, avisos };
        }""",
        base_url,
    )

    if not isinstance(payload, dict):
        return {"resumo": {}, "partes": [], "andamentos": [], "documentos": [], "avisos": ["Falha ao interpretar a pagina."]}

    return payload


async def _settle(page: Page) -> None:
    try:
        await page.wait_for_load_state("networkidle", timeout=5000)
    except PlaywrightTimeoutError:
        pass


def _extract_url_from_js(value: str) -> Optional[str]:
    if not value:
        return None

    matches: List[Tuple[str, ...]] = re.findall(r"(['\"])([^'\"]*(?:ConsultaPublica|Processo|Documento)[^'\"]*)\\1", value)
    for _, candidate in matches:
        if candidate and candidate != "about:blank":
            return candidate
    return None
