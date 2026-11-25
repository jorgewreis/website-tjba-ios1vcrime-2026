<?php
/**
 * Migração: Cartas Recebidas — fluxo com 2 subtarefas (cumprimento + devolução).
 * Executar uma vez (ex.: php scripts/migrate-cartas-recebidas-2-subtarefas.php).
 * Requer execução a partir da raiz do projeto.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/CartasRecebidasUiCopy.php';
require_once $basePath . '/app/repositories/CartasRecebidasRepository.php';
require_once $basePath . '/app/repositories/CartasRecebidasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();
$cartasRepo = new CartasRecebidasRepository($connection);
$subtarefasRepo = new CartasRecebidasSubtarefasRepository($connection);

$stmt = $connection->query('SELECT `id`, `status` FROM `cartas_recebidas` ORDER BY `id`');
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($cartas);
$ok = 0;
$err = 0;

foreach ($cartas as $carta) {
    $cartaId = trim((string) ($carta['id'] ?? ''));
    if ($cartaId === '') {
        continue;
    }

    try {
        $status = strtolower(trim((string) ($carta['status'] ?? '')));
        if ($status === 'concluída') {
            $status = 'concluida';
        }

        $subtarefasRepo->deleteByCartaId($cartaId);

        if ($status === 'cancelada') {
            $ok++;
            continue;
        }

        if ($status === 'concluida') {
            $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO, CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO, 'concluida', null);
            $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_DEVOLUCAO, CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO, 'concluida', null);
            $cartasRepo->update($cartaId, ['status' => 'concluída']);
            $ok++;
            continue;
        }

        if ($status === 'em_andamento' || $status === CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO) {
            $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO, CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO, 'concluida', null);
            $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_DEVOLUCAO, CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO, 'em_andamento', null);
            $cartasRepo->update($cartaId, ['status' => CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO]);
            $ok++;
            continue;
        }

        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO, CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO, 'em_andamento', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_DEVOLUCAO, CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO, 'pendente', null);
        $cartasRepo->update($cartaId, ['status' => CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO]);
        $ok++;
    } catch (Throwable $e) {
        $err++;
        echo "Erro carta {$cartaId}: " . $e->getMessage() . "\n";
    }
}

echo "Migração concluída: {$ok} cartas atualizadas, {$err} erros (total {$total}).\n";
