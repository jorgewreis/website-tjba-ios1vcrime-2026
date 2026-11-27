<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../../app/helpers/CartasExpedidasTempoEtapa.php';

date_default_timezone_set('America/Bahia');
function igual(mixed $observado, mixed $esperado): void
{
    if ($observado !== $esperado) {
        throw new RuntimeException(var_export([$observado, $esperado], true));
    }
}
$cadastro = '2026-11-10 11:00:00';
$envio = '2026-11-19 11:00:00';
$sub = ['tipo' => 'aguardando_envio', 'status' => 'concluída', 'updated_at' => $envio];
igual(CartasExpedidasTempoEtapa::inicio('envio', $cadastro, [$sub]), strtotime($cadastro));
igual(CartasExpedidasTempoEtapa::inicio('devolucao', $cadastro, [$sub]), strtotime($envio));
igual(CartasExpedidasTempoEtapa::inicio('devolucao', $cadastro, []), null);
igual(CartasExpedidasTempoEtapa::inicio('devolucao', $cadastro, [$sub, $sub]), null);
igual(CartasExpedidasTempoEtapa::inicio('devolucao', $cadastro, [['status' => 'em_andamento'] + $sub]), null);
igual(CartasExpedidasTempoEtapa::inicio('envio', '2026-11-31 11:00:00', []), null);
igual(CartasExpedidasTempoEtapa::inicio('encerrada', $cadastro, [$sub]), null);
$inicio = (int) strtotime($envio);
foreach ([0=>'<1min', 59=>'<1min', 600=>'10min', 3599=>'59min', 3600=>'1h', 691200=>'8d', 2592000=>'1mês', 5184000=>'2meses'] as $seg => $resumo) {
    igual(CartasExpedidasTempoEtapa::formatar($inicio, $inicio + $seg), $resumo);
}
echo "PASS: origem, datas ausentes ou inválidas, e formato do tempo na etapa.\n";
