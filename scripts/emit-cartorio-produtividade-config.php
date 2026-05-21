<?php
declare(strict_types=1);

$def = static function (string $mod, string $slug, int $q = 1): array {
    return [
        'tipo_ato' => 'Cartório',
        'tipo_documento' => $slug,
        'sistema_modulo' => $mod,
        'quantidade_default' => $q,
        'referencia_sistema' => $mod,
    ];
};

$registry = [];
$texts = [];

$add = static function (array &$registry, array &$texts, string $code, array $r, string $obs) use ($def): void {
    $registry[$code] = $r;
    $texts[$code] = ['observacao' => $obs];
};

// --- Guias ---
$m = 'cartorio-guias';
$add($registry, $texts, 'cartorio-guias.create', $def($m, 'cartorio-guias.create'), 'Guia cadastrada no cartório (quantidade {quantidade}). Processo {numero_processo}.');
$guiasSubs = [
    'detalhamento_informacoes' => 'Concluiu subtarefa de detalhamento da guia.',
    'calculo_pena_multa' => 'Concluiu subtarefa de cálculo de pena/multa da guia.',
    'expedicao_guia' => 'Concluiu subtarefa de expedição da guia.',
    'aguardando_assinatura' => 'Concluiu subtarefa de aguardando assinatura da guia.',
    'juntada_automatica' => 'Concluiu subtarefa de juntada automática da guia.',
    'gerar_pdf' => 'Concluiu subtarefa de gerar PDF da guia.',
    'envio_guia' => 'Concluiu subtarefa de envio da guia.',
    'juntada_termo_envio' => 'Concluiu subtarefa de juntada do termo de envio da guia.',
];
foreach ($guiasSubs as $tipo => $label) {
    $code = 'cartorio-guias.subtarefa.' . $tipo;
    $add($registry, $texts, $code, $def($m, $code), $label . ' Processo {numero_processo}.');
}

// --- Cartas expedidas ---
$m = 'cartorio-cartas-expedidas';
$add($registry, $texts, 'cartorio-cartas-expedidas.create', $def($m, 'cartorio-cartas-expedidas.create'), 'Carta expedida cadastrada para {finalidade}. Processo {numero_processo}.');
$ceSubs = [
    'aguardando_assinatura' => 'Carta expedida assinada pela magistrada.',
    'aguardando_gerar_pdf' => 'Carta expedida: PDF gerado.',
    'aguardando_envio' => 'Carta expedida enviada.',
    'aguardando_juntada_termo' => 'Carta expedida: termo de juntada concluído.',
    'aguardando_informacoes' => 'Carta expedida: informações de destino registradas.',
    'aguardando_devolucao' => 'Carta expedida: devolução concluída.',
];
foreach ($ceSubs as $tipo => $label) {
    $code = 'cartorio-cartas-expedidas.subtarefa.' . $tipo;
    $add($registry, $texts, $code, $def($m, $code), $label . ' Processo {numero_processo}.');
}

// --- Ofícios ---
$m = 'cartorio-oficios';
$add($registry, $texts, 'cartorio-oficios.create', $def($m, 'cartorio-oficios.create'), 'Ofício cadastrado no cartório. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-oficios.subtarefa.enviar_oficio', $def($m, 'cartorio-oficios.subtarefa.enviar_oficio'), 'Ofício enviado. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-oficios.subtarefa.juntar_termo_envio', $def($m, 'cartorio-oficios.subtarefa.juntar_termo_envio'), 'Termo de envio do ofício juntado. Processo {numero_processo}.');

// --- Medidas ---
$m = 'cartorio-medidas-protetivas';
$add($registry, $texts, 'cartorio-medidas-protetivas.create', $def($m, 'cartorio-medidas-protetivas.create'), 'Medida protetiva cadastrada. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-medidas-protetivas.subtarefa.cadastro_partes_pje', $def($m, 'cartorio-medidas-protetivas.subtarefa.cadastro_partes_pje'), 'Medida protetiva: cadastro de partes no PJe. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-medidas-protetivas.subtarefa.expedicao_mandados_intimacao', $def($m, 'cartorio-medidas-protetivas.subtarefa.expedicao_mandados_intimacao'), 'Medida protetiva: expedição de mandados/intimação. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-medidas-protetivas.subtarefa.cadastro_bnmp', $def($m, 'cartorio-medidas-protetivas.subtarefa.cadastro_bnmp'), 'Medida protetiva: cadastro no BNMP. Processo {numero_processo}.');
$add($registry, $texts, 'cartorio-medidas-protetivas.subtarefa.certidao_cumprimento', $def($m, 'cartorio-medidas-protetivas.subtarefa.certidao_cumprimento'), 'Medida protetiva: certidão de cumprimento. Processo {numero_processo}.');

// --- Editais ---
$m = 'cartorio-editais';
$add($registry, $texts, 'cartorio-editais.create', $def($m, 'cartorio-editais.create'), 'Edital cadastrado. Processo {numero_processo}.');
$edSubs = [
    'expedicao_edital' => 'Edital expedido.',
    'envio_djen' => 'Edital enviado ao DJEN.',
    'certificacao_publicacao' => 'Edital: certificação de publicação.',
    'certificacao_decurso_prazo' => 'Edital: certificação de decurso de prazo.',
];
foreach ($edSubs as $tipo => $label) {
    $code = 'cartorio-editais.subtarefa.' . $tipo;
    $add($registry, $texts, $code, $def($m, $code), $label . ' Processo {numero_processo}.');
}

// --- Cartas recebidas ---
$m = 'cartorio-cartas-recebidas';
$add($registry, $texts, 'cartorio-cartas-recebidas.create', $def($m, 'cartorio-cartas-recebidas.create'), 'Carta recebida cadastrada. Processo {numero_processo}.');
$crSubs = [
    'ato_ordinatorio_cumprimento', 'expedicao_documento', 'juntada_certidao_oficial',
    'ato_ordinatorio_devolucao', 'envio_processo', 'juntada_termo_envio', 'arquivamento_definitivo',
];
foreach ($crSubs as $tipo) {
    $code = 'cartorio-cartas-recebidas.subtarefa.' . $tipo;
    $add($registry, $texts, $code, $def($m, $code), 'Carta recebida: ' . str_replace('_', ' ', $tipo) . ' concluída. Processo {numero_processo}.');
}

// Estorno (texto exibido quando saldo zerado após cancel/delete)
$add($registry, $texts, 'cartorio-interno.estorno-saldo', $def('cartorio-interno', 'cartorio-interno.estorno-saldo'), 'Estorno de produtividade (encerramento de tarefa).');

file_put_contents(
    __DIR__ . '/../app/config/cartorio-produtividade-registry.php',
    "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($registry, true) . ";\n"
);
file_put_contents(
    __DIR__ . '/../app/config/cartorio-produtividade-list-texts.php',
    "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($texts, true) . ";\n"
);
echo "OK " . count($registry) . " eventos\n";
