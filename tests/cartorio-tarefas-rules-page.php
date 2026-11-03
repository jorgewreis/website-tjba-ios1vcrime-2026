<?php
declare(strict_types=1);

// Harness isolado para verificar o servidor da página sem uma sessão real.
$dsn = (string) getenv('TAREFAS_TEST_DSN');
if (!str_contains($dsn, 'host=127.0.0.1') || !str_contains($dsn, 'dbname=automacao_teste')) throw new RuntimeException('Use o banco descartável automacao_teste no localhost.');
$connection = new PDO($dsn, (string) getenv('TAREFAS_TEST_USER'), (string) getenv('TAREFAS_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
final class Security
{
    public static function verifyCSRFToken(?string $token): bool { return $token === 'teste'; }
    public static function generateCSRFToken(): string { return 'teste'; }
}
$_SESSION = [];
$mode = (string) getenv('TAREFAS_TEST_PAGE_MODE');
$userPerfils = $mode === 'operacional' ? ['SERVIDOR-CARTORIO'] : ['ADMIN'];
$userId = 'USR-1';
$_SERVER['REQUEST_METHOD'] = in_array($mode, ['salvar', 'csrf', 'alternar'], true) ? 'POST' : 'GET';
$_POST = in_array($mode, ['salvar', 'csrf'], true) ? [
    'csrf_token' => $mode === 'csrf' ? 'inválido' : 'teste', 'acao' => 'salvar', 'regra_id' => '0',
    'regra_json' => json_encode(['nome' => 'Regra de teste da página', 'evento' => 'criacao', 'prioridade' => 99, 'ativa' => true, 'condicoes' => [['local_tarefa' => 'Sala de Audiências']], 'acoes' => [['id' => 'prazo', 'tipo' => 'definir_prazo', 'inicio' => 'criacao', 'dias' => 3, 'alerta_dias' => 1]]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
] : ($mode === 'alternar' ? ['csrf_token' => 'teste', 'acao' => 'alternar', 'regra_id' => getenv('TAREFAS_TEST_RULE_ID')] : []);
$_GET = $mode === 'editar' ? ['editar' => getenv('TAREFAS_TEST_RULE_ID')] : [];
require __DIR__ . '/../app/views/internal/cartorio-tarefas-rules.php';
