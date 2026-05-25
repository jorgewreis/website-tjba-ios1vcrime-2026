<?php
declare(strict_types=1);

/**
 * Parsea o XML SOAP retornado pelo MNI em arrays PHP planos.
 *
 * Suporta dois shapes de resposta:
 *  - v2.2.x (legado): <consultarProcessoResponse><sucesso/><mensagem/><processo/></>
 *  - v3.0.0 (atual):  <respostaConsultarProcesso>
 *                       <recibo>
 *                         <sucesso/>
 *                         <mensagens>...</mensagens>   (lista)
 *                         <reciboToken>...</reciboToken> (opcional)
 *                       </recibo>
 *                       <processo>...</processo>
 *                     </respostaConsultarProcesso>
 *
 * Detecta SOAP Fault e devolve estrutura uniforme em parseEnvelope().
 * Nao interpreta semanticamente: a traducao para o shape final
 * fica no MniProcessoMapper (apenas XML -> array aqui).
 */
final class MniResponseParser
{
    /**
     * @return array{
     *   ok: bool,
     *   sucesso: ?bool,
     *   mensagem: ?string,
     *   mensagens: list<string>,
     *   recibo_token: ?array{token: string, data_expiracao: ?string},
     *   processo: ?array<string, mixed>,
     *   fault: ?array{code: string, string: string}
     * }
     */
    public static function parseEnvelope(string $xml, string $version = MniRequestBuilder::VERSION_2_2_2): array
    {
        $previous = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($sx === false) {
            return self::empty('XML invalido recebido do MNI.');
        }

        $sx->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');

        $faults = $sx->xpath('//s:Fault');
        if (!empty($faults)) {
            $fault = $faults[0];
            $code = (string) ($fault->faultcode ?? $fault->Code->Value ?? '');
            $msg  = (string) ($fault->faultstring ?? $fault->Reason->Text ?? '');
            return [
                'ok'           => false,
                'sucesso'      => false,
                'mensagem'     => $msg !== '' ? $msg : 'SOAP Fault sem detalhes.',
                'mensagens'    => $msg !== '' ? [$msg] : [],
                'recibo_token' => null,
                'processo'     => null,
                'fault'        => ['code' => $code, 'string' => $msg],
            ];
        }

        // Localiza o elemento de resposta (varia entre versoes).
        $resp = self::locateResponse($sx, $version);
        if ($resp === null) {
            return self::empty('Resposta SOAP nao contem elemento de resposta esperado.');
        }

        // v3: status vem dentro de <recibo>. v2: status vem direto no body da resposta.
        $reciboNodes = $resp->xpath('.//*[local-name()="recibo"]');
        if (!empty($reciboNodes)) {
            $recibo = $reciboNodes[0];
            $sucesso = self::firstChildString($recibo, 'sucesso');
            $mensagens = self::collectChildStrings($recibo, 'mensagens');
            // Fallback: alguns servidores podem usar "mensagem" singular dentro do recibo.
            if (empty($mensagens)) {
                $mensagens = self::collectChildStrings($recibo, 'mensagem');
            }
            $reciboToken = self::extractReciboToken($recibo);
        } else {
            $sucesso = self::firstChildString($resp, 'sucesso');
            $msgSingle = self::firstChildString($resp, 'mensagem');
            $mensagens = $msgSingle !== null ? [$msgSingle] : [];
            $reciboToken = null;
        }

        $processoNodes = $resp->xpath('.//*[local-name()="processo"]');
        $processo = empty($processoNodes) ? null : self::xmlNodeToArray($processoNodes[0]);

        $mensagemFinal = !empty($mensagens) ? $mensagens[0] : null;

        return [
            'ok'           => true,
            'sucesso'      => $sucesso === null ? null : ($sucesso === 'true' || $sucesso === '1'),
            'mensagem'     => $mensagemFinal,
            'mensagens'    => $mensagens,
            'recibo_token' => $reciboToken,
            'processo'     => $processo,
            'fault'        => null,
        ];
    }

    /**
     * Localiza o elemento que envelopa a resposta:
     *  - v3: <respostaConsultarProcesso>
     *  - v2: <consultarProcessoResponse>
     * Falha graceful: se nenhum dos dois bater, tenta qualquer um por local-name.
     */
    private static function locateResponse(SimpleXMLElement $sx, string $version): ?SimpleXMLElement
    {
        $candidates = $version === MniRequestBuilder::VERSION_3_0_0
            ? ['respostaConsultarProcesso', 'consultarProcessoResponse']
            : ['consultarProcessoResponse', 'respostaConsultarProcesso'];

        foreach ($candidates as $name) {
            $found = $sx->xpath('//*[local-name()="' . $name . '"]');
            if (!empty($found)) {
                return $found[0];
            }
        }
        return null;
    }

    /**
     * Parsea o XML da operacao consultarAlteracao.
     *
     * Shape v3 oficial (api/mni3/xsd_wsdl/tipos-servico-intercomunicacao.xsd, linhas 307+):
     *   <tic:respostaConsultarAlteracao>
     *     <tic:recibo>
     *       <pji:sucesso>true|false</pji:sucesso>
     *       <pji:mensagens>...</pji:mensagens>
     *       <pji:reciboToken>...</pji:reciboToken>   (opcional)
     *     </tic:recibo>
     *     <tic:hashCabecalho>...</tic:hashCabecalho>          (opcional)
     *     <tic:hashMovimentacoes>...</tic:hashMovimentacoes>  (opcional)
     *     <tic:hashDocumentos>...</tic:hashDocumentos>        (opcional)
     *   </tic:respostaConsultarAlteracao>
     *
     * @return array{
     *   ok: bool,
     *   sucesso: ?bool,
     *   mensagens: list<string>,
     *   recibo_token: ?array{token: string, data_expiracao: ?string},
     *   alteracoes: array<string, mixed>,
     *   fault: ?array{code: string, string: string}
     * }
     */
    public static function parseConsultarAlteracaoResponse(string $xml, string $version = MniRequestBuilder::VERSION_3_0_0): array
    {
        $previous = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($sx === false) {
            return self::emptyAlteracao('XML invalido recebido do MNI.');
        }

        $sx->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');

        $faults = $sx->xpath('//s:Fault');
        if (!empty($faults)) {
            $fault = $faults[0];
            $code = (string) ($fault->faultcode ?? $fault->Code->Value ?? '');
            $msg  = (string) ($fault->faultstring ?? $fault->Reason->Text ?? '');
            return [
                'ok'           => false,
                'sucesso'      => false,
                'mensagens'    => $msg !== '' ? [$msg] : [],
                'recibo_token' => null,
                'alteracoes'   => [],
                'fault'        => ['code' => $code, 'string' => $msg],
            ];
        }

        $candidates = $version === MniRequestBuilder::VERSION_3_0_0
            ? ['respostaConsultarAlteracao', 'consultarAlteracaoResponse']
            : ['consultarAlteracaoResponse', 'respostaConsultarAlteracao'];

        $resp = null;
        foreach ($candidates as $name) {
            $found = $sx->xpath('//*[local-name()="' . $name . '"]');
            if (!empty($found)) {
                $resp = $found[0];
                break;
            }
        }
        if ($resp === null) {
            return self::emptyAlteracao('Resposta SOAP nao contem elemento de resposta esperado.');
        }

        $reciboNodes = $resp->xpath('.//*[local-name()="recibo"]');
        if (!empty($reciboNodes)) {
            $recibo    = $reciboNodes[0];
            $sucesso   = self::firstChildString($recibo, 'sucesso');
            $mensagens = self::collectChildStrings($recibo, 'mensagens');
            if (empty($mensagens)) {
                $mensagens = self::collectChildStrings($recibo, 'mensagem');
            }
            $reciboToken = self::extractReciboToken($recibo);
        } else {
            $sucesso     = self::firstChildString($resp, 'sucesso');
            $msgSingle   = self::firstChildString($resp, 'mensagem');
            $mensagens   = $msgSingle !== null ? [$msgSingle] : [];
            $reciboToken = null;
        }

        $alteracoes = self::collectAlteracoes($resp);

        return [
            'ok'           => true,
            'sucesso'      => $sucesso === null ? null : ($sucesso === 'true' || $sucesso === '1'),
            'mensagens'    => $mensagens,
            'recibo_token' => $reciboToken,
            'alteracoes'   => $alteracoes,
            'fault'        => null,
        ];
    }

    /**
     * Extrai apenas os hashes da resposta consultarAlteracao (ignora <recibo>).
     *
     * @return array<string, mixed>
     */
    private static function collectAlteracoes(SimpleXMLElement $resp): array
    {
        $out = [];
        $fields = ['hashCabecalho', 'hashMovimentacoes', 'hashDocumentos', 'dataReferencia'];
        foreach ($fields as $f) {
            $nodes = $resp->xpath('./*[local-name()="' . $f . '"]');
            if (!empty($nodes)) {
                $v = trim((string) $nodes[0]);
                if ($v !== '') {
                    $out[$f] = $v;
                }
            }
        }
        return $out;
    }

    /**
     * @return array{ok: false, sucesso: null, mensagens: list<string>, recibo_token: null, alteracoes: array<string, mixed>, fault: null}
     */
    private static function emptyAlteracao(string $msg): array
    {
        return [
            'ok'           => false,
            'sucesso'      => null,
            'mensagens'    => [$msg],
            'recibo_token' => null,
            'alteracoes'   => [],
            'fault'        => null,
        ];
    }

    /**
     * @return ?array{token: string, data_expiracao: ?string}
     */
    private static function extractReciboToken(SimpleXMLElement $recibo): ?array
    {
        $nodes = $recibo->xpath('.//*[local-name()="reciboToken"]');
        if (empty($nodes)) {
            return null;
        }
        $node = $nodes[0];
        $token = self::firstChildString($node, 'token');
        if ($token === null || $token === '') {
            return null;
        }
        $expira = self::firstChildString($node, 'dataExpiracao');
        return ['token' => $token, 'data_expiracao' => $expira];
    }

    /**
     * Converte recursivamente um no XML em array PHP com chaves por local-name.
     * Atributos viram chave '@' (subarray). Texto vira chave '#text'.
     * Listas de elementos com mesmo local-name viram array indexado.
     *
     * @return array<string, mixed>|string
     */
    public static function xmlNodeToArray(SimpleXMLElement $node)
    {
        $children = $node->children();
        $hasChildren = false;
        foreach ($children as $_) {
            $hasChildren = true;
            break;
        }

        // Tambem considera filhos em outros namespaces (MNI mistura tic/pji/cnj).
        if (!$hasChildren) {
            foreach ($node->getNamespaces(true) as $uri) {
                foreach ($node->children($uri) as $_) {
                    $hasChildren = true;
                    break 2;
                }
            }
        }

        $out = [];

        foreach ($node->attributes() as $attrName => $attrValue) {
            $out['@'][(string) $attrName] = (string) $attrValue;
        }
        foreach ($node->getNamespaces(true) as $uri) {
            foreach ($node->attributes($uri) as $attrName => $attrValue) {
                $out['@'][(string) $attrName] = (string) $attrValue;
            }
        }

        if (!$hasChildren) {
            $text = trim((string) $node);
            if (empty($out)) {
                return $text;
            }
            $out['#text'] = $text;
            return $out;
        }

        $collected = [];
        $collect = function (SimpleXMLElement $child) use (&$collected) {
            $name = $child->getName();
            $value = self::xmlNodeToArray($child);
            if (isset($collected[$name])) {
                if (!is_array($collected[$name]) || !array_is_list($collected[$name])) {
                    $collected[$name] = [$collected[$name]];
                }
                $collected[$name][] = $value;
            } else {
                $collected[$name] = $value;
            }
        };

        foreach ($node->children() as $child) {
            $collect($child);
        }
        foreach ($node->getNamespaces(true) as $uri) {
            foreach ($node->children($uri) as $child) {
                $collect($child);
            }
        }

        return $out + $collected;
    }

    private static function firstChildString(SimpleXMLElement $node, string $localName): ?string
    {
        $found = $node->xpath('.//*[local-name()="' . $localName . '"]');
        if (empty($found)) {
            return null;
        }
        $v = trim((string) $found[0]);
        return $v === '' ? null : $v;
    }

    /**
     * @return list<string>
     */
    private static function collectChildStrings(SimpleXMLElement $node, string $localName): array
    {
        $found = $node->xpath('.//*[local-name()="' . $localName . '"]');
        $out = [];
        foreach ($found as $n) {
            $text = trim((string) $n);
            if ($text === '') {
                continue;
            }
            $out[] = $text;
        }
        return $out;
    }

    /**
     * @return array{ok: false, sucesso: null, mensagem: string, mensagens: list<string>, recibo_token: null, processo: null, fault: null}
     */
    private static function empty(string $msg): array
    {
        return [
            'ok'           => false,
            'sucesso'      => null,
            'mensagem'     => $msg,
            'mensagens'    => [$msg],
            'recibo_token' => null,
            'processo'     => null,
            'fault'        => null,
        ];
    }
}
