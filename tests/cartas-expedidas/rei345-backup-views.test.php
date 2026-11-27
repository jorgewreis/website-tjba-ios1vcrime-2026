<?php
declare(strict_types=1);

require_once __DIR__.'/../../scripts/backup-vara-cartas-db.php';

class VaraFixtureStatement extends PDOStatement
{
    public function __construct(private array $rows = []) {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->rows[0] ?? false; }
    public function closeCursor(): bool { return true; }
}
class VaraFixtureDb extends PDO
{
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, '(SELECT COUNT(*)')) return new VaraFixtureStatement([[8, 3, 2, 1, 0]]);
        if (str_contains($query, 'SELECT TABLE_NAME')) return new VaraFixtureStatement([
            'v_processos_completo', 'v_usuarios_completo', 'private`name',
        ]);
        if ($query === 'SELECT 1 FROM `v_processos_completo` LIMIT 0' || $query === 'SHOW CREATE VIEW `private``name`') {
            $err = new PDOException('Sensitive fixture must never be returned.');
            $err->errorInfo = ['HY000', 1356, 'Sensitive fixture must never be returned.'];
            throw $err;
        }
        if ($query === 'SHOW CREATE VIEW `v_processos_completo`'
            || $query === 'SHOW CREATE VIEW `v_usuarios_completo`'
            || $query === 'SELECT 1 FROM `private``name` LIMIT 0'
            || $query === 'SELECT 1 FROM `v_usuarios_completo` LIMIT 0') return new VaraFixtureStatement();
        throw new LogicException('Consulta fora do contrato: '.$query);
    }
}
$db = new VaraFixtureDb();
if (probeVaraViews($db) !== ['PROCESSOS LEITURA:1356', 'OUTRA DDL:1356']) throw new RuntimeException('Sonda incorreta ou vazou nome.');
if (varaBackupInventory($db) !== '8:3:2:1:0') throw new RuntimeException('Inventário incorreto.');
echo "PASS: diagnóstico só de metadados, identificadores com escape e log sanitizado.\n";
