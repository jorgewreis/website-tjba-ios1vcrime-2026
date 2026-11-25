<?php
/**
 * Migração única: Cartas expedidas — fluxo de 2 etapas (envio ordem 1, devolução ordem 2).
 * Executar uma vez: php scripts/migrate-cartas-expedidas-fluxo-2-etapas.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/CartasExpedidasRepository.php';
require_once $basePath . '/app/repositories/CartasExpedidasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();
$cartasRepo = new CartasExpedidasRepository($connection);
$subtarefasRepo = new CartasExpedidasSubtarefasRepository($connection);

$deleted = $connection->exec(
    sprintf(
        "DELETE FROM `cartas_expedidas_subtarefas` WHERE `tipo` NOT IN ('%s', '%s')",
        CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
        CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO
    )
);
echo 'Subtarefas legadas removidas: ' . (int) $deleted . "\n";

$connection->exec(
    sprintf(
        "UPDATE `cartas_expedidas_subtarefas` SET `ordem` = 1 WHERE `tipo` = '%s'",
        CartasExpedidasSubtarefasRepository::TIPO_ENVIO
    )
);
$connection->exec(
    sprintf(
        "UPDATE `cartas_expedidas_subtarefas` SET `ordem` = 2 WHERE `tipo` = '%s'",
        CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO
    )
);
echo "Ordens renumeradas (envio=1, devolução=2).\n";

$statusLegadosEnvio = [
    'pendente',
    'aguardando assinatura',
    'aguardando envio',
    'em_andamento',
];
$statusLegadosCumprimento = [
    'aguardando cumprimento',
    'aguardando juntada do termo',
    'aguardando juntada de termo',
];

$stmt = $connection->query('SELECT `id`, `status` FROM `cartas_expedidas` ORDER BY `id`');
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ok = 0;
$err = 0;

foreach ($cartas as $cartaRow) {
    $cartaId = trim((string) ($cartaRow['id'] ?? ''));
    if ($cartaId === '') {
        continue;
    }

    try {
        $statusRaw = trim((string) ($cartaRow['status'] ?? ''));
        $statusNorm = mb_strtolower($statusRaw, 'UTF-8');
        $terminal = in_array($statusNorm, ['concluída', 'concluida', 'finalizada', 'cancelada'], true);

        if (!$terminal) {
            if (in_array($statusNorm, $statusLegadosCumprimento, true)) {
                $cartasRepo->update($cartaId, ['status' => 'aguardando cumprimento']);
            } elseif (in_array($statusNorm, $statusLegadosEnvio, true) || $statusNorm === '') {
                $cartasRepo->update($cartaId, ['status' => 'em_andamento']);
            }
        }

        $subtarefas = $subtarefasRepo->findByCartaId($cartaId);
        $porTipo = [];
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            if ($tipo !== '') {
                $porTipo[$tipo] = $st;
            }
        }

        if ($terminal) {
            $ok++;
            continue;
        }

        $statusAtual = trim((string) ($cartasRepo->find($cartaId)['status'] ?? ''));
        $statusAtualNorm = mb_strtolower($statusAtual, 'UTF-8');
        $faseCumprimento = $statusAtualNorm === 'aguardando cumprimento';

        $envio = $porTipo[CartasExpedidasSubtarefasRepository::TIPO_ENVIO] ?? null;
        $devolucao = $porTipo[CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO] ?? null;

        if ($envio === null && $devolucao === null) {
            if ($faseCumprimento) {
                $subtarefasRepo->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_ENVIO, 1, 'concluida', null);
                $subtarefasRepo->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO, 2, 'pendente', null);
            } else {
                $subtarefasRepo->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_ENVIO, 1, 'em_andamento', null);
                $subtarefasRepo->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO, 2, 'pendente', null);
            }
            $ok++;
            continue;
        }

        if ($envio === null) {
            $subtarefasRepo->create(
                $cartaId,
                CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
                1,
                $faseCumprimento ? 'concluida' : 'em_andamento',
                null
            );
        }

        if ($devolucao === null) {
            $subtarefasRepo->create(
                $cartaId,
                CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO,
                2,
                $faseCumprimento ? 'pendente' : 'pendente',
                null
            );
        }

        $subtarefas = $subtarefasRepo->findByCartaId($cartaId);
        $ativas = [];
        foreach ($subtarefas as $st) {
            $s = trim((string) ($st['status'] ?? ''));
            if ($s === 'em_andamento' || $s === 'pendente') {
                $ativas[] = $st;
            }
        }

        if (count($ativas) > 1) {
            usort($ativas, static fn (array $a, array $b): int => ((int) ($b['ordem'] ?? 0)) <=> ((int) ($a['ordem'] ?? 0)));
            $manter = array_shift($ativas);
            foreach ($ativas as $extra) {
                $extraId = trim((string) ($extra['id'] ?? ''));
                if ($extraId !== '') {
                    $subtarefasRepo->marcarConcluida($extraId);
                }
            }
        }

        if ($faseCumprimento) {
            foreach ($subtarefasRepo->findByCartaId($cartaId) as $st) {
                $tipo = trim((string) ($st['tipo'] ?? ''));
                $idSt = trim((string) ($st['id'] ?? ''));
                if ($idSt === '') {
                    continue;
                }
                if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_ENVIO) {
                    $subtarefasRepo->marcarConcluida($idSt);
                }
                if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO
                    && trim((string) ($st['status'] ?? '')) === 'em_andamento') {
                    $subtarefasRepo->atualizarStatus($idSt, 'pendente');
                }
            }
        } else {
            $temEnvioAtivo = false;
            foreach ($subtarefasRepo->findByCartaId($cartaId) as $st) {
                if (trim((string) ($st['tipo'] ?? '')) !== CartasExpedidasSubtarefasRepository::TIPO_ENVIO) {
                    continue;
                }
                if (trim((string) ($st['status'] ?? '')) === 'em_andamento') {
                    $temEnvioAtivo = true;
                }
            }
            if (!$temEnvioAtivo) {
                foreach ($subtarefasRepo->findByCartaId($cartaId) as $st) {
                    if (trim((string) ($st['tipo'] ?? '')) === CartasExpedidasSubtarefasRepository::TIPO_ENVIO
                        && trim((string) ($st['status'] ?? '')) !== 'concluida') {
                        $subtarefasRepo->atualizarStatus((string) $st['id'], 'em_andamento');
                        break;
                    }
                }
            }
        }

        $ok++;
    } catch (Throwable $e) {
        $err++;
        echo "Erro carta {$cartaId}: " . $e->getMessage() . "\n";
    }
}

echo "Migração concluída: {$ok} cartas processadas, {$err} erros (total " . count($cartas) . ").\n";
