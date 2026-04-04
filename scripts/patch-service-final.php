<?php
$path = dirname(__DIR__) . '/app/services/ProdutividadeService.php';
$c = file_get_contents($path);
$c = str_replace("\r\n", "\n", $c);

$old = <<<'OLD'
        $observacoes = trim((string) ($payload['observacoes'] ?? ''));

        $contextoHash = null;
        if ($referenciaItemId !== '') {
            $contextoHash = hash('sha256', implode('|', [
                $actorUserId,
                $tipoAto,
                $tipoDocumento,
                $sistemaModulo,
                $referenciaItemId,
                date('Y-m-d H:i', strtotime($dataHoraAto)),
            ]));
        }

        $pontosPorAto = (float) ($regra['pontos_por_ato'] ?? 0);
        $multiplicador = (float) ($regra['pontos_multiplicador'] ?? 1);
        $pontosCalculados = $quantidade * $pontosPorAto * $multiplicador;

        $this->connection->beginTransaction();
        try {
            $lancamentoId = $this->lancamentosRepository->insert([
                'usuario_id' => $actorUserId,
                'regra_id' => (string) ($regra['id'] ?? ''),
                'tipo_lancamento' => 'automatico',
OLD;

$new = <<<'NEW'
        $observacoes = trim((string) ($payload['observacoes'] ?? ''));

        $contextoHash = null;
        if ($referenciaItemId !== '') {
            $forcedHash = trim((string) ($payload['contexto_hash_forced'] ?? ''));
            if ($forcedHash !== '') {
                $contextoHash = $forcedHash;
            } else {
                $contextoHash = hash('sha256', implode('|', [
                    $actorUserId,
                    $tipoAto,
                    $tipoDocumento,
                    $sistemaModulo,
                    $referenciaItemId,
                    date('Y-m-d H:i', strtotime($dataHoraAto)),
                ]));
            }
        }

        $pontosPorAto = (float) ($regra['pontos_por_ato'] ?? 0);
        $multiplicador = (float) ($regra['pontos_multiplicador'] ?? 1);
        if (array_key_exists('pontos_calculados_override', $payload)) {
            $pontosCalculados = (float) $payload['pontos_calculados_override'];
        } else {
            $pontosCalculados = $quantidade * $pontosPorAto * $multiplicador;
        }

        $this->connection->beginTransaction();
        try {
            $lancamentoId = $this->lancamentosRepository->insert([
                'usuario_id' => $actorUserId,
                'regra_id' => (string) ($regra['id'] ?? ''),
                'tipo_lancamento' => 'automatico',
NEW;

$c = str_replace($old, $new, $c, $n1);
if ($n1 !== 1) {
    fwrite(STDERR, "block1 n=$n1\n");
    exit(1);
}

$marker = "        return ['created' => true, 'id' => \$lancamentoId, 'reason' => 'ok'];\n    }\n\n    public function listLancamentos";
$method = <<<'PHP'
        return ['created' => true, 'id' => $lancamentoId, 'reason' => 'ok'];
    }

    public function registrarEstornoSaldoReferencia(string $actorUserId, string $referenciaItemId, string $referenciaSistema, string $motivoObs = ''): void
    {
        $this->assertAuthenticated($actorUserId);
        $referenciaItemId = trim($referenciaItemId);
        $referenciaSistema = trim($referenciaSistema);
        if ($referenciaItemId === '' || $referenciaSistema === '') {
            return;
        }
        $net = $this->lancamentosRepository->sumNetAutomaticoAtivo($referenciaItemId, $referenciaSistema);
        if ($net <= 0.00001) {
            return;
        }
        $neg = -1 * $net;
        $obs = trim($motivoObs) !== '' ? trim($motivoObs) : 'Estorno de produtividade (encerramento de tarefa).';
        $hash = hash('sha256', 'estorno-neto|' . $referenciaItemId . '|' . $referenciaSistema);
        $this->registrarAuto($actorUserId, [
            'tipo_ato' => 'Estorno',
            'tipo_documento' => 'Espelho cartório',
            'sistema_modulo' => 'cartorio-interno',
            'quantidade' => 1,
            'referencia_item_id' => $referenciaItemId,
            'referencia_sistema' => $referenciaSistema,
            'observacoes' => $obs,
            'contexto_hash_forced' => $hash,
            'pontos_calculados_override' => $neg,
        ]);
    }

    public function listLancamentos
PHP;

$c = str_replace($marker, rtrim($method), $c, $n2);
if ($n2 !== 1) {
    fwrite(STDERR, "block2 n=$n2\n");
    exit(1);
}

file_put_contents($path, $c);
echo "service patched\n";
