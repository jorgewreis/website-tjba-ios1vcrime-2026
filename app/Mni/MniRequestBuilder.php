<?php
declare(strict_types=1);

/**
 * Monta envelopes SOAP das operacoes do MNI (CNJ).
 *
 * Versoes suportadas:
 *  - 2.2.2 / 2.2.3 (legado): parametros planos no body, namespaces
 *    "servico-intercomunicacao-2.2.x" e "tipos-servico-intercomunicacao-2.2.x".
 *  - 3.0.0 (novo): parametros wrapped em <requisicaoConsultarProcesso>,
 *    credencial em <consultante> via xsd:choice (token | autenticacaoSimples |
 *    autenticacaoCertificada), namespaces canonicos "mni/v300/...". Fonte:
 *    api/mni3/xsd_wsdl/servico-intercomunicacao.wsdl e intercomunicacao.xsd.
 *    O modo `token` e' ativado passando $options['auth_token'] em buildConsultarProcesso
 *    ou buildConsultarAlteracao; quando ausente, cai em autenticacaoSimples.
 *
 * Por que XML literal em vez de SoapClient nativo? Historicamente o SoapClient
 * do PHP trata mal os types derivados do MNI (xsd:choice e namespaces
 * mesclados); cURL + envelope literal e mais confiavel.
 */
final class MniRequestBuilder
{
    public const VERSION_2_2_2 = '2.2.2';
    public const VERSION_2_2_3 = '2.2.3';
    public const VERSION_3_0_0 = '3.0.0';

    private const NS_BY_VERSION = [
        self::VERSION_2_2_2 => [
            'soap'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            'inter' => 'http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/',
            'tipos' => 'http://www.cnj.jus.br/tipos-servico-intercomunicacao-2.2.2',
        ],
        self::VERSION_2_2_3 => [
            'soap'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            'inter' => 'http://www.cnj.jus.br/servico-intercomunicacao-2.2.3/',
            'tipos' => 'http://www.cnj.jus.br/tipos-servico-intercomunicacao-2.2.3',
        ],
        self::VERSION_3_0_0 => [
            'soap'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            // tipos-servico-intercomunicacao (escopo dos elementos requisicao*/resposta*)
            'tipos' => 'http://www.cnj.jus.br/mni/v300/tipos-servico-intercomunicacao',
            // intercomunicacao (escopo de autenticacaoSimples, polo, etc.)
            'inter' => 'http://www.cnj.jus.br/mni/v300/intercomunicacao',
        ],
    ];

    /**
     * Base oficial da SOAPAction na v3.0. Cada operacao concatena seu nome.
     * Fonte: api/mni3/xsd_wsdl/servico-intercomunicacao.wsdl (binding mniSOAP).
     */
    private const SOAPACTION_BASE_V3 = 'http://www.cnj.jus.br/mni/v300/';

    /**
     * @param array{
     *   incluir_cabecalho?: bool,
     *   incluir_partes?: bool,
     *   incluir_enderecos?: bool,
     *   incluir_movimentos?: bool,
     *   incluir_documentos?: bool,
     *   data_inicial?: ?string,
     *   data_final?: ?string,
     *   auth_token?: ?string,
     * } $options
     */
    public static function buildConsultarProcesso(
        string $numeroProcessoDigits20,
        string $idConsultante,
        string $senhaConsultante,
        string $version = self::VERSION_2_2_2,
        array $options = []
    ): string {
        if ($version === self::VERSION_3_0_0) {
            return self::buildConsultarProcessoV3(
                $numeroProcessoDigits20,
                $idConsultante,
                $senhaConsultante,
                $options
            );
        }
        return self::buildConsultarProcessoV2(
            $numeroProcessoDigits20,
            $idConsultante,
            $senhaConsultante,
            $version,
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildConsultarProcessoV2(
        string $numero,
        string $idConsultante,
        string $senhaConsultante,
        string $version,
        array $options
    ): string {
        $ns = self::NS_BY_VERSION[$version] ?? self::NS_BY_VERSION[self::VERSION_2_2_2];

        $incluirCabecalho  = self::boolOpt($options, 'incluir_cabecalho', true);
        $incluirDocumentos = self::boolOpt($options, 'incluir_documentos', false);
        $incluirMovimentos = self::boolOpt($options, 'incluir_movimentos', true);

        $id    = self::escape($idConsultante);
        $senha = self::escape($senhaConsultante);
        $num   = self::escape($numero);

        $cabFlag = $incluirCabecalho  ? 'true' : 'false';
        $movFlag = $incluirMovimentos ? 'true' : 'false';
        $docFlag = $incluirDocumentos ? 'true' : 'false';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="{$ns['soap']}" xmlns:int="{$ns['inter']}" xmlns:tip="{$ns['tipos']}">
  <soapenv:Header/>
  <soapenv:Body>
    <int:consultarProcesso>
      <tip:idConsultante>{$id}</tip:idConsultante>
      <tip:senhaConsultante>{$senha}</tip:senhaConsultante>
      <tip:numeroProcesso>{$num}</tip:numeroProcesso>
      <tip:movimentos>{$movFlag}</tip:movimentos>
      <tip:incluirCabecalho>{$cabFlag}</tip:incluirCabecalho>
      <tip:incluirDocumentos>{$docFlag}</tip:incluirDocumentos>
    </int:consultarProcesso>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Envelope v3.0 — wrapper requisicaoConsultarProcesso + <consultante> com
     * <autenticacaoSimples><usuario>/<senha>. Inclui flags granulares
     * (incluirPartes/incluirEnderecos) ausentes na v2.
     *
     * @param array<string, mixed> $options
     */
    private static function buildConsultarProcessoV3(
        string $numero,
        string $idConsultante,
        string $senhaConsultante,
        array $options
    ): string {
        $ns = self::NS_BY_VERSION[self::VERSION_3_0_0];

        $incluirCabecalho  = self::boolOpt($options, 'incluir_cabecalho', true);
        $incluirPartes     = self::boolOpt($options, 'incluir_partes', true);
        $incluirEnderecos  = self::boolOpt($options, 'incluir_enderecos', false);
        $incluirMovimentos = self::boolOpt($options, 'incluir_movimentos', true);
        $incluirDocumentos = self::boolOpt($options, 'incluir_documentos', false);

        $dataInicial = isset($options['data_inicial']) && $options['data_inicial'] !== ''
            ? (string) $options['data_inicial']
            : null;
        $dataFinal = isset($options['data_final']) && $options['data_final'] !== ''
            ? (string) $options['data_final']
            : null;

        $num     = self::escape($numero);

        $flagCab = $incluirCabecalho  ? 'true' : 'false';
        $flagPar = $incluirPartes     ? 'true' : 'false';
        $flagEnd = $incluirEnderecos  ? 'true' : 'false';
        $flagMov = $incluirMovimentos ? 'true' : 'false';
        $flagDoc = $incluirDocumentos ? 'true' : 'false';

        $dataInicialXml = $dataInicial !== null
            ? '      <tic:dataInicial>' . self::escape($dataInicial) . '</tic:dataInicial>' . "\n"
            : '';
        $dataFinalXml = $dataFinal !== null
            ? '      <tic:dataFinal>' . self::escape($dataFinal) . '</tic:dataFinal>' . "\n"
            : '';

        $consultanteXml = self::buildConsultanteV3($idConsultante, $senhaConsultante, $options);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="{$ns['soap']}" xmlns:tic="{$ns['tipos']}" xmlns:pji="{$ns['inter']}">
  <soapenv:Header/>
  <soapenv:Body>
    <tic:requisicaoConsultarProcesso>
{$consultanteXml}
      <tic:numeroProcesso>{$num}</tic:numeroProcesso>
{$dataInicialXml}{$dataFinalXml}      <tic:incluirCabecalho>{$flagCab}</tic:incluirCabecalho>
      <tic:incluirPartes>{$flagPar}</tic:incluirPartes>
      <tic:incluirEnderecos>{$flagEnd}</tic:incluirEnderecos>
      <tic:incluirMovimentos>{$flagMov}</tic:incluirMovimentos>
      <tic:incluirDocumentos>{$flagDoc}</tic:incluirDocumentos>
    </tic:requisicaoConsultarProcesso>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Monta o bloco <tic:consultante> da v3 respeitando o xsd:choice de tipoAutenticacao:
     *  - token (preferido quando disponivel; opt-in via $options['auth_token'])
     *  - autenticacaoSimples (usuario/senha) — fallback padrao
     *  - autenticacaoCertificada (assinatura ICP-Br) — NAO IMPLEMENTADO (TODO Fase 10)
     *
     * @param array<string, mixed> $options
     */
    private static function buildConsultanteV3(string $idConsultante, string $senhaConsultante, array $options): string
    {
        $token = isset($options['auth_token']) && is_string($options['auth_token']) && $options['auth_token'] !== ''
            ? (string) $options['auth_token']
            : null;

        if ($token !== null) {
            $tokenXml = self::escape($token);
            return "      <tic:consultante>\n"
                . "        <pji:token>{$tokenXml}</pji:token>\n"
                . "      </tic:consultante>";
        }

        $usuario = self::escape($idConsultante);
        $senha   = self::escape($senhaConsultante);
        return "      <tic:consultante>\n"
            . "        <pji:autenticacaoSimples>\n"
            . "          <pji:usuario>{$usuario}</pji:usuario>\n"
            . "          <pji:senha>{$senha}</pji:senha>\n"
            . "        </pji:autenticacaoSimples>\n"
            . "      </tic:consultante>";
    }

    /**
     * Envelope da operacao consultarAlteracao (polling barato de mudancas no processo).
     *
     * v3.0.0 (XSD oficial): <tic:requisicaoConsultarAlteracao> com <consultante> +
     * <numeroProcesso>. Resposta traz hashes de cabecalho/movimentacoes/documentos.
     *
     * v2.2.x: o XSD/WSDL v2 nao esta versionado neste repo; usamos a forma plana
     * analoga a consultarProcesso v2 (idConsultante/senhaConsultante/numeroProcesso),
     * presumindo que o servidor implementa a operacao do mesmo modo. Caso o tribunal
     * recuse, basta migrar para a v3 — que ja' funciona.
     *
     * @param array{
     *   auth_token?: ?string,
     *   data_referencia?: ?string,
     * } $options
     */
    public static function buildConsultarAlteracao(
        string $numeroProcessoDigits20,
        string $idConsultante,
        string $senhaConsultante,
        string $version = self::VERSION_3_0_0,
        array $options = []
    ): string {
        if ($version === self::VERSION_3_0_0) {
            return self::buildConsultarAlteracaoV3(
                $numeroProcessoDigits20,
                $idConsultante,
                $senhaConsultante,
                $options
            );
        }
        return self::buildConsultarAlteracaoV2(
            $numeroProcessoDigits20,
            $idConsultante,
            $senhaConsultante,
            $version
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildConsultarAlteracaoV3(
        string $numero,
        string $idConsultante,
        string $senhaConsultante,
        array $options
    ): string {
        $ns  = self::NS_BY_VERSION[self::VERSION_3_0_0];
        $num = self::escape($numero);

        $consultanteXml = self::buildConsultanteV3($idConsultante, $senhaConsultante, $options);

        // dataReferencia nao consta do XSD oficial v300 (apenas consultante + numeroProcesso);
        // mantemos a chave $options['data_referencia'] apenas para forward-compat caso o
        // tribunal aceite extensao. Emitir <tic:dataReferencia> so quando explicitamente passado.
        $dataRef = isset($options['data_referencia']) && is_string($options['data_referencia']) && $options['data_referencia'] !== ''
            ? (string) $options['data_referencia']
            : null;
        $dataRefXml = $dataRef !== null
            ? "\n      <tic:dataReferencia>" . self::escape($dataRef) . '</tic:dataReferencia>'
            : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="{$ns['soap']}" xmlns:tic="{$ns['tipos']}" xmlns:pji="{$ns['inter']}">
  <soapenv:Header/>
  <soapenv:Body>
    <tic:requisicaoConsultarAlteracao>
{$consultanteXml}
      <tic:numeroProcesso>{$num}</tic:numeroProcesso>{$dataRefXml}
    </tic:requisicaoConsultarAlteracao>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private static function buildConsultarAlteracaoV2(
        string $numero,
        string $idConsultante,
        string $senhaConsultante,
        string $version
    ): string {
        $ns    = self::NS_BY_VERSION[$version] ?? self::NS_BY_VERSION[self::VERSION_2_2_2];
        $id    = self::escape($idConsultante);
        $senha = self::escape($senhaConsultante);
        $num   = self::escape($numero);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="{$ns['soap']}" xmlns:int="{$ns['inter']}" xmlns:tip="{$ns['tipos']}">
  <soapenv:Header/>
  <soapenv:Body>
    <int:consultarAlteracao>
      <tip:idConsultante>{$id}</tip:idConsultante>
      <tip:senhaConsultante>{$senha}</tip:senhaConsultante>
      <tip:numeroProcesso>{$num}</tip:numeroProcesso>
    </int:consultarAlteracao>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * SOAPAction esperada pelo binding MNI:
     *  - v3.0: URL completa http://www.cnj.jus.br/mni/v300/<operacao>
     *  - v2.x: nome simples da operacao (alguns tribunais aceitam vazio)
     */
    public static function soapActionFor(string $operation, string $version = self::VERSION_2_2_2): string
    {
        if ($version === self::VERSION_3_0_0) {
            return self::SOAPACTION_BASE_V3 . $operation;
        }
        return $operation;
    }

    public static function supportsVersion(string $version): bool
    {
        return isset(self::NS_BY_VERSION[$version]);
    }

    /**
     * @return list<string>
     */
    public static function supportedVersions(): array
    {
        return array_keys(self::NS_BY_VERSION);
    }

    /**
     * Namespaces XML para registro em xpath/parser.
     *
     * @return array{soap: string, inter: string, tipos: string}
     */
    public static function namespacesFor(string $version): array
    {
        return self::NS_BY_VERSION[$version] ?? self::NS_BY_VERSION[self::VERSION_2_2_2];
    }

    private static function boolOpt(array $options, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }
        return (bool) $options[$key];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
