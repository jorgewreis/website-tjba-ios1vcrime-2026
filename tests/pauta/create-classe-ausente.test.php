<?php
declare(strict_types=1);

require_once __DIR__ . '/../processos/pauta-transaction.test.php';

foreach (['existente', 'novo', 'codigo'] as $cenario) {
    $db = new PautaTestDatabase();
    foreach (['data_hora_designacao TEXT', 'hora_inicio TEXT', 'hora_fim TEXT', 'duracao_estimada_minutos INTEGER'] as $column) {
        $db->exec('ALTER TABLE sala_audiencias_pauta ADD COLUMN ' . $column);
    }
    $db->exec('CREATE TABLE sala_audiencias_pauta_parte (id_parte TEXT, pauta TEXT, ativo INTEGER)');
    $entrada = $payload;
    $entrada['classe_codigo'] = $cenario === 'codigo' ? '123' : '';
    $entrada['classe_descricao'] = 'CLASSE NÃO INFORMADA';
    if ($cenario === 'novo') {
        $entrada['numero_processo'] = '0000002-54.2020.8.05.0001';
    }
    (new SalaAudienciasPautaService($db))->create($entrada, null);
    $processo = $db->query($cenario === 'novo'
        ? "SELECT classe_id FROM processos WHERE id <> 'PRC-TESTE'"
        : "SELECT classe_id FROM processos WHERE id = 'PRC-TESTE'")->fetch(PDO::FETCH_ASSOC);
    $classe = $db->query('SELECT codigo, nome FROM classes')->fetch(PDO::FETCH_ASSOC);
    if ($cenario === 'existente' && ($processo['classe_id'] !== 'CLASSE-ORIGINAL' || $classe !== false)) {
        throw new RuntimeException('Placeholder alterou a classe existente.');
    }
    if ($cenario === 'novo' && ($processo['classe_id'] !== null || $classe !== false)) {
        throw new RuntimeException('Placeholder gerou classe para processo novo.');
    }
    if ($cenario === 'codigo' && (!$classe || $classe['codigo'] !== '123' || $classe['nome'] !== '123' || !$processo['classe_id'])) {
        throw new RuntimeException('Código válido foi perdido ou placeholder foi persistido.');
    }
}
echo "PASS classe ausente preserva classe existente e código válido (SQLite em memória; SQL adaptado)\n";
