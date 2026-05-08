import os
import re
import time
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urljoin

from playwright.async_api import Page, TimeoutError as PlaywrightTimeoutError, async_playwright

from schemas import Documento, MetaConsulta, ProcessoResponse


DEFAULT_TJBA_URLS = [
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
    await _open_first_result_if_available(page, target_url)

    await page.wait_for_load_state("domcontentloaded")
    await _settle(page)

    parsed = await _parse_process_page(page, target_url)
    avisos = parsed.pop("avisos", [])
    resumo = parsed.get("resumo", {})
    resumo.setdefault("numero_consultado", formatted)

    meta = MetaConsulta(
        url_consultada=target_url,
        url_resultado=page.url,
        duracao_ms=int((time.perf_counter() - started_at) * 1000),
        avisos=avisos,
    )

    raw_payload = parsed if include_raw else {}
    return ProcessoResponse(
        resumo=resumo,
        partes=parsed.get("partes", []),
        andamentos=parsed.get("andamentos", []),
        documentos=[Documento(**item) for item in parsed.get("documentos", [])],
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

            const documentos = [];
            document.querySelectorAll('a').forEach((link) => {
                const text = clean(link.textContent).replace('Visualizar documentos', '').trim();
                const onclick = link.getAttribute('onclick') || '';
                const href = link.getAttribute('href') || '';
                const looksLikeDoc = /documento|visualizar|download|anexo/i.test(`${text} ${onclick} ${href}`);
                if (!text || !looksLikeDoc) return;

                const match = onclick.match(/['\"]([^'\"]*(?:Documento|documento|download|Download|about:blank)[^'\"]*)['\"]/);
                let url = match ? match[1] : href;
                if (url && url !== 'about:blank') {
                    url = new URL(url, baseUrl).toString();
                }
                documentos.push({ title: text, url: url || null });
            });

            if (!Object.keys(resumo).length && !partes.length && !andamentos.length) {
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
