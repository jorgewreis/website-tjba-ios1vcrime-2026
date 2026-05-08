from fastapi import FastAPI, HTTPException, Query

from schemas import ProcessoResponse
from scraper import check_browser, consultar_processo, validate_cnj

app = FastAPI(
    title="PJe Public Search Test API",
    description="Servico isolado de testes para consulta publica PJe via Playwright.",
    version="0.1.0",
)


@app.get("/health")
async def health():
    try:
        return await check_browser()
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"Chromium indisponivel: {exc}") from exc


@app.get("/pje/{numero}", response_model=ProcessoResponse)
async def consultar(numero: str):
    try:
        validate_cnj(numero)
        return await consultar_processo(numero, include_raw=False)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"Falha na consulta publica PJe: {exc}") from exc


@app.get("/pje/{numero}/raw", response_model=ProcessoResponse)
async def consultar_raw(numero: str, include_raw: bool = Query(default=True)):
    try:
        validate_cnj(numero)
        return await consultar_processo(numero, include_raw=include_raw)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"Falha na consulta publica PJe: {exc}") from exc
