<?php
/**
 * Migração: Guias — substituir 7 subtarefas antigas pelas 8 novas.
 * Executar uma vez (ex.: php scripts/migrate-guias-8-subtarefas.php).
 * Requer execução a partir da raiz do projeto.
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/GuiasRepository.php';
require_once $basePath . '/app/repositories/GuiasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();
$guiasRepo = new GuiasRepository($connection);
$subtarefasRepo = new GuiasSubtarefasRepository($connection);

$stmt = $connection->query('SELECT `id` FROM `guias` ORDER BY `id`');
$guias = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total = count($guias);
$ok = 0;
$err = 0;

foreach ($guias as $guiaId) {
    try {
        $subtarefasRepo->deleteByGuiaId($guiaId);

        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_4, 4, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_5, 5, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_6, 6, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_7, 7, 'pendente', null);
        $subtarefasRepo->create($guiaId, GuiasSubtarefasRepository::TIPO_ORDEM_8, 8, 'pendente', null);

        $guiasRepo->update($guiaId, ['status' => 'em_andamento']);
        $ok++;
    } catch (Throwable $e) {
        $err++;
        echo "Erro guia {$guiaId}: " . $e->getMessage() . "\n";
    }
}

echo "Migração concluída: {$ok} guias atualizadas, {$err} erros (total {$total}).\n";
