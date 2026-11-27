<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/ProcessoSigiloCadastroService.php';

$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE processos(id TEXT PRIMARY KEY, classe_id TEXT, id_setor_responsavel TEXT, sigiloso INTEGER, nivel_sigilo INTEGER, updated_at TEXT);
CREATE TABLE classes(id TEXT PRIMARY KEY, codigo TEXT);
CREATE TABLE setores(id_setor TEXT PRIMARY KEY, codigo_area TEXT, ativo INTEGER);
CREATE TABLE usuario_setor(usuario TEXT, setor TEXT);
CREATE TABLE processo_prioridades(processo_id TEXT, codigo TEXT, origem TEXT);
CREATE TABLE partes(id TEXT PRIMARY KEY, data_nascimento TEXT);
CREATE TABLE processos_partes(processo_id TEXT, parte_id TEXT, prioridade_crianca_adolescente INTEGER);
CREATE TABLE sala_audiencias_pauta(id_pauta TEXT, processo TEXT, ativo INTEGER);
CREATE TABLE sala_audiencias_pauta_parte(pauta TEXT, maioridade TEXT, ativo INTEGER);
CREATE TABLE logs_sistema(id_log_sistema TEXT PRIMARY KEY, usuario TEXT, modulo TEXT, acao TEXT, tabela_afetada TEXT,
    registro_id TEXT, dados_anteriores TEXT, dados_novos TEXT, ip TEXT, data_hora TEXT);
INSERT INTO processos VALUES('P1',NULL,'CART',1,4,NULL);
INSERT INTO classes VALUES('MP','1268');
INSERT INTO setores VALUES('CART','CARTORIO',1),('GAB','GABINETE',1);
INSERT INTO usuario_setor VALUES('INTERNO','CART'),('FORA','GAB'),('EXTERNO','CART');
INSERT INTO sala_audiencias_pauta VALUES('PA','P1',1);");

$service = new ProcessoSigiloCadastroService($db);
$interno = ['SERVIDOR-CARTORIO'];
$salvar = static fn($nivel, $pje=false, $autor='INTERNO', $perfis=null) =>
    $service->alterar('P1',$nivel,$pje,$autor,$perfis ?? $interno,'127.0.0.1');
$estado = static fn() => array_map(static fn($v) => $v === null ? null : (int) $v,
    $db->query("SELECT sigiloso,nivel_sigilo FROM processos WHERE id='P1'")->fetch(PDO::FETCH_NUM));
$verificar = static function (bool $ok, string $motivo): void {
    if (!$ok) throw new RuntimeException($motivo);
};
$negar = static function (callable $comando, int $status) use ($db, $estado, $verificar): void {
    $antes = $estado();
    $logs = $db->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn();
    try {
        $comando();
        throw new RuntimeException('Mudança deveria ser bloqueada.');
    } catch (ProcessoSigiloCadastroErro $erro) {
        $verificar($erro->http === $status, 'Motivo de bloqueio incorreto.');
    }
    $verificar($estado() === $antes && !$db->inTransaction(), 'Bloqueio deixou alteração ou transação.');
    $verificar($logs === $db->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'Bloqueio gerou log de alteração.');
};
$negar(static fn() => $salvar('publico'),400);
$negar(static fn() => $salvar('publico','true'),400);
$negar(static fn() => $salvar(''),400);
$negar(static fn() => $salvar(null),400);
$negar(static fn() => $salvar(6),400);
$negar(static fn() => $salvar('publico',true,'FORA',['SERVIDOR-GABINETE']),403);
$negar(static fn() => $salvar('publico',true,'FORA',['ADMIN']),403);
$negar(static fn() => $salvar('publico',true,'EXTERNO',['ESTUDANTE']),403);
$negar(static fn() => $salvar('publico',true,'EXTERNO',['CONSULTOR']),403);

foreach ([
    ["INSERT INTO processo_prioridades VALUES('P1','sigiloso','manual')", 'DELETE FROM processo_prioridades'],
    ["INSERT INTO processo_prioridades VALUES('P1','sigiloso','migracao_legado')", 'DELETE FROM processo_prioridades'],
    ["INSERT INTO processo_prioridades VALUES('P1','Segredo de Justiça','migracao_legado')", 'DELETE FROM processo_prioridades'],
    ["INSERT INTO processo_prioridades VALUES('P1','sigiloso','regra_derivada_menor_ou_vd')", 'DELETE FROM processo_prioridades'],
    ["INSERT INTO processo_prioridades VALUES('P1','violencia_domestica','manual')", 'DELETE FROM processo_prioridades'],
    ["INSERT INTO processo_prioridades VALUES('P1','medida_protetiva','manual')", 'DELETE FROM processo_prioridades'],
    ["UPDATE processos SET classe_id='MP'","UPDATE processos SET classe_id=NULL"],
    ["INSERT INTO sala_audiencias_pauta_parte VALUES('PA','menor',1)",'DELETE FROM sala_audiencias_pauta_parte'],
    ["INSERT INTO partes VALUES('MENOR', date('now','-17 years')); INSERT INTO processos_partes VALUES('P1','MENOR',0)",'DELETE FROM processos_partes; DELETE FROM partes'],
    ["INSERT INTO partes VALUES('INDICADOR',NULL); INSERT INTO processos_partes VALUES('P1','INDICADOR',1)",'DELETE FROM processos_partes; DELETE FROM partes'],
] as [$seed,$limpar]) {
    $db->exec($seed);
    $verificar($service->preflight('P1')['protecao'] === true, 'Sinal não encontrado no preflight.');
    $negar(static fn() => $salvar('publico',true),409);
    $negar(static fn() => $salvar('1'),409);
    $db->exec($limpar);
}

$db->exec('ALTER TABLE partes RENAME TO fonte_indisponivel');
$negar(static fn() => $salvar('publico',true),503);
$negar(static fn() => $salvar('5'),503);
$db->exec('ALTER TABLE fonte_indisponivel RENAME TO partes');

$db->exec("CREATE TRIGGER recusar_log BEFORE INSERT ON logs_sistema BEGIN SELECT RAISE(ABORT, 'falha sintética'); END");
try {
    $salvar('publico',true);
    throw new RuntimeException('Falha da auditoria deveria impedir mudança.');
} catch (PDOException) {}
$verificar($estado() === [1,4] && !$db->inTransaction(), 'Falha de auditoria deixou alteração.');
$db->exec('DROP TRIGGER recusar_log');
$db->exec("INSERT INTO processo_prioridades VALUES('P1','sigiloso','regra_sigiloso')");
$verificar($service->preflight('P1')['protecao'] === false, 'Espelho do próprio cadastro não é proteção independente.');
$saida = $salvar('publico',true);
$verificar($saida['alterado'] && $estado() === [0,null], 'Público não limpou flag e nível.');
$log = $db->query('SELECT * FROM logs_sistema')->fetch(PDO::FETCH_ASSOC);
$verificar($log['usuario'] === 'INTERNO' && $log['acao'] === 'sigilo-cadastral'
    && json_decode($log['dados_anteriores'], true) === ['sigiloso'=>true,'nivel_sigilo'=>4]
    && json_decode($log['dados_novos'], true) === ['sigiloso'=>false,'nivel_sigilo'=>null,'conferencia_pje_declarada'=>true],
    'Auditoria não contém autoria, valores e confirmação.');
$verificar($salvar('publico',true)['alterado'] === false && (int) $db->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn() === 1, 'Repetição criou log duplicado.');
$verificar($salvar('3')['alterado'] && $estado() === [1,3], 'Nível não ativou flag sigiloso.');
echo "PASS: sigilo cadastral sintético, setor/perfis, proteções, confirmação e rollback auditado.\n";
