<?php
declare(strict_types=1);
// SQLite in memory; no application configuration or external connection.
require_once __DIR__ . '/../../app/services/CartasRecebidasService.php';

final class CartaTestPdo extends PDO
{
    public bool $mutateBeforeLockedRead = false;
    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn(): string => '2026-01-01');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($this->mutateBeforeLockedRead && str_contains($query, 'WHERE `id` = :id LIMIT 1 FOR UPDATE') && str_contains($query, '`tribunal_id`')) {
            $this->mutateBeforeLockedRead = false;
            $this->exec("UPDATE comarcas SET cidade = 'Outra cidade' WHERE id = 'AG'");
        }
        $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        return parent::prepare(preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query), $options);
    }
}
$db = new CartaTestPdo();
$db->exec('CREATE TABLE comarcas (id TEXT PRIMARY KEY, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE tribunais (id TEXT PRIMARY KEY, sigla TEXT, nome TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO tribunais VALUES ('T','TJBA','Tribunal','',''), ('TP','TJPI','Tribunal','',''), ('OUT','OUTRO','Outro','','')");
$db->exec("INSERT INTO comarcas VALUES ('BA','T','BA','Bom Jesus','Bom Jesus','Ativa','',''), ('PI','TP','PI','Bom Jesus','Bom Jesus','Ativa','',''), ('AG','T','BA','Ilhéus','Itajuípe','Agregada','',''), ('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','',''), ('OUT-SEDE','OUT','BA','Ilhéus','Ilhéus','Ativa','',''), ('OUT-ONLY','OUT','BA','Outro Local','Outro Local','Ativa','','')");
$db->exec('CREATE TABLE cartas_recebidas (id TEXT PRIMARY KEY, cidade_origem TEXT, uf_origem TEXT, comarca_origem_id TEXT, finalidade TEXT, updated_at TEXT)');
$db->exec("INSERT INTO cartas_recebidas VALUES ('C','Bom Jesus','BA','BA','Inicial','')");
$repo = new ComarcasRepository($db);
if (($repo->findFirstByNomeOuCidade('bom jesus', 'PI')['id'] ?? '') !== 'PI') throw new RuntimeException('UF não filtrada');
if (($repo->findFirstByNomeOuCidade('Ilheus', 'BA', 'T')['id'] ?? '') !== 'SEDE') throw new RuntimeException('Cidade exata não prevaleceu sobre nome de agregada');
if (($repo->findFirstByNomeOuCidade('Itajuipe', 'BA', 'T')['id'] ?? '') !== 'AG') throw new RuntimeException('Cidade agregada não encontrada');
$db->exec("DELETE FROM comarcas WHERE id IN ('AG','SEDE')");
$db->exec("INSERT INTO comarcas VALUES ('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','',''), ('AG','T','BA','Ilhéus','Itajuípe','Agregada','','')");
if (($repo->findFirstByNomeOuCidade('Ilheus', 'BA', 'T')['id'] ?? '') !== 'SEDE') throw new RuntimeException('Resultado depende da ordem de inserção');
$db->exec("INSERT INTO comarcas VALUES ('OUTRA','T','BA','Outra comarca','Ilhéus','Ativa','','')");
if ($repo->findFirstByNomeOuCidade('Ilheus', 'BA', 'T') !== null) throw new RuntimeException('Ambiguidade escolheu ID arbitrário');
$db->exec("DELETE FROM comarcas WHERE id = 'OUTRA'");
$db->exec("DELETE FROM comarcas WHERE id = 'SEDE'");
if (($repo->findFirstByNomeOuCidade('Ilheus', 'BA', 'T')['id'] ?? '') !== 'AG') throw new RuntimeException('Fallback único por nome não reconhecido');
$db->exec("INSERT INTO comarcas VALUES ('AG2','T','BA','Ilhéus','Buerarema','Agregada','','')");
if ($repo->findFirstByNomeOuCidade('Ilheus', 'BA', 'T') !== null) throw new RuntimeException('Fallback ambíguo escolheu ID arbitrário');
$db->exec("DELETE FROM comarcas WHERE id = 'AG2'");
if ($repo->resolverCidadeUf('AG', 'Itajuipe', 'ba') !== null) throw new RuntimeException('Agregada sem sede foi aceita');
$db->exec("INSERT INTO comarcas VALUES ('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','','')");
if (($repo->resolverCidadeUf('AG', 'Itajuipe', 'ba')['id'] ?? '') !== 'SEDE' || ($repo->resolverCidadeUf('AG', 'Ilheus', 'BA')['id'] ?? '') !== 'SEDE') throw new RuntimeException('Sede não resolvida');
$db->exec("UPDATE comarcas SET nome = 'Ilheus' WHERE id = 'AG'");
$db->beginTransaction();
if (($repo->resolverCidadeUf('AG', 'Itajuipe', 'BA', true)['id'] ?? '') !== 'SEDE') throw new RuntimeException('Acentuação divergente impediu resolução com lock');
$db->rollBack();
$db->exec("UPDATE comarcas SET nome = 'Ilhéus' WHERE id = 'AG'");
if ($repo->resolverCidadeUf('AG', 'Itajuipe', 'PI') !== null) throw new RuntimeException('Agregada de outra UF aceita');
$db->exec("INSERT INTO comarcas VALUES ('SEDE2','T','BA','Ilhéus','Ilhéus','Ativa','','')");
if ($repo->resolverCidadeUf('AG', 'Itajuipe', 'BA') !== null) throw new RuntimeException('Sede ambígua aceita');
$db->exec("DELETE FROM comarcas WHERE id = 'SEDE2'");
$service = new CartasRecebidasService($db);
$resolverCadastro = new ReflectionMethod(CartasRecebidasService::class, 'resolverLocalidadeCadastro');
$db->beginTransaction();
if ($resolverCadastro->invoke($service, 'Itajuípe', 'BA') !== ['Ilhéus', 'SEDE']) throw new RuntimeException('Cadastro não escolheu sede da agregada');
if ($resolverCadastro->invoke($service, 'Ilhéus', 'BA') !== ['Ilhéus', 'SEDE']) throw new RuntimeException('Cadastro da sede divergiu');
$db->mutateBeforeLockedRead = true;
try { $resolverCadastro->invoke($service, 'Itajuípe', 'BA'); throw new RuntimeException('Mapeamento alterado foi aceito'); }
catch (InvalidArgumentException $e) {}
$db->exec("UPDATE comarcas SET cidade = 'Itajuípe' WHERE id = 'AG'");
$onlyOther = $resolverCadastro->invoke($service, 'Outro Local', 'BA');
if ($onlyOther[1] === 'OUT-ONLY' || $onlyOther[0] !== 'Outro Local') throw new RuntimeException('Cadastro usou tribunal incorreto');
$unknown = $resolverCadastro->invoke($service, 'Cidade Nova', 'BA');
if ($unknown[0] !== 'Cidade Nova' || $unknown[1] === '' || !$repo->existeNomeOuCidade('Cidade Nova', 'BA')) throw new RuntimeException('Fallback de cadastro não preservado');
$db->exec("DELETE FROM comarcas WHERE id = 'SEDE'");
try { $resolverCadastro->invoke($service, 'Itajuípe', 'BA'); throw new RuntimeException('Cadastro aceitou sede ausente'); }
catch (InvalidArgumentException $e) {}
$db->exec("INSERT INTO comarcas VALUES ('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','',''), ('SEDE2','T','BA','Ilhéus','Ilhéus','Ativa','','')");
try { $resolverCadastro->invoke($service, 'Itajuípe', 'BA'); throw new RuntimeException('Cadastro aceitou sede ambígua'); }
catch (InvalidArgumentException $e) {}
$db->rollBack();
foreach ([['uf_origem'=>'PI'], ['uf_origem'=>''], ['uf_origem'=>null], ['cidade_origem'=>'Inexistente'], ['comarca_origem_id'=>null], ['comarca_origem_id'=>'PI'], ['cidade_origem'=>'Ilhéus','comarca_origem_id'=>'OUT-SEDE']] as $bad) {
    try { $service->update('C', $bad); throw new RuntimeException('Entrada incorreta aceita'); }
    catch (InvalidArgumentException $e) {}
    if ($db->query("SELECT cidade_origem || '/' || uf_origem || '/' || comarca_origem_id FROM cartas_recebidas WHERE id='C'")->fetchColumn() !== 'Bom Jesus/BA/BA') throw new RuntimeException('Dado inválido gravado');
}
if (!$service->update('C', ['cidade_origem'=>'Bom Jesus','uf_origem'=>'PI','comarca_origem_id'=>'PI'])) throw new RuntimeException('Atualização válida recusada');
if ($db->query("SELECT uf_origem || '/' || comarca_origem_id FROM cartas_recebidas WHERE id='C'")->fetchColumn() !== 'PI/PI') throw new RuntimeException('Atualização válida não persistiu');
if (!$service->update('C', ['cidade_origem'=>'Itajuípe','uf_origem'=>'BA','comarca_origem_id'=>'AG'])) throw new RuntimeException('Agregada válida recusada');
if ($db->query("SELECT cidade_origem || '/' || uf_origem || '/' || comarca_origem_id FROM cartas_recebidas WHERE id='C'")->fetchColumn() !== 'Ilhéus/BA/SEDE') throw new RuntimeException('Sede não persistida');
$db->exec("DELETE FROM comarcas WHERE id = 'SEDE'");
try { $service->update('C', ['cidade_origem'=>'Itajuípe','uf_origem'=>'BA','comarca_origem_id'=>'AG']); throw new RuntimeException('Agregada sem sede aceita'); }
catch (InvalidArgumentException $e) {}
if ($db->query("SELECT cidade_origem || '/' || uf_origem || '/' || comarca_origem_id FROM cartas_recebidas WHERE id='C'")->fetchColumn() !== 'Ilhéus/BA/SEDE') throw new RuntimeException('Falha alterou carta');
$db->exec("INSERT INTO comarcas VALUES ('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','','')");
$db->beginTransaction();
try { $service->update('C', ['uf_origem'=>'PI']); throw new RuntimeException('Divergência em transação aceita'); }
catch (InvalidArgumentException $e) {}
if (!$db->inTransaction() || $db->query("SELECT uf_origem FROM cartas_recebidas WHERE id='C'")->fetchColumn() !== 'BA') throw new RuntimeException('Savepoint não preservou estado');
$db->rollBack();
if (!$service->update('C', ['finalidade'=>'Outra'])) throw new RuntimeException('Atualização parcial sem localidade recusada');
echo "PASS comarca, UF, cidade e persistência (SQLite em memória; SQL adaptado)\n";
