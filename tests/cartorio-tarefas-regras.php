<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers/CartorioTarefasRegras.php';

function verificar(bool $resultado, string $mensagem): void
{
    if (!$resultado) throw new RuntimeException($mensagem);
}
function invalida(array $regra): void
{
    try {
        CartorioTarefasRegras::validar($regra);
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Uma regra inválida foi aceita.');
}
$regra = [
    'nome' => 'Exemplo', 'evento' => 'finalizacao', 'prioridade' => 0,
    'condicoes' => [['tipo' => 'expedicao'], ['tipo' => 'assinatura']],
    'acoes' => [
        ['id' => 'oficio', 'tipo' => 'criar_tarefa', 'tipo_tarefa' => 'expedicao', 'tipo_documento' => 'Ofício', 'local_tarefa' => 'Cartório'],
        ['id' => 'status', 'tipo' => 'alterar_status', 'alvo' => 'oficio', 'status' => 'em_andamento'],
    ],
];
CartorioTarefasRegras::validar($regra);
$invalida = $regra;
$invalida['acoes'] = array_reverse($invalida['acoes']);
invalida($invalida);
$invalida = $regra;
$invalida['evento'] = 'criacao';
invalida($invalida);
$invalida = $regra;
$invalida['acoes'][] = $invalida['acoes'][0];
invalida($invalida);
$alocacao = ['id' => 'usuario', 'tipo' => 'alocar', 'usuario_id' => 'USR-1'];
$base = ['nome' => 'A', 'evento' => 'criacao', 'prioridade' => 0, 'condicoes' => [[]], 'acoes' => [$alocacao]];
$especifica = $base;
$especifica['nome'] = 'B';
$especifica['condicoes'] = [['tipo' => 'expedicao']];
$especifica['prioridade'] = -1;
CartorioTarefasRegras::validar($base);
CartorioTarefasRegras::validar($especifica);
verificar(CartorioTarefasRegras::vencedora([$base, $especifica], ['tipo' => 'expedicao'], 'alocar')['nome'] === 'B', 'A especificidade deve prevalecer sobre a prioridade.');
$cruzada = $base;
$cruzada['nome'] = 'C';
$cruzada['condicoes'] = [['local_tarefa' => 'Cartório']];
$cruzada['prioridade'] = 3;
verificar(CartorioTarefasRegras::vencedora([$especifica, $cruzada], ['tipo' => 'expedicao', 'local_tarefa' => 'Cartório'], 'alocar')['nome'] === 'C', 'A prioridade deve resolver regras incomparáveis.');
$cruzada['prioridade'] = -1;
verificar(CartorioTarefasRegras::conflitantes($especifica, $cruzada), 'Empate com sobreposição deveria ser rejeitado.');
verificar(!CartorioTarefasRegras::conflitantes($base, $especifica), 'Subconjunto próprio não é conflito.');
$seq = $base;
$seq['evento'] = 'finalizacao';
$seq['acoes'] = [$regra['acoes'][0]];
verificar(!CartorioTarefasRegras::conflitantes($seq, $seq), 'Criações independentes não devem conflitar.');
$seq['acoes'][] = ['id' => 'alocar_filha', 'tipo' => 'alocar', 'alvo' => 'oficio', 'usuario_id' => 'USR-1'];
verificar(!CartorioTarefasRegras::conflitantes($seq, $seq), 'Ações em filhas criadas por regras diferentes não devem conflitar.');
$prazoA = ['nome' => 'Prazo A', 'evento' => 'criacao', 'prioridade' => 0, 'condicoes' => [[]], 'acoes' => [['id' => 'p', 'tipo' => 'definir_prazo', 'inicio' => 'criacao', 'dias' => 3, 'alerta_dias' => 1]]];
$prazoB = $prazoA;
$prazoB['evento'] = 'alocacao';
verificar(CartorioTarefasRegras::conflitantes($prazoA, $prazoB), 'Prazos empatados em eventos diferentes devem ser rejeitados.');
$statusA = ['nome' => 'A para B', 'evento' => 'status_vinculada', 'evento_status' => 'a_fazer', 'prioridade' => 0, 'condicoes' => [[]], 'acoes' => [['id' => 'status', 'tipo' => 'alterar_status', 'status' => 'em_andamento']]];
$statusB = ['nome' => 'B para A', 'evento' => 'status_vinculada', 'evento_status' => 'em_andamento', 'prioridade' => 0, 'condicoes' => [[]], 'acoes' => [['id' => 'status', 'tipo' => 'alterar_status', 'status' => 'a_fazer']]];
try {
    CartorioTarefasRegras::validarCiclos([$statusA, $statusB]);
    throw new RuntimeException('Um ciclo de status foi aceito.');
} catch (InvalidArgumentException $e) {}
CartorioTarefasRegras::validarCiclos([$statusA]);
$seqA = ['nome' => 'A para B', 'evento' => 'finalizacao', 'prioridade' => 0, 'condicoes' => [['tipo' => 'expedicao']], 'acoes' => [
    ['id' => 'filha', 'tipo' => 'criar_tarefa', 'tipo_tarefa' => 'assinatura', 'tipo_documento' => 'Ofício', 'local_tarefa' => 'Cartório'],
    ['id' => 'final', 'tipo' => 'alterar_status', 'alvo' => 'filha', 'status' => 'finalizada']
]];
$seqB = $seqA;
$seqB['condicoes'] = [['tipo' => 'assinatura']];
$seqB['acoes'][0]['tipo_tarefa'] = 'expedicao';
try {
    CartorioTarefasRegras::validarCiclos([$seqA, $seqB]);
    throw new RuntimeException('Um ciclo de criação em sequência foi aceito.');
} catch (InvalidArgumentException $e) {}
CartorioTarefasRegras::validarCiclos([$seqA]);
echo "Regras de automação verificadas.\n";
