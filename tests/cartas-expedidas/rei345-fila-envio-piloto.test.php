<?php
declare(strict_types=1);

require_once __DIR__.'/../../app/repositories/CartasExpedidasRepository.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// Adapt the two MySQL primitives used by this read-only query for the SQLite fixture.
$db->sqliteCreateCollation('utf8mb4_unicode_ci', 'strcasecmp');
$db->sqliteCreateFunction('CONCAT', static fn (...$parts) => implode('', $parts));
$db->exec("
    CREATE TABLE cartas_expedidas (id TEXT PRIMARY KEY, processo_origem_id TEXT, numero_processo_origem TEXT,
        finalidade TEXT, prazo_dias INTEGER, cidade_destino TEXT, uf_destino TEXT, status TEXT, created_at TEXT,
        updated_at TEXT, criado_por TEXT, atualizado_por TEXT);
    CREATE TABLE cartas_expedidas_subtarefas (carta_expedida_id TEXT, tipo TEXT, status TEXT, ordem INTEGER, updated_at TEXT);
    CREATE TABLE processos (id TEXT, classe_id TEXT);
    CREATE TABLE classes (id TEXT, nome TEXT, codigo TEXT);
    CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT);
");
for ($i = 1; $i <= 35; $i++) {
    $id = sprintf('C%02d', $i);
    $status = $i === 35 ? 'cancelada' : 'em_andamento';
    $finalidade = $i === 34 ? 'Outro' : 'Intimação';
    $envio = $i % 2 === 0 ? 'concluida' : 'em_andamento';
    $db->prepare("INSERT INTO cartas_expedidas VALUES (?,NULL,? ,?,30,'Una','BA',?,'2026-11-26','2026-11-26',NULL,NULL)")
        ->execute([$id, $id, $finalidade, $status]);
    $db->prepare("INSERT INTO cartas_expedidas_subtarefas VALUES (?, 'aguardando_envio', ?, 1, '2026-11-26'),(?, 'aguardando_devolucao', 'pendente', 2, '2026-11-26')")
        ->execute([$id, $envio, $id]);
}
$repo = new CartasExpedidasRepository($db);
$filtros = ['finalidade' => 'Intimação'];
$primeiro = $repo->fetchList($filtros + ['status' => 'aguardando_envio'], null, 0);
$resto = $filtros + ['piloto_sem_envio' => true];
$p1 = $repo->fetchList($resto, 15, 0);
$p2 = $repo->fetchList($resto, 15, 15);
$ids = array_column(array_merge($primeiro, $p1, $p2), 'IdCarta');
if (count($primeiro) !== 17 || count($p1) !== 15 || count($p2) !== 1 || $repo->count($resto) !== 16
    || count(array_unique($ids)) !== 33 || in_array('C34', $ids, true) || in_array('C35', $ids, true)
    || count($repo->fetchList($filtros, 15, 0)) !== 15) {
    throw new RuntimeException('Partição de fila, filtros ou paginação não preservados.');
}
echo "PASS: consultas reais da fila prioritária e das demais etapas com dados sintéticos.\n";
