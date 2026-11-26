<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/GabineteReusPresosService.php';

// Fixture inteiramente em memória, com adaptação apenas da sintaxe MySQL.
$db = new class ('sqlite::memory:') extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        $query = str_replace('TIMESTAMPDIFF(YEAR, p.`data_nascimento`, CURDATE())', '100', $query);
        return parent::prepare($query, $options);
    }
};
$db->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
$db->exec(<<<'SQL'
CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE gabinete_reu_preso (id TEXT PRIMARY KEY, status TEXT, controle_tipo TEXT, proxima_revisao TEXT, updated_at TEXT);
CREATE TABLE gabinete_reu_preso_processo (id TEXT PRIMARY KEY, reu_preso_id TEXT, processo_id TEXT, principal INTEGER, situacao TEXT, data_prisao TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE gabinete_reu_preso_movimentacao (reu_preso_id TEXT, reu_preso_processo_id TEXT, data_movimentacao TEXT, tipo_codigo TEXT);
CREATE TABLE partes (id TEXT, data_nascimento TEXT);
CREATE TABLE processos_partes (processo_id TEXT, parte_id TEXT, prioridade_crianca_adolescente INTEGER);
CREATE TABLE processo_prioridades (id TEXT, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT);
INSERT INTO processos (id, numero_processo, sigiloso) VALUES ('P1', '', 0), ('P2', '', 0);
INSERT INTO gabinete_reu_preso VALUES ('R', 'ativo', 'principal', NULL, NULL);
INSERT INTO gabinete_reu_preso_processo VALUES ('L1', 'R', 'P1', 1, 'solto', '2026-01-01', '1', NULL), ('L2', 'R', 'P2', 0, 'solto', '2026-02-01', '2', NULL);
SQL);
$service = new GabineteReusPresosService($db);
$selecionado = static fn () => $db->query('SELECT id FROM gabinete_reu_preso_processo WHERE principal = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$data = static fn () => $db->query("SELECT proxima_revisao FROM gabinete_reu_preso WHERE id = 'R'")->fetchColumn();
$verifica = static function (bool $condition, string $error): void {
    if (!$condition) {
        throw new RuntimeException($error);
    }
};
$service->setPrincipal('R', 'L2');
$verifica($selecionado() === ['L2'] && $data() === '2026-05-02', 'A troca precisa refletir a nova data-base.');
$db->exec("CREATE TRIGGER falha_promocao BEFORE UPDATE OF principal ON gabinete_reu_preso_processo WHEN NEW.id = 'L1' AND NEW.principal = 1 BEGIN SELECT RAISE(ABORT, 'fixture_falha'); END");
try {
    $service->setPrincipal('R', 'L1');
    throw new RuntimeException('A fixture deveria impedir a promoção.');
} catch (PDOException $exception) {
    $verifica(str_contains($exception->getMessage(), 'fixture_falha'), 'Falha de banco inesperada.');
}
$verifica($selecionado() === ['L2'] && $data() === '2026-05-02' && !$db->inTransaction(), 'Rollback deve preservar o principal e a revisão.');
$db->exec('DROP TRIGGER falha_promocao');
$service->unlinkProcesso('L2');
$verifica($selecionado() === ['L1'] && $data() === '2026-04-01', 'Remoção deve promover o vínculo restante e recalcular.');
$service->unlinkProcesso('L1');
$verifica($selecionado() === [] && $data() === null, 'Sem vínculos não deve restar prazo.');
echo "PASS REI-94/95: troca, rollback, remoção, e ausência de vínculos (SQLite em memória)\n";
