<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers/CartorioTarefasCondicoes.php';

function conferir(bool $condicao, string $mensagem): void
{
    if (!$condicao) throw new RuntimeException($mensagem);
}

$geral = [['tipo' => 'expedicao', 'tipo_documento' => 'Ofício'], ['tipo' => 'assinatura', 'tipo_documento' => 'Ofício']];
$restrita = [['tipo' => 'expedicao', 'tipo_documento' => 'Ofício']];
$cruzada = [['tipo' => 'expedicao', 'local_tarefa' => 'Cartório']];
conferir(CartorioTarefasCondicoes::corresponde($geral, ['tipo' => 'assinatura', 'tipo_documento' => 'Ofício']), 'Grupo OU não corresponde.');
conferir(!CartorioTarefasCondicoes::corresponde($geral, ['tipo' => 'assinatura', 'tipo_documento' => 'Mandado']), 'Condições E não foram respeitadas.');
$relacao = CartorioTarefasCondicoes::comparar($restrita, $geral);
conferir($relacao['sobrepoem'] && $relacao['primeiro_mais_especifico'] && !$relacao['segundo_mais_especifico'], 'Subconjunto não identificado.');
$relacao = CartorioTarefasCondicoes::comparar($restrita, $cruzada);
conferir($relacao['sobrepoem'] && !$relacao['primeiro_mais_especifico'] && !$relacao['segundo_mais_especifico'], 'Regras incomparáveis não identificadas.');
$relacao = CartorioTarefasCondicoes::comparar($restrita, [['tipo' => 'envio']]);
conferir(!$relacao['sobrepoem'], 'Regras disjuntas foram consideradas sobrepostas.');
$relacao = CartorioTarefasCondicoes::comparar($restrita, $restrita);
conferir($relacao['sobrepoem'] && !$relacao['primeiro_mais_especifico'] && !$relacao['segundo_mais_especifico'], 'Regras iguais foram consideradas mais específicas.');
conferir(CartorioTarefasCondicoes::corresponde([[]], []), 'Grupo vazio deve corresponder a todas as tarefas.');
conferir(CartorioTarefasCondicoes::corresponde([['especificacao_documento' => 'Informando Baixa']], ['especificacao_documento' => 'informando Baixa']), 'Especificação antiga deve corresponder à grafia atual.');
$relacao = CartorioTarefasCondicoes::comparar([['especificacao_documento' => 'Informando Condenação']], [['especificacao_documento' => 'informando Condenação']]);
conferir($relacao['sobrepoem'] && !$relacao['primeiro_mais_especifico'] && !$relacao['segundo_mais_especifico'], 'Grafias equivalentes devem gerar conflito, sem alterar a especificidade.');
conferir(CartorioTarefasCondicoes::comparar([['tipo' => '1']], [['tipo' => '1']])['sobrepoem'], 'Valores numéricos textuais não foram preservados.');
try {
    CartorioTarefasCondicoes::validar([['status' => 'finalizada']]);
    throw new RuntimeException('Campo não permitido foi aceito.');
} catch (InvalidArgumentException $e) {
    // Esperado.
}
echo "Condições de automação verificadas.\n";
