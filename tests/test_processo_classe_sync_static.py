from pathlib import Path


def test_medida_protetiva_noop_reconciles_priorities_before_return() -> None:
    service_path = Path(__file__).resolve().parents[1] / "app/services/ProcessoClasseSyncService.php"
    source = service_path.read_text(encoding="utf-8")

    noop_branch_start = source.index("if ($existingCodigo === $codigo) {")
    noop_branch_end = source.index("'action' => 'noop'", noop_branch_start)
    noop_branch_prefix = source[noop_branch_start:noop_branch_end]

    assert "aplicarPrioridadesMedidaProtetivaSeClasse1268($pid, $codigo, $numeroFormatado)" in noop_branch_prefix
