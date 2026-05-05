<?php

declare(strict_types=1);

/**
 * Regras de produtividade automática — fluxos internos (consultas, audiência, prioridades).
 * IDs alinhados a docs/database/seed-interno-produtividade-regras.sql.
 *
 * @var array<string, array{regra_id:string,tipo_ato:string,tipo_documento:string,sistema_modulo:string,referencia_sistema:string}>
 */
return [
    'consulta_partes_list' => [
        'regra_id' => 'PRG-EFF49597A5B9B97D6F92A2419A',
        'tipo_ato' => 'Consulta',
        'tipo_documento' => 'Partes',
        'sistema_modulo' => 'consultas-partes',
        'referencia_sistema' => 'consultas-partes',
    ],
    'consulta_processos_list' => [
        'regra_id' => 'PRG-5F1EA1F9E26716D9E352B6F59E',
        'tipo_ato' => 'Consulta',
        'tipo_documento' => 'Processos',
        'sistema_modulo' => 'consultas-processos-lista',
        'referencia_sistema' => 'consultas-processos',
    ],
    'consulta_processos_get' => [
        'regra_id' => 'PRG-0F85EC3B6FCDB550F743AF0984',
        'tipo_ato' => 'Consulta',
        'tipo_documento' => 'Processos',
        'sistema_modulo' => 'consultas-processos-detalhe',
        'referencia_sistema' => 'consultas-processos',
    ],
    'audiencia_parte_cadastro' => [
        'regra_id' => 'PRG-6507451C24356F8EEF2A35A401',
        'tipo_ato' => 'Audiência',
        'tipo_documento' => 'Parte cadastro',
        'sistema_modulo' => 'sala-audiencias-pauta',
        'referencia_sistema' => 'sala-audiencias-pauta',
    ],
    'audiencia_parte_edicao' => [
        'regra_id' => 'PRG-28C23BA8F0F10EDC5D13251640',
        'tipo_ato' => 'Audiência',
        'tipo_documento' => 'Parte edição',
        'sistema_modulo' => 'sala-audiencias-pauta',
        'referencia_sistema' => 'sala-audiencias-pauta',
    ],
    'processo_prioridade_add' => [
        'regra_id' => 'PRG-98ABBF962B6895908B340940D4',
        'tipo_ato' => 'Processo',
        'tipo_documento' => 'Prioridade inclusão',
        'sistema_modulo' => 'processos-prioridades',
        'referencia_sistema' => 'processos-prioridades',
    ],
];
