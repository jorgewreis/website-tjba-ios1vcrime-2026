<?php

declare(strict_types=1);

return array (
  'cartorio-guias.create' => 
  array (
    'observacao' => '{tipo_guia} cadastrada.',
  ),
  'cartorio-guias.subtarefa.detalhamento_informacoes' => 
  array (
    'observacao' => '{tipo_guia} detalhada.',
  ),
  'cartorio-guias.subtarefa.calculo_pena_multa' => 
  array (
    'observacao' => 'Pena/multa calculada para a {tipo_guia}.',
  ),
  'cartorio-guias.subtarefa.expedicao_guia' => 
  array (
    'observacao' => '{tipo_guia} expedida.',
  ),
  'cartorio-guias.subtarefa.aguardando_assinatura' => 
  array (
    'observacao' => '{tipo_guia} assinada pela magistrada.',
  ),
  'cartorio-guias.subtarefa.juntada_automatica' => 
  array (
    'observacao' => '{tipo_guia} juntada automaticamente.',
  ),
  'cartorio-guias.subtarefa.gerar_pdf' => 
  array (
    'observacao' => 'PDF da {tipo_guia} gerado.',
  ),
  'cartorio-guias.subtarefa.envio_guia' => 
  array (
    'observacao' => 'Envio da {tipo_guia} para {destinatario}.',
  ),
  'cartorio-guias.subtarefa.juntada_termo_envio' => 
  array (
    'observacao' => 'Termo de envio da {tipo_guia} juntado.',
  ),
  'cartorio-oficios.create' => 
  array (
    'observacao' => '{tipo_oficio} cadastrado.',
  ),
  'cartorio-oficios.subtarefa.enviar_oficio' => 
  array (
    'observacao' => 'Ofício enviado para o destinatário — {meio_envio}.',
  ),
  'cartorio-oficios.subtarefa.juntar_termo_envio' => 
  array (
    'observacao' => 'Termo de envio do {tipo_oficio} juntado.',
  ),
  'cartorio-medidas-protetivas.create' => 
  array (
    'observacao' => 'Medida protetiva cadastrada.',
  ),
  'cartorio-medidas-protetivas.subtarefa.cadastro_partes_pje' => 
  array (
    'observacao' => 'Cadastro de {quantidade} partes no PJe realizado',
  ),
  'cartorio-medidas-protetivas.subtarefa.expedicao_mandados_intimacao' => 
  array (
    'observacao' => 'Expedição de {quantidade} mandados/intimação realizada',
  ),
  'cartorio-medidas-protetivas.subtarefa.cadastro_bnmp' =>
  array (
    'observacao' => 'Cadastro no BNMP realizado',
  ),
  'cartorio-medidas-protetivas.subtarefa.certidao_cumprimento' =>
  array (
    'observacao' => 'Certidão de cumprimento expedida',
  ),
  'cartorio-editais.create' => 
  array (
    'observacao' => '{tipo_edital} cadastrado — prazo {prazo_dias} dia(s).',
  ),
  'cartorio-editais.subtarefa.expedicao_edital' => 
  array (
    'observacao' => '{tipo_edital} expedido.',
  ),
  'cartorio-editais.subtarefa.envio_djen' => 
  array (
    'observacao' => '{tipo_edital} enviado ao DJEN.',
  ),
  'cartorio-editais.subtarefa.certificacao_publicacao' => 
  array (
    'observacao' => '{tipo_edital} — certificação de publicação em {data_publicacao}.',
  ),
  'cartorio-editais.subtarefa.certificacao_decurso_prazo' => 
  array (
    'observacao' => '{tipo_edital} — certificação de decurso de prazo (término {data_decurso_prazo}).',
  ),
  'cartorio-cartas-recebidas.create' => 
  array (
    'observacao' => '{tipo_carta} cadastrada — finalidade: {finalidade}.',
  ),
  'cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_cumprimento' => 
  array (
    'observacao' => 'Ato ordinário — cumprimento da {tipo_carta}',
  ),
  'cartorio-cartas-recebidas.subtarefa.expedicao_documento' => 
  array (
    'observacao' => 'Expedição de documento — {tipo_carta}.',
  ),
  'cartorio-cartas-recebidas.subtarefa.juntada_certidao_oficial' => 
  array (
    'observacao' => '{tipo_carta} — juntada de certidão do oficial de justiça.',
  ),
  'cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_devolucao' => 
  array (
    'observacao' => 'Ato ordinário — devolução da {tipo_carta}.',
  ),
  'cartorio-cartas-recebidas.subtarefa.envio_processo' => 
  array (
    'observacao' => '{tipo_carta} devolvida',
  ),
  'cartorio-cartas-recebidas.subtarefa.juntada_termo_envio' => 
  array (
    'observacao' => 'Termo de envio da devolução da {tipo_carta} juntado',
  ),
  'cartorio-cartas-recebidas.subtarefa.arquivamento_definitivo' => 
  array (
    'observacao' => 'Arquivamento definitivo com baixa da {tipo_carta}',
  ),
  'sala-midias.create' =>
  array (
    'observacao' => 'Cadastro de tarefa de mídia ({origem}) — {tipo_audiencia}.',
  ),
  'sala-midias.update' =>
  array (
    'observacao' => 'Alteração de tarefa de mídia — {tipo_audiencia}.',
  ),
  'sala-midias.subtarefa.download_midia' =>
  array (
    'observacao' => 'Download de mídia realizado no Lifesize.',
  ),
  'sala-midias.subtarefa.sincronizacao_midia' =>
  array (
    'observacao' => 'Sincronização de mídia concluída no PJe ({qtd_midias} mídia(s)).',
  ),
  'sala-midias.subtarefa.upload_midia' =>
  array (
    'observacao' => 'Upload de mídia concluído na Pasta Pública ({qtd_midias} mídia(s)).',
  ),
  'sala-midias.subtarefa.certidao_upload_midia' =>
  array (
    'observacao' => 'Certidão de upload de mídia registrada no PJe.',
  ),
  'cartorio-interno.estorno-saldo' => 
  array (
    'observacao' => 'Estorno de produtividade (encerramento de tarefa).',
  ),
);
