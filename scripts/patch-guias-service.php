<?php
$p = dirname(__DIR__) . '/app/services/GuiasService.php';
$c = file_get_contents($p);
$c = str_replace("\r\n", "\n", $c);

$c = str_replace(
    "require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';\n\nfinal class GuiasService",
    "require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';\nrequire_once __DIR__ . '/../helpers/CartorioProdutividade.php';\n\nfinal class GuiasService",
    $c,
    $n0
);
if ($n0 !== 1) {
    exit("req $n0");
}

$insCreate = <<<'PHP'

        $uidProd = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($uidProd !== '' && $uidProd !== 'sistema') {
            $temDetalhamento = false;
            foreach (['data_fato', 'dias_multa', 'regime', 'observacoes'] as $k) {
                if (isset($payload[$k]) && trim((string) $payload[$k]) !== '') {
                    $temDetalhamento = true;
                    break;
                }
            }
            $q = $temDetalhamento ? 2 : 1;
            $guiaRow = $this->repository->findById($idGuia);
            $np = $guiaRow !== null ? trim((string) ($guiaRow['numero_processo'] ?? '')) : $numeroFormatado;
            $cl = $guiaRow !== null ? trim((string) ($guiaRow['tipo'] ?? '')) : $tipo;
            CartorioProdutividade::record($this->connection, $uidProd, 'cartorio-guias.create', [
                'quantidade' => $q,
                'referencia_item_id' => $idGuia,
                'referencia_sistema' => 'cartorio-guias',
                'numero_processo' => $np,
                'classe_processo' => $cl,
            ]);
        }

PHP;

$c = str_replace(
    "        \$this->sincronizarDetalhamentoAposUpdate(\$idGuia, \$usuarioCadastroId);\n\n        return \$idGuia;",
    "        \$this->sincronizarDetalhamentoAposUpdate(\$idGuia, \$usuarioCadastroId);" . rtrim($insCreate) . "\n\n        return \$idGuia;",
    $c,
    $n1
);
if ($n1 !== 1) {
    exit("create $n1");
}

$c = str_replace(
    "            \$resultado['tarefa_principal_concluida'] = false;\n            return \$resultado;\n        }\n\n        if (\$ordem < 8) {",
    "            \$resultado['tarefa_principal_concluida'] = false;\n            \$this->registrarProdutividadeSubtarefaGuia(\$subtarefaId, \$subtarefa, \$guiaId, \$usuarioIdMovimentacao ?? '');\n            return \$resultado;\n        }\n\n        if (\$ordem < 8) {",
    $c,
    $n2
);

$c = str_replace(
    "                . (\$denunciado !== '' ? \$denunciado : 'denunciado não informado') . ').';\n        }\n\n        return \$resultado;\n    }\n\n    public function cancelarSubtarefa",
    "                . (\$denunciado !== '' ? \$denunciado : 'denunciado não informado') . ').';\n        }\n\n        \$this->registrarProdutividadeSubtarefaGuia(\$subtarefaId, \$subtarefa, \$guiaId, \$usuarioIdMovimentacao ?? '');\n        return \$resultado;\n    }\n\n    public function cancelarSubtarefa",
    $c,
    $n3
);

if ($n2 !== 1 || $n3 !== 1) {
    exit("sub n2=$n2 n3=$n3");
}

$meth = <<<'PHP'

    private function registrarProdutividadeSubtarefaGuia(string $subtarefaId, array $subtarefa, string $guiaId, string $usuarioId): void
    {
        $usuarioId = trim($usuarioId);
        if ($usuarioId === '' || $usuarioId === 'sistema') {
            return;
        }
        $tipo = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($tipo === '') {
            return;
        }
        $guia = $this->repository->findById($guiaId);
        $np = $guia !== null ? trim((string) ($guia['numero_processo'] ?? '')) : '';
        $cl = $guia !== null ? trim((string) ($guia['tipo'] ?? '')) : '';
        $event = 'cartorio-guias.subtarefa.' . $tipo;
        CartorioProdutividade::record($this->connection, $usuarioId, $event, [
            'referencia_item_id' => $subtarefaId,
            'referencia_sistema' => 'cartorio-guias',
            'numero_processo' => $np,
            'classe_processo' => $cl,
        ]);
    }

PHP;

$c = str_replace(
    "    public function cancelarSubtarefa(string \$subtarefaId): void\n    {",
    rtrim($meth) . "\n\n    public function cancelarSubtarefa(string \$subtarefaId): void\n    {",
    $c,
    $n4
);
if ($n4 !== 1) {
    exit("meth $n4");
}

file_put_contents($p, $c);
echo "guias service ok\n";
