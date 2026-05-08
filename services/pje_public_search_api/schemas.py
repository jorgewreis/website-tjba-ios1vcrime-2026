from typing import Any, Dict, List, Optional

from pydantic import BaseModel, Field


class Documento(BaseModel):
    title: str
    url: Optional[str] = None


class MetaConsulta(BaseModel):
    tribunal: str = "tjba"
    url_consultada: Optional[str] = None
    url_resultado: Optional[str] = None
    duracao_ms: int = 0
    estrategia: str = "pje_publico_playwright"
    avisos: List[str] = Field(default_factory=list)


class ProcessoResponse(BaseModel):
    resumo: Dict[str, Any] = Field(default_factory=dict)
    partes: List[Dict[str, str]] = Field(default_factory=list)
    andamentos: List[str] = Field(default_factory=list)
    documentos: List[Documento] = Field(default_factory=list)
    meta_consulta: MetaConsulta
    dados_brutos: Dict[str, Any] = Field(default_factory=dict)
