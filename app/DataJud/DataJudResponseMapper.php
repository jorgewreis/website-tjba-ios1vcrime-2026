<?php
declare(strict_types=1);

/**
 * Formatação única da resposta DataJud (Elasticsearch _source → payload da API interna).
 */
final class DataJudResponseMapper
{
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

    /**
     * @return array<string, mixed>
     */
    public static function mapSourceToPayload(array $source, string $numeroDigitos, bool $redactSigilo): array
    {
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
            'assuntos' => self::formatarAssuntos($source['assuntos'] ?? []),
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
            'sigilo_mensagem' => $redactSigilo
                ? 'Este processo possui restrição de sigilo na base pública do DataJud. Partes e movimentos não são exibidos; informe a classe manualmente se necessário.'
                : null,
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
     * @return list<string>
     */
    public static function formatarAssuntos($assuntos): array
    {
        if (empty($assuntos) || !is_array($assuntos)) {
            return [];
        }
        $lista = [];
        foreach ($assuntos as $item) {
            if (is_string($item)) {
                $lista[] = $item;
                continue;
            }
            if (is_array($item)) {
                $desc = $item['descricaoAssunto'] ?? $item['descricao'] ?? $item['nome'] ?? null;
                if ($desc) {
                    $lista[] = $desc;
                    continue;
                }
                foreach ($item as $sub) {
                    if (is_string($sub)) {
                        $lista[] = $sub;
                        continue;
                    }
                    if (is_array($sub)) {
                        $nested = $sub['descricaoAssunto'] ?? $sub['descricao'] ?? $sub['nome'] ?? null;
                        if ($nested) {
                            $lista[] = $nested;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($lista));
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
