<?php
/**
 * Migração: replica movimentações de réus presos por réu.
 *
 * Contexto: até esta versão, a deduplicação de movimentação era por (processo, tipo, data),
 * de modo que um processo compartilhado por mais de um réu tinha apenas UMA linha em
 * `gabinete_reu_preso_movimentacao`. A partir desta versão a granularidade passa a ser
 * (réu + processo). Este script garante, retroativamente, que cada réu vinculado a um
 * processo tenha sua própria cópia das movimentações existentes.
 *
 * O script é idempotente: rodá-lo novamente não duplica linhas.
 *
 * Após replicar, recalcula `proxima_revisao` de cada réu impactado, usando a mesma
 * lógica de `GabineteReusPresosService::recalcProximaRevisao`.
 *
 * Execução: php scripts/migrate-grp-movimentacao-replicar-por-reu.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}


$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/IdGenerator.php';
require_once $basePath . '/app/services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $connection, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column';
    $st = $connection->prepare($sql);
    $st->bindValue(':table', $table);
    $st->bindValue(':column', $column);
    $st->execute();
    return (int) $st->fetchColumn() > 0;
}

try {
    if (!columnExists($connection, 'gabinete_reu_preso_movimentacao', 'processo_id')) {
        echo "Atenção: coluna `processo_id` ausente em gabinete_reu_preso_movimentacao.\n";
        echo "Execute antes: php scripts/migrate-grp-movimentacao-processo-id.php\n";
        exit(1);
    }

    $connection->beginTransaction();

    $distinctSql = "SELECT DISTINCT m.`processo_id`, m.`tipo_codigo`, m.`data_movimentacao`, m.`observacoes`
                    FROM `gabinete_reu_preso_movimentacao` m
                    WHERE m.`processo_id` IS NOT NULL AND m.`processo_id` <> ''";
    $distinct = $connection->query($distinctSql);
    $tuplas = $distinct !== false ? $distinct->fetchAll(PDO::FETCH_ASSOC) : [];

    $linksStmt = $connection->prepare(
        'SELECT `id` AS `link_id`, `reu_preso_id`
         FROM `gabinete_reu_preso_processo`
         WHERE `processo_id` = :pid'
    );

    $existsStmt = $connection->prepare(
        'SELECT 1 FROM `gabinete_reu_preso_movimentacao`
         WHERE `reu_preso_id` = :reu AND `processo_id` = :pid
               AND `tipo_codigo` = :tipo AND `data_movimentacao` = :dt
         LIMIT 1'
    );

    $inserted = 0;
    $reusAfetados = [];

    foreach ($tuplas as $t) {
        $pid = trim((string) ($t['processo_id'] ?? ''));
        $tipo = trim((string) ($t['tipo_codigo'] ?? ''));
        $data = trim((string) ($t['data_movimentacao'] ?? ''));
        $obs = $t['observacoes'] ?? null;
        if ($pid === '' || $tipo === '' || $data === '') {
            continue;
        }

        $linksStmt->bindValue(':pid', $pid);
        $linksStmt->execute();
        $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($links as $ln) {
            $reuId = trim((string) ($ln['reu_preso_id'] ?? ''));
            $linkId = trim((string) ($ln['link_id'] ?? ''));
            if ($reuId === '' || $linkId === '') {
                continue;
            }

            $existsStmt->bindValue(':reu', $reuId);
            $existsStmt->bindValue(':pid', $pid);
            $existsStmt->bindValue(':tipo', $tipo);
            $existsStmt->bindValue(':dt', $data);
            $existsStmt->execute();
            $found = $existsStmt->fetchColumn();
            if ($found !== false) {
                continue;
            }

            $newId = IdGenerator::generateUniquePrefixedId($connection, 'gabinete_reu_preso_movimentacao', 'id', 'GPM', 30);
            $ins = $connection->prepare(
                'INSERT INTO `gabinete_reu_preso_movimentacao`
                    (`id`, `reu_preso_id`, `reu_preso_processo_id`, `processo_id`,
                     `tipo_codigo`, `data_movimentacao`, `observacoes`, `created_at`, `user_cadastro`)
                 VALUES (:id, :reu, :link, :pid, :tipo, :dt, :obs, NOW(), NULL)'
            );
            $ins->bindValue(':id', $newId);
            $ins->bindValue(':reu', $reuId);
            $ins->bindValue(':link', $linkId);
            $ins->bindValue(':pid', $pid);
            $ins->bindValue(':tipo', $tipo);
            $ins->bindValue(':dt', $data);
            $ins->bindValue(':obs', $obs);
            $ins->execute();

            $inserted++;
            $reusAfetados[$reuId] = true;
        }
    }

    $connection->commit();
    echo "OK: " . $inserted . " linha(s) inserida(s) para replicação por réu.\n";

    $reusRecalc = 0;
    if ($reusAfetados !== []) {
        $service = new GabineteReusPresosService($connection);
        foreach (array_keys($reusAfetados) as $reuId) {
            try {
                $service->recalcProximaRevisao($reuId);
                $reusRecalc++;
            } catch (Throwable $e) {
                echo "Falha ao recalcular proxima_revisao do réu " . $reuId . ": " . $e->getMessage() . "\n";
            }
        }
    }
    echo "OK: proxima_revisao recalculada para " . $reusRecalc . " réu(s).\n";
    echo "Migração concluída.\n";
} catch (Throwable $e) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
