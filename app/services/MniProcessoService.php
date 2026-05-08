<?php
declare(strict_types=1);

require_once __DIR__ . '/MniProcessoResponseMapper.php';

final class MniProcessoService
{
    /** @var array<string,mixed> */
    private array $config;
    /** @var list<array<string,mixed>> */
    private array $operationTrace = [];

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
        $this->operationTrace = [];
        $endpoints = $this->resolveEndpointTargets();
        $lastRuntimeError = null;
        $selected = null;
        $debugTrace = [];
        $merged = [
            'consultar_processo' => [],
            'consultar_alteracao' => [],
        ];
        $numeroVariants = [$cnjDigits];
        $formattedCnj = $this->formatCnjDigits($cnjDigits);
        if ($formattedCnj !== null && !in_array($formattedCnj, $numeroVariants, true)) {
            $numeroVariants[] = $formattedCnj;
        }
        $enableConsultarProcesso = $this->isConsultarProcessoEnabled();

        foreach ($endpoints as $target) {
            foreach ($numeroVariants as $numeroVariant) {
                try {
                    $attempt = [
                        'wsdl' => (string) ($target['wsdl'] ?? ''),
                        'location' => (string) ($target['location'] ?? ''),
                        'numero_processo_enviado' => $numeroVariant,
                        'consultar_processo' => 'not_run',
                        'consultar_alteracao' => 'not_run',
                        'resultado_vazio' => null,
                        'erro' => null,
                    ];
                // #region agent log
                $this->debugLog('diag-run-1', 'H10', 'MniProcessoService::consultarPorNumeroCnj:50', 'Tentando endpoint + formato de número', [
                    'wsdl' => (string) ($target['wsdl'] ?? ''),
                    'location' => (string) ($target['location'] ?? ''),
                    'numero_processo_enviado' => $numeroVariant,
                ]);
                // #endregion
                $consultarProcessoPayload = [];
                if ($enableConsultarProcesso) {
                    $consultarProcessoPayload = $this->runCurlOnlyOperation(
                        $target,
                        'consultarProcesso',
                        $this->buildConsultarProcessoParamVariants($numeroVariant)
                    );
                    $attempt['consultar_processo'] = is_array($consultarProcessoPayload) && $consultarProcessoPayload === [] ? 'empty_array' : 'ok';
                } else {
                    $attempt['consultar_processo'] = 'disabled_by_config';
                }

                $alteracaoPayload = null;
                $consultarProcessoEstaVazio = !is_array($consultarProcessoPayload) || $consultarProcessoPayload === [];
                if ($consultarProcessoEstaVazio) {
                    try {
                        $alteracaoPayload = $this->runCurlOnlyOperation(
                            $target,
                            'consultarAlteracao',
                            $this->buildConsultarAlteracaoParamVariants($numeroVariant)
                        );
                        $attempt['consultar_alteracao'] = is_array($alteracaoPayload) && $alteracaoPayload === [] ? 'empty_array' : 'ok';
                    } catch (RuntimeException $e) {
                        $alteracaoPayload = ['aviso' => $e->getMessage()];
                        $attempt['consultar_alteracao'] = 'runtime_error';
                        $attempt['erro'] = $e->getMessage();
                    }
                } else {
                    $attempt['consultar_alteracao'] = 'skipped_not_needed';
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

                $attempt['resultado_vazio'] = $this->isResultEffectivelyEmpty($merged);
                $debugTrace[] = $attempt;

                if (!$attempt['resultado_vazio']) {
                    break 2;
                }
                } catch (RuntimeException $e) {
                    $debugTrace[] = [
                        'wsdl' => (string) ($target['wsdl'] ?? ''),
                        'location' => (string) ($target['location'] ?? ''),
                        'numero_processo_enviado' => $numeroVariant,
                        'consultar_processo' => 'runtime_error',
                        'consultar_alteracao' => 'not_run',
                        'resultado_vazio' => null,
                        'erro' => $e->getMessage(),
                    ];
                // #region agent log
                $this->debugLog('diag-run-1', 'H4', 'MniProcessoService::consultarPorNumeroCnj:87', 'Erro no endpoint durante consulta', [
                    'wsdl' => (string) ($target['wsdl'] ?? ''),
                    'numero_processo_enviado' => $numeroVariant,
                    'error' => $e->getMessage(),
                ]);
                // #endregion
                $lastRuntimeError = $e;
                }
            }
        }

        if ($selected === null && $lastRuntimeError instanceof RuntimeException) {
            throw $lastRuntimeError;
        }

        $businessError = $this->extractBusinessErrorMessage($merged);
        if ($businessError !== null) {
            // #region agent log
            $this->debugLog('post-fix-run-1', 'H12', 'MniProcessoService::consultarPorNumeroCnj:140', 'Erro de negócio retornado pelo MNI', [
                'message' => $businessError,
                'selected_wsdl' => (string) ($selected['wsdl'] ?? ''),
            ]);
            // #endregion
            throw new RuntimeException($businessError);
        }

        $output = MniProcessoResponseMapper::mapToOutput($cnjDigits, $merged);
        $output['meta_consulta'] = [
            'tribunal_detectado_cnj' => $this->detectTribunalCodeFromCnj($cnjDigits),
            'tribunal_configurado' => $this->normalizeTribunalCode((string) ($this->config['tribunal_code'] ?? '')),
            'divergencia_tribunal' => $this->hasTribunalMismatch($cnjDigits),
            'fonte_wsdl' => (string) ($selected['wsdl'] ?? ''),
            'fonte_location' => (string) ($selected['location'] ?? ''),
            'fallback_multibase_ativo' => count($endpoints) > 1,
            'trace_tentativas' => $debugTrace,
            'consultar_processo_habilitada' => $enableConsultarProcesso,
            'credenciais_presentes' => [
                'id_len' => strlen(trim((string) ($this->config['consultante_id'] ?? ''))),
                'senha_len' => strlen(trim((string) ($this->config['consultante_senha'] ?? ''))),
            ],
            'trace_operacoes' => $this->operationTrace,
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
     * Executa operação de consulta em modo cURL-only para evitar falhas de host no SoapClient.
     *
     * @param array{wsdl:string,location:string} $target
     * @param list<array<string,mixed>> $paramVariants
     * @return array<string,mixed>
     */
    private function runCurlOnlyOperation(array $target, string $operationName, array $paramVariants): array
    {
        $lastError = null;
        foreach ($paramVariants as $index => $params) {
            $numeroParam = $this->extractNumeroProcessoFromParams($params);
            // #region agent log
            $this->debugLog('diag-run-2', 'H14', 'MniProcessoService::runCurlOnlyOperation:190', 'Executando variante de payload MNI', [
                'operation' => $operationName,
                'variant_index' => $index,
                'numero_digits_len' => $numeroParam !== null ? strlen(preg_replace('/\D+/', '', $numeroParam) ?? '') : null,
                'numero_has_punctuation' => $numeroParam !== null ? ((preg_replace('/\d+/', '', $numeroParam) ?? '') !== '') : null,
            ]);
            // #endregion
            $result = $this->callOperationViaCurlWithTarget($target, $operationName, $params);
            if ($result !== null) {
                $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
                $debug = is_array($result['debug'] ?? null) ? $result['debug'] : [];
                $this->operationTrace[] = [
                    'operation' => $operationName,
                    'param_keys' => array_keys($params),
                    'normalized_empty' => $payload === [],
                    'raw_has_xsi_nil' => (bool) (($debug['soap_debug']['xsi_nil'] ?? false)),
                    'raw_headers_snippet' => 'curl_only',
                    'raw_response_snippet' => (string) ($debug['fallback_body_snippet'] ?? ''),
                    'soap_body_child' => (string) (($debug['soap_debug']['body_child'] ?? '')),
                    'soap_body_is_nil' => (bool) (($debug['soap_debug']['xsi_nil'] ?? false)),
                    'soap_body_text' => (string) (($debug['soap_debug']['body_text'] ?? '')),
                ];
                return $payload;
            }
            $lastError = 'sem resposta válida via cURL';
        }

        throw new RuntimeException('Falha na operação SOAP MNI via cURL (' . $operationName . '): ' . ($lastError ?? 'erro desconhecido'));
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

    /**
     * Diagnóstico leve: executa apenas consultarAlteracao.
     *
     * @return array<string,mixed>
     */
    public function consultarAlteracaoSomente(string $numero): array
    {
        $cnjDigits = preg_replace('/\D+/', '', $numero) ?? '';
        if ($cnjDigits === '' || strlen($cnjDigits) !== 20) {
            throw new InvalidArgumentException('Número CNJ inválido. Informe os 20 dígitos.');
        }

        $this->assertIntercomCredentials();
        $this->operationTrace = [];
        $targets = $this->resolveEndpointTargets();

        $lastError = null;
        foreach ($targets as $target) {
            try {
                $payload = $this->runCurlOnlyOperation(
                    $target,
                    'consultarAlteracao',
                    $this->buildConsultarAlteracaoParamVariants($cnjDigits)
                );

                return [
                    'numero_consultado' => $cnjDigits,
                    'fonte_wsdl' => (string) ($target['wsdl'] ?? ''),
                    'fonte_location' => (string) ($target['location'] ?? ''),
                    'dados' => $payload,
                    'trace_operacoes' => $this->operationTrace,
                ];
            } catch (RuntimeException $e) {
                $lastError = $e;
            }
        }

        if ($lastError instanceof RuntimeException) {
            throw $lastError;
        }

        throw new RuntimeException('Falha ao executar consultarAlteracao no modo diagnóstico.');
    }

    /**
     * Diagnóstico isolado: executa apenas consultarProcesso.
     *
     * @return array<string,mixed>
     */
    public function consultarProcessoSomente(string $numero): array
    {
        $cnjDigits = preg_replace('/\D+/', '', $numero) ?? '';
        if ($cnjDigits === '' || strlen($cnjDigits) !== 20) {
            throw new InvalidArgumentException('Número CNJ inválido. Informe os 20 dígitos.');
        }

        $this->assertIntercomCredentials();
        $this->operationTrace = [];
        $targets = $this->resolveEndpointTargets();
        $numeroVariants = [$cnjDigits];
        $formattedCnj = $this->formatCnjDigits($cnjDigits);
        if ($formattedCnj !== null && !in_array($formattedCnj, $numeroVariants, true)) {
            $numeroVariants[] = $formattedCnj;
        }

        $attempts = [];
        $lastError = null;
        foreach ($targets as $target) {
            foreach ($numeroVariants as $numeroVariant) {
                try {
                    $payload = $this->runCurlOnlyOperation(
                        $target,
                        'consultarProcesso',
                        $this->buildConsultarProcessoParamVariants($numeroVariant)
                    );

                    $attempts[] = [
                        'wsdl' => (string) ($target['wsdl'] ?? ''),
                        'location' => (string) ($target['location'] ?? ''),
                        'numero_processo_enviado' => $numeroVariant,
                        'resultado' => (is_array($payload) && $payload === []) ? 'empty_array' : 'ok',
                    ];

                    if (!(is_array($payload) && $payload === [])) {
                        return [
                            'numero_consultado' => $cnjDigits,
                            'fonte_wsdl' => (string) ($target['wsdl'] ?? ''),
                            'fonte_location' => (string) ($target['location'] ?? ''),
                            'dados' => $payload,
                            'trace_tentativas' => $attempts,
                            'trace_operacoes' => $this->operationTrace,
                        ];
                    }
                } catch (RuntimeException $e) {
                    $lastError = $e;
                    $attempts[] = [
                        'wsdl' => (string) ($target['wsdl'] ?? ''),
                        'location' => (string) ($target['location'] ?? ''),
                        'numero_processo_enviado' => $numeroVariant,
                        'resultado' => 'runtime_error',
                        'erro' => $e->getMessage(),
                    ];
                }
            }
        }

        if ($lastError instanceof RuntimeException) {
            throw $lastError;
        }

        return [
            'numero_consultado' => $cnjDigits,
            'fonte_wsdl' => (string) ($targets[0]['wsdl'] ?? ''),
            'fonte_location' => (string) ($targets[0]['location'] ?? ''),
            'dados' => [],
            'trace_tentativas' => $attempts,
            'trace_operacoes' => $this->operationTrace,
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
                    $normalized = $this->normalizeSoapResponse($response);
                    $this->operationTrace[] = [
                        'operation' => $operationName,
                        'param_keys' => array_keys($params),
                        'normalized_empty' => is_array($normalized) && $normalized === [],
                        'raw_has_xsi_nil' => null,
                        'raw_headers_snippet' => 'soap_trace_disabled',
                        'raw_response_snippet' => '',
                    ];
                    return $normalized;
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
                            $fallbackPayload = is_array($fallback['payload'] ?? null) ? $fallback['payload'] : [];
                            $fallbackDebug = is_array($fallback['debug'] ?? null) ? $fallback['debug'] : [];
                            $this->operationTrace[] = [
                                'operation' => $operationName,
                                'param_keys' => array_keys($params),
                                'normalized_empty' => $fallbackPayload === [],
                                'raw_has_xsi_nil' => (bool) (($fallbackDebug['soap_debug']['xsi_nil'] ?? false)),
                                'raw_headers_snippet' => 'fallback_curl',
                                'raw_response_snippet' => (string) ($fallbackDebug['fallback_body_snippet'] ?? ''),
                                'soap_body_child' => (string) (($fallbackDebug['soap_debug']['body_child'] ?? '')),
                                'soap_body_is_nil' => (bool) (($fallbackDebug['soap_debug']['xsi_nil'] ?? false)),
                                'soap_body_text' => (string) (($fallbackDebug['soap_debug']['body_text'] ?? '')),
                            ];
                            return $fallbackPayload;
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
        $target = [
            'wsdl' => (string) ($this->config['wsdl'] ?? ''),
            'location' => (string) ($this->config['location'] ?? ''),
        ];
        return $this->callOperationViaCurlWithTarget($target, $operationName, $params);
    }

    /**
     * @param array{wsdl:string,location:string} $target
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    private function callOperationViaCurlWithTarget(array $target, string $operationName, array $params): ?array
    {
        $location = trim((string) ($target['location'] ?? ''));
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

        $parsed = $this->parseSoapEnvelopeBody($xmlPayload);
        $payload = is_array($parsed['payload'] ?? null) ? $parsed['payload'] : [];
        $debug = is_array($parsed['debug'] ?? null) ? $parsed['debug'] : [];

        return [
            'payload' => $payload,
            'debug' => [
                'fallback_headers_snippet' => substr($raw, 0, $headerSize),
                'fallback_body_snippet' => substr($body, 0, 600),
                'soap_debug' => $debug,
            ],
        ];
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
     * @return array{payload: array<string,mixed>, debug: array<string,mixed>}
     */
    private function parseSoapEnvelopeBody(string $xmlPayload): array
    {
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return [
                'payload' => $this->xmlToArray($xmlPayload),
                'debug' => ['parse_mode' => 'dom_extension_unavailable_simplexml_fallback'],
            ];
        }

        $doc = new DOMDocument();
        $ok = @$doc->loadXML($xmlPayload);
        if (!$ok) {
            return [
                'payload' => $this->xmlToArray($xmlPayload),
                'debug' => ['parse_mode' => 'simplexml_fallback'],
            ];
        }

        $xpath = new DOMXPath($doc);
        $bodyList = $xpath->query('//*[local-name()="Body"]');
        if (!$bodyList || $bodyList->length === 0) {
            return [
                'payload' => $this->xmlToArray($xmlPayload),
                'debug' => ['parse_mode' => 'no_body_fallback'],
            ];
        }

        $body = $bodyList->item(0);
        $firstElement = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $firstElement = $child;
                break;
            }
        }

        if (!$firstElement instanceof DOMElement) {
            return [
                'payload' => [],
                'debug' => ['parse_mode' => 'empty_body'],
            ];
        }

        $xsiNil = strtolower((string) $firstElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil')) === 'true';
        $payloadXml = $doc->saveXML($firstElement) ?: '';
        $payload = $payloadXml !== '' ? $this->xmlToArray($payloadXml) : [];

        return [
            'payload' => $payload,
            'debug' => [
                'parse_mode' => 'dom_body_first_child',
                'body_child' => $firstElement->localName,
                'xsi_nil' => $xsiNil,
                'body_text' => trim($firstElement->textContent ?? ''),
            ],
        ];
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
    private function buildIntercomBasePayload(string $numeroProcesso): array
    {
        $includeMovimentos = strtolower((string) ($this->config['include_movimentos'] ?? '0'));
        $includeMovimentosBool = in_array($includeMovimentos, ['1', 'true', 'yes', 'on'], true);
        return [
            'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
            'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
            'numeroProcesso' => $numeroProcesso,
            'movimentos' => $includeMovimentosBool,
            'incluirCabecalho' => true,
            'incluirDocumentos' => false,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildConsultarProcessoParamVariants(string $numeroProcesso): array
    {
        $base = $this->buildIntercomBasePayload($numeroProcesso);

        return [
            $base,
            ['consultarProcesso' => $base],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildConsultarAlteracaoParamVariants(string $numeroProcesso): array
    {
        $base = [
            'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
            'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
            'numeroProcesso' => $numeroProcesso,
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

        usort($targets, function (array $a, array $b): int {
            return $this->endpointPriority($a) <=> $this->endpointPriority($b);
        });

        // #region agent log
        $this->debugLog('diag-run-2', 'H13', 'MniProcessoService::resolveEndpointTargets:801', 'Lista final de endpoints resolvidos', [
            'targets' => $targets,
            'wsdl_config' => (string) ($this->config['wsdl'] ?? ''),
            'consulta_wsdl_config' => (string) ($this->config['consulta_wsdl'] ?? ''),
        ]);
        // #endregion

        return $targets;
    }

    /**
     * @param array{wsdl:string,location:string} $target
     */
    private function endpointPriority(array $target): int
    {
        $wsdl = strtolower((string) ($target['wsdl'] ?? ''));
        $location = strtolower((string) ($target['location'] ?? ''));
        $haystack = $wsdl . ' ' . $location;

        if (strpos($haystack, '/pje2/') !== false) {
            return 20;
        }
        if (strpos($haystack, '/pje/') !== false) {
            return 10;
        }
        return 30;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function extractNumeroProcessoFromParams(array $params): ?string
    {
        if (isset($params['numeroProcesso']) && is_scalar($params['numeroProcesso'])) {
            return (string) $params['numeroProcesso'];
        }
        foreach ($params as $value) {
            if (is_array($value) && isset($value['numeroProcesso']) && is_scalar($value['numeroProcesso'])) {
                return (string) $value['numeroProcesso'];
            }
        }
        return null;
    }

    private function formatCnjDigits(string $digits): ?string
    {
        if (strlen($digits) !== 20) {
            return null;
        }
        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4)
        );
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

    private function isConsultarProcessoEnabled(): bool
    {
        $raw = strtolower(trim((string) ($this->config['enable_consultar_processo'] ?? '0')));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string,mixed> $merged
     */
    private function extractBusinessErrorMessage(array $merged): ?string
    {
        foreach (['consultar_processo', 'consultar_alteracao'] as $key) {
            $part = $merged[$key] ?? null;
            if (!is_array($part) || $part === []) {
                continue;
            }

            $sucesso = $part['sucesso'] ?? null;
            $mensagem = isset($part['mensagem']) ? trim((string) $part['mensagem']) : '';
            if ($mensagem === '') {
                continue;
            }

            if (is_string($sucesso)) {
                $s = strtolower(trim($sucesso));
                if (in_array($s, ['false', '0', 'nao', 'não'], true)) {
                    return $mensagem;
                }
            } elseif (is_bool($sucesso) && $sucesso === false) {
                return $mensagem;
            } elseif ($sucesso === null && stripos($mensagem, 'erro') !== false) {
                return $mensagem;
            }
        }

        return null;
    }

}
