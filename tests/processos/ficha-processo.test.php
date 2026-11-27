<?php
declare(strict_types=1);

require __DIR__ . '/../../app/helpers/ProcessoFicha.php';

function check(bool $ok, string $what): void
{
    if (!$ok) throw new RuntimeException($what);
}

// Números montados para o teste, sem consulta a nenhuma base.
function cnj(string $jtrUnidade): string
{
    $seq = '0001234';
    $ano = '2026';
    $r = 0;
    foreach (str_split($seq . $ano . $jtrUnidade . '00') as $n) $r = (10 * $r + (int) $n) % 97;
    return $seq . str_pad((string) (98 - $r), 2, '0', STR_PAD_LEFT) . $ano . $jtrUnidade;
}
$origem = ProcessoFicha::origem(cnj('8050103'));
check($origem['comarca'] === 'Ilhéus' && $origem['unidade'] === '0103' && $origem['ano'] === '2026', 'origem TJBA');
check($origem['tribunal'] === 'Tribunal de Justiça da Bahia (TJBA)', 'rótulo Tribunal TJBA');
check(ProcessoFicha::origem(cnj('8260103'))['comarca'] === null, 'não reutilizar código do TJBA em outro tribunal');
check(ProcessoFicha::origem(cnj('4020001'))['tribunal'] === 'Tribunal Regional Federal da 2ª Região (TRF2)', 'tribunal federal');
check(ProcessoFicha::origem('número inválido')['unidade'] === null, 'não inferir de número inválido');

$dados = ['resultado_unico_verificado'=>true, 'sigilo_ausencia_verificada'=>true,
    'orgao_julgador'=>['nome'=>'Vara de destino fictícia'],
    'classe'=>['descricao'=>'Classe fictícia'], 'grau'=>'G1',
    'assuntos'=>[['descricao'=>'Assunto fictício']],
    'polo_passivo'=>['não remeter nomes'],
    'movimentos'=>[['nome'=>'Movimento fictício','dataHora'=>'2026-11-26T12:00:00Z','complementos'=>['não remeter']]],
];
$vistos = ProcessoFicha::dataJudPublico($dados);
check($vistos['vara'] === 'Vara de destino fictícia' && $origem['comarca'] === 'Ilhéus', 'órgão atual não sobrescreve origem');
check($vistos['grau'] === 'G1', 'grau informado pela fonte');
check($vistos['sigilo'] === 'Público' && count($vistos['movimentos']) === 1, 'resposta pública confirmada');
check(!isset($vistos['polo_passivo'], $vistos['movimentos'][0]['complementos']), 'somente dados necessários');
check(ProcessoFicha::dataJudPublico(['indisponivel_na_base_publica'=>true]) === ['estado'=>'indisponivel'], 'vazio não é público');
check(ProcessoFicha::dataJudPublico(['sigilo_restrito'=>true,'nivel_sigilo'=>3] + $dados) === ['estado'=>'restrito','sigilo'=>'Sigilo Médio'], 'resposta restrita não exibe histórico');
check(ProcessoFicha::dataJudPublico(['sigilo_ausencia_verificada'=>false] + $dados) === ['estado'=>'indisponivel'], 'sem nível não se presume público');

check(ProcessoFicha::retorno('/frame.php?page=cartasexpedidas&status=aguardando_envio&page_number=2') === '/frame.php?page=cartasexpedidas&status=aguardando_envio&page_number=2', 'preservar lista e filtros');
foreach (['//outro.example/frame.php?page=lista','https://outro.example/frame.php?page=lista','/outro.php?page=lista','/frame.php?page=consultaprocessosedit','/frame.php?page[]=lista'] as $ruim) {
    check(ProcessoFicha::retorno($ruim) === null, 'rejeitar retorno não elegível');
}
echo "PASS: origem CNJ, retorno local e separação dos dados públicos e sigilosos.\n";
