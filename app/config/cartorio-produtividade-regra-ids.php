<?php

declare(strict_types=1);

/**
 * event_code => id em produtividade_regras (seed docs/database/seed-cartorio-produtividade-regras.sql).
 * Resolver por PK evita falhas de matching por texto/encoding no tipo_ato.
 */
return [
    'cartorio-guias.create' => 'PRG-BD68B244003885B13B51A4B0AD',
    'cartorio-guias.subtarefa.detalhamento_informacoes' => 'PRG-D748607B3D1507B04C2E14A9B2',
    'cartorio-guias.subtarefa.calculo_pena_multa' => 'PRG-950DE665AF8B6D44E9CA351B54',
    'cartorio-guias.subtarefa.expedicao_guia' => 'PRG-2568DFBE6366A592C9C3855CB6',
    'cartorio-guias.subtarefa.aguardando_assinatura' => 'PRG-B3097F26046F35EAD76E8ABA6F',
    'cartorio-guias.subtarefa.juntada_automatica' => 'PRG-1E1D075C9EA0413F4655FBE160',
    'cartorio-guias.subtarefa.gerar_pdf' => 'PRG-AB215295822FF8CE93695EC3C6',
    'cartorio-guias.subtarefa.envio_guia' => 'PRG-87B7FFF1DE942DFD9E349E07BB',
    'cartorio-guias.subtarefa.juntada_termo_envio' => 'PRG-0545BA6A8137AD47EEC47A8135',
    'cartorio-cartas-expedidas.create' => 'PRG-6148EE02061B193A910123ED4C',
    'cartorio-cartas-expedidas.subtarefa.aguardando_assinatura' => 'PRG-A6DBB780E4687FF524A82966CC',
    'cartorio-cartas-expedidas.subtarefa.aguardando_gerar_pdf' => 'PRG-87CA1B5B698F64B58E12AFE9AE',
    'cartorio-cartas-expedidas.subtarefa.aguardando_envio' => 'PRG-22C5B0096FFF3D807ACCE213D5',
    'cartorio-cartas-expedidas.subtarefa.aguardando_juntada_termo' => 'PRG-BD9A9B7BAE7D9A9CC09D8DF2D0',
    'cartorio-cartas-expedidas.subtarefa.aguardando_informacoes' => 'PRG-DA117D56EE4C8929140C7B4FFC',
    'cartorio-cartas-expedidas.subtarefa.aguardando_devolucao' => 'PRG-7B672E63B4967D6B985D7AE6D4',
    'cartorio-oficios.create' => 'PRG-C404300C356C31805B746AB6EC',
    'cartorio-oficios.subtarefa.enviar_oficio' => 'PRG-60F3F5C4A5A7501EE25B02591E',
    'cartorio-oficios.subtarefa.juntar_termo_envio' => 'PRG-D993A747EF3853E7D1E0972A8B',
    'cartorio-medidas-protetivas.create' => 'PRG-618FEA0A452D545A805F60679E',
    'cartorio-medidas-protetivas.subtarefa.cadastro_partes_pje' => 'PRG-0F2D775643AD5FFD89D3D91D99',
    'cartorio-medidas-protetivas.subtarefa.expedicao_mandados_intimacao' => 'PRG-79CE811EE5D41053DDD5C381CD',
    'cartorio-editais.create' => 'PRG-06B9DA113BC2DCF2F26056A991',
    'cartorio-editais.subtarefa.expedicao_edital' => 'PRG-C2CF47AC5C69FE199EE74F1C6C',
    'cartorio-editais.subtarefa.envio_djen' => 'PRG-D4AC13962913F3F1E5F5F31747',
    'cartorio-editais.subtarefa.certificacao_publicacao' => 'PRG-AD3756AF9C4E0DC0C3E3547A5C',
    'cartorio-editais.subtarefa.certificacao_decurso_prazo' => 'PRG-532763C83D7234A5D2B0335A3E',
    'cartorio-cejusc.create' => 'PRG-EB3B92922F6DFA8A33BA6C4315',
    'cartorio-cejusc.subtarefa.gerar_pdf_decisao' => 'PRG-CA70F2DEAB2FEEDFAF68936862',
    'cartorio-cejusc.subtarefa.enviar_decisao' => 'PRG-BAD00BE11DA19DD34A84811725',
    'cartorio-cejusc.subtarefa.juntar_termo_envio' => 'PRG-4B28F72D87545F77319471EACF',
    'cartorio-cartas-recebidas.create' => 'PRG-BFDDBFECBFDD99615E2878B1B4',
    'cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_cumprimento' => 'PRG-D754E646011532CEE50EC9B405',
    'cartorio-cartas-recebidas.subtarefa.expedicao_documento' => 'PRG-5BF563FCD19F86F7E9A3FF8FE3',
    'cartorio-cartas-recebidas.subtarefa.juntada_certidao_oficial' => 'PRG-DEFC90B6DDDA7947045C757244',
    'cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_devolucao' => 'PRG-623C6D54BC080577077EAA28C5',
    'cartorio-cartas-recebidas.subtarefa.envio_processo' => 'PRG-528B9AC348917721DA284DCD93',
    'cartorio-cartas-recebidas.subtarefa.juntada_termo_envio' => 'PRG-DE9FE653361B20C0304DC496F6',
    'cartorio-cartas-recebidas.subtarefa.arquivamento_definitivo' => 'PRG-2369E8BB6B5914F8A2A0998BDB',
    'cartorio-ceapa.create' => 'PRG-C299A59B8C1F2F0EBD87BC8F4D',
];
