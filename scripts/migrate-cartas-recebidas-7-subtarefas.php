<?php
/**
 * Migração: Cartas Recebidas — substituir 4 subtarefas antigas pelas 7 novas.
 * Executar uma vez (ex.: php scripts/migrate-cartas-recebidas-7-subtarefas.php).
 * Requer execução a partir da raiz do projeto.
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/CartasRecebidasRepository.php';
require_once $basePath . '/app/repositories/CartasRecebidasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();
$cartasRepo = new CartasRecebidasRepository($connection);
$subtarefasRepo = new CartasRecebidasSubtarefasRepository($connection);

$stmt = $connection->query('SELECT `id` FROM `cartas_recebidas` ORDER BY `id`');
$cartas = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total = count($cartas);
$ok = 0;
$err = 0;

foreach ($cartas as $cartaId) {
    try {
        $subtarefasRepo->deleteByCartaId($cartaId);

        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4, 4, 'pendente', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5, 5, 'pendente', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6, 6, 'pendente', null);
        $subtarefasRepo->create($cartaId, CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7, 7, 'pendente', null);

        $cartasRepo->update($cartaId, ['status' => 'em_andamento']);
        $ok++;
    } catch (Throwable $e) {
        $err++;
        echo "Erro carta {$cartaId}: " . $e->getMessage() . "\n";
    }
}

echo "Migração concluída: {$ok} cartas atualizadas, {$err} erros (total {$total}).\n";
