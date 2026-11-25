<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/ProcessResponsibleSectorAdminPolicy.php';
require_once __DIR__ . '/../../app/helpers/EffectiveAccessProfiles.php';
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
$pdo->exec('CREATE TABLE setores (id_setor TEXT PRIMARY KEY, nome TEXT NOT NULL, ativo INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE logs_sistema (id_log_sistema TEXT PRIMARY KEY, usuario TEXT NOT NULL, modulo TEXT NOT NULL, acao TEXT NOT NULL, tabela_afetada TEXT NULL, registro_id TEXT NULL, dados_anteriores TEXT NULL, dados_novos TEXT NULL, ip TEXT NOT NULL, data_hora TEXT NULL)');
$pdo->exec("INSERT INTO processos (id, id_setor_responsavel) VALUES ('PRC-1', NULL), ('PRC-2', 'SET-OLD')");
$pdo->exec("INSERT INTO setores (id_setor, nome, ativo) VALUES ('SET-1', 'Cartório', 1), ('SET-2', 'Gabinete', 1), ('SET-OLD', 'Setor encerrado', 0)");
$service = new ProcessResponsibleSectorService($pdo);

assertSameValue(true, ProcessResponsibleSectorAdminPolicy::allows(['ADMIN']), 'ADMIN deve poder atribuir.');
assertSameValue(true, ProcessResponsibleSectorAdminPolicy::allows(['SERVIDOR-CARTORIO', 'ADMIN']), 'Perfil misto com ADMIN ativo deve poder atribuir.');
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows(['CHEFIA']), 'CHEFIA sem ADMIN não deve poder atribuir.');
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows(['SERVIDOR-CARTORIO']), 'Perfil operacional não deve poder atribuir.');

$_SESSION = ['id_usuario' => 'ADMIN-1', EffectiveAccessProfiles::SESSION_SUPPRESS_ADMIN => true];
$suppressedProfiles = EffectiveAccessProfiles::applyForUser('ADMIN-1', ['ADMIN', 'SERVIDOR-CARTORIO']);
assertSameValue(false, ProcessResponsibleSectorAdminPolicy::allows($suppressedProfiles), 'ADMIN suprimido pelo modo operacional deve ser negado.');
$_SESSION = [];

$assigned = $service->assign('PRC-1', 'SET-1', 'Atribuição inicial autorizada', 'ADMIN-1', '192.0.2.10');
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

$changed = $service->assign('PRC-1', 'SET-2', 'Revisão de lotação', 'ADMIN-1', '192.0.2.10');
assertSameValue('SET-1', $changed['previous_sector_id'], 'A reatribuição precisa retornar o valor anterior.');
assertSameValue(2, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'A reatribuição precisa gerar log.');
$noOp = $service->assign('PRC-1', 'SET-2', 'Confirmação da atribuição', 'ADMIN-1', '192.0.2.10');
assertSameValue(false, $noOp['changed'], 'A repetição do setor atual deve ser idempotente.');
assertSameValue(2, (int) $pdo->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn(), 'Uma operação sem mudança não deve duplicar a trilha.');
$cleared = $service->assign('PRC-1', null, 'Retirada deliberada da unidade', 'ADMIN-1', '192.0.2.10');
assertSameValue('SET-2', $cleared['previous_sector_id'], 'A retirada precisa registrar o setor anterior.');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'A retirada deliberada deve retornar a NULL.');

assertThrows(fn () => $service->assign('MISSING', 'SET-1', 'Teste', 'ADMIN-1', '192.0.2.10'), ResponsibleProcessNotFound::class, 'Processo inexistente deve falhar');
assertThrows(fn () => $service->assign('PRC-1', 'SET-OLD', 'Teste', 'ADMIN-1', '192.0.2.10'), InvalidArgumentException::class, 'Setor inativo deve falhar');
assertThrows(fn () => $service->assign('PRC-1', 'SET-1', '  ', 'ADMIN-1', '192.0.2.10'), InvalidArgumentException::class, 'Justificativa vazia deve falhar');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Falhas de validação não devem alterar o processo.');

$pdo->beginTransaction();
$service->assign('PRC-1', 'SET-1', 'Rollback da transação externa', 'ADMIN-1', '192.0.2.10');
assertSameValue(true, $pdo->inTransaction(), 'O serviço não deve concluir a transação do chamador.');
$pdo->rollBack();
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Rollback externo deve reverter a atribuição.');

$pdo->exec('DROP TABLE logs_sistema');
assertThrows(fn () => $service->assign('PRC-1', 'SET-1', 'Auditoria obrigatória', 'ADMIN-1', '192.0.2.10'), PDOException::class, 'Falha de auditoria deve fazer a operação falhar');
assertSameValue(null, $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn(), 'Falha ao gravar auditoria deve reverter a atribuição.');

echo "PASS: ADMIN estrito, atribuição explícita, trilha, validações, idempotência e rollback atômico.\n";
