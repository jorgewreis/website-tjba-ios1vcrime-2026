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

        $this->assertIntercomCredentials();

        $soapClient = $this->buildClient();
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
            // Nem todo provedor habilita operação de alteração para o mesmo perfil.
            $alteracaoPayload = ['aviso' => $e->getMessage()];
        }

        $merged = [
            'consultar_processo' => $consultarProcessoPayload,
            'consultar_alteracao' => $alteracaoPayload,
        ];

        return MniProcessoResponseMapper::mapToOutput($cnjDigits, $merged);
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

        $avisosPayload = null;
        if (!$quick) {
            $base = [
                'idConsultante' => (string) ($this->config['consultante_id'] ?? ''),
                'senhaConsultante' => (string) ($this->config['consultante_senha'] ?? ''),
                'dataReferencia' => date('Y-m-d\TH:i:s'),
            ];

            $avisosPayload = $this->runSoapOperation(
                $soapClient,
                ['consultarAvisosPendentes', 'ConsultarAvisosPendentes'],
                [
                    $base,
                    ['consultarAvisosPendentes' => $base],
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
            'auth_check_operation' => $quick ? 'wsdl_only' : 'consultarAvisosPendentes',
            'auth_check_response' => $quick ? ['info' => 'Modo rápido: apenas carga WSDL e introspecção de funções.'] : $avisosPayload,
            'timestamp' => date(DATE_ATOM),
        ];
    }

    private function buildClient(): SoapClient
    {
        $wsdl = trim((string) ($this->config['wsdl'] ?? ''));
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

        $location = trim((string) ($this->config['location'] ?? ''));
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
                    return $this->normalizeSoapResponse($response);
                } catch (SoapFault $e) {
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
}
