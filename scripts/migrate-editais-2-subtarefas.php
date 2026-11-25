<?php
/**
 * Migração: Editais — fluxo com 2 subtarefas (publicação + decurso).
 * Remove etapas legadas 1–2 (expedição / DJEN). Finaliza automaticamente quando o prazo já encerrou.
 *
 * Executar uma vez: php scripts/migrate-editais-2-subtarefas.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/EditaisUiCopy.php';
require_once $basePath . '/app/repositories/EditaisRepository.php';
require_once $basePath . '/app/repositories/EditaisSubtarefasRepository.php';
require_once $basePath . '/app/services/EditaisService.php';

$connection = Conection::getInstance()->getConnection();
$editaisRepo = new EditaisRepository($connection);
$subtarefasRepo = new EditaisSubtarefasRepository($connection);
$service = new EditaisService($connection);

$stmt = $connection->query('SELECT `id`, `status`, `data_publicacao` FROM `editais` ORDER BY `id`');
$editais = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($editais);
$ok = 0;
$err = 0;
$autoFinalizados = 0;

foreach ($editais as $editalRow) {
    $editalId = trim((string) ($editalRow['id'] ?? ''));
    if ($editalId === '') {
        continue;
    }

    try {
        $statusPrincipal = strtolower(trim((string) ($editalRow['status'] ?? '')));
        if ($statusPrincipal === 'concluída') {
            $statusPrincipal = 'concluida';
        }

        $antigas = $subtarefasRepo->findByEditalId($editalId);
        $pubConcluida = trim((string) ($editalRow['data_publicacao'] ?? '')) !== '';
        $decConcluida = $statusPrincipal === 'concluida';

        foreach ($antigas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordem = (int) ($st['ordem'] ?? 0);
            $stStatus = strtolower(trim((string) ($st['status'] ?? '')));
            if ($stStatus === 'concluída') {
                $stStatus = 'concluida';
            }
            if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordem === 3) {
                if ($stStatus === 'concluida') {
                    $pubConcluida = true;
                }
            }
            if ($tipo === EditaisSubtarefasRepository::TIPO_DECURSO || $ordem === 4) {
                if ($stStatus === 'concluida') {
                    $decConcluida = true;
                }
            }
        }

        $subtarefasRepo->deleteByEditalId($editalId);

        if ($statusPrincipal === 'cancelada') {
            $ok++;
            continue;
        }

        if ($statusPrincipal === 'concluida' || $decConcluida) {
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_PUBLICACAO, EditaisSubtarefasRepository::ORDEM_PUBLICACAO, 'concluida', null);
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_DECURSO, EditaisSubtarefasRepository::ORDEM_DECURSO, 'concluida', null);
            $editaisRepo->update($editalId, ['status' => 'concluída']);
            $ok++;
            continue;
        }

        if ($pubConcluida) {
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_PUBLICACAO, EditaisSubtarefasRepository::ORDEM_PUBLICACAO, 'concluida', null);
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_DECURSO, EditaisSubtarefasRepository::ORDEM_DECURSO, 'em_andamento', null);
            $editaisRepo->update($editalId, ['status' => EditaisUiCopy::STATUS_AGUARDANDO_DECURSO]);
        } else {
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_PUBLICACAO, EditaisSubtarefasRepository::ORDEM_PUBLICACAO, 'em_andamento', null);
            $subtarefasRepo->create($editalId, EditaisSubtarefasRepository::TIPO_DECURSO, EditaisSubtarefasRepository::ORDEM_DECURSO, 'pendente', null);
            $editaisRepo->update($editalId, ['status' => EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO]);
        }

        if ($service->tentarFinalizacaoAutomaticaPorDecurso($editalId)) {
            $autoFinalizados++;
        }

        $ok++;
    } catch (Throwable $e) {
        $err++;
        echo "Erro edital {$editalId}: " . $e->getMessage() . "\n";
    }
}

echo "Migração concluída: {$ok} editais atualizados, {$autoFinalizados} finalizados automaticamente por decurso, {$err} erros (total {$total}).\n";
