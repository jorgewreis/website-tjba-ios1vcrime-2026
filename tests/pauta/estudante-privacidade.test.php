<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/SalaAudienciasEstudantePrivacidade.php';

foreach (['processo_sigiloso', 'envolve_menor', 'qtd_menor', 'tem_prioridade_bloqueio'] as $flag) {
    foreach ([0, 1] as $inscrito) {
        $row = [$flag => 1, 'ja_inscrito' => $inscrito, 'data_audiencia' => 'SEGREDO', 'link_audiencia' => 'SEGREDO'];
        if (!SalaAudienciasEstudantePrivacidade::indisponivel($row)) throw new RuntimeException('Bloqueio não detectado.');
        $payload = SalaAudienciasEstudantePrivacidade::respostaIndisponivel('pauta-teste');
        if ($payload !== ['id_pauta' => 'pauta-teste', 'indisponivel' => true]) throw new RuntimeException('Resposta não mínima.');
    }
}
if (SalaAudienciasEstudantePrivacidade::indisponivel(['ja_inscrito' => 1])) throw new RuntimeException('Inscrição existente não deve bloquear por si só.');
if (SalaAudienciasEstudantePrivacidade::indisponivel([])) throw new RuntimeException('Pauta livre foi bloqueada.');
echo "PASS: resposta mínima para bloqueios e inscritos/não inscritos sintéticos\n";
