<?php
declare(strict_types=1);

/**
 * Backfill de tarefas automaticas de midia para audiencias ja finalizadas
 * que ficaram sem registro em sala_midias_tarefa.
 *
 * Uso (na raiz do repositório ou com caminho absoluto ao PHP):
 *   php scripts/backfill-sala-midias-auto.php
 *   php scripts/backfill-sala-midias-auto.php --dry-run
 *
 * Requisito: arquivo `.env` na raiz do projeto com DB_HOST, DB_NAME, DB_USER
 * e DB_PASS (copie de `.env.example` se ainda não existir).
 *
 * O script lista todas as pautas com status 'realizada' ou 'finalizada' e
 * invoca SalaMidiasService::ensureAutoFromPautaIfEligible para cada uma.
 * Em --dry-run apenas reporta o que seria feito, sem alterar nada.
 *
 * O script imprime um sumario e cada linha carrega o id da pauta, o
 * resultado e a explicacao quando nao cria. Isso ajuda a diagnosticar o
 * motivo especifico de cada caso (sem processo vinculado, dados
 * incompletos, etc.).
 */

if (php_sapi_name() !== 'cli') {
    echo "Execute apenas via CLI.\n";
    exit(1);
}

$basePath = dirname(__DIR__);
$envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
$envExamplePath = $basePath . DIRECTORY_SEPARATOR . '.env.example';

if (!is_file($envPath)) {
    fwrite(STDERR, "Arquivo .env não encontrado.\n");
    fwrite(STDERR, 'Esperado em: ' . $envPath . "\n");
    if (is_file($envExamplePath)) {
        fwrite(STDERR, "Copie o modelo e preencha o banco:\n");
        fwrite(STDERR, "  copy .env.example .env   (Windows)\n");
        fwrite(STDERR, "  cp .env.example .env     (Linux/macOS)\n");
    }
    fwrite(STDERR, "Defina DB_HOST, DB_NAME, DB_USER e DB_PASS no .env.\n");
    exit(1);
}

try {
    require_once $basePath . '/config/config.php';
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'Configuracao obrigatoria ausente')) {
        fwrite(STDERR, $e->getMessage() . "\n");
        fwrite(STDERR, "Edite o arquivo .env na raiz do projeto e preencha as variáveis de banco.\n");
        fwrite(STDERR, 'Arquivo: ' . $envPath . "\n");
        exit(1);
    }
    throw $e;
}

require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/SalaMidiasService.php';

$dryRun = in_array('--dry-run', $argv, true);

$pdo = Conection::getInstance()->getConnection();
$service = new SalaMidiasService($pdo);

$sql = "
    SELECT a.`id_pauta`, a.`status`, a.`data_audiencia`, a.`tipo_audiencia`, a.`criado_por`
    FROM `sala_audiencias_pauta` a
    WHERE a.`ativo` = 1
      AND a.`status` IN ('realizada', 'finalizada')
      AND NOT EXISTS (
          SELECT 1 FROM `sala_midias_tarefa` m
          WHERE m.`pauta_id` = a.`id_pauta` AND m.`ativo` = 1
      )
    ORDER BY a.`data_audiencia` ASC, a.`hora_audiencia` ASC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$total = count($rows);

echo "=== Backfill de tarefas automaticas de midia ===\n";
echo "Pautas finalizadas sem tarefa ativa: {$total}\n";
if ($dryRun) {
    echo "MODO DRY-RUN: nada sera criado, apenas simulado.\n";
}
echo str_repeat('-', 60) . "\n";

$criados = 0;
$reativados = 0;
$pulados = 0;
$erros = 0;

foreach ($rows as $row) {
    $idPauta = (string) $row['id_pauta'];
    $status = (string) $row['status'];
    $ator = trim((string) ($row['criado_por'] ?? '')) ?: null;

    if ($dryRun) {
        echo sprintf("[DRY] %s status=%s ator=%s => simulado.\n", $idPauta, $status, $ator ?: '(null)');
        continue;
    }

    try {
        $resultado = $service->ensureAutoFromPautaIfEligible($idPauta, $ator);
        if ($resultado !== null) {
            echo sprintf("[OK ] %s => tarefa=%s\n", $idPauta, $resultado);
            $criados++;
        } else {
            echo sprintf("[SKIP] %s => nao criada (ver error_log para motivo).\n", $idPauta);
            $pulados++;
        }
    } catch (Throwable $e) {
        echo sprintf("[ERR] %s => %s\n", $idPauta, $e->getMessage());
        $erros++;
    }
}

echo str_repeat('-', 60) . "\n";
echo sprintf(
    "Total=%d criados=%d pulados=%d erros=%d%s\n",
    $total,
    $criados,
    $pulados,
    $erros,
    $dryRun ? ' (dry-run)' : ''
);
