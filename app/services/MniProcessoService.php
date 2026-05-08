<?php
declare(strict_types=1);

require_once __DIR__ . '/MniProcessoResponseMapper.php';

final class MniProcessoService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array<string,mixed>
     */
    public function consultarPorNumeroCnj(string $numero): array
    {
        $cnjDigits = preg_replace('/\D+/', '', $numero) ?? '';
        if ($cnjDigits === '' || strlen($cnjDigits) !== 20) {
            throw new InvalidArgumentException('Número CNJ inválido. Informe os 20 dígitos.');
        }

        // #region agent log
        $this->debugLog('diag-run-1', 'H1', 'MniProcessoService::consultarPorNumeroCnj:25', 'Entrada da consulta MNI', [
            'cnj' => $cnjDigits,
            'tribunal_detectado' => $this->detectTribunalCodeFromCnj($cnjDigits),
            'tribunal_configurado' => $this->normalizeTribunalCode((string) ($this->config['tribunal_code'] ?? '')),
            'divergencia_tribunal' => $this->hasTribunalMismatch($cnjDigits),
        ]);
        // #endregion

        $this->assertIntercomCredentials();
        $endpoints = $this->resolveEndpointTargets();
        $lastRuntimeError = null;
        $selected = null;
        $merged = [
            'consultar_processo' => [],
            'consultar_alteracao' => [],
        ];

        foreach ($endpoints as $target) {
            try {
                // #region agent log
                $this->debugLog('diag-run-1', 'H1', 'MniProcessoService::consultarPorNumeroCnj:48', 'Tentando endpoint', [
                    'wsdl' => (string) ($target['wsdl'] ?? ''),
                    'location' => (string) ($target['location'] ?? ''),
                ]);
                // #endregion
                $soapClient = $this->buildClientForTarget($target);
                $consultarProcessoPayload = $this->runSoapOperation(
                    $soapClient,
                    ['consultarProcesso', 'ConsultarProcesso'],
                    $this->buildConsultarProcessoParamVariants($cnjDigits)
                );

                $alteracaoPayload = null;
                try {
                    $alteracaoPayload = $this->runSoapOperation(
                        $soapClient,
                        ['consultarAlteracao', 'ConsultarAlteracao'],
                        $this->buildConsultarAlteracaoParamVariants($cnjDigits)
                    );
                } catch (RuntimeException $e) {
                    $alteracaoPayload = ['aviso' => $e->getMessage()];
                }

                $merged = [
                    'consultar_processo' => $consultarProcessoPayload,
                    'consultar_alteracao' => $alteracaoPayload,
                ];
                $selected = $target;

                // #region agent log
                $this->debugLog('diag-run-1', 'H2', 'MniProcessoService::consultarPorNumeroCnj:74', 'Retorno bruto por endpoint', [
                    'wsdl' => (string) ($target['wsdl'] ?? ''),
                    'consultar_processo_tipo' => gettype($consultarProcessoPayload),
                    'consultar_processo_vazio' => (is_array($consultarProcessoPayload) && $consultarProcessoPayload === []),
                    'consultar_alteracao_tipo' => gettype($alteracaoPayload),
                    'consultar_alteracao_vazio' => (is_array($alteracaoPayload) && $alteracaoPayload === []),
                ]);
                // #endregion

                if (!$this->isResultEffectivelyEmpty($merged)) {
                    break;
                }
            } catch (RuntimeException $e) {
                // #region agent log
                $this->debugLog('diag-run-1', 'H4', 'MniProcessoService::consultarPorNumeroCnj:87', 'Erro no endpoint durante consulta', [
                    'wsdl' => (string) ($target['wsdl'] ?? ''),
                    'error' => $e->getMessage(),
                ]);
                // #endregion
                $lastRuntimeError = $e;
            }
        }

        if ($selected === null && $lastRuntimeError instanceof RuntimeException) {
            throw $lastRuntimeError;
        }

        $output = MniProcessoResponseMapper::mapToOutput($cnjDigits, $merged);
        $output['meta_consulta'] = [
            'tribunal_detectado_cnj' => $this->detectTribunalCodeFromCnj($cnjDigits),
            'tribunal_configurado' => $this->normalizeTribunalCode((string) ($this->config['tribunal_code'] ?? '')),
            'divergencia_tribunal' => $this->hasTribunalMismatch($cnjDigits),
            'fonte_wsdl' => (string) ($selected['wsdl'] ?? ''),
            'fonte_location' => (string) ($selected['location'] ?? ''),
            'fallback_multibase_ativo' => count($endpoints) > 1,
        ];
        // #region agent log
        $this->debugLog('diag-run-1', 'H5', 'MniProcessoService::consultarPorNumeroCnj:108', 'Resultado final da consulta MNI', [
            'meta_consulta' => $output['meta_consulta'],
            'resultado_efetivamente_vazio' => $this->isResultEffectivelyEmpty($merged),
        ]);
        // #endregion
        return $output;
    }

    /**
     * Diagnóstico rápido de conectividade e autenticação no endpoint MNI.
     *
     * @return array<string,mixed>
     */
    public function diagnosticarConectividade(bool $quick = true): array
    {
        $this->assertIntercomCredentials();
        $soapClient = $this->buildClient();

        $functions = [];
        try {
            $functions = $soapClient->__getFunctions();
        } catch (Throwable $e) {
            $functions = [];
        }

        $probePayload = null;
        if (!$quick) {
            $probeNumero = $this->resolveProbeNumeroProcesso();
            $base = [
                'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
                'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
                'numeroProcesso' => $probeNumero,
            ];

            $probePayload = $this->runSoapOperation(
                $soapClient,
                ['consultarAlteracao', 'ConsultarAlteracao'],
                [
                    $base,
                    ['consultarAlteracao' => $base],
                ]
            );
        }

        return [
            'ok' => true,
            'wsdl' => (string) ($this->config['wsdl'] ?? ''),
            'location' => (string) ($this->config['location'] ?? ''),
            'functions_count' => count($functions),
            'functions_preview' => array_slice($functions, 0, 8),
            'auth_check_mode' => $quick ? 'quick' : 'full',
            'auth_check_operation' => $quick ? 'wsdl_only' : 'consultarAlteracao',
            'auth_check_response' => $quick ? ['info' => 'Modo rápido: apenas carga WSDL e introspecção de funções.'] : $probePayload,
            'timestamp' => date(DATE_ATOM),
        ];
    }

    private function buildClient(): SoapClient
    {
        return $this->buildClientForTarget([
            'wsdl' => (string) ($this->config['wsdl'] ?? ''),
            'location' => (string) ($this->config['location'] ?? ''),
        ]);
    }

    /**
     * @param array{wsdl:string,location:string} $target
     */
    private function buildClientForTarget(array $target): SoapClient
    {
        $wsdl = trim((string) ($target['wsdl'] ?? ''));
        if ($wsdl === '') {
            throw new RuntimeException('WSDL do MNI não configurado.');
        }

        $options = [
            'trace' => 0,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'connection_timeout' => (int) ($this->config['connect_timeout'] ?? 10),
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'encoding' => 'UTF-8',
        ];

        $location = trim((string) ($target['location'] ?? ''));
        if ($location !== '') {
            $options['location'] = $location;
        }

        $login = trim((string) ($this->config['login'] ?? ''));
        $password = trim((string) ($this->config['password'] ?? ''));
        if ($login !== '') {
            $options['login'] = $login;
            $options['password'] = $password;
        }

        $certPath = trim((string) ($this->config['cert_path'] ?? ''));
        $certPassphrase = trim((string) ($this->config['cert_passphrase'] ?? ''));
        if ($certPath !== '' && is_file($certPath)) {
            $options['local_cert'] = $certPath;
            if ($certPassphrase !== '') {
                $options['passphrase'] = $certPassphrase;
            }
        }

        $streamContext = stream_context_create([
            'http' => [
                'timeout' => (int) ($this->config['timeout'] ?? 30),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $options['stream_context'] = $streamContext;

        try {
            return new SoapClient($wsdl, $options);
        } catch (SoapFault $e) {
            throw new RuntimeException('Falha ao inicializar cliente MNI: ' . $e->getMessage());
        }
    }

    /**
     * @param list<string> $candidates
     * @param list<array<string,mixed>> $paramVariants
     * @return array<string,mixed>
     */
    private function runSoapOperation(SoapClient $client, array $candidates, array $paramVariants): array
    {
        $lastError = null;
        foreach ($candidates as $operationName) {
            foreach ($paramVariants as $params) {
                try {
                    $response = $client->__soapCall($operationName, [$params]);
                    // #region agent log
                    $this->debugLog('diag-run-1', 'H2', 'MniProcessoService::runSoapOperation:228', 'SoapCall retornou sem exception', [
                        'operation' => $operationName,
                        'response_type' => gettype($response),
                        'param_keys' => array_keys($params),
                    ]);
                    // #endregion
                    return $this->normalizeSoapResponse($response);
                } catch (SoapFault $e) {
                    if (stripos((string) $e->getMessage(), 'no XML document') !== false) {
                        $fallback = $this->callOperationViaCurl($operationName, $params);
                        if ($fallback !== null) {
                            // #region agent log
                            $this->debugLog('diag-run-1', 'H3', 'MniProcessoService::runSoapOperation:239', 'Fallback MTOM via cURL ativado', [
                                'operation' => $operationName,
                                'fallback_keys' => array_keys($fallback),
                            ]);
                            // #endregion
                            return $fallback;
                        }
                    }
                    // #region agent log
                    $this->debugLog('diag-run-1', 'H4', 'MniProcessoService::runSoapOperation:247', 'SoapFault em operação', [
                        'operation' => $operationName,
                        'error' => $e->getMessage(),
                    ]);
                    // #endregion
                    $lastError = $e;
                }
            }
        }

        throw new RuntimeException(
            'Falha na operação SOAP MNI (' . implode(', ', $candidates) . '): '
            . ($lastError ? $lastError->getMessage() : 'erro desconhecido')
        );
    }

    /**
     * Fallback para provedores que retornam MTOM multipart/related.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    private function callOperationViaCurl(string $operationName, array $params): ?array
    {
        $location = trim((string) ($this->config['location'] ?? ''));
        if ($location === '') {
            return null;
        }

        $namespace = 'http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/';
        $typeNamespace = 'http://www.cnj.jus.br/tipos-servico-intercomunicacao-2.2.2';
        $soapAction = $namespace . $operationName;
        $bodyXml = $this->buildSoapBodyXml($operationName, $params, $typeNamespace);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:ns1="' . $typeNamespace . '"'
            . ' xmlns:ns2="' . $namespace . '"><SOAP-ENV:Body>'
            . $bodyXml
            . '</SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $ch = curl_init($location);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
            ],
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 8),
            CURLOPT_CONNECTTIMEOUT => (int) ($this->config['connect_timeout'] ?? 4),
        ]);

        $certPath = trim((string) ($this->config['cert_path'] ?? ''));
        $certPassphrase = trim((string) ($this->config['cert_passphrase'] ?? ''));
        if ($certPath !== '' && is_file($certPath)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
            if ($certPassphrase !== '') {
                curl_setopt($ch, CURLOPT_KEYPASSWD, $certPassphrase);
            }
        }

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr !== '' || !is_string($raw) || $raw === '' || $status < 200 || $status >= 500) {
            return null;
        }

        $body = substr($raw, $headerSize);
        if (!is_string($body) || $body === '') {
            return null;
        }

        $xmlPayload = $this->extractSoapEnvelope($body);
        if ($xmlPayload === null) {
            return null;
        }

        return $this->xmlToArray($xmlPayload);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function buildSoapBodyXml(string $operationName, array $params, string $typeNamespace): string
    {
        $payload = $params;
        if (isset($params[$operationName]) && is_array($params[$operationName])) {
            $payload = $params[$operationName];
        } elseif (isset($params['arg0']) && is_array($params['arg0'])) {
            $payload = $params['arg0'];
        }

        $parts = [];
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = '<ns1:' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '>'
                . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                . '</ns1:' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '>';
        }

        return '<ns2:' . htmlspecialchars($operationName, ENT_QUOTES, 'UTF-8')
            . ' xmlns:ns1="' . $typeNamespace . '">'
            . implode('', $parts)
            . '</ns2:' . htmlspecialchars($operationName, ENT_QUOTES, 'UTF-8') . '>';
    }

    private function extractSoapEnvelope(string $payload): ?string
    {
        if (preg_match('/(<soap:Envelope[\s\S]*<\/soap:Envelope>)/i', $payload, $m)) {
            return $m[1];
        }
        if (preg_match('/(<SOAP-ENV:Envelope[\s\S]*<\/SOAP-ENV:Envelope>)/i', $payload, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function xmlToArray(string $xmlPayload): array
    {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlPayload, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return ['raw_xml' => $xmlPayload];
        }
        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        $arr = json_decode((string) $json, true);
        return is_array($arr) ? $arr : ['raw_xml' => $xmlPayload];
    }

    /**
     * @param mixed $response
     * @return array<string,mixed>
     */
    private function normalizeSoapResponse($response): array
    {
        if (is_object($response)) {
            $response = json_decode(json_encode($response, JSON_UNESCAPED_UNICODE), true);
        }
        if (!is_array($response)) {
            return ['payload' => $response];
        }
        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildIntercomBasePayload(string $cnjDigits): array
    {
        return [
            'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
            'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
            'numeroProcesso' => $cnjDigits,
            'movimentos' => true,
            'incluirCabecalho' => true,
            'incluirDocumentos' => false,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildConsultarProcessoParamVariants(string $cnjDigits): array
    {
        $base = $this->buildIntercomBasePayload($cnjDigits);

        return [
            $base,
            ['consultarProcesso' => $base],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildConsultarAlteracaoParamVariants(string $cnjDigits): array
    {
        $base = [
            'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
            'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
            'numeroProcesso' => $cnjDigits,
        ];

        return [
            $base,
            ['consultarAlteracao' => $base],
        ];
    }

    private function assertIntercomCredentials(): void
    {
        $id = trim((string) ($this->config['consultante_id'] ?? ''));
        $senha = trim((string) ($this->config['consultante_senha'] ?? ''));
        if ($id === '' || $senha === '') {
            throw new RuntimeException(
                'Credenciais MNI ausentes. Configure MNI_CONSULTANTE_ID e MNI_CONSULTANTE_SENHA no ambiente.'
            );
        }
    }

    private function resolveProbeNumeroProcesso(): string
    {
        $probe = trim((string) ($this->config['probe_numero_processo'] ?? ''));
        $digits = preg_replace('/\D+/', '', $probe) ?? '';
        if (strlen($digits) === 20) {
            return $digits;
        }

        // Número sintético para validar credencial/operação com baixo custo.
        return '00000000000000000000';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function debugLog(string $runId, string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $path = dirname(__DIR__, 2) . '/debug-f5d219.log';
        $payload = [
            'sessionId' => 'f5d219',
            'runId' => $runId,
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }

    /**
     * @return list<array{wsdl:string,location:string}>
     */
    private function resolveEndpointTargets(): array
    {
        $targets = [];
        $wsdl = trim((string) ($this->config['wsdl'] ?? ''));
        $location = trim((string) ($this->config['location'] ?? ''));
        if ($wsdl !== '') {
            $targets[] = ['wsdl' => $wsdl, 'location' => $location];
        }

        $consultaWsdl = trim((string) ($this->config['consulta_wsdl'] ?? ''));
        $consultaLocation = trim((string) ($this->config['consulta_location'] ?? ''));
        if ($consultaWsdl !== '') {
            $alreadyIncluded = false;
            foreach ($targets as $target) {
                if ($target['wsdl'] === $consultaWsdl && $target['location'] === $consultaLocation) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) {
                $targets[] = ['wsdl' => $consultaWsdl, 'location' => $consultaLocation];
            }
        }

        // Regra solicitada: se consultar no 1º grau e vier vazio, tentar 2º grau.
        // Para isso, quando só houver endpoint de 1º grau, derivamos automaticamente o equivalente de 2º grau.
        if (count($targets) === 1) {
            $autoSecondDegree = $this->deriveSecondDegreeTarget($targets[0]);
            if ($autoSecondDegree !== null) {
                $targets[] = $autoSecondDegree;
            }
        }

        return $targets;
    }

    /**
     * @param array{wsdl:string,location:string} $target
     * @return array{wsdl:string,location:string}|null
     */
    private function deriveSecondDegreeTarget(array $target): ?array
    {
        $wsdl = trim((string) ($target['wsdl'] ?? ''));
        $location = trim((string) ($target['location'] ?? ''));
        if ($wsdl === '') {
            return null;
        }

        // Só deriva quando o endpoint atual aparenta ser 1º grau (/pje/) e ainda não é /pje2/.
        if (strpos($wsdl, '/pje2/') !== false || strpos($wsdl, '/pje/') === false) {
            return null;
        }

        $derivedWsdl = str_replace('/pje/', '/pje2/', $wsdl);
        $derivedLocation = $location !== '' ? str_replace('/pje/', '/pje2/', $location) : '';

        if ($derivedWsdl === $wsdl) {
            return null;
        }

        return [
            'wsdl' => $derivedWsdl,
            'location' => $derivedLocation,
        ];
    }

    /**
     * @param array<string,mixed> $merged
     */
    private function isResultEffectivelyEmpty(array $merged): bool
    {
        foreach (['consultar_processo', 'consultar_alteracao'] as $key) {
            $part = $merged[$key] ?? null;
            if (is_array($part) && $part !== []) {
                return false;
            }
            if (!is_array($part) && $part !== null && $part !== '') {
                return false;
            }
        }
        return true;
    }

    private function detectTribunalCodeFromCnj(string $cnjDigits): ?string
    {
        if (strlen($cnjDigits) !== 20) {
            return null;
        }
        return substr($cnjDigits, 14, 2);
    }

    private function normalizeTribunalCode(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }
        return str_pad(substr($digits, -2), 2, '0', STR_PAD_LEFT);
    }

    private function hasTribunalMismatch(string $cnjDigits): bool
    {
        $detected = $this->detectTribunalCodeFromCnj($cnjDigits);
        $configured = $this->normalizeTribunalCode((string) ($this->config['tribunal_code'] ?? ''));
        if ($detected === null || $configured === null) {
            return false;
        }
        return $detected !== $configured;
    }
}
