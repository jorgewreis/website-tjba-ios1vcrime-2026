<?php
declare(strict_types=1);

require_once __DIR__ . '/../processos/pauta-transaction.test.php';

function rejeitaPauta(callable $op, string $motivo): void
{
    try {
        $op();
    } catch (InvalidArgumentException $e) {
        if (str_contains($e->getMessage(), $motivo)) {
            return;
        }
        throw $e;
    }
    throw new RuntimeException("A regra de pauta foi aceita incorretamente: " . $motivo);
}

$db = new PautaTestDatabase();
foreach (['data_hora_designacao TEXT', 'hora_inicio TEXT', 'hora_fim TEXT', 'duracao_estimada_minutos INTEGER',
    'data_hora_inicio_efetivo TEXT', 'data_hora_fim_efetivo TEXT', 'updated_at TEXT'] as $column) {
    $db->exec('ALTER TABLE sala_audiencias_pauta ADD COLUMN ' . $column);
}
$db->exec('CREATE TABLE sala_audiencias_pauta_parte (id_parte TEXT, pauta TEXT, ativo INTEGER)');
$hoje = date('Y-m-d');
$amanha = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
$service = new SalaAudienciasPautaService($db);
$put = static function (string $id, string $processo, string $status, string $data, string $hora) use ($db): void {
    $db->prepare('INSERT INTO sala_audiencias_pauta (id_pauta, processo, ativo, status, data_audiencia, hora_audiencia) VALUES (?, ?, 1, ?, ?, ?)')
        ->execute([$id, $processo, $status, $data, $hora]);
};
$put('A', 'PRC-TESTE', 'designada', $hoje, '09:00');
$put('B', 'PRC-OUTRO', 'designada', $hoje, '09:30');
$put('C', 'PRC-TESTE', 'cancelada', $hoje, '10:00');
$dados = ['tipo_audiencia' => 'Instrução', 'localizacao' => 'Sala sintética', 'hora_audiencia' => '09:30'];
rejeitaPauta(fn () => $service->update('B', $dados + ['status' => 'em_andamento', 'data_audiencia' => $amanha], null), 'data designada');
rejeitaPauta(fn () => $service->update('C', $dados + ['status' => 'designada', 'data_audiencia' => $hoje], null), 'outra audiência ativa');
$service->setStatusPainel('A', 'iniciar', null);
rejeitaPauta(fn () => $service->update('B', $dados + ['status' => 'em_andamento', 'data_audiencia' => $hoje], null), 'outra audiência em andamento');
$service->setStatusPainel('A', 'encerrar', null);
if (!$service->update('B', $dados + ['status' => 'em_andamento', 'data_audiencia' => $hoje], null)
    || $service->findIdEmAndamentoNaData($hoje) !== 'B') {
    throw new RuntimeException('A edição legítima não iniciou a audiência.');
}
echo "PASS REI-131: data, exclusividade do processo, exclusividade em andamento e transições válidas\n";

$db->exec('CREATE TABLE sala_audiencias_pauta_alocados (id TEXT PRIMARY KEY, pauta_id TEXT, usuario_id TEXT, tipo TEXT, created_at TEXT, UNIQUE (pauta_id, usuario_id))');
$repo = new SalaAudienciasPautaAlocadosRepository($db);
$first = $repo->addAlocado('B', 'USR-1', SalaAudienciasPautaAlocadosRepository::TIPO_ALL);
foreach ([2, 3, 4, 5] as $n) {
    $service->addAlocadoUsuario('B', 'USR-' . $n);
}
if ($first !== $repo->addAlocado('B', 'USR-1') || $repo->countByPautaId('B') !== 5) {
    throw new RuntimeException('Autoalocação ou repetição alterou a cardinalidade.');
}
rejeitaPauta(fn () => $service->addAlocadoUsuario('B', 'USR-6'), 'Limite de 5');
if ($db->inTransaction() || $repo->countByPautaId('B') !== 5 || $repo->countByPautaId('A') !== 0) {
    throw new RuntimeException('O limite ou o isolamento entre pautas não foi preservado.');
}
echo "PASS REI-334: autoalocação, repetição com lotação, limite e isolamento (SQL adaptado; sem concorrência MySQL)\n";
