<?php
declare(strict_types=1);

$runId = (string) getenv('REI167_TEST_RUN_ID');
$marker = (string) getenv('REI167_TEST_DISPOSABLE_MARKER');
$dsn = (string) getenv('REI167_MYSQL_DSN');
if (getenv('REI167_TEST_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei167_test;charset=utf8mb4'
    || getenv('REI167_MYSQL_USER') !== 'root') {
    fwrite(STDERR, "FAIL CLOSED: disposable MySQL fixture configuration invalid\n");
    exit(90);
}

require_once '/repo/app/services/DashboardResumoService.php';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]);
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei167_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((int) $guard->fetchColumn() !== 1) {
    throw new RuntimeException('MySQL fixture ownership marker did not match.');
}
$pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
if (!str_contains((string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(), 'ONLY_FULL_GROUP_BY')) {
    throw new RuntimeException('MySQL did not enable ONLY_FULL_GROUP_BY for this test session.');
}

$modules = [
    ['cartas_expedidas', 'cartas_expedidas_subtarefas', 'carta_expedida_id'],
    ['cartas_recebidas', 'cartas_recebidas_subtarefas', 'carta_recebida_id'],
    ['guias', 'guias_subtarefas', 'guia_id'],
    ['editais', 'editais_subtarefas', 'edital_id'],
    ['oficios_cartorio', 'oficios_cartorio_subtarefas', 'oficio_cartorio_id'],
];
foreach ($modules as [$parent, $child, $fk]) {
    $pdo->exec("CREATE TABLE `$parent` (
        id VARCHAR(40) PRIMARY KEY,
        status VARCHAR(40) NULL,
        created_at DATETIME NULL,
        criado_por VARCHAR(40) NULL,
        prazo_dias INT NULL,
        data_recebimento DATE NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE `$child` (
        id VARCHAR(40) PRIMARY KEY,
        `$fk` VARCHAR(40) NOT NULL,
        tipo VARCHAR(80) NOT NULL,
        ordem INT NULL,
        status VARCHAR(40) NULL,
        updated_at DATETIME NULL,
        KEY idx_fixture_parent_order (`$fk`, ordem)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
$pdo->exec("CREATE TABLE oficios_gabinete (
    id VARCHAR(40) PRIMARY KEY, status VARCHAR(40) NULL,
    created_at DATETIME NULL, criado_por VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$types = [
    'detalhamento_informacoes', 'calculo_pena_multa', 'expedicao_guia', 'aguardando_assinatura',
    'juntada_automatica', 'gerar_pdf', 'envio_guia', 'juntada_termo_envio',
];
$pdo->exec("INSERT INTO guias (id,status,created_at) VALUES ('guide-open','em_andamento',NOW()),('guide-done','concluída',NOW()),('guide-cancel','cancelada',NOW())");
$insert = $pdo->prepare('INSERT INTO guias_subtarefas (id,guia_id,tipo,ordem,status,updated_at) VALUES (:id,:guia,:tipo,:ordem,:status,NOW())');
foreach ($types as $index => $type) {
    $order = $index + 1;
    $insert->execute([':id' => 'done-'.$order, ':guia' => 'guide-done', ':tipo' => $type, ':ordem' => $order, ':status' => 'concluida']);
    $insert->execute([':id' => 'cancel-'.$order, ':guia' => 'guide-cancel', ':tipo' => $type, ':ordem' => $order, ':status' => $order === 1 ? 'em_andamento' : 'pendente']);
    $insert->execute([':id' => 'open-'.$order, ':guia' => 'guide-open', ':tipo' => $type, ':ordem' => $order, ':status' => $order === 1 ? 'em_andamento' : 'pendente']);
}

$service = new DashboardResumoService($pdo);
$summary = static fn (): array => $service->resumo('fixture-user', ['CARTORIO']);
$guideQueue = static function (array $result): array {
    $queues = array_values(array_filter($result['geral']['filas'], static fn (array $queue): bool => ($queue['key'] ?? '') === 'guias'));
    if (count($queues) !== 1) throw new RuntimeException('Expected a single guide queue row.');
    return $queues[0];
};
foreach ($types as $index => $type) {
    if ($index > 0) {
        $completePrevious = $pdo->prepare("UPDATE guias_subtarefas SET status='concluida' WHERE id=:id");
        $completePrevious->execute([':id' => 'open-'.$index]);
    }
    $result = $summary();
    $queue = $guideQueue($result);
    if (($queue['type'] ?? null) !== $type || ($queue['count'] ?? null) !== 1
        || $result['geral']['tarefas_abertas'] !== 1 || $result['fila_parcial']) {
        throw new RuntimeException('MySQL dashboard queue mismatch at guide step '.$type.': '.json_encode($queue, JSON_THROW_ON_ERROR));
    }
}
$pdo->exec("UPDATE guias_subtarefas SET status='concluida' WHERE guia_id='guide-open'");
$result = $summary();
if ($result['geral']['tarefas_abertas'] !== 0 || $result['fila_parcial']) {
    throw new RuntimeException('Completed guides must not remain in the executable queue.');
}

$pdo->exec("INSERT INTO guias (id,status,created_at) VALUES ('guide-invalid','em_andamento',NOW())");
$insert->execute([':id' => 'invalid-active', ':guia' => 'guide-invalid', ':tipo' => 'detalhamento_informacoes', ':ordem' => 1, ':status' => 'em_andamento']);
$insert->execute([':id' => 'invalid-future', ':guia' => 'guide-invalid', ':tipo' => 'unknown-step', ':ordem' => 2, ':status' => 'pendente']);
$result = $summary();
$queue = $guideQueue($result);
if ($result['geral']['tarefas_abertas'] !== null || !$result['fila_parcial'] || !($queue['error'] ?? false) || $queue['count'] !== null) {
    throw new RuntimeException('An unknown future step must fail the guide queue closed.');
}

$pdo->exec('DROP TABLE guias_subtarefas');
$result = $summary();
$queue = $guideQueue($result);
if ($result['geral']['tarefas_abertas'] !== null || !$result['fila_parcial'] || !($queue['error'] ?? false) || $queue['count'] !== null) {
    throw new RuntimeException('A missing guide queue table must surface an unavailable count.');
}

$pdo->exec("CREATE TABLE guias_subtarefas (
    id VARCHAR(40) PRIMARY KEY, guia_id VARCHAR(40) NOT NULL, tipo VARCHAR(80) NOT NULL,
    ordem INT NULL, status VARCHAR(40) NULL,
    KEY idx_fixture_parent_order (guia_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("INSERT INTO guias (id,status,created_at) VALUES ('guide-query-error','em_andamento',NOW())");
$pdo->exec("INSERT INTO guias_subtarefas (id,guia_id,tipo,ordem,status) VALUES ('query-error-step','guide-query-error','detalhamento_informacoes',1,'em_andamento')");
$result = $summary();
$queue = $guideQueue($result);
if ($result['geral']['tarefas_abertas'] !== null || !$result['fila_parcial'] || !($queue['error'] ?? false) || $queue['count'] !== null) {
    throw new RuntimeException('A SQL error after table discovery must surface an unavailable count.');
}

echo "PASS: REI-167 dashboard queue ran on disposable MySQL/InnoDB with ONLY_FULL_GROUP_BY\n";
