<?php
declare(strict_types=1);

// O teste só escreve no PDO SQLite em memória; a configuração TCP fictícia não é usada.
foreach (['DB_HOST'=>'127.0.0.1','DB_PORT'=>'1','DB_NAME'=>'isolated_fixture_only','DB_USER'=>'fixture','DB_PASS'=>'fixture-only-unused'] as $name=>$value) putenv($name.'='.$value);
require_once __DIR__ . '/../../app/services/CartasExpedidasService.php';
require_once __DIR__ . '/../../app/access/PageAccessRegistry.php';
require_once __DIR__ . '/../../app/helpers/DeleteAuthorization.php';
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$check(PageAccessRegistry::ruleForPage('cartasexpedidas') === PageAccessRegistry::ruleForPage('cartasexpedidasnova'), 'A rota piloto deve herdar o acesso do Cartório.');
$check(DeleteAuthorization::perfisAdminOuChefia() === ['ADMIN','CHEFIA'], 'Somente ADMIN e CHEFIA podem excluir.');
foreach (['baixo','medio','alto','urgente','critico'] as $value) $check(CartasExpedidasPrioridade::validar($value)===$value, 'Prioridade legítima rejeitada.');
foreach (['agora', [], true, 0, '', null] as $invalid) {
    try { CartasExpedidasPrioridade::validar($invalid); throw new RuntimeException('Prioridade inválida aceita.'); }
    catch (InvalidArgumentException) {}
}
final class PrioridadeFixtureDb extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if (str_starts_with($query,'UPDATE ')) $query=preg_replace('/ LIMIT 1$/','',$query);
        return parent::prepare($query,$options);
    }
}
$db = new PrioridadeFixtureDb('sqlite::memory:');
$db->sqliteCreateFunction('NOW', static fn()=>'2026-11-27 11:00:00',0);
$db->exec("CREATE TABLE cartas_expedidas (id TEXT PRIMARY KEY, processo_origem_id TEXT, numero_processo_origem TEXT, finalidade TEXT, prazo_dias INT, comarca_destino_id TEXT, cidade_destino TEXT, uf_destino TEXT, numero_processo_destino TEXT, vara_destino_id TEXT, vara_destino_nome TEXT, status TEXT, observacoes TEXT, criado_por TEXT, atualizado_por TEXT, created_at TEXT, updated_at TEXT, prioridade_tarefa TEXT DEFAULT 'medio')");
$db->exec("INSERT INTO cartas_expedidas(id,prioridade_tarefa) VALUES ('C0',NULL)");
$repo = new CartasExpedidasRepository($db);
$base = ['processo_origem_id'=>'P','numero_processo_origem'=>'0000000-00.2026.0.00.0000','finalidade'=>'Citação','prazo_dias'=>30,'comarca_destino_id'=>'C','cidade_destino'=>'Local Sintético','uf_destino'=>'BA','status'=>'em_andamento'];
$repo->insert($base+['id'=>'C1']);
$repo->insert($base+['id'=>'C2','prioridade_tarefa'=>'critico']);
$check(CartasExpedidasPrioridade::disponivel($db), 'Campo não foi encontrado no banco sintético.');
$prior = CartasExpedidasPrioridade::paraCartas($db,['C0','C1','C2']);
$check($prior===['C0'=>null,'C1'=>'medio','C2'=>'critico'], 'Valor antigo, padrão, ou escolha divergiu.');
$service=new CartasExpedidasService($db);
$check($service->update('C0',['prioridade_tarefa'=>'urgente']), 'Prioridade não editada.');
try { $service->update('C0',['prioridade_tarefa'=>false]); throw new RuntimeException('Edição deveria rejeitar valor inválido.'); }
catch (InvalidArgumentException) {}
$check($repo->find('C0')['prioridade_tarefa']==='urgente', 'Atualização inválida alterou o registro.');
$hash=new ReflectionMethod(CartasExpedidasService::class,'hashCreatePayload');
$h0=$hash->invoke(null,[],'0','U');
$h1=$hash->invoke(null,['prioridade_tarefa'=>'medio'],'0','U');
$h2=$hash->invoke(null,['prioridade_tarefa'=>'critico'],'0','U');
$check(count(array_unique([$h0,$h1,$h2]))===3, 'Hash de idempotência deve distinguir prioridades.');
echo "PASS: acesso compartilhado; prioridade da carta persistida e validada, legados neutros e hash de cadastro.\n";
