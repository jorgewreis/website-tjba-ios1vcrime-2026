<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/repositories/CartorioTarefasRegrasRepository.php';

function verificarRepositorio(bool $condicao, string $mensagem): void
{
    if (!$condicao) throw new RuntimeException($mensagem);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE cartorio_tarefas_automacao_lock (id INTEGER PRIMARY KEY)');
$db->exec('INSERT INTO cartorio_tarefas_automacao_lock VALUES (1)');
$db->exec('CREATE TABLE cartorio_tarefas (id TEXT PRIMARY KEY)');
$db->exec("INSERT INTO cartorio_tarefas VALUES ('t1'), ('t2')");
$db->exec("CREATE TABLE cartorio_tarefas_regras (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, evento TEXT, evento_status TEXT, prioridade INTEGER, condicoes TEXT, acoes TEXT, ativa INTEGER, versao INTEGER, evento_apos_id INTEGER DEFAULT 0, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, atualizado_em TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec('CREATE TABLE cartorio_tarefas_automacao_eventos (id INTEGER PRIMARY KEY AUTOINCREMENT)');
$db->exec("CREATE TABLE cartorio_tarefas_regra_lotes (id INTEGER PRIMARY KEY AUTOINCREMENT, regra_id INTEGER, versao INTEGER, estado TEXT DEFAULT 'pendente', cursor_tarefa TEXT, total INTEGER DEFAULT 0, processadas INTEGER DEFAULT 0, falhas INTEGER DEFAULT 0, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, atualizado_em TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec('CREATE TABLE cartorio_tarefas_regra_lote_itens (lote_id INTEGER NOT NULL, tarefa_id TEXT NOT NULL, PRIMARY KEY(lote_id, tarefa_id))');
$db->exec("CREATE TABLE cartorio_tarefas_automacao_falhas (id INTEGER PRIMARY KEY AUTOINCREMENT, regra_id INTEGER, tarefa_id TEXT, evento TEXT, detalhes TEXT, contexto TEXT, pode_tentar INTEGER DEFAULT 0, resolvida_em TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)");
$repo = new CartorioTarefasRegrasRepository($db);
$regra = ['nome' => 'Distribuição', 'evento' => 'criacao', 'prioridade' => 2, 'condicoes' => [['tipo' => 'expedicao']], 'acoes' => [['id' => 'alocar', 'tipo' => 'alocar', 'usuario_id' => 'USR-1']], 'ativa' => true];
$id = $repo->salvar($regra);
verificarRepositorio($id > 0 && $repo->obter($id)['ativa'] && $repo->obter($id)['versao'] === 1, 'Falha ao salvar regra ativa.');
verificarRepositorio((int) $repo->lotesRecentes()[0]['total'] === 2, 'O lote deve registrar a quantidade inicial de tarefas.');
try {
    $repo->salvar($regra);
    throw new RuntimeException('Conflito foi aceito.');
} catch (InvalidArgumentException $e) {
    verificarRepositorio(count($repo->listar()) === 1, 'O conflito não deve gravar uma regra.');
}
$regra['nome'] = 'Distribuição revista';
$repo->salvar($regra, $id);
$lotes = $repo->lotesRecentes();
verificarRepositorio($repo->obter($id)['versao'] === 2 && $lotes[1]['estado'] === 'cancelado' && $lotes[0]['estado'] === 'pendente', 'Edição deve cancelar o lote anterior.');
$regra['ativa'] = false;
$repo->salvar($regra, $id);
verificarRepositorio(count($repo->listar(true)) === 0 && $repo->lotesRecentes()[0]['estado'] === 'cancelado', 'Desativação deve cancelar o lote.');
verificarRepositorio((int) $db->query('SELECT COUNT(*) FROM cartorio_tarefas_regra_lote_itens')->fetchColumn() === 0, 'Itens de lotes cancelados devem ser removidos.');
echo "Persistência de regras verificada.\n";
