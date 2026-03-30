<?php
declare(strict_types=1);

/**
 * Migração CartorioOficios → oficios_cartorio (leitura de cartorio_oficios_legacy no destino).
 *
 * Uso:
 *   php tools/migrate-cartorio-oficios.php [--dry-run] [--limit=N] [--batch-size=100]
 *
 * Pré-requisitos: executar database/migrations/20260330_cartorio_oficios_legacy_and_map.sql
 * e importar os dados legados em cartorio_oficios_legacy.
 *
 * Conexão: use o .env na raiz (DB_HOST, DB_PORT opcional, DB_NAME, DB_USER, DB_PASS)
 * se o host padrão não for alcançável (VPN, localhost, túnel SSH).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/helpers/IdGenerator.php';
require_once __DIR__ . '/../app/repositories/ProcessosRepository.php';

const LEGACY_PLACEHOLDER_PROCESSO = '0000000-00.0000.8.05.0103';

/** @return array{dry_run: bool, limit: int|null, batch_size: int} */
function parseArgs(array $argv): array
{
    $dryRun = false;
    $limit = null;
    $batchSize = 100;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $limit = (int) $m[1];
            continue;
        }
        if (preg_match('/^--batch-size=(\d+)$/', $arg, $m)) {
            $batchSize = max(1, (int) $m[1]);
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDERR, "Uso: php tools/migrate-cartorio-oficios.php [--dry-run] [--limit=N] [--batch-size=100]\n");
            exit(0);
        }
    }

    return ['dry_run' => $dryRun, 'limit' => $limit, 'batch_size' => $batchSize];
}

function digitsOnly(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function logLine(string $path, string $line): void
{
    file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}

function loadUsuarioCache(PDO $pdo): callable
{
    /** @var array<string, string|null> */
    $cache = [];

    return static function (string $nomeLivre) use ($pdo, &$cache): ?string {
        $trimmed = trim($nomeLivre);
        $key = mb_strtolower($trimmed, 'UTF-8');
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $st = $pdo->prepare(
            'SELECT `id_usuario` FROM `usuarios` WHERE LOWER(TRIM(CONCAT(COALESCE(`nome`, \'\'), \' \', COALESCE(`sobrenome`, \'\')))) = LOWER(?) LIMIT 2'
        );
        $st->execute([$trimmed]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 0) {
            $cache[$key] = null;

            return null;
        }
        $cache[$key] = (string) $rows[0]['id_usuario'];

        return $cache[$key];
    };
}

function rejectRow(PDO $pdo, bool $dryRun, string $legacyId, string $motivo, ?string $detalhe = null): void
{
    if ($dryRun) {
        return;
    }
    $st = $pdo->prepare(
        'INSERT INTO `oficios_cartorio_migracao_rejects` (`legacy_id`, `motivo`, `detalhe`, `created_at`) VALUES (:id, :motivo, :detalhe, NOW())
         ON DUPLICATE KEY UPDATE `motivo` = VALUES(`motivo`), `detalhe` = VALUES(`detalhe`), `created_at` = NOW()'
    );
    $st->bindValue(':id', $legacyId);
    $st->bindValue(':motivo', $motivo);
    $st->bindValue(':detalhe', $detalhe);
    $st->execute();
}

$opts = parseArgs($argv);
$dryRun = $opts['dry_run'];
$limit = $opts['limit'];
$batchSize = $opts['batch_size'];

$logPath = __DIR__ . '/migrate-cartorio-oficios-' . date('Ymd') . '.log';
file_put_contents($logPath, '[' . date('c') . "] início dry_run=" . ($dryRun ? '1' : '0') . " limit=" . ($limit ?? 'null') . " batch={$batchSize}\n");

$pdo = Conection::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$resolveUser = loadUsuarioCache($pdo);
$processosRepo = new ProcessosRepository($pdo);

// Duplicatas no legado (Ano + Numero)
$dupSt = $pdo->query(
    'SELECT `Ano`, `Numero`, COUNT(*) AS c FROM `cartorio_oficios_legacy` GROUP BY `Ano`, `Numero` HAVING c > 1 LIMIT 1'
);
$dupRows = $dupSt->fetchAll(PDO::FETCH_ASSOC);
if ($dupRows !== []) {
    $msg = 'Existem duplicatas (Ano, Numero) em cartorio_oficios_legacy; corrija antes de migrar.';
    logLine($logPath, 'ERRO: ' . $msg);
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

$sql = 'SELECT `IdCrtOficios`, `Numero`, `Ano`, `Processo`, `Descricao`, `Meio`, `Prioridade`, `Data`, `Usuario` FROM `cartorio_oficios_legacy` ORDER BY `Ano` ASC, `Numero` ASC';
if ($limit !== null) {
    $sql .= ' LIMIT ' . (int) $limit;
}
$legacyRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$inserted = 0;
$skipped = 0;
$errors = 0;

$checkMap = $pdo->prepare('SELECT 1 FROM `oficios_cartorio_migracao_map` WHERE `legacy_id` = ? LIMIT 1');
$checkConflict = $pdo->prepare(
    'SELECT COUNT(*) FROM `oficios_cartorio` WHERE `ano` = ? AND `numero_sequencial` = ?'
);
$insertOficio = $pdo->prepare(
    'INSERT INTO `oficios_cartorio` (`id`, `processo_id`, `numero_processo`, `ano`, `numero_sequencial`, `tipo_oficio`, `forma_cumprimento`, `meio_envio`, `status`, `criado_por`, `atualizado_por`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :numero_processo, :ano, :numero_sequencial, :tipo_oficio, \'ccm\', NULL, \'finalizada\', :criado_por, :atualizado_por, :created_at, :updated_at)'
);
$insertMap = $pdo->prepare(
    'INSERT INTO `oficios_cartorio_migracao_map` (`legacy_id`, `novo_id`, `migrated_at`) VALUES (:legacy_id, :novo_id, NOW())'
);

$batchCount = 0;
if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    foreach ($legacyRows as $row) {
        $legacyId = (string) ($row['IdCrtOficios'] ?? '');
        $ano = (int) ($row['Ano'] ?? 0);
        $numeroSeq = (int) ($row['Numero'] ?? 0);
        $processoRaw = trim((string) ($row['Processo'] ?? ''));
        $descricao = (string) ($row['Descricao'] ?? '');
        $dataRaw = (string) ($row['Data'] ?? '');
        $usuarioNome = isset($row['Usuario']) ? trim((string) $row['Usuario']) : '';

        if ($legacyId === '' || $ano < 2000 || $ano > 2100 || $numeroSeq < 1) {
            $errors++;
            rejectRow($pdo, $dryRun, $legacyId !== '' ? $legacyId : '?', 'dados_legado_invalidos', json_encode($row, JSON_UNESCAPED_UNICODE));
            logLine($logPath, "REJEITADO {$legacyId}: dados inválidos");
            continue;
        }

        $checkMap->execute([$legacyId]);
        if ($checkMap->fetchColumn()) {
            $skipped++;
            continue;
        }

        $checkConflict->execute([$ano, $numeroSeq]);
        if ((int) $checkConflict->fetchColumn() > 0) {
            $errors++;
            $msg = "Conflito (ano, numero_sequencial) existente: {$ano}/{$numeroSeq}";
            rejectRow($pdo, $dryRun, $legacyId, 'conflito_sequencial', $msg);
            logLine($logPath, "REJEITADO {$legacyId}: {$msg}");
            continue;
        }

        $tipoOficio = mb_substr($descricao, 0, 255, 'UTF-8');
        if ($tipoOficio === '') {
            $tipoOficio = 'Ofício';
        }

        $digits = digitsOnly($processoRaw);
        $processoId = null;
        $numeroProcesso = mb_substr($processoRaw, 0, 64, 'UTF-8');

        if ($processoRaw === LEGACY_PLACEHOLDER_PROCESSO) {
            $processoId = null;
            $numeroProcesso = mb_substr($processoRaw, 0, 64, 'UTF-8');
        } elseif (strlen($digits) === 20) {
            if ($dryRun) {
                // Não chama ensureProcess (alteraria `processos`).
                $processoId = null;
            } else {
                try {
                    $processoId = $processosRepo->ensureProcess($processoRaw, null, null, false);
                    $proc = $processosRepo->findById($processoId);
                    if ($proc !== null && isset($proc['numero_processo'])) {
                        $numeroProcesso = (string) $proc['numero_processo'];
                    }
                } catch (Throwable $e) {
                    $errors++;
                    rejectRow($pdo, false, $legacyId, 'ensure_process', $e->getMessage());
                    logLine($logPath, "REJEITADO {$legacyId}: ensureProcess — " . $e->getMessage());
                    continue;
                }
            }
        } else {
            $processoId = null;
        }

        $userId = $usuarioNome !== '' ? $resolveUser($usuarioNome) : null;

        $ts = $dataRaw !== '' ? $dataRaw : date('Y-m-d H:i:s');

        if ($dryRun) {
            $inserted++;
            continue;
        }

        $novoId = IdGenerator::generateUniquePrefixedId($pdo, 'oficios_cartorio', 'id', 'OFC', 30);

        $insertOficio->execute([
            ':id' => $novoId,
            ':processo_id' => $processoId,
            ':numero_processo' => $numeroProcesso,
            ':ano' => $ano,
            ':numero_sequencial' => $numeroSeq,
            ':tipo_oficio' => $tipoOficio,
            ':criado_por' => $userId,
            ':atualizado_por' => $userId,
            ':created_at' => $ts,
            ':updated_at' => $ts,
        ]);

        $insertMap->execute([
            ':legacy_id' => $legacyId,
            ':novo_id' => $novoId,
        ]);

        $inserted++;
        logLine($logPath, "OK {$legacyId} → {$novoId}");

        $batchCount++;
        if ($batchCount >= $batchSize) {
            $pdo->commit();
            $pdo->beginTransaction();
            $batchCount = 0;
        }
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logLine($logPath, 'FATAL: ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$summary = "Concluído: inseridos/simulados={$inserted}, ignorados(já no mapa)={$skipped}, erros={$errors}. Log: {$logPath}\n";
echo $summary;
logLine($logPath, $summary);
