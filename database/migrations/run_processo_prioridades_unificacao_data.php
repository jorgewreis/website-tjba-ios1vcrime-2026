<?php
declare(strict_types=1);

/**
 * Copia prioridades da tabela legada `processoPrioridades` (rótulos por número CNJ)
 * para `processo_prioridades` (processo_id + código estável).
 *
 * Executar após `20260331_processo_prioridades_unificacao.sql`. Idempotente por
 * UNIQUE(processo_id, codigo): linhas já existentes são ignoradas pelo repositório.
 *
 * Uso (na raiz do projeto):
 *   php database/migrations/run_processo_prioridades_unificacao_data.php
 */

$root = dirname(__DIR__, 2);
require_once $root . '/app/helpers/Conection.php';
require_once $root . '/app/helpers/PrioridadesProcessuaisCatalog.php';
require_once $root . '/app/helpers/ProcessoPrioridadeOrigem.php';
require_once $root . '/app/repositories/ProcessoPrioridadesRepository.php';
require_once $root . '/app/repositories/ProcessosRepository.php';

$pdo = Conection::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$check = $pdo->query("SHOW TABLES LIKE 'processoPrioridades'");
if ($check === false || $check->rowCount() === 0) {
    fwrite(STDOUT, "Tabela processoPrioridades não existe; nada a migrar.\n");
    exit(0);
}

$processosRepo = new ProcessosRepository($pdo);
$prioridadesRepo = new ProcessoPrioridadesRepository($pdo);

$rows = $pdo->query('SELECT `numero`, `prioridade` FROM `processoPrioridades`')->fetchAll(PDO::FETCH_ASSOC);
if ($rows === false) {
    fwrite(STDERR, "Falha ao ler processoPrioridades.\n");
    exit(1);
}

$inseridos = 0;
$ignorados = 0;
$semProcesso = 0;
$semCodigo = 0;

foreach ($rows as $row) {
    $numero = trim((string) ($row['numero'] ?? ''));
    $prioridade = trim((string) ($row['prioridade'] ?? ''));
    if ($numero === '' || $prioridade === '') {
        $ignorados++;
        continue;
    }
    $proc = $processosRepo->findByNumero($numero);
    if ($proc === null) {
        $semProcesso++;
        continue;
    }
    $processoId = trim((string) ($proc['id'] ?? ''));
    if ($processoId === '') {
        $semProcesso++;
        continue;
    }
    $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo($prioridade);
    if ($codigo === null || !PrioridadesProcessuaisCatalog::isValidCodigo($codigo)) {
        $semCodigo++;
        continue;
    }
    $ex = $prioridadesRepo->findOneByProcessoAndCodigo($processoId, $codigo);
    if ($ex !== null) {
        $ignorados++;
        continue;
    }
    $prioridadesRepo->insert(
        $processoId,
        $codigo,
        ProcessoPrioridadeOrigem::MIGRACAO_PROCESSO_PRIORIDADES,
        ['fonte' => 'processoPrioridades', 'numero_legado' => $numero]
    );
    $inseridos++;
}

fwrite(
    STDOUT,
    sprintf(
        "Migracao processoPrioridades: inseridos=%d ignorados_duplicata_ou_vazio=%d sem_processo=%d sem_codigo_catalogo=%d\n",
        $inseridos,
        $ignorados,
        $semProcesso,
        $semCodigo
    )
);
