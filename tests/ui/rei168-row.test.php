<?php
declare(strict_types=1);
// Render the actual PHP partial, with no database or authenticated application bootstrap.
function renderLetter(array $row): string {
    $connection = null;
    $registroListViewerContext = ['usuario_id'=>'fixture', 'perfis'=>[]];
    ob_start();
    require __DIR__ . '/../../app/views/internal/partials/cartas-expedidas-v2-row.php';
    return (string) ob_get_clean();
}
function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function step(string $type, string $status, int $order): array {
    return ['id'=>'step-'.$order,'tipo'=>$type,'status'=>$status,'ordem'=>$order,
        'updated_at'=>'2026-01-01 12:00:00'];
}
$base = ['IdCarta'=>'letter-fixture','Status'=>'em_andamento','Prazo'=>5,
    'Finalidade'=>'<script>invalid</script>','Comarca'=>'Cidade fictícia','Estado'=>'BA'];
$send=step('aguardando_envio','em_andamento',1);
$return=step('aguardando_devolucao','pendente',2);
$html=renderLetter($base+['SubtarefasAll'=>[$send,$return],'EtapaEfetiva'=>'envio']);
expect(str_contains($html,'data-subtarefa-id="step-1"'), 'Initial sending action missing');
expect(!str_contains($html,'class="prazo-restante'), 'Unsent letter must not have return deadline');
expect(!str_contains($html,'<script>invalid</script>'), 'Row data must be escaped');
$send['status']='concluida';
$html=renderLetter(array_replace($base,['Status'=>'aguardando cumprimento','SubtarefasAll'=>[$send,$return],'EtapaEfetiva'=>'devolucao']));
expect(str_contains($html,'data-subtarefa-id="step-2"'), 'Pending executable return must offer return action');
expect(str_contains($html,'class="prazo-restante'), 'Active return deadline missing');
$html=renderLetter($base+['SubtarefasAll'=>[$return],'FluxoInconsistente'=>true,'EtapaEfetiva'=>null]);
expect(str_contains($html,'Etapa a conferir'), 'Unknown stage needs visible diagnostic');
expect(!str_contains($html,'js-continuar-subtarefa'), 'Unknown stage must not offer arbitrary action');
expect(!str_contains($html,'js-reiniciar-carta'), 'Unknown stage must not offer destructive reset');
expect(!str_contains($html,'class="prazo-restante'), 'Unknown stage must not fabricate deadline');
$return['status']='concluida';
$html=renderLetter(array_replace($base,['Status'=>'concluída','SubtarefasAll'=>[$send,$return]]));
expect(!str_contains($html,'js-continuar-subtarefa'), 'Completed letter must not offer next action');
expect(!str_contains($html,'class="prazo-restante'), 'Completed letter must not show active overdue deadline');
$html=renderLetter($base+['SubtarefasAll'=>[$send,$return],'EtapaEfetiva'=>'encerrada']);
expect(str_contains($html,'Finalizado'), 'Completed pair with stale parent must render the effective completed status');
expect(!str_contains($html,'js-continuar-subtarefa'), 'Completed pair with stale parent must not offer sending');
$send['tipo']=' AGUARDANDO_ENVIO '; $send['status']=' CONCLUÍDA ';
$return['tipo']='AGUARDANDO_DEVOLUCAO'; $return['status']='PENDENTE';
$html=renderLetter(array_replace($base,['SubtarefasAll'=>[$send,$return],'EtapaEfetiva'=>'devolucao']));
expect(str_contains($html,'data-subtarefa-id="step-2"'), 'Normalized legacy return must retain correct action');
expect(str_contains($html,'class="prazo-restante'), 'Normalized legacy return must retain deadline');
echo "REI-168 row rendering: 15 assertions passed; no database.\n";
