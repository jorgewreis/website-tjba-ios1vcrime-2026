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
require_once '/repo/app/repositories/GuiasSubtarefasRepository.php';
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
        dados_especificos TEXT NULL,
        created_at DATETIME NULL,
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
$guideSteps = new GuiasSubtarefasRepository($pdo);
$summary = static function (array $areas = ['CARTORIO'], ?string $selectedArea = null) use ($service): array {
    $result = $service->resumo('fixture-user', $areas, $selectedArea);
    $cartorioSelected = in_array('CARTORIO', $areas, true) && ($selectedArea === null || $selectedArea === 'CARTORIO');
    if (($result['setor']['conectado'] ?? null) !== $cartorioSelected) {
        throw new RuntimeException('Cartório authorization signal does not match the selected area.');
    }
    $sectorQueues = $result['setor']['filas'] ?? null;
    if ($cartorioSelected && $sectorQueues !== $result['geral']['filas']) {
        throw new RuntimeException('Authorized Cartório queues must match the served general queue contract.');
    }
    if (!$cartorioSelected && $sectorQueues !== []) {
        throw new RuntimeException('A non-Cartório selection must not expose sector queues.');
    }
    return $result;
};
$guideCount = static function (array $result, string $type): ?int {
    foreach ($result['setor']['filas'] as $queue) {
        if (($queue['key'] ?? null) !== 'guias') continue;
        if (!empty($queue['error'])) return null;
        if (($queue['type'] ?? null) === $type) {
            return ($queue['count'] ?? null) === null ? null : (int) $queue['count'];
        }
    }
    return 0;
};
$guideQueue = static function (array $result): array {
    $queues = array_values(array_filter($result['geral']['filas'], static fn (array $queue): bool => ($queue['key'] ?? '') === 'guias'));
    if (count($queues) !== 1) throw new RuntimeException('Expected a single guide queue row.');
    return $queues[0];
};
$emitGuidePayload = static function (string $scenario, array $result): void {
    $onlyGuideRows = static fn (array $queues): array => array_values(array_filter(
        $queues,
        static fn (array $queue): bool => ($queue['key'] ?? null) === 'guias'
    ));
    $payload = [
        'geral' => ['filas' => $onlyGuideRows($result['geral']['filas'] ?? [])],
        'setor' => [
            'areas' => $result['setor']['areas'] ?? [],
            'selecionado' => $result['setor']['selecionado'] ?? null,
            'conectado' => (bool) ($result['setor']['conectado'] ?? false),
            'filas' => $onlyGuideRows($result['setor']['filas'] ?? []),
        ],
    ];
    echo 'REI167_PAYLOAD '.json_encode(['scenario' => $scenario, 'payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
};
foreach ($types as $index => $type) {
    if ($index > 0) {
        $completePrevious = $pdo->prepare("UPDATE guias_subtarefas SET status='concluida' WHERE id=:id");
        $completePrevious->execute([':id' => 'open-'.$index]);
    }
    $result = $summary();
    $listStep = $guideSteps->findPendenteOuEmAndamentoByGuiaId('guide-open');
    if (count($listStep) !== 1 || ($listStep[0]['tipo'] ?? null) !== $type) {
        throw new RuntimeException('List repository and dashboard disagree on the executable guide step: '.$type);
    }
    $queue = $guideQueue($result);
    if (($queue['type'] ?? null) !== $type || ($queue['count'] ?? null) !== 1
        || $result['geral']['tarefas_abertas'] !== 1 || $result['fila_parcial']) {
        throw new RuntimeException('MySQL dashboard queue mismatch at guide step '.$type.': '.json_encode($queue, JSON_THROW_ON_ERROR));
    }
    if ($type === 'expedicao_guia'
        && ($guideCount($result, 'expedicao_guia') !== 1 || $guideCount($result, 'envio_guia') !== 0)) {
        throw new RuntimeException('Guide dispatch state must serve one dispatch and zero sending guides.');
    }
    if ($type === 'envio_guia'
        && ($guideCount($result, 'expedicao_guia') !== 0 || $guideCount($result, 'envio_guia') !== 1)) {
        throw new RuntimeException('Guide send transition must serve zero dispatch and one sending guide.');
    }
    if ($type === 'envio_guia') $emitGuidePayload('envio', $result);
    if ($type === 'expedicao_guia') {
        $gabinete = $summary(['CARTORIO', 'GABINETE'], 'GABINETE');
        if ($gabinete['setor']['selecionado'] !== 'GABINETE' || $gabinete['setor']['conectado']
            || $gabinete['setor']['filas'] !== [] || $guideCount($gabinete, 'expedicao_guia') !== 0) {
            throw new RuntimeException('Explicit Gabinete selection must not expose Cartório guide queues.');
        }
        $generalGuide = array_values(array_filter($gabinete['geral']['filas'], static fn (array $row): bool => ($row['key'] ?? null) === 'guias'));
        if (count($generalGuide) !== 1 || ($generalGuide[0]['count'] ?? null) !== 1) {
            throw new RuntimeException('The synthetic guide remains present in the general summary fixture.');
        }
        $emitGuidePayload('gabinete', $gabinete);
        $emitGuidePayload('expedicao', $result);
    }
}
$pdo->exec("UPDATE guias_subtarefas SET status='concluida' WHERE guia_id='guide-open'");
$result = $summary();
if ($result['geral']['tarefas_abertas'] !== 0 || $result['fila_parcial']
    || $guideCount($result, 'expedicao_guia') !== 0 || $guideCount($result, 'envio_guia') !== 0) {
    throw new RuntimeException('Completed guides must be available as zero, not remain in the executable queue.');
}
$emitGuidePayload('zero', $result);

$pdo->exec("INSERT INTO guias (id,status,created_at) VALUES ('guide-invalid','em_andamento',NOW())");
$insert->execute([':id' => 'invalid-active', ':guia' => 'guide-invalid', ':tipo' => 'detalhamento_informacoes', ':ordem' => 1, ':status' => 'em_andamento']);
$insert->execute([':id' => 'invalid-future', ':guia' => 'guide-invalid', ':tipo' => 'unknown-step', ':ordem' => 2, ':status' => 'pendente']);
$result = $summary();
$queue = $guideQueue($result);
if ($result['geral']['tarefas_abertas'] !== null || !$result['fila_parcial'] || !($queue['error'] ?? false) || $queue['count'] !== null
    || $guideCount($result, 'expedicao_guia') !== null) {
    throw new RuntimeException('An unknown future step must fail the guide queue closed as unavailable, not zero.');
}
$emitGuidePayload('falha', $result);

$pdo->exec('DROP TABLE guias_subtarefas');
$result = $summary();
$queue = $guideQueue($result);
if ($result['geral']['tarefas_abertas'] !== null || !$result['fila_parcial'] || !($queue['error'] ?? false) || $queue['count'] !== null
    || $guideCount($result, 'expedicao_guia') !== null) {
    throw new RuntimeException('A missing guide queue table must surface unavailable, not zero.');
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
