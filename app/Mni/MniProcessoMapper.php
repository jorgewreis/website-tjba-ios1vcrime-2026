<?php
declare(strict_types=1);

require_once __DIR__ . '/MniEnums.php';

/**
 * Converte a arvore PHP do MniResponseParser para um shape JSON estavel,
 * inspirado no shape do DataJud (DataJudResponseMapper::mapSourceToPayload),
 * para que a facade ProcessoLookup possa fundir/diffar os dois sem retrabalho.
 *
 * Campos extras exclusivos do MNI (nao existem no shape DataJud) ficam sob "mni":
 *   - mni.partes (polo ativo/passivo/terceiro/etc., com tipo de pessoa, nome, documento)
 *   - mni.orgaoJulgador (codigo, nome, instancia, codigoLocalidade, orgaoColegiado)
 *   - mni.dataAjuizamento (tambem replicado no campo top-level data_ajuizamento)
 *   - mni.classeProcessual
 *   - mni.assuntosHierarquicos
 *   - mni.documentos (apenas metadados — sinaliza criptografados)
 *   - mni.cabecalho.* (todos os atributos extras: prioridade, sigilo, valor_causa,
 *     magistrados, pedido_liminar_pendente, processo_fisico, intervencao_mp,
 *     competencia, tamanho_processo_bytes, historico_deslocamento, processo_vinculado)
 *   - mni.totalMovimentos
 *
 * No top-level, sempre que ha equivalencia com DataJud, populamos:
 *   - prioridades (com codigo/descricao baseados em MniEnums)
 *   - nivel_sigilo
 *   - data_ajuizamento
 *   - polo_passivo
 *   - movimentos
 *   - orgao_julgador (codigo, nome)
 */
final class MniProcessoMapper
{
    /**
     * @param array<string, mixed>|null $processo
     * @return array<string, mixed>
     */
    public static function mapToCommonShape(?array $processo, string $numeroDigitos): array
    {
        if ($processo === null) {
            return self::emptyShape($numeroDigitos);
        }

        $dadosBasicos = self::firstSubarray($processo, ['dadosBasicos']) ?? [];
        $classe       = self::extractClasseProcessual($dadosBasicos);
        $assuntos     = self::extractAssuntos($dadosBasicos);
        $partes       = self::extractPartes($dadosBasicos);
        $movimentos   = self::extractMovimentos($processo);
        $orgao        = self::firstSubarray($dadosBasicos, ['orgaoJulgador']) ?? [];

        $poloPassivo = array_values(array_filter(array_map(
            static fn (array $p): string => trim((string) ($p['nome'] ?? '')),
            $partes['passivo'] ?? []
        ), static fn (string $n): bool => $n !== ''));

        $prioridadeCodigo = self::firstScalar($dadosBasicos, ['prioridade']);
        $prioridades = $prioridadeCodigo !== null
            ? [[
                'codigo'    => $prioridadeCodigo,
                'descricao' => MniEnums::prioridadeLabel($prioridadeCodigo) ?? $prioridadeCodigo,
            ]]
            : [];

        $cabecalhoExtra = self::extractCabecalhoExtra($dadosBasicos);

        return [
            'numero'              => self::firstScalar($dadosBasicos, ['numero']) ?? $numeroDigitos,
            'tribunal'            => null,
            'classe'              => $classe,
            'assuntos'            => self::normalizeAssuntosForShape($assuntos),
            'prioridades'         => $prioridades,
            'polo_passivo'        => $poloPassivo,
            'movimentos'          => $movimentos,
            'orgao_julgador'      => self::normalizeOrgaoJulgador($orgao),
            'nivel_sigilo'        => self::nullableInt(self::firstScalar($dadosBasicos, ['nivelSigilo'])),
            'grau'                => null,
            'formato'             => self::normalizeCodigoNome($dadosBasicos['formato'] ?? null),
            'sistema'             => self::normalizeCodigoNome($dadosBasicos['sistema'] ?? null),
            'data_ajuizamento'    => self::firstScalar($dadosBasicos, ['dataAjuizamento']),
            'ultima_atualizacao'  => null,
            'id'                  => null,
            'sigilo_restrito'     => self::isSigiloRestrito($cabecalhoExtra['nivel_sigilo'] ?? null),
            'sigilo_mensagem'     => null,

            'mni' => [
                'fonte'                  => 'mni',
                'partes'                 => $partes,
                'orgaoJulgador'          => $orgao,
                'dataAjuizamento'        => self::firstScalar($dadosBasicos, ['dataAjuizamento']),
                'classeProcessual'       => $classe,
                'assuntosHierarquicos'   => $assuntos,
                'documentos'             => self::extractDocumentosMetadados($processo),
                'totalMovimentos'        => count($movimentos),
                'cabecalho'              => $cabecalhoExtra,
            ],
        ];
    }

    /**
     * Shape vazio compativel, usado quando MNI retorna sem processo (ex.: SOAP Fault leve).
     *
     * @return array<string, mixed>
     */
    public static function emptyShape(string $numeroDigitos): array
    {
        return [
            'numero' => $numeroDigitos,
            'tribunal' => null,
            'classe' => ['codigo' => null, 'descricao' => null],
            'assuntos' => [],
            'prioridades' => [],
            'polo_passivo' => [],
            'movimentos' => [],
            'orgao_julgador' => [],
            'nivel_sigilo' => null,
            'grau' => null,
            'formato' => [],
            'sistema' => [],
            'data_ajuizamento' => null,
            'ultima_atualizacao' => null,
            'id' => null,
            'sigilo_restrito' => false,
            'sigilo_mensagem' => null,
            'mni' => [
                'fonte' => 'mni',
                'partes' => ['ativo' => [], 'passivo' => [], 'terceiro' => [], 'fiscal' => [], 'testemunha' => [], 'amicus' => [], 'vitima' => [], 'outros' => []],
                'orgaoJulgador' => [],
                'dataAjuizamento' => null,
                'classeProcessual' => ['codigo' => null, 'descricao' => null],
                'assuntosHierarquicos' => [],
                'documentos' => [],
                'totalMovimentos' => 0,
                'cabecalho' => [],
            ],
        ];
    }

    // ---------------- extractors do cabecalho v3 ----------------

    /**
     * Extrai todos os campos do tipoCabecalhoProcesso v3 que enriquecem o
     * panorama do DataJud (prioridade, sigilo, valor da causa, magistrados,
     * processo fisico, intervencao MP, pedido liminar pendente, etc.).
     *
     * @param array<string, mixed> $dadosBasicos
     * @return array<string, mixed>
     */
    private static function extractCabecalhoExtra(array $dadosBasicos): array
    {
        $prioridade = self::firstScalar($dadosBasicos, ['prioridade']);

        return [
            'prioridade' => $prioridade === null ? null : [
                'codigo'    => $prioridade,
                'descricao' => MniEnums::prioridadeLabel($prioridade),
            ],
            'nivel_sigilo' => self::nullableInt(self::firstScalar($dadosBasicos, ['nivelSigilo'])),
            'nivel_sigilo_label' => MniEnums::nivelSigiloLabel(self::firstScalar($dadosBasicos, ['nivelSigilo'])),
            'valor_causa' => self::nullableFloat(self::firstScalar($dadosBasicos, ['valorCausa'])),
            'competencia' => self::nullableInt(self::firstScalar($dadosBasicos, ['competencia'])),
            'magistrados_atuantes' => self::collectScalarList($dadosBasicos, ['magistradoAtuante']),
            'pedido_liminar_pendente' => self::nullableBool(self::firstScalar($dadosBasicos, ['pedidoLiminarPendente'])),
            'processo_fisico' => self::nullableBool(self::firstScalar($dadosBasicos, ['processoFisico'])),
            'intervencao_mp' => self::nullableBool(self::firstScalar($dadosBasicos, ['intervencaoMP'])),
            'tamanho_processo_bytes' => self::nullableInt(self::firstScalar($dadosBasicos, ['tamanhoProcesso'])),
            'outros_numeros' => self::collectScalarList($dadosBasicos, ['outrosNumeros']),
            'historico_deslocamento' => self::extractHistoricoDeslocamento($dadosBasicos),
            'processo_vinculado' => self::extractProcessoVinculado($dadosBasicos),
        ];
    }

    /**
     * @param array<string, mixed> $dadosBasicos
     * @return array{codigo: ?string, descricao: ?string}
     */
    private static function extractClasseProcessual(array $dadosBasicos): array
    {
        $codigo = self::firstScalar($dadosBasicos, ['classeProcessual']);
        return [
            'codigo'    => $codigo,
            'descricao' => null, // MNI v3 retorna apenas codigo numerico; descricao vem do catalogo local
        ];
    }

    /**
     * @param array<string, mixed> $dadosBasicos
     * @return list<array<string, mixed>>
     */
    private static function extractAssuntos(array $dadosBasicos): array
    {
        if (!isset($dadosBasicos['assunto'])) {
            return [];
        }
        $raw = $dadosBasicos['assunto'];
        if (is_array($raw) && array_is_list($raw)) {
            $items = $raw;
        } else {
            $items = [$raw];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            // tipoAssuntoProcessual e um choice: codigoNacional (int) OU assuntoLocal (descricao+codigo local).
            $codigo = self::firstScalar($item, ['codigoNacional'])
                ?? self::firstScalar($item['assuntoLocal'] ?? [], ['codigoPaiNacional', 'codigoAssunto']);
            $descricao = self::firstScalar($item['assuntoLocal'] ?? [], ['descricao'])
                ?? self::firstScalar($item, ['descricao']);
            $principal = self::firstScalar($item, ['principal']) === 'true';
            $out[] = [
                'codigo'    => $codigo,
                'descricao' => $descricao,
                'principal' => $principal,
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $assuntos
     * @return list<array{codigo: ?string, descricao: string}>
     */
    private static function normalizeAssuntosForShape(array $assuntos): array
    {
        $out = [];
        foreach ($assuntos as $a) {
            $desc = trim((string) ($a['descricao'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $codigo = $a['codigo'] !== null ? trim((string) $a['codigo']) : null;
            if ($codigo === '') {
                $codigo = null;
            }
            $out[] = ['codigo' => $codigo, 'descricao' => $desc];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $dadosBasicos
     * @return array{
     *   ativo: list<array<string, mixed>>,
     *   passivo: list<array<string, mixed>>,
     *   terceiro: list<array<string, mixed>>,
     *   fiscal: list<array<string, mixed>>,
     *   testemunha: list<array<string, mixed>>,
     *   amicus: list<array<string, mixed>>,
     *   vitima: list<array<string, mixed>>,
     *   outros: list<array<string, mixed>>,
     * }
     */
    private static function extractPartes(array $dadosBasicos): array
    {
        $resultado = [
            'ativo'      => [],
            'passivo'    => [],
            'terceiro'   => [],
            'fiscal'     => [],
            'testemunha' => [],
            'amicus'     => [],
            'vitima'     => [],
            'outros'     => [],
        ];
        if (!isset($dadosBasicos['polo'])) {
            return $resultado;
        }
        $polos = $dadosBasicos['polo'];
        if (is_array($polos) && !array_is_list($polos)) {
            $polos = [$polos];
        }
        if (!is_array($polos)) {
            return $resultado;
        }
        foreach ($polos as $polo) {
            if (!is_array($polo)) {
                continue;
            }
            // O atributo 'polo' fica em '@' apos o xmlNodeToArray.
            $polaridade = strtoupper((string) (
                $polo['@']['polo'] ?? self::firstScalar($polo, ['polo']) ?? ''
            ));
            $bucket = self::bucketForPolo($polaridade);

            $parteRaw = $polo['parte'] ?? [];
            if (is_array($parteRaw) && !array_is_list($parteRaw)) {
                $parteRaw = [$parteRaw];
            }
            if (!is_array($parteRaw)) {
                continue;
            }
            foreach ($parteRaw as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $pessoa = $p['pessoa'] ?? [];
                if (!is_array($pessoa)) {
                    $pessoa = [];
                }
                $dadosPessoa = $pessoa['dadosBasicos'] ?? $pessoa;
                if (!is_array($dadosPessoa)) {
                    $dadosPessoa = [];
                }
                $resultado[$bucket][] = [
                    'nome'             => self::firstScalar($dadosPessoa, ['nome']),
                    'tipoPessoa'       => self::firstScalar($dadosPessoa, ['tipoPessoa']),
                    'tipoPessoaLabel'  => MniEnums::qualificacaoPessoaLabel(self::firstScalar($dadosPessoa, ['tipoPessoa'])),
                    'numeroDocumento'  => self::firstScalar($dadosPessoa, ['numeroDocumentoPrincipal', 'numeroDocumento']),
                ];
            }
        }
        return $resultado;
    }

    private static function bucketForPolo(string $polaridade): string
    {
        return match ($polaridade) {
            MniEnums::POLO_ATIVO          => 'ativo',
            MniEnums::POLO_PASSIVO        => 'passivo',
            MniEnums::POLO_TERCEIRO       => 'terceiro',
            MniEnums::POLO_FISCAL_DA_LEI  => 'fiscal',
            MniEnums::POLO_TESTEMUNHA     => 'testemunha',
            MniEnums::POLO_AMICUS_CURIAE  => 'amicus',
            MniEnums::POLO_VITIMA         => 'vitima',
            default                       => 'outros',
        };
    }

    /**
     * @param array<string, mixed> $processo
     * @return list<array<string, mixed>>
     */
    private static function extractMovimentos(array $processo): array
    {
        if (!isset($processo['movimento'])) {
            return [];
        }
        $raw = $processo['movimento'];
        if (is_array($raw) && !array_is_list($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $m) {
            if (!is_array($m)) {
                continue;
            }
            $movimentoNacional = $m['movimentoNacional'] ?? [];
            $codigo = self::firstScalar(is_array($movimentoNacional) ? $movimentoNacional : [], ['codigoNacional'])
                ?? self::firstScalar($m, ['codigoNacional']);
            $descricao = self::firstScalar(is_array($movimentoNacional) ? $movimentoNacional : [], ['descricao'])
                ?? self::firstScalar($m, ['descricao']);
            $out[] = [
                'codigo'      => $codigo,
                'descricao'   => $descricao,
                'dataHora'    => self::firstScalar($m, ['dataHora']),
                'complemento' => self::firstScalar($m, ['complemento']),
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $processo
     * @return list<array<string, mixed>>
     */
    private static function extractDocumentosMetadados(array $processo): array
    {
        if (!isset($processo['documento'])) {
            return [];
        }
        $raw = $processo['documento'];
        if (is_array($raw) && !array_is_list($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $d) {
            if (!is_array($d)) {
                continue;
            }
            $conteudo = is_array($d['conteudo'] ?? null) ? $d['conteudo'] : [];
            $mimetype = self::firstScalar($conteudo, ['mimetype']);
            $hash     = self::firstScalar($conteudo, ['hash']);
            $temBinario = isset($conteudo['conteudo']);
            $nivelSigiloDoc = self::nullableInt(self::firstScalar($d, ['nivelSigilo']));

            $out[] = [
                'idDocumento'         => self::firstScalar($d, ['idDocumento']),
                'idDocumentoVinculado'=> self::firstScalar($d, ['idDocumentoVinculado']),
                'codigoTipoDocumento' => self::firstScalar($d, ['codigoTipoDocumento']),
                'tipoDocumentoLocal'  => self::firstScalar($d, ['codigoTipoDocumentoLocal']),
                'descricao'           => self::firstScalar($d, ['descricao']),
                'mimetype'            => $mimetype,
                'hash'                => $hash,
                'dataHora'            => self::firstScalar($d, ['dataHora']),
                'nivelSigilo'         => $nivelSigiloDoc,
                'nivelSigiloLabel'    => MniEnums::nivelSigiloLabel($nivelSigiloDoc),
                'tamanhoConteudoBytes'=> self::nullableInt(self::firstScalar($d, ['tamanhoConteudo'])),
                // Heuristica: documento marcado como criptografado quando ha conteudo
                // binario mas sem mimetype declarado (cenario citado pelo XSD em casos
                // de intimacao pendente).
                'criptografado'       => $temBinario && ($mimetype === null || $mimetype === ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $orgao
     * @return array{codigo: ?string, nome: ?string, instancia: ?string, codigo_localidade: ?string}
     */
    private static function normalizeOrgaoJulgador(array $orgao): array
    {
        return [
            'codigo'            => self::firstScalar($orgao, ['codigo', 'codigoOrgao']),
            'nome'              => self::firstScalar($orgao, ['nome', 'nomeOrgao']),
            'instancia'         => self::firstScalar($orgao, ['instancia']),
            'codigo_localidade' => self::firstScalar($orgao, ['codigoLocalidade']),
        ];
    }

    /**
     * @param mixed $node
     * @return array<string, ?string>
     */
    private static function normalizeCodigoNome($node): array
    {
        if (!is_array($node)) {
            return [];
        }
        return [
            'codigo' => self::firstScalar($node, ['codigo']),
            'nome'   => self::firstScalar($node, ['nome']) ?? self::firstScalar($node, ['descricao']),
        ];
    }

    /**
     * @param array<string, mixed> $dadosBasicos
     * @return list<array<string, mixed>>
     */
    private static function extractHistoricoDeslocamento(array $dadosBasicos): array
    {
        if (!isset($dadosBasicos['historicoDeslocamento'])) {
            return [];
        }
        $raw = $dadosBasicos['historicoDeslocamento'];
        if (is_array($raw) && !array_is_list($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $h) {
            if (!is_array($h)) {
                continue;
            }
            $out[] = [
                'orgaoOrigem'  => self::firstScalar($h['orgaoOrigem'] ?? [], ['nome', 'codigo']),
                'orgaoDestino' => self::firstScalar($h['orgaoDestino'] ?? [], ['nome', 'codigo']),
                'dataHora'     => self::firstScalar($h, ['dataHora']),
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $dadosBasicos
     * @return list<array<string, mixed>>
     */
    private static function extractProcessoVinculado(array $dadosBasicos): array
    {
        if (!isset($dadosBasicos['processoVinculado'])) {
            return [];
        }
        $raw = $dadosBasicos['processoVinculado'];
        if (is_array($raw) && !array_is_list($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (!is_array($v)) {
                continue;
            }
            $out[] = [
                'numeroProcesso' => self::firstScalar($v, ['numeroProcesso']),
                'tipoVinculacao' => self::firstScalar($v, ['tipoVinculacao']),
            ];
        }
        return $out;
    }

    // ---------------- helpers ----------------

    /**
     * @param array<string, mixed> $node
     * @param list<string> $candidates
     * @return array<string, mixed>|null
     */
    private static function firstSubarray(array $node, array $candidates): ?array
    {
        foreach ($candidates as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                return $node[$key];
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $candidates
     */
    private static function firstScalar(array $node, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $node)) {
                continue;
            }
            $v = $node[$key];
            if (is_scalar($v)) {
                $s = trim((string) $v);
                return $s === '' ? null : $s;
            }
            if (is_array($v) && isset($v['#text'])) {
                $s = trim((string) $v['#text']);
                return $s === '' ? null : $s;
            }
        }
        return null;
    }

    /**
     * Para campos com maxOccurs=unbounded que devem virar lista simples de strings.
     *
     * @param array<string, mixed> $node
     * @param list<string> $candidates
     * @return list<string>
     */
    private static function collectScalarList(array $node, array $candidates): array
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $node)) {
                continue;
            }
            $v = $node[$key];
            if (is_scalar($v)) {
                $s = trim((string) $v);
                return $s === '' ? [] : [$s];
            }
            if (is_array($v)) {
                if (array_is_list($v)) {
                    $out = [];
                    foreach ($v as $item) {
                        if (is_scalar($item)) {
                            $s = trim((string) $item);
                            if ($s !== '') {
                                $out[] = $s;
                            }
                        } elseif (is_array($item) && isset($item['#text'])) {
                            $s = trim((string) $item['#text']);
                            if ($s !== '') {
                                $out[] = $s;
                            }
                        }
                    }
                    return $out;
                }
                if (isset($v['#text'])) {
                    $s = trim((string) $v['#text']);
                    return $s === '' ? [] : [$s];
                }
            }
        }
        return [];
    }

    private static function nullableInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!preg_match('/^-?\d+$/', $value)) {
            return null;
        }
        return (int) $value;
    }

    private static function nullableFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    private static function nullableBool(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        $lower = strtolower(trim($value));
        if (in_array($lower, ['true', '1', 'sim', 'yes'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '0', 'nao', 'no'], true)) {
            return false;
        }
        return null;
    }

    private static function isSigiloRestrito(?int $nivelSigilo): bool
    {
        return $nivelSigilo !== null && $nivelSigilo >= 1;
    }
}
