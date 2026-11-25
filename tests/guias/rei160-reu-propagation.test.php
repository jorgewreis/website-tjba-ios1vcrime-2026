<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/Guias.php';

final class Rei160Sqlite extends PDO
{
    public function __construct(bool $seedProcess = true)
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->sqliteCreateFunction('CURDATE', static fn (): string => '2026-01-01', 0);
        $this->sqliteCreateFunction('TIMESTAMPDIFF', static fn ($unit, $start, $end): int => 99, 3);

        $this->exec('CREATE TABLE processos (
            id TEXT PRIMARY KEY, id_proc TEXT, numero TEXT, classe TEXT, data_cadastro TEXT,
            numero_processo TEXT NOT NULL, classe_id TEXT, data_fato TEXT, sigiloso INTEGER NOT NULL DEFAULT 0,
            created_at TEXT, updated_at TEXT
        )');
        $this->exec('CREATE TABLE partes (
            id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT NOT NULL, sexo TEXT, data_nascimento TEXT,
            profissao TEXT, created_at TEXT, updated_at TEXT
        )');
        $this->exec('CREATE TABLE processos_partes (
            id TEXT PRIMARY KEY, processo_id TEXT NOT NULL, parte_id TEXT NOT NULL,
            tipo_parte TEXT NOT NULL, polo TEXT NOT NULL, prioridade_mulher INTEGER NOT NULL DEFAULT 0,
            prioridade_crianca_adolescente INTEGER NOT NULL DEFAULT 0, prioridade_idoso INTEGER NOT NULL DEFAULT 0,
            prioridade_pessoa_deficiencia INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT
        )');
        $this->exec('CREATE TABLE guia (
            id_guia TEXT PRIMARY KEY, tipo TEXT, processo TEXT, parte_id TEXT, etapa TEXT, status TEXT,
            regime TEXT, data_fato TEXT, dias_multa INTEGER, observacoes TEXT, data_cadastro TEXT,
            data_atualizacao TEXT, user_cadastro TEXT
        )');
        $this->exec('CREATE TABLE processo_prioridades (
            id TEXT PRIMARY KEY, processo_id TEXT NOT NULL, codigo TEXT NOT NULL, origem TEXT NOT NULL,
            regra_detalhe TEXT, created_at TEXT
        )');
        $this->exec('CREATE TABLE guiasUsuarios (id_alocacao TEXT PRIMARY KEY, id_usuario TEXT, id_guia TEXT, data_cadastro TEXT)');
        $this->exec('CREATE TABLE guia_subtarefas (
            id_guia TEXT, codigo TEXT, titulo TEXT, status TEXT, created_at TEXT,
            started_at TEXT, completed_at TEXT, checkbox_reu_provisoria INTEGER, destino_guia TEXT, dados_extra TEXT
        )');
        $this->exec('CREATE TABLE audit_log (id TEXT PRIMARY KEY, user_id TEXT, action TEXT, context TEXT, created_at TEXT)');
        if ($seedProcess) {
            $this->exec("INSERT INTO processos (id, id_proc, numero, numero_processo, sigiloso) VALUES
                ('PRC-TEST', 'PRC-TEST', '0000001-69.2020.8.05.0001', '0000001-69.2020.8.05.0001', 0)");
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = preg_replace('/TIMESTAMPDIFF\(YEAR,/i', "TIMESTAMPDIFF('YEAR',", $query) ?? $query;
        if (preg_match('/^\s*UPDATE\b/i', $query) === 1) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query) ?? $query;
        }

        return parent::prepare($query, $options);
    }
}

final class Rei160ProcessManager extends ProcessoManager
{
    public function cadastrarPrioridades(string $numeroProcesso, array $prioridades): bool
    {
        return true;
    }
}

function rei160Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei160CreateInput(string $name = 'Pessoa Sintetica', string $process = '0000001-69.2020.8.05.0001'): array
{
    return [
        'tipo' => 'Guia de Execução', 'processo' => $process, 'reu' => $name,
        'prioridades' => [], 'etapa' => 'Cadastro', 'status' => 'Pendente',
        'user_cadastro' => 'TESTE', 'alocacao' => 'nao-alocar', 'subtarefas' => [],
    ];
}

function rei160EditInput(string $name = 'Pessoa Sintetica', string $process = ''): array
{
    return [
        'id_guia' => 'GUIA-EXISTENTE', 'tipo' => 'Guia de Execução', 'processo' => $process, 'reu' => $name,
        'regime' => '', 'data_fato' => null, 'dias_multa' => '', 'observacoes' => null,
        'user_cadastro' => 'TESTE', 'alocacao' => 'nao-alocar', 'servidores' => [],
    ];
}

function rei160Snapshot(PDO $db): array
{
    return [
        $db->query('SELECT * FROM processos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM partes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM processos_partes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guia ORDER BY id_guia')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guia_subtarefas ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function rei160Guides(PDO $db, bool $skipPriorityService = false): Guias
{
    $guias = new Guias($db);
    if ($skipPriorityService) {
        $property = new ReflectionProperty(Guias::class, 'processoManager');
        $property->setValue($guias, new Rei160ProcessManager($db));
    }

    return $guias;
}

// A failure to create the part aborts the guide write; retry succeeds after
// removing the injected database failure.
$db = new Rei160Sqlite();
$db->exec("CREATE TRIGGER rei160_fail_part BEFORE INSERT ON partes BEGIN SELECT RAISE(ABORT, 'injected part insert failure'); END");
$failed = (new Guias($db))->cadastrarGuia(rei160CreateInput());
rei160Assert(($failed['ok'] ?? null) === false, 'Create aceitou uma falha ao cadastrar a parte.');
rei160Assert(!str_contains((string) ($failed['message'] ?? ''), 'injected'), 'Create expôs detalhe interno da falha da parte.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 0, 'Create persistiu a parte após falha injetada.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() === 0, 'Create persistiu o vínculo após falha de cadastro da parte.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM guia')->fetchColumn() === 0, 'Create persistiu guia após falha ao cadastrar a parte.');
rei160Assert(!$db->inTransaction(), 'Create deixou transação aberta após falha ao cadastrar a parte.');
$db->exec('DROP TRIGGER rei160_fail_part');
$retried = (new Guias($db))->cadastrarGuia(rei160CreateInput());
rei160Assert(($retried['ok'] ?? null) === true, 'Retry de create após corrigir o cadastro da parte falhou.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 1, 'Retry não persistiu exatamente uma parte.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() === 1, 'Retry não persistiu exatamente um vínculo.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM guia')->fetchColumn() === 1, 'Retry não persistiu exatamente uma guia.');

// If linking fails after creating the part, the outer guide transaction must
// roll the part and guide back together. A corrected retry must succeed.
$db = new Rei160Sqlite(false);
$db->exec("CREATE TRIGGER rei160_fail_link BEFORE INSERT ON processos_partes BEGIN SELECT RAISE(ABORT, 'injected part link failure'); END");
$before = rei160Snapshot($db);
$failed = (new Guias($db))->cadastrarGuia(rei160CreateInput('Pessoa de Vinculo'));
rei160Assert(($failed['ok'] ?? null) === false, 'Create aceitou falha ao vincular a parte.');
rei160Assert(!str_contains((string) ($failed['message'] ?? ''), 'injected'), 'Create expôs detalhe interno da falha de vínculo.');
rei160Assert(rei160Snapshot($db) === $before, 'Create deixou parte ou guia parcial após falha de vínculo.');
rei160Assert(!$db->inTransaction(), 'Create deixou transação aberta após falha de vínculo.');
$db->exec('DROP TRIGGER rei160_fail_link');
$retried = (new Guias($db))->cadastrarGuia(rei160CreateInput('Pessoa de Vinculo'));
rei160Assert(($retried['ok'] ?? null) === true, 'Retry de create após corrigir o vínculo falhou.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 1, 'Retry de vínculo deixou quantidade inesperada de partes.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() === 1, 'Retry de vínculo não persistiu uma relação.');

// Link failure also aborts an edit after it has created the requested part.
$db = new Rei160Sqlite();
$db->exec("INSERT INTO guia (id_guia, tipo, processo, parte_id, etapa, status) VALUES ('GUIA-EXISTENTE', 'Guia anterior', 'antigo', 'PARTE-ANTIGA', 'Cadastro', 'Pendente')");
$db->exec("CREATE TRIGGER rei160_fail_edit_link BEFORE INSERT ON processos_partes BEGIN SELECT RAISE(ABORT, 'injected edit link failure'); END");
$before = rei160Snapshot($db);
$failed = rei160Guides($db, true)->atualizarGuia(rei160EditInput('Pessoa Editada', '0000001-69.2020.8.05.0001'));
rei160Assert(($failed['ok'] ?? null) === false, 'Edit aceitou falha ao vincular a parte.');
rei160Assert(rei160Snapshot($db) === $before, 'Edit deixou parte, vínculo ou guia parcial após falha de vínculo.');
rei160Assert(!$db->inTransaction(), 'Edit deixou transação aberta após falha de vínculo.');
$db->exec('DROP TRIGGER rei160_fail_edit_link');
$retried = rei160Guides($db, true)->atualizarGuia(rei160EditInput('Pessoa Editada', '0000001-69.2020.8.05.0001'));
rei160Assert(($retried['ok'] ?? null) === true, 'Retry de edit após corrigir o vínculo falhou.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 1, 'Retry de edit-link não persistiu exatamente uma parte.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() === 1, 'Retry de edit-link não persistiu exatamente um vínculo.');

// Edit must also roll back its part creation when persistence fails, and be
// retryable after the database condition is corrected.
$db = new Rei160Sqlite();
$db->exec("INSERT INTO guia (id_guia, tipo, processo, parte_id, etapa, status) VALUES ('GUIA-EXISTENTE', 'Guia anterior', 'antigo', 'PARTE-ANTIGA', 'Cadastro', 'Pendente')");
$before = rei160Snapshot($db);
$db->exec("CREATE TRIGGER rei160_fail_part BEFORE INSERT ON partes BEGIN SELECT RAISE(ABORT, 'injected edit part failure'); END");
$failed = (new Guias($db))->atualizarGuia(rei160EditInput('Pessoa Editada'));
rei160Assert(($failed['ok'] ?? null) === false, 'Edit aceitou falha ao cadastrar a parte.');
rei160Assert(rei160Snapshot($db) === $before, 'Edit deixou alterações parciais após falha de cadastro da parte.');
rei160Assert(!$db->inTransaction(), 'Edit deixou transação aberta após falha ao cadastrar a parte.');
$db->exec('DROP TRIGGER rei160_fail_part');
$retried = (new Guias($db))->atualizarGuia(rei160EditInput('Pessoa Editada'));
rei160Assert(($retried['ok'] ?? null) === true, 'Retry de edit após corrigir o cadastro da parte falhou.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 1, 'Retry de edit não persistiu a parte esperada.');
rei160Assert($db->query("SELECT parte_id FROM guia WHERE id_guia='GUIA-EXISTENTE'")->fetchColumn() !== 'PARTE-ANTIGA', 'Retry de edit não atualizou a parte da guia.');

// An omitted optional part remains valid for both operations.
$db = new Rei160Sqlite();
$noPartCreate = rei160CreateInput('');
$noPartCreate['processo'] = '';
$result = (new Guias($db))->cadastrarGuia($noPartCreate);
rei160Assert(($result['ok'] ?? null) === true, 'Create sem parte opcional deixou de ser aceito.');
$db->exec("INSERT INTO guia (id_guia, tipo, processo, etapa, status) VALUES ('GUIA-EXISTENTE', 'Guia anterior', '', 'Cadastro', 'Pendente')");
$noPartEdit = rei160EditInput('');
$result = (new Guias($db))->atualizarGuia($noPartEdit);
rei160Assert(($result['ok'] ?? null) === true, 'Edit sem parte opcional deixou de ser aceito.');
rei160Assert((int) $db->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 0, 'Ausência legítima criou uma parte inesperada.');
rei160Assert((new ReuManager($db))->cadastrarReu('') === null, 'Cadastro com nome ausente deveria permanecer uma ausência legítima.');
rei160Assert((new ReuManager($db))->vincularReuProcesso('', '') === false, 'Vínculo sem identificadores deveria permanecer uma ausência legítima.');

echo "PASS REI-160: falhas de cadastro e vínculo fazem rollback em create/edit; retries e parte opcional funcionam.\n";
