<?php
declare(strict_types=1);

// Use somente um banco de teste vazio e descartável.
$dsn = getenv('TAREFAS_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=automacao_teste')) throw new RuntimeException('Informe o DSN de um banco automacao_teste descartável.');
$db = new PDO($dsn, (string) getenv('TAREFAS_TEST_USER'), (string) getenv('TAREFAS_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas (id CHAR(30) PRIMARY KEY, status VARCHAR(30) NOT NULL DEFAULT 'a_fazer', finalizado_em DATETIME DEFAULT NULL) ENGINE=InnoDB");
require __DIR__ . '/../scripts/migrate-cartorio-tarefas-automacao.php';
require __DIR__ . '/../scripts/migrate-cartorio-tarefas-automacao.php';
require_once __DIR__ . '/../app/repositories/CartorioTarefasRegrasRepository.php';
$repositorio = new CartorioTarefasRegrasRepository($db);
$regra = ['nome' => 'Teste isolado', 'evento' => 'criacao', 'prioridade' => 11, 'condicoes' => [['tipo' => 'expedicao']], 'acoes' => [['id' => 'a', 'tipo' => 'alocar', 'usuario_id' => 'USR-1']], 'ativa' => true];
$id = $repositorio->salvar($regra);
if ($id < 1 || $repositorio->obter($id)['nome'] !== 'Teste isolado') throw new RuntimeException('A regra não foi salva no MySQL.');
try {
    $repositorio->salvar($regra);
    throw new RuntimeException('Uma regra conflitante foi aceita.');
} catch (InvalidArgumentException $e) {
    // A segunda gravação deve ser rejeitada pela transação.
}
echo "Migração e persistência verificadas no banco descartável.\n";
