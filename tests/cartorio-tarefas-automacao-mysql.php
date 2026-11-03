<?php
declare(strict_types=1);

// Use somente um banco de teste vazio e descartável.
$dsn = getenv('TAREFAS_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=automacao_teste')) throw new RuntimeException('Informe o DSN de um banco automacao_teste descartável.');
$db = new PDO($dsn, (string) getenv('TAREFAS_TEST_USER'), (string) getenv('TAREFAS_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas (id CHAR(30) PRIMARY KEY, titulo VARCHAR(255) NOT NULL, numero_processo VARCHAR(40), tipo VARCHAR(30) NOT NULL, tipo_documento VARCHAR(100), especificacao_documento VARCHAR(180), local_tarefa VARCHAR(40), status VARCHAR(30) NOT NULL DEFAULT 'a_fazer', criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, finalizado_em DATETIME DEFAULT NULL) ENGINE=InnoDB");
$db->exec('CREATE TABLE IF NOT EXISTS cartorio_tarefas_servidores (tarefa_id CHAR(30), usuario_id VARCHAR(80), PRIMARY KEY(tarefa_id, usuario_id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE IF NOT EXISTS usuarios (id_usuario VARCHAR(80) PRIMARY KEY, nome VARCHAR(100), sobrenome VARCHAR(100), cadastro_ativo TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TABLE IF NOT EXISTS usuario_perfil (usuario VARCHAR(80), perfil VARCHAR(80)) ENGINE=InnoDB');
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

require_once __DIR__ . '/../app/services/CartorioTarefasAutomacaoService.php';
$db->exec("INSERT INTO usuarios VALUES ('USR-1', 'Ana', 'Teste', 1)");
$db->exec("INSERT INTO usuario_perfil VALUES ('USR-1', 'SERVIDOR')");
$origem = 'TAR-00000000000000000000000001';
$st = $db->prepare("INSERT INTO cartorio_tarefas (id, titulo, numero_processo, tipo, tipo_documento, local_tarefa, status, finalizado_em) VALUES (?, 'Expedir Ofício', '0000000-00.0000.0.00.0000', 'expedicao', 'Ofício', 'Cartório', 'finalizada', CURRENT_TIMESTAMP)");
$st->execute([$origem]);
$criar = ['id' => 'gerar', 'tipo' => 'criar_tarefa', 'tipo_tarefa' => 'assinatura', 'tipo_documento' => 'Ofício', 'local_tarefa' => 'Cartório'];
$sequencia = ['nome' => 'Sequência', 'evento' => 'finalizacao', 'prioridade' => 0, 'condicoes' => [['tipo' => 'expedicao']], 'acoes' => [$criar, ['id' => 'status', 'tipo' => 'alterar_status', 'alvo' => 'gerar', 'status' => 'em_andamento']], 'ativa' => true];
$repositorio->salvar($sequencia);
$servico = new CartorioTarefasAutomacaoService($db);
$servico->registrarEvento($origem, 'finalizacao');
$idEvento = (int) $db->query('SELECT MAX(id) FROM cartorio_tarefas_automacao_eventos')->fetchColumn();
$servico->processarEvento($idEvento);
$st = $db->prepare('SELECT id, status, origem_id, sequencia_id FROM cartorio_tarefas WHERE origem_id = ?');
$st->execute([$origem]);
$filhas = $st->fetchAll(PDO::FETCH_ASSOC);
if (count($filhas) !== 1 || $filhas[0]['status'] !== 'em_andamento' || $filhas[0]['sequencia_id'] !== $origem) throw new RuntimeException('A sequência não foi criada corretamente.');
$db->prepare("UPDATE cartorio_tarefas SET status = 'com_pendencias' WHERE id = ?")->execute([$filhas[0]['id']]);
$servico->registrarEvento($origem, 'finalizacao');
$idEvento = (int) $db->query('SELECT MAX(id) FROM cartorio_tarefas_automacao_eventos')->fetchColumn();
$servico->processarEvento($idEvento);
$st->execute([$origem]);
$depois = $st->fetchAll(PDO::FETCH_ASSOC);
if (count($depois) !== 1 || $depois[0]['status'] !== 'com_pendencias') throw new RuntimeException('Reabertura repetiu a criação ou as ações da regra.');
$falha = $sequencia;
$falha['nome'] = 'Sequência com erro';
$falha['acoes'] = [$criar, ['id' => 'invalida', 'tipo' => 'criar_tarefa', 'tipo_tarefa' => 'inexistente', 'tipo_documento' => 'Ofício', 'local_tarefa' => 'Cartório']];
$repositorio->salvar($falha);
$servico->registrarEvento($origem, 'finalizacao');
$idEvento = (int) $db->query('SELECT MAX(id) FROM cartorio_tarefas_automacao_eventos')->fetchColumn();
$servico->processarEvento($idEvento);
$st->execute([$origem]);
if (count($st->fetchAll(PDO::FETCH_ASSOC)) !== 1 || (int) $db->query('SELECT COUNT(*) FROM cartorio_tarefas_automacao_falhas')->fetchColumn() !== 1) throw new RuntimeException('A falha deveria reverter a regra e manter a tarefa original.');
echo "Sequência, idempotência e reversão verificadas.\n";
