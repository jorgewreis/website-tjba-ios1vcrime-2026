<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/Guias.php';

final class Rei159Sqlite extends PDO
{
    public array $preparedSql = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->sqliteCreateFunction('CURDATE', static fn (): string => '2026-01-01', 0);
        $this->sqliteCreateFunction('TIMESTAMPDIFF', static fn ($unit, $start, $end): int => 99, 3);

        $this->exec('CREATE TABLE processos (
            id TEXT PRIMARY KEY, id_proc TEXT, numero TEXT, classe TEXT,
            numero_processo TEXT NOT NULL, classe_id TEXT, data_fato TEXT,
            sigiloso INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT
        )');
        $this->exec('CREATE TABLE processo_prioridades (
            id TEXT PRIMARY KEY, processo_id TEXT NOT NULL, codigo TEXT NOT NULL,
            origem TEXT NOT NULL, regra_detalhe TEXT, created_at TEXT
        )');
        $this->exec('CREATE TABLE processos_partes (
            id TEXT PRIMARY KEY, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT,
            prioridade_crianca_adolescente INTEGER NOT NULL DEFAULT 0
        )');
        $this->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, data_nascimento TEXT, nome_completo TEXT)');
        $this->exec('CREATE TABLE guia (
            id_guia TEXT PRIMARY KEY, tipo TEXT, processo TEXT, parte_id TEXT, etapa TEXT, status TEXT,
            regime TEXT, data_fato TEXT, dias_multa INTEGER, observacoes TEXT, data_cadastro TEXT,
            data_atualizacao TEXT, user_cadastro TEXT
        )');
        $this->exec('CREATE TABLE guiasUsuarios (id_alocacao TEXT PRIMARY KEY, id_usuario TEXT, id_guia TEXT, data_cadastro TEXT)');
        $this->exec('CREATE TABLE guia_subtarefas (
            id_guia TEXT, codigo TEXT, titulo TEXT, status TEXT, created_at TEXT,
            started_at TEXT, completed_at TEXT, checkbox_reu_provisoria INTEGER,
            destino_guia TEXT, dados_extra TEXT
        )');
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;
        $query = preg_replace('/TIMESTAMPDIFF\(YEAR,/i', "TIMESTAMPDIFF('YEAR',", $query);
        $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}

function rei159Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei159Db(bool $withProcess = true): Rei159Sqlite
{
    $db = new Rei159Sqlite();
    if ($withProcess) {
        $db->exec("INSERT INTO processos (id, id_proc, numero, numero_processo, sigiloso) VALUES ('P1', 'P1', '12345678901234567890', '1234567-89.0123.4567.8.90', 0)");
        $db->exec("INSERT INTO processo_prioridades (id, processo_id, codigo, origem) VALUES ('OLD', 'P1', 'idoso', 'manual')");
    }
    return $db;
}

function rei159CreateInput(array $priorities = ['idoso']): array
{
    return [
        'tipo' => 'Guia de Execução', 'processo' => '1234567-89.0123.4567.8.90',
        'reu' => '', 'prioridades' => $priorities, 'etapa' => 'Cadastro', 'status' => 'Pendente',
        'user_cadastro' => 'TESTE', 'alocacao' => 'nao-alocar', 'subtarefas' => [],
    ];
}

function rei159EditInput(array $priorities = ['idoso']): array
{
    return [
        'id_guia' => 'G1', 'tipo' => 'Guia de Execução',
        'processo' => '1234567-89.0123.4567.8.90', 'reu' => '', 'prioridades' => $priorities,
        'regime' => '', 'data_fato' => null, 'dias_multa' => '', 'observacoes' => null,
        'user_cadastro' => 'TESTE', 'alocacao' => 'nao-alocar', 'servidores' => [],
    ];
}

function rei159Snapshot(PDO $db): array
{
    return [
        $db->query('SELECT * FROM guia ORDER BY id_guia')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM processo_prioridades ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guia_subtarefas ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guiasUsuarios ORDER BY id_alocacao')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function rei159AssertDeleteBeforeInsert(Rei159Sqlite $db): void
{
    $deleteIndex = $insertIndex = null;
    foreach ($db->preparedSql as $index => $sql) {
        if ($deleteIndex === null && preg_match('/^\s*DELETE\s+FROM\s+`?processo_prioridades`?/i', $sql)) {
            $deleteIndex = $index;
        }
        if ($insertIndex === null && preg_match('/^\s*INSERT\s+INTO\s+`?processo_prioridades`?/i', $sql)) {
            $insertIndex = $index;
        }
    }
    rei159Check($deleteIndex !== null && $insertIndex !== null && $deleteIndex < $insertIndex, 'O teste não comprovou falha de inserção após a remoção anterior.');
}

// A failure during replacement happens after the old manual row was deleted.
$db = rei159Db();
$db->exec("CREATE TRIGGER rei159_fail_priority_insert BEFORE INSERT ON processo_prioridades WHEN NEW.origem = 'cadastro_guias' BEGIN SELECT RAISE(ABORT, 'synthetic priority insert failure'); END");
$result = (new Guias($db))->cadastrarGuia(rei159CreateInput());
rei159Check(($result['ok'] ?? null) === false, 'Create aceitou falha ao substituir prioridades.');
rei159AssertDeleteBeforeInsert($db);
rei159Check(!str_contains((string) ($result['message'] ?? ''), 'synthetic'), 'Create expôs detalhe interno do erro.');
rei159Check((int) $db->query('SELECT COUNT(*) FROM guia')->fetchColumn() === 0, 'Create persistiu guia após falha de prioridade.');
rei159Check((int) $db->query("SELECT COUNT(*) FROM processo_prioridades WHERE id='OLD' AND origem='manual'")->fetchColumn() === 1, 'Rollback não restaurou prioridade manual removida.');
rei159Check(!$db->inTransaction(), 'Create deixou transação aberta após falha.');
$db->exec('DROP TRIGGER rei159_fail_priority_insert');
$result = (new Guias($db))->cadastrarGuia(rei159CreateInput());
rei159Check(($result['ok'] ?? null) === true, 'Retry do create após correção não funcionou.');
rei159Check((int) $db->query("SELECT COUNT(*) FROM processo_prioridades WHERE processo_id='P1' AND codigo='idoso'")->fetchColumn() === 1, 'Retry não persistiu exatamente uma prioridade.');

// Editing must also roll back both the guide update and priority replacement.
$db = rei159Db();
$db->exec("INSERT INTO guia (id_guia, tipo, processo, regime, status, data_atualizacao) VALUES ('G1', 'Guia anterior', '1234567-89.0123.4567.8.90', 'aberto', 'Pendente', 'antes')");
$db->exec("CREATE TRIGGER rei159_fail_priority_insert BEFORE INSERT ON processo_prioridades WHEN NEW.origem = 'cadastro_guias' BEGIN SELECT RAISE(ABORT, 'synthetic priority insert failure'); END");
$before = rei159Snapshot($db);
$result = (new Guias($db))->atualizarGuia(rei159EditInput());
rei159Check(($result['ok'] ?? null) === false, 'Edit aceitou falha ao substituir prioridades.');
rei159AssertDeleteBeforeInsert($db);
rei159Check(!str_contains((string) ($result['message'] ?? ''), 'synthetic'), 'Edit expôs detalhe interno do erro.');
rei159Check(rei159Snapshot($db) === $before, 'Edit deixou gravações parciais após falha de prioridade.');
rei159Check(!$db->inTransaction(), 'Edit deixou transação aberta após falha.');
$db->exec('DROP TRIGGER rei159_fail_priority_insert');
$result = (new Guias($db))->atualizarGuia(rei159EditInput());
rei159Check(($result['ok'] ?? null) === true, 'Retry do edit após correção não funcionou.');
rei159Check($db->query("SELECT tipo FROM guia WHERE id_guia='G1'")->fetchColumn() === 'Guia de Execução', 'Edit válido não atualizou a guia.');

// A manager's false result for a missing local process must abort create and edit.
$db = rei159Db(false);
$result = (new Guias($db))->cadastrarGuia(rei159CreateInput());
rei159Check(($result['ok'] ?? null) === false && (int) $db->query('SELECT COUNT(*) FROM guia')->fetchColumn() === 0, 'Create aceitou prioridades para processo inexistente.');
$db = rei159Db(false);
$db->exec("INSERT INTO guia (id_guia, tipo, processo, regime, status, data_atualizacao) VALUES ('G1', 'Guia anterior', '1234567-89.0123.4567.8.90', 'aberto', 'Pendente', 'antes')");
$before = rei159Snapshot($db);
$result = (new Guias($db))->atualizarGuia(rei159EditInput([]));
rei159Check(($result['ok'] ?? null) === false && rei159Snapshot($db) === $before, 'Edit aceitou limpar prioridades em processo inexistente.');

// Successful empty replacement and repeated replacement remain supported.
$db = rei159Db();
$guias = new Guias($db);
rei159Check(($guias->cadastrarPrioridades('1234567-89.0123.4567.8.90', []) === true), 'Substituição vazia deveria limpar prioridades manuais.');
rei159Check((int) $db->query("SELECT COUNT(*) FROM processo_prioridades WHERE processo_id='P1' AND origem IN ('manual', 'cadastro_guias')")->fetchColumn() === 0, 'Substituição vazia não removeu as prioridades manuais antigas.');
rei159Check($guias->cadastrarPrioridades('1234567-89.0123.4567.8.90', ['idoso']), 'Primeira substituição válida falhou.');
rei159Check($guias->cadastrarPrioridades('1234567-89.0123.4567.8.90', ['idoso']), 'Retentativa de substituição válida falhou.');
rei159Check((int) $db->query("SELECT COUNT(*) FROM processo_prioridades WHERE processo_id='P1' AND codigo='idoso'")->fetchColumn() === 1, 'Retentativa duplicou a prioridade.');

echo "PASS REI-159: rollback em create/edit, processo ausente, falha de insert e retry em SQLite sintético.\n";
