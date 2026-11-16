<?php
declare(strict_types=1);

/**
 * Formatação única da resposta DataJud (Elasticsearch _source → payload da API interna).
 */
final class DataJudResponseMapper
{
    /** Texto alinhado ao toast do front (`DataJudCycle.MSG_TOAST_SIGILO_PUBLICO`). */
    private const MSG_TOAST_SIGILO_PUBLICO = 'Não há dados públicos completos no DataJud para este processo (sigilo ou indisponível na base). Informe a classe manualmente se necessário.';

    /**
     * Indica se o nível de sigilo deve ocultar dados sensíveis na resposta pública.
     */
    public static function isNivelSigiloRestrito($nivelSigilo): bool
    {
        if ($nivelSigilo === null || $nivelSigilo === '') {
            return false;
        }
        if (is_string($nivelSigilo) && preg_match('/^\s*0+\s*$/', $nivelSigilo)) {
            return false;
        }
        if (is_numeric($nivelSigilo) && (int) $nivelSigilo === 0) {
            return false;
        }
        if (is_string($nivelSigilo) && preg_match('/sem\s*sigilo/iu', $nivelSigilo)) {
            return false;
        }

        return true;
    }

    /** Sincronizações exigem prova explícita de que o registro não tem sigilo. */
    public static function isAusenciaSigiloExplicitamenteInformada($nivelSigilo): bool
    {
        if (is_int($nivelSigilo)) {
            return $nivelSigilo === 0;
        }
        if (!is_string($nivelSigilo)) {
            return false;
        }
        $nivelSigilo = trim($nivelSigilo);
        return preg_match('/^0+$/', $nivelSigilo) === 1
            || preg_match('/sem\s*sigilo/iu', $nivelSigilo) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapSourceToPayload(array $source, string $numeroDigitos, bool $redactSigilo): array
    {
        $estadoAssuntos = array_key_exists('assuntos', $source)
            ? (is_array($source['assuntos']) && $source['assuntos'] === [] ? 'vazio' : 'presente')
            : 'ausente';
        $assuntos = $estadoAssuntos === 'ausente' ? [] : self::formatarAssuntos($source['assuntos']);
        $poloPassivo = self::extractPartNamesFromPolos($source);
        $movimentos = self::formatarMovimentos($source['movimentos'] ?? []);

        if ($redactSigilo) {
            $poloPassivo = [];
            $movimentos = [];
        }

        return [
            'numero' => $source['numeroProcesso'] ?? $numeroDigitos,
            'tribunal' => $source['tribunal'] ?? null,
            'classe' => self::formatarClasse($source),
            'assuntos' => $assuntos,
            'assuntos_estado' => $estadoAssuntos,
            'prioridades' => self::formatarPrioridades($source['prioridade'] ?? []),
            'polo_passivo' => $poloPassivo,
            'movimentos' => $movimentos,
            'orgao_julgador' => self::formatarOrgao($source['orgaoJulgador'] ?? []),
            'nivel_sigilo' => $source['nivelSigilo'] ?? null,
            'grau' => $source['grau'] ?? null,
            'formato' => self::formatarCodigoNome($source['formato'] ?? []),
            'sistema' => self::formatarCodigoNome($source['sistema'] ?? []),
            'data_ajuizamento' => $source['dataAjuizamento'] ?? null,
            'ultima_atualizacao' => $source['dataHoraUltimaAtualizacao'] ?? null,
            'id' => $source['id'] ?? null,
            'sigilo_restrito' => $redactSigilo,
            'sigilo_ausencia_verificada' => self::isAusenciaSigiloExplicitamenteInformada($source['nivelSigilo'] ?? null),
            'sigilo_mensagem' => $redactSigilo ? self::MSG_TOAST_SIGILO_PUBLICO : null,
        ];
    }

    /**
     * Resposta quando a API pública não retorna documento (0 hits).
     * Processos sigilosos costumam não aparecer na base pública — não é o mesmo que “número inválido”.
     *
     * @return array<string, mixed>
     */
    public static function mapEmptyPublicSearch(string $numeroDigitos): array
    {
        return [
            'numero' => $numeroDigitos,
            'tribunal' => null,
            'classe' => ['codigo' => null, 'descricao' => null],
            'assuntos' => [],
            'assuntos_estado' => 'indisponivel',
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
            'indisponivel_na_base_publica' => true,
            'sigilo_restrito' => false,
            'sigilo_mensagem' => self::MSG_TOAST_SIGILO_PUBLICO,
        ];
    }

    public static function formatarClasse(array $source): array
    {
        $classe = $source['classe'] ?? $source['classeProcessual'] ?? null;
        if (is_string($classe) && $classe !== '') {
            return ['codigo' => $classe, 'descricao' => $classe];
        }
        if (is_array($classe)) {
            return [
                'codigo' => $classe['codigo'] ?? $classe['numClasse'] ?? $classe['codigoClasse'] ?? null,
                'descricao' => $classe['descricao'] ?? $classe['nome'] ?? $classe['descricaoClasse'] ?? null,
            ];
        }

        return ['codigo' => null, 'descricao' => null];
    }

    /**
     * @param mixed $assuntos
     * @return list<array{codigo: ?string, descricao: string}>
     */
    public static function formatarAssuntos($assuntos): array
    {
        if (!is_array($assuntos) || !array_is_list($assuntos)) {
            throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
        }

        $lista = [];
        foreach ($assuntos as $item) {
            self::extrairAssunto($item, '', $lista, 0);
        }

        $unicos = [];
        foreach ($lista as $entry) {
            $chave = trim((string) ($entry['codigo'] ?? '')) . '|' . trim((string) ($entry['descricao'] ?? ''));
            if ($chave === '|') {
                continue;
            }
            $unicos[$chave] = $entry;
        }

        return array_values($unicos);
    }

    /** @param mixed $item @param list<array{codigo: string, descricao: string}> $lista */
    private static function extrairAssunto($item, string $codigoPai, array &$lista, int $profundidade): void
    {
        if (!is_array($item) || array_is_list($item) || $profundidade > 8) {
            throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
        }
        $inicio = count($lista);
        $codigo = self::campoAssunto($item, ['codigo', 'codigoAssunto', 'numAssunto']);
        $codigo = $codigo !== '' ? $codigo : $codigoPai;
        $descricao = self::campoAssunto($item, ['descricaoAssunto', 'descricao', 'nome']);
        if ($descricao !== '') {
            self::validarCodigoAssunto($codigo);
            $lista[] = ['codigo' => $codigo, 'descricao' => $descricao];
        }
        foreach ($item as $chave => $valor) {
            if ($chave === 'assunto' || $chave === 'assuntos') {
                if (!is_array($valor) || $valor === []) throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
                $filhos = array_is_list($valor) ? $valor : [$valor];
                foreach ($filhos as $filho) self::extrairAssunto($filho, $codigo, $lista, $profundidade + 1);
            } elseif (is_array($valor) || is_object($valor)) {
                // Estruturas não reconhecidas podem conter outro assunto; não as descarte.
                throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
            }
            // Metadados escalares não reconhecidos não são interpretados como assuntos.
        }
        if (count($lista) === $inicio) throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
    }

    /** @param array<string, mixed> $item @param list<string> $nomes */
    private static function campoAssunto(array $item, array $nomes): string
    {
        foreach ($nomes as $nome) {
            if (!array_key_exists($nome, $item) || $item[$nome] === null) continue;
            $valor = $item[$nome];
            if (!is_string($valor) && !is_int($valor)) {
                throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
            }
            return trim((string) $valor);
        }
        return '';
    }

    private static function validarCodigoAssunto(string $codigo): void
    {
        if (!preg_match('/^(?=.*[1-9])[0-9]+$/D', $codigo)) {
            throw new RuntimeException('Resposta de assuntos do DataJud inválida.');
        }
    }

    /**
     * @param mixed $movimentos
     * @return list<array<string, mixed>>
     */
    public static function formatarMovimentos($movimentos): array
    {
        if (empty($movimentos) || !is_array($movimentos)) {
            return [];
        }
        $resultado = [];
        foreach ($movimentos as $movimento) {
            if (!is_array($movimento)) {
                continue;
            }
            $resultado[] = [
                'codigo' => $movimento['codigo'] ?? null,
                'nome' => $movimento['nome'] ?? null,
                'dataHora' => $movimento['dataHora'] ?? $movimento['data_hora'] ?? null,
                'complementos' => self::formatarComplementos($movimento['complementosTabelados'] ?? []),
            ];
        }

        return $resultado;
    }

    /**
     * @param mixed $complementos
     * @return list<array<string, mixed>>
     */
    public static function formatarComplementos($complementos): array
    {
        $lista = [];
        foreach ((array) $complementos as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lista[] = [
                'codigo' => $item['codigo'] ?? null,
                'nome' => $item['nome'] ?? null,
                'valor' => $item['valor'] ?? null,
                'descricao' => $item['descricao'] ?? null,
            ];
        }

        return $lista;
    }

    /**
     * @param mixed $orgao
     * @return array<string, mixed>
     */
    public static function formatarOrgao($orgao): array
    {
        if (!is_array($orgao)) {
            return [];
        }

        return [
            'codigo' => $orgao['codigo'] ?? null,
            'nome' => $orgao['nome'] ?? null,
            'codigoMunicipioIBGE' => $orgao['codigoMunicipioIBGE'] ?? null,
        ];
    }

    /**
     * @param mixed $obj
     * @return array<string, mixed>
     */
    public static function formatarCodigoNome($obj): array
    {
        if (!is_array($obj)) {
            return [];
        }

        return [
            'codigo' => $obj['codigo'] ?? null,
            'descricao' => $obj['nome'] ?? $obj['descricao'] ?? null,
        ];
    }

    /**
     * @param mixed $prioridades
     * @return list<string>
     */
    public static function formatarPrioridades($prioridades): array
    {
        if (empty($prioridades)) {
            return [];
        }
        $lista = [];
        foreach ((array) $prioridades as $item) {
            if (is_string($item)) {
                $lista[] = $item;
            } elseif (is_array($item)) {
                $lista[] = $item['descricaoPrioridade'] ?? $item['descricao'] ?? implode(', ', $item);
            }
        }

        return array_values(array_filter($lista));
    }

    /**
     * Nomes de partes extraídos de polo / poloAtivo / poloPassivo (mesmo padrão do endpoint legado).
     *
     * @param mixed $polos
     * @return list<string>
     */
    public static function formatarPolo($polos): array
    {
        $nomes = [];
        foreach ((array) $polos as $polo) {
            $partes = (array) ($polo['parte'] ?? $polo['partes'] ?? []);
            foreach ($partes as $parte) {
                if (is_string($parte)) {
                    $nome = $parte;
                } elseif (is_array($parte)) {
                    $nome = $parte['nome'] ?? ($parte['descricao'] ?? null);
                } else {
                    $nome = null;
                }
                if ($nome) {
                    $nomes[] = trim((string) $nome);
                }
            }
        }
        $nomes = array_values(array_filter($nomes, static function ($v) {
            return trim((string) $v) !== '';
        }));

        return array_values(array_unique($nomes));
    }

    /**
     * @return list<string>
     */
    public static function extractPartNamesFromPolos(array $source): array
    {
        $nomes = [];
        foreach (['polo', 'poloAtivo', 'poloPassivo'] as $campo) {
            $extraidos = self::formatarPolo($source[$campo] ?? []);
            foreach ($extraidos as $nome) {
                $v = trim((string) $nome);
                if ($v !== '') {
                    $nomes[$v] = true;
                }
            }
        }

        return array_values(array_keys($nomes));
    }
}
