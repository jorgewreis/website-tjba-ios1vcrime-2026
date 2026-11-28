<?php
declare(strict_types=1);
// Executar apenas dentro da rede interna descartável do runner isolado.
if (getenv('CEJUSC_DISPOSABLE_TEST') !== '1') {
    throw new RuntimeException('Este teste exige o runner descartável isolado.');
}
$database = (string) getenv('CEJUSC_TEST_DATABASE');
if (!preg_match('/^cejusc_[a-f0-9]{32}$/D', $database)) throw new RuntimeException('Nome descartável inválido.');
$host = (string) getenv('CEJUSC_TEST_HOST');
if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw new RuntimeException('Host IPv4 da rede descartável inválido.');
require_once __DIR__ . '/../../app/services/CejuscRegistrosService.php';
function ck(bool $v, string $msg): void { if (!$v) throw new RuntimeException($msg); }
$connect = static fn(): PDO => new PDO('mysql:host='.$host.';port=3306;dbname='.$database.';charset=utf8mb4', 'root', getenv('CEJUSC_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = $connect();
foreach ([
    'CREATE TABLE processos (id VARCHAR(64) PRIMARY KEY, numero_processo VARCHAR(64), classe_id VARCHAR(64), data_fato DATE, sigiloso INT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE partes (id VARCHAR(64) PRIMARY KEY, cpf VARCHAR(32), nome_completo VARCHAR(180), sexo VARCHAR(10), data_nascimento DATE, profissao VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE processos_partes (id VARCHAR(64) PRIMARY KEY, processo_id VARCHAR(64), parte_id VARCHAR(64), tipo_parte VARCHAR(50), polo VARCHAR(50), prioridade_mulher INT, prioridade_crianca_adolescente INT, prioridade_idoso INT, prioridade_pessoa_deficiencia INT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE processo_prioridades (id VARCHAR(64) PRIMARY KEY, processo_id VARCHAR(64), codigo VARCHAR(100), origem VARCHAR(100), regra_detalhe TEXT, created_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE cejusc_registros (id VARCHAR(64) PRIMARY KEY, numero_processo VARCHAR(64), classe_codigo VARCHAR(30), classe_descricao VARCHAR(255), nome_parte VARCHAR(180), data_envio_cejusc DATE, criado_por VARCHAR(64), atualizado_por VARCHAR(64), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB',
] as $ddl) $db->exec($ddl);
function cnj(string $seq): string { $base = $seq . '20248100001'; for ($i=0; $i<100; $i++) { $v=substr($base,0,7).str_pad((string)$i,2,'0',STR_PAD_LEFT).substr($base,7); if (CnjValidator::isValid($v)) return $v; } throw new RuntimeException('CNJ sintético inválido'); }
$service = new CejuscRegistrosService($db);
$payload = ['numero_processo'=>cnj('0000001'), 'nome_parte'=>'Pessoa Sintetica A', 'data_envio_cejusc'=>'2026-01-01', 'usuario_id'=>'forjado'];
$id = $service->create($payload, 'criador');
$row = $service->find($id); ck($row['criado_por']==='criador' && $row['atualizado_por']==='criador', 'Autoria de criação');
ck($service->update($id,$payload,'editor'), 'Edição válida');
$row=$service->find($id); ck($row['criado_por']==='criador' && $row['atualizado_por']==='editor', 'Autoria de edição');
$tables=['processos','partes','processos_partes','processo_prioridades','cejusc_registros'];
$snapshot=static function() use ($db,$tables): array { $out=[]; foreach($tables as $t) $out[$t]=$db->query("SELECT * FROM `$t` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); return $out; };
$new=['numero_processo'=>cnj('0000002'),'nome_parte'=>'Pessoa Sintetica Nova','data_envio_cejusc'=>'2026-01-01','usuario_id'=>'forjado'];
$before=$snapshot(); ck(!$service->update('ausente',$new,'editor'), 'ID ausente'); ck($snapshot()===$before,'Efeitos após ausência');
$oldNumber = $service->find($id)['numero_processo'];
$oldPriority = $db->query("SELECT p.origem FROM processo_prioridades p JOIN processos pr ON pr.id=p.processo_id WHERE pr.numero_processo=".$db->quote($oldNumber)." AND p.codigo=".$db->quote(PrioridadesProcessuaisCatalog::codigoViolenciaDomestica()))->fetchColumn();
ck($oldPriority===ProcessoPrioridadeOrigem::REGRA_VIOLENCIA_DOMESTICA_CEJUSC, 'Prioridade CEJUSC original ausente');
$destinationProcessId=(new ProcessosRepository($db))->ensureProcess($new['numero_processo']);
$manualPriorityId='PRP-SINTETICO-MANUAL-001';
$manual=$db->prepare('INSERT INTO processo_prioridades (id,processo_id,codigo,origem,regra_detalhe,created_at) VALUES (?,?,?,?,NULL,NOW())');
$manual->execute([$manualPriorityId,$destinationProcessId,PrioridadesProcessuaisCatalog::codigoViolenciaDomestica(),ProcessoPrioridadeOrigem::MANUAL]);
ck($service->update($id,$new,'editor'), 'Mudança de processo falhou');
$newNumber = $service->find($id)['numero_processo'];
$newPriority = $db->query("SELECT p.origem FROM processo_prioridades p JOIN processos pr ON pr.id=p.processo_id WHERE pr.numero_processo=".$db->quote($newNumber)." AND p.codigo=".$db->quote(PrioridadesProcessuaisCatalog::codigoViolenciaDomestica()))->fetchColumn();
ck($newPriority===ProcessoPrioridadeOrigem::MANUAL, 'A prioridade de origem manual no destino foi sobrescrita');
$oldPriorityAfter = $db->query("SELECT p.origem FROM processo_prioridades p JOIN processos pr ON pr.id=p.processo_id WHERE pr.numero_processo=".$db->quote($oldNumber)." AND p.codigo=".$db->quote(PrioridadesProcessuaisCatalog::codigoViolenciaDomestica()))->fetchColumn();
ck($oldPriorityAfter===$oldPriority, 'Prioridade histórica do processo antigo foi removida ou alterada');
$secondPayload=$payload; $secondPayload['nome_parte']='Pessoa Sintetica B'; $secondPayload['force_duplicate']=true;
$secondId=$service->create($secondPayload,'criador');
ck($service->find($secondId)!==null, 'Registro CEJUSC múltiplo não foi criado');
$next=['numero_processo'=>cnj('0000003'),'nome_parte'=>'Pessoa Sintetica Nova','data_envio_cejusc'=>'2026-01-01'];
ck($service->update($secondId,$next,'editor'), 'Edição do registro CEJUSC múltiplo falhou');
$oldPriorityMultiple=$db->query("SELECT p.origem FROM processo_prioridades p JOIN processos pr ON pr.id=p.processo_id WHERE pr.numero_processo=".$db->quote($oldNumber)." AND p.codigo=".$db->quote(PrioridadesProcessuaisCatalog::codigoViolenciaDomestica()))->fetchColumn();
ck($oldPriorityMultiple===$oldPriority, 'Prioridade compartilhada por registros CEJUSC foi removida');
$unlinkedParteId=bin2hex(random_bytes(16));
$stmt=$db->prepare('INSERT INTO partes (id,nome_completo,created_at,updated_at) VALUES (?, ?, NOW(), NOW())');
$stmt->execute([$unlinkedParteId,'Parte Sintética sem vínculo']);
$before=$snapshot();
$unlinked=$next; $unlinked['numero_processo']=cnj('0000004'); $unlinked['parte_id']=$unlinkedParteId;
try { $service->update($id,$unlinked,'editor'); throw new RuntimeException('Parte sem vínculo aceita'); } catch (InvalidArgumentException $expected) {}
ck($snapshot()===$before, 'Falha por ausência de vínculo deixou gravações parciais');
$before=$snapshot();
$db->exec("CREATE TRIGGER falha_cejusc BEFORE UPDATE ON cejusc_registros FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falha sintetica'");
try { $service->update($id,$new,'editor'); throw new RuntimeException('Falha não propagada'); } catch (PDOException $e) { ck($e->getCode()==='45000','Falha inesperada'); }
ck($snapshot()===$before,'Rollback incompleto com novas entidades'); $db->exec('DROP TRIGGER falha_cejusc');
$holder=$connect(); $holder->beginTransaction(); (new CejuscRegistrosRepository($holder))->findForUpdate($id);
$db->exec('SET SESSION innodb_lock_wait_timeout = 1');
try { $service->update($id,$new,'editor'); throw new RuntimeException('Edição não aguardou lock'); } catch (PDOException $e) { ck((int)($e->errorInfo[1]??0)===1205,'Erro de lock inesperado'); }
ck(!$db->inTransaction(),'Transação bloqueada não revertida'); $holder->rollBack(); ck($snapshot()===$before,'Efeitos durante lock');
echo "MySQL: autoria, troca de processo, registros múltiplos, preservação histórica, rollback e lock: OK\n";
