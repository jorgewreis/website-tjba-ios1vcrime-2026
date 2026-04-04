<?php
$base = dirname(__DIR__);
$path = $base . '/app/services/ProdutividadeService.php';
$c = file_get_contents($path);
if (strpos($c, 'contexto_hash_forced') !== false) {
    echo "already patched registrarAuto\n";
    exit(0);
}
$oldHash = <<<'OLD'
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
OLD;
$newHash = <<<'NEW'
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
NEW;
$c = str_replace($oldHash, $newHash, $c, $cnt);
if ($cnt !== 1) {
    fwrite(STDERR, "hash block replace count $cnt\n");
    exit(1);
}
$insertMarker = "        return ['created' => true, 'id' => \$lancamentoId, 'reason' => 'ok'];\n    }\n\n    public function listLancamentos";
$insertMethod = <<<'METH'
        return ['created' => true, 'id' => $lancamentoId, 'reason' => 'ok'];
    }

    /**
     * Zera o saldo líquido de lançamentos automáticos ativos para a referência (cancel/delete).
     */
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
METH;
$c = str_replace($insertMarker, rtrim($insertMethod), $c, $cnt2);
if ($cnt2 !== 1) {
    fwrite(STDERR, "insert method replace count $cnt2\n");
    exit(1);
}
file_put_contents($path, $c);
echo "service ok\n";
