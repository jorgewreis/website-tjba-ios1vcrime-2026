<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/ProcessResponsibleSectorAdminPolicy.php';
require_once __DIR__ . '/../../app/helpers/EffectiveAccessProfiles.php';
require_once __DIR__ . '/../../app/helpers/ProcessoSetorAuthorization.php';
require_once __DIR__ . '/../../app/services/ProcessResponsibleSectorService.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertThrows(callable $operation, string $class, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $error) {
        if (!$error instanceof $class) {
            throw new RuntimeException($message . ': unexpected ' . get_class($error), 0, $error);
        }
        return $error;
    }
    throw new RuntimeException($message . ': exception not thrown');
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO SQLite is required for the isolated assignment test.');
}

$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, id_setor_responsavel TEXT NULL, updated_at TEXT NULL)');
$pdo->exec('CREATE TABLE setores (id_setor TEXT PRIMARY KEY, nome TEXT NOT NULL, ativo INTEGER NOT NULL, codigo_area TEXT)');
$pdo->exec('CREATE TABLE logs_sistema (id_log_sistema TEXT PRIMARY KEY, usuario TEXT NOT NULL, modulo TEXT NOT NULL, acao TEXT NOT NULL, tabela_afetada TEXT NULL, registro_id TEXT NULL, dados_anteriores TEXT NULL, dados_novos TEXT NULL, ip TEXT NOT NULL, data_hora TEXT NULL)');
$pdo->exec("INSERT INTO processos (id, id_setor_responsavel) VALUES ('PRC-1', NULL), ('PRC-2', 'SET-OLD'), ('SINTESE-NOVO', NULL)");
$pdo->exec("INSERT INTO setores (id_setor, nome, ativo, codigo_area) VALUES ('SET-1', 'Cartório', 1, 'CARTORIO'), ('SET-2', 'Gabinete', 1, 'GABINETE'), ('SET-OLD', 'Setor encerrado', 0, 'CARTORIO')");
$service = new ProcessResponsibleSectorService($pdo);
$pdo->exec("CREATE TABLE usuario_setor (usuario TEXT, setor TEXT)");
$pdo->exec("INSERT INTO usuario_setor VALUES ('CART', 'SET-1'), ('GAB', 'SET-2'), ('ANT', 'SET-OLD')");
assertSameValue(true, ProcessoSetorAuthorization::canAccessProcess($pdo, 'CART', 'PRC-1', false), 'Legado nulo deve usar Cartório.');
assertSameValue(true, ProcessoSetorAuthorization::canAccessProcess($pdo, 'CART', 'SINTESE-NOVO', false), 'Cadastro novo com valor inicial nulo deve usar Cartório.');
assertSameValue(false, ProcessoSetorAuthorization::canAccessProcess($pdo, 'GAB', 'PRC-1', false), 'Outro setor não recebe acesso pelo padrão.');
assertSameValue(false, ProcessoSetorAuthorization::canAccessProcess($pdo, 'CART', 'PRC-2', false), 'Setor explícito inativo não vira Cartório.');
$pdo->exec("UPDATE setores SET ativo=1 WHERE id_setor='SET-OLD'");
assertSameValue(false, ProcessoSetorAuthorization::canAccessProcess($pdo, 'CART', 'PRC-1', false), 'Padrão duplicado não deve liberar acesso.');
$pdo->exec("UPDATE setores SET ativo=0 WHERE id_setor='SET-OLD'");


assertSameValue(true, ProcessResponsibleSectorAdminPolicy::allows(['ADMIN']), 'ADMIN deve poder atribuir.');
assertSameValue(true, ProcessResponsibleSectorAdminPolicy::allows(['SERVIDOR-CARTORIO', 'ADMIN']), 'Perfil misto com ADMIN ativo deve poder atribuir.');
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows(['CHEFIA']), 'CHEFIA sem ADMIN não deve poder atribuir.');
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows(['SERVIDOR-CARTORIO']), 'Perfil operacional não deve poder atribuir.');

$_SESSION = ['id_usuario' => 'ADMIN-1', EffectiveAccessProfiles::SESSION_SUPPRESS_ADMIN => true];
$suppressedProfiles = EffectiveAccessProfiles::applyForUser('ADMIN-1', ['ADMIN', 'SERVIDOR-CARTORIO']);
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows($suppressedProfiles), 'ADMIN suprimido pelo modo operacional deve ser negado.');
$_SESSION = [];

$assigned = $service->assign('PRC-1', 'SET-1', 'Atribuição inicial autorizada', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue(true, $assigned['changed'], 'Atribuição inicial deve alterar o processo.');
assertSameValue('SET-1', $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Setor não foi persistido.');
assertSameValue(1, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'A atribuição precisa gerar log.');
$audit = $pdo->query("SELECT usuario, modulo, acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip FROM logs_sistema")->fetch(PDO::FETCH_ASSOC);
assertSameValue('ADMIN-1', $audit['usuario'], 'O operador precisa constar no log.');
assertSameValue('setor-responsavel', $audit['acao'], 'A ação precisa ser identificável no log.');
assertSameValue('PRC-1', $audit['registro_id'], 'O processo precisa constar no log.');
assertSameValue(null, json_decode((string) $audit['dados_anteriores'], true)['id_setor_responsavel'], 'O valor anterior precisa ser auditado.');
$newAudit = json_decode((string) $audit['dados_novos'], true);
assertSameValue('SET-1', $newAudit['id_setor_responsavel'], 'O novo setor precisa ser auditado.');
assertSameValue('Atribuição inicial autorizada', $newAudit['justificativa'], 'A justificativa precisa ser auditada.');
assertSameValue('192.0.2.10', $audit['ip'], 'O endereço do operador precisa ser registrado.');

$changed = $service->assign('PRC-1', 'SET-2', 'Revisão de lotação', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue('SET-1', $changed['previous_sector_id'], 'A reatribuição precisa retornar o valor anterior.');
assertSameValue(2, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'A reatribuição precisa gerar log.');
$noOp = $service->assign('PRC-1', 'SET-2', 'Confirmação da atribuição', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue(false, $noOp['changed'], 'A repetição do setor atual deve ser idempotente.');
assertSameValue(2, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'Uma operação sem mudança não deve duplicar a trilha.');
$cleared = $service->assign('PRC-1', null, 'Retirada deliberada da unidade', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue('SET-2', $cleared['previous_sector_id'], 'A retirada precisa registrar o setor anterior.');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'A retirada deliberada deve retornar a NULL.');

assertThrows(fn () => $service->assign('MISSING', 'SET-1', 'Teste', 'ADMIN-1', '192.0.2.10', ['ADMIN']), ResponsibleProcessNotFound::class, 'Processo inexistente deve falhar');
assertThrows(fn () => $service->assign('PRC-1', 'SET-OLD', 'Teste', 'ADMIN-1', '192.0.2.10', ['ADMIN']), InvalidArgumentException::class, 'Setor inativo deve falhar');
assertThrows(fn () => $service->assign('PRC-1', 'SET-1', str_repeat('a', 501), 'ADMIN-1', '192.0.2.10', ['ADMIN']), InvalidArgumentException::class, 'Justificativa longa deve falhar');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Falhas de validação não devem alterar o processo.');

$pdo->beginTransaction();
$service->assign('PRC-1', 'SET-1', 'Rollback da transação externa', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue(true, $pdo->inTransaction(), 'O serviço não deve concluir a transação do chamador.');
$pdo->rollBack();
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Rollback externo deve reverter a atribuição.');

$withoutNote = $service->assign('PRC-1', 'SET-2', '  ', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue(true, $withoutNote['changed'], 'Justificativa vazia é permitida.');
assertSameValue(null, json_decode($pdo->query("SELECT dados_novos FROM logs_sistema WHERE dados_novos LIKE '%SET-2%' ORDER BY rowid DESC LIMIT 1")->fetchColumn(), true)['justificativa'], 'Atribuição sem texto deve ser auditada.');
assertSameValue(false, ProcessoSetorAuthorization::canAccessProcess($pdo, 'CART', 'PRC-1', false), 'Unidade explícita prevalece sobre o padrão.');
assertSameValue(true, ProcessoSetorAuthorization::canAccessProcess($pdo, 'GAB', 'PRC-1', false), 'A unidade explícita preserva seu acesso.');
$reset = $service->assign('PRC-1', null, '', 'ADMIN-1', '192.0.2.10', ['ADMIN']);
assertSameValue('SET-1', $reset['sector_id'], 'Retorno ao padrão deve informar a unidade efetiva.');
assertSameValue(true, $reset['is_default'], 'Retorno ao padrão deve ser identificado.');
$pdo->exec("INSERT INTO processos (id,id_setor_responsavel) VALUES ('PRC-OPS',NULL), ('PRC-OPROLL','SET-2')");
$pdo->exec("INSERT INTO usuario_setor VALUES ('EXT-STUDENT','SET-1'), ('EXT-CONSULT','SET-1')");
$perfisCart = ['SERVIDOR-CARTORIO'];
$perfisGab = ['ESTAGIARIO-GABINETE'];
$beforeOps = (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn();
foreach ([['EXT-STUDENT',['ESTUDANTE']], ['EXT-CONSULT',['CONSULTOR']], ['CART',[]]] as [$ator,$perfis]) {
    assertThrows(fn () => $service->assign('PRC-OPS', 'SET-2', '', $ator, '192.0.2.10', $perfis),
        ResponsibleSectorForbidden::class, 'Perfil externo/ausente deve ser negado mesmo com lotação');
}
$negado = assertThrows(fn () => $service->assign('PRC-OPS', 'SET-2', '', 'GAB', '192.0.2.10', $perfisGab),
    ResponsibleSectorForbidden::class, 'Sem lotação atual não pode encaminhar para o próprio setor');
$ausente = assertThrows(fn () => $service->assign('PRC-NOT-FOUND', 'SET-2', '', 'GAB', '192.0.2.10', $perfisGab),
    ResponsibleSectorForbidden::class, 'Operador não deve inferir registro ausente');
assertSameValue($negado->getMessage(), $ausente->getMessage(), 'Registro inexistente e não autorizado precisam da mesma resposta.');
assertThrows(fn () => $service->assign('PRC-OPS', 'SET-OLD', '', 'CART', '192.0.2.10', $perfisCart),
    InvalidArgumentException::class, 'Destino inativo deve ser recusado.');
assertThrows(fn () => $service->assign('PRC-OPS', null, '', 'CART', '192.0.2.10', $perfisCart),
    ResponsibleSectorForbidden::class, 'Operador deve selecionar setor ativo explicitamente.');
assertThrows(fn () => $service->assign('PRC-OPS', 'SET-2', '', 'GAB', '192.0.2.10', $suppressedProfiles),
    ResponsibleSectorForbidden::class, 'ADMIN suprimido não pode ignorar a lotação.');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-OPS'")->fetchColumn(),
    'Tentativa recusada não pode alterar o processo.');
assertSameValue($beforeOps, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(),
    'Tentativa recusada não pode criar auditoria de sucesso.');
$encaminhado = $service->assign('PRC-OPS', 'SET-2', 'Encaminhamento sintético', 'CART', '192.0.2.10', $perfisCart);
assertSameValue(true, $encaminhado['changed'], 'Operador do Cartório padrão deve encaminhar a setor ativo.');
assertSameValue('SET-2', $encaminhado['sector_id'], 'Setor de destino incorreto.');
assertSameValue('CART', $pdo->query("SELECT usuario FROM logs_sistema WHERE registro_id='PRC-OPS'")->fetchColumn(),
    'Identidade do operador precisa constar na auditoria.');
assertThrows(fn () => $service->assign('PRC-OPS', 'SET-1', '', 'CART', '192.0.2.10', $perfisCart),
    ResponsibleSectorForbidden::class, 'Ex-setor não pode mais alterar nem recuperar a unidade.');
$novoLotado = $service->assign('PRC-OPS', 'SET-1', '', 'GAB', '192.0.2.10', $perfisGab);
assertSameValue(true, $novoLotado['changed'], 'Estagiário com lotação atual também deve encaminhar.');
assertSameValue($beforeOps + 2, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(),
    'Cada encaminhamento alterado precisa de auditoria.');
$pdo->exec('DROP TABLE logs_sistema');
assertThrows(fn () => $service->assign('PRC-OPROLL', 'SET-1', '', 'GAB', '192.0.2.10', $perfisGab),
    PDOException::class, 'Falha de auditoria precisa abortar encaminhamento de operador.');
assertSameValue('SET-2', $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-OPROLL'")->fetchColumn(),
    'Falha de auditoria não pode mudar o setor atual.');
assertThrows(fn () => $service->assign('PRC-1', 'SET-1', 'Auditoria obrigatória', 'ADMIN-1', '192.0.2.10', ['ADMIN']), PDOException::class, 'Falha de auditoria deve fazer a operação falhar');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Falha ao gravar auditoria deve reverter a atribuição.');

echo "PASS: ADMIN e operadores autorizados, destino ativo, recusa sem lotação ou perfil, auditoria e rollback atômico.\n";
