<?php
declare(strict_types=1);

/**
 * Template versionável de configuração do MNI (CNJ).
 *
 * - Copie/adapte os valores reais APENAS no .env (já ignorado pelo git).
 * - Nenhuma página existente lê este arquivo: a leitura efetiva é feita por
 *   public/api/mni/config.php (que delega para MniCredentials).
 * - Os defaults daqui são apenas para referência/documentação.
 */

return [
    'tjba' => [
        // Endpoint SOAP do MNI do TJBA (PJe-1g). Confirmar formato com o tribunal.
        'endpoint' => '',
        // Versões suportadas: 2.2.2 | 2.2.3 | 3.0.0
        'version' => '2.2.2',
        // Credenciais de consultante (preenchidas via .env).
        'id_consultante' => '',
        'senha_consultante' => '',
    ],
    'http' => [
        // Timeouts em milissegundos (MNI tende a ser bem mais lento que DataJud).
        'timeout_ms' => 30000,
        'connect_timeout_ms' => 10000,
        // Cabeçalho User-Agent enviado nas chamadas SOAP — facilita rastrear logs no tribunal.
        'user_agent' => 'TJBA-1VCrime-Ilheus/MNI-Client (PHP cURL)',
    ],
    'options' => [
        // Flags da operação consultarProcesso. Mantém o payload pequeno por padrão.
        'incluir_cabecalho' => true,
        'incluir_documentos' => false,
        'incluir_movimentos' => true,
        // Máximo de movimentos a manter no shape final (proteção contra processos com milhares de movimentos).
        'movimentos_limit' => 200,
    ],
];
