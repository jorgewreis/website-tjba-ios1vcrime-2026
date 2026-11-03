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

$antiga = 'TAR-00000000000000000000000002';
$nova = 'TAR-00000000000000000000000003';
$insert = $db->prepare("INSERT INTO cartorio_tarefas (id, titulo, numero_processo, tipo, tipo_documento, local_tarefa, status) VALUES (?, 'Expedir Ofício', '0000000-00.0000.0.00.0000', 'expedicao', 'Ofício', 'Cartório', 'a_fazer')");
$insert->execute([$antiga]);
$especifica = $regra;
$especifica['nome'] = 'Alocação específica';
$especifica['condicoes'] = [['tipo' => 'expedicao', 'local_tarefa' => 'Cartório']];
$novaRegraId = $repositorio->salvar($especifica);
$insert->execute([$nova]);
for ($i = 0; $i < 12; $i++) $servico->executarFila();
$st = $db->prepare('SELECT usuario_id FROM cartorio_tarefas_servidores WHERE tarefa_id = ?');
$st->execute([$antiga]);
if ($st->fetchColumn() !== 'USR-1') throw new RuntimeException('O lote não aplicou a alocação à tarefa existente.');
$st->execute([$nova]);
if ($st->fetchColumn() !== false) throw new RuntimeException('O lote incluiu tarefa criada após o salvamento.');
$st = $db->prepare('SELECT estado, total, processadas FROM cartorio_tarefas_regra_lotes WHERE regra_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$novaRegraId]);
$lote = $st->fetch(PDO::FETCH_ASSOC);
if ($lote['estado'] !== 'concluido' || (int) $lote['total'] !== (int) $lote['processadas']) throw new RuntimeException('O lote não concluiu o processamento da fotografia inicial.');
echo "Fila e reprocessamento por fotografia verificados.\n";

$cnj = '';
for ($dv = 0; $dv < 100; $dv++) {
    $candidato = '0000001-' . str_pad((string) $dv, 2, '0', STR_PAD_LEFT) . '.2020.8.05.0103';
    if (CnjValidator::isValid($candidato)) { $cnj = $candidato; break; }
}
$tarefas = new CartorioTarefasRepository($db);
$manual = $tarefas->cadastrar('expedicao', 'Ofício', 'Cartório', 'USR-1', $cnj);
$st = $db->prepare('SELECT evento FROM cartorio_tarefas_automacao_eventos WHERE tarefa_id = ? ORDER BY id');
$st->execute([$manual]);
if ($st->fetchAll(PDO::FETCH_COLUMN) !== ['criacao', 'alocacao']) throw new RuntimeException('O cadastro manual não registrou os eventos.');
$tarefas->alterarStatus($manual, 'em_andamento');
$tarefas->alterarStatus($manual, 'finalizada');
$st = $db->prepare('SELECT status, finalizado_em, primeiro_andamento_em FROM cartorio_tarefas WHERE id = ?');
$st->execute([$manual]);
$atual = $st->fetch(PDO::FETCH_ASSOC);
if ($atual['status'] !== 'finalizada' || !$atual['finalizado_em'] || !$atual['primeiro_andamento_em']) throw new RuntimeException('Alteração de status manual não registrou os marcos.');
$st = $db->prepare("SELECT COUNT(*) FROM cartorio_tarefas_automacao_eventos WHERE tarefa_id = ? AND evento = 'finalizacao'");
$st->execute([$manual]);
if ((int) $st->fetchColumn() !== 1) throw new RuntimeException('Finalização manual não registrou o evento.');
$db->exec("UPDATE usuarios SET cadastro_ativo = 0 WHERE id_usuario = 'USR-1'");
try {
    $tarefas->cadastrar('expedicao', 'Ofício', 'Cartório', 'USR-1', $cnj);
    throw new RuntimeException('Usuário inativo foi aceito no cadastro.');
} catch (InvalidArgumentException $e) {
    // Esperado.
}
echo "Cadastro e alterações manuais integrados à fila.\n";

$insertRetencao = $db->prepare("INSERT INTO cartorio_tarefas (id, titulo, tipo, status, finalizado_em, sequencia_id) VALUES (?, 'Retenção', 'expedicao', ?, ?, ?)");
$doisAnos = (new DateTimeImmutable('-2 years'))->format('Y-m-d H:i:s');
$seisMeses = (new DateTimeImmutable('-6 months'))->format('Y-m-d H:i:s');
$prefixo = 'TAR-111111111111111111111111';
$abertaRaiz = $prefixo . '01';
$abertaFilha = $prefixo . '02';
$recenteRaiz = $prefixo . '03';
$recenteFilha = $prefixo . '04';
$antigaRaiz = $prefixo . '05';
$antigaFilha = $prefixo . '06';
$isolada = $prefixo . '07';
foreach ([[$abertaRaiz, 'finalizada', $doisAnos, null], [$abertaFilha, 'a_fazer', null, $abertaRaiz], [$recenteRaiz, 'finalizada', $doisAnos, null], [$recenteFilha, 'finalizada', $seisMeses, $recenteRaiz], [$antigaRaiz, 'finalizada', $doisAnos, null], [$antigaFilha, 'finalizada', $doisAnos, $antigaRaiz], [$isolada, 'finalizada', $doisAnos, null]] as $linha) $insertRetencao->execute($linha);
require __DIR__ . '/../scripts/purge-cartorio-tarefas.php';
$st = $db->prepare('SELECT COUNT(*) FROM cartorio_tarefas WHERE id = ?');
foreach ([$abertaRaiz, $abertaFilha, $recenteRaiz, $recenteFilha] as $id) {
    $st->execute([$id]);
    if ((int) $st->fetchColumn() !== 1) throw new RuntimeException('Uma sequência aberta ou recente foi excluída.');
}
foreach ([$antigaRaiz, $antigaFilha, $isolada] as $id) {
    $st->execute([$id]);
    if ((int) $st->fetchColumn() !== 0) throw new RuntimeException('Uma sequência elegível não foi excluída.');
}
echo "Retenção por sequência verificada.\n";
