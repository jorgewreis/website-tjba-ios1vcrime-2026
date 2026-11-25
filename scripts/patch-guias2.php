<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$p = dirname(__DIR__) . '/app/services/GuiasService.php';
$c = file_get_contents($p);
$c = str_replace("\r\n", "\n", $c);

$c = str_replace(
    "require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';\n\nfinal class GuiasService",
    "require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';\nrequire_once __DIR__ . '/../helpers/CartorioProdutividade.php';\n\nfinal class GuiasService",
    $c,
    $n0
);
if ($n0 !== 1) exit("n0=$n0");

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
if ($n1 !== 1) exit("n1=$n1");

$c = str_replace(
    "            \$resultado['tarefa_principal_concluida'] = false;\n            return \$resultado;\n        }\n\n        if (\$ordem < 8) {",
    "            \$resultado['tarefa_principal_concluida'] = false;\n            \$this->registrarProdutividadeSubtarefaGuia(\$subtarefaId, \$subtarefa, \$guiaId, \$usuarioIdMovimentacao ?? '');\n            return \$resultado;\n        }\n\n        if (\$ordem < 8) {",
    $c,
    $n2
);
if ($n2 !== 1) exit("n2=$n2");

$c = str_replace(
    "        }\n\n        return \$resultado;\n    }\n\n    public function cancelarSubtarefa(string \$subtarefaId): void",
    "        }\n\n        \$this->registrarProdutividadeSubtarefaGuia(\$subtarefaId, \$subtarefa, \$guiaId, \$usuarioIdMovimentacao ?? '');\n        return \$resultado;\n    }\n\n    private function registrarProdutividadeSubtarefaGuia(string \$subtarefaId, array \$subtarefa, string \$guiaId, string \$usuarioId): void\n    {\n        \$usuarioId = trim(\$usuarioId);\n        if (\$usuarioId === '' || \$usuarioId === 'sistema') {\n            return;\n        }\n        \$tipo = trim((string) (\$subtarefa['tipo'] ?? ''));\n        if (\$tipo === '') {\n            return;\n        }\n        \$guia = \$this->repository->findById(\$guiaId);\n        \$np = \$guia !== null ? trim((string) (\$guia['numero_processo'] ?? '')) : '';\n        \$cl = \$guia !== null ? trim((string) (\$guia['tipo'] ?? '')) : '';\n        \$event = 'cartorio-guias.subtarefa.' . \$tipo;\n        CartorioProdutividade::record(\$this->connection, \$usuarioId, \$event, [\n            'referencia_item_id' => \$subtarefaId,\n            'referencia_sistema' => 'cartorio-guias',\n            'numero_processo' => \$np,\n            'classe_processo' => \$cl,\n        ]);\n    }\n\n    public function cancelarSubtarefa(string \$subtarefaId): void",
    $c,
    $n3
);
if ($n3 !== 1) exit("n3=$n3");

file_put_contents($p, $c);
echo "guias ok\n";
