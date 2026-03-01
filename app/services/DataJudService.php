<?php
declare(strict_types=1);

/**
 * Serviço para consulta ao DataJud (CNJ).
 * Retorna a mesma estrutura que api/datajud/search-processo.php.
 */
final class DataJudService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** Consulta processo por número (20 dígitos). Retorna array com numero, tribunal, classe, orgao_julgador, etc. */
    public function searchProcesso(string $numero): array
    {
        $numero = trim(preg_replace('/\D+/', '', $numero));
        if ($numero === '' || strlen($numero) !== 20) {
            throw new InvalidArgumentException('Número do processo inválido. Forneça 20 dígitos.');
        }

        $body = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['numeroProcesso' => $numero]],
                    ],
                ],
            ],
            'size' => 1,
            '_source' => [
                'numeroProcesso',
                'classeProcessual',
                'classe',
                'assuntos',
                'polo',
                'prioridade',
                'tribunal',
                'movimentos',
                'orgaoJulgador',
                'nivelSigilo',
                'grau',
                'formato',
                'sistema',
                'dataHoraUltimaAtualizacao',
                'dataAjuizamento',
                'id',
            ],
        ];

        $ch = curl_init($this->config['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: APIKey ' . $this->config['api_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Falha ao contatar o DataJud: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['hits']['hits'][0]['_source'])) {
            throw new RuntimeException('Processo não encontrado na base do DataJud.');
        }

        $source = $decoded['hits']['hits'][0]['_source'];

        return [
            'numero' => $source['numeroProcesso'] ?? $numero,
            'tribunal' => $source['tribunal'] ?? 'TJBA',
            'classe' => $this->formatarClasse($source),
            'assuntos' => $this->formatarAssuntos($source['assuntos'] ?? []),
            'prioridades' => $this->formatarPrioridades($source['prioridade'] ?? []),
            'polo_passivo' => $this->formatarPolo($source['polo'] ?? []),
            'movimentos' => $this->formatarMovimentos($source['movimentos'] ?? []),
            'orgao_julgador' => $this->formatarOrgao($source['orgaoJulgador'] ?? []),
            'nivel_sigilo' => $source['nivelSigilo'] ?? null,
            'grau' => $source['grau'] ?? null,
            'formato' => $this->formatarCodigoNome($source['formato'] ?? []),
            'sistema' => $this->formatarCodigoNome($source['sistema'] ?? []),
            'data_ajuizamento' => $source['dataAjuizamento'] ?? null,
            'ultima_atualizacao' => $source['dataHoraUltimaAtualizacao'] ?? null,
            'id' => $source['id'] ?? null,
        ];
    }

    private function formatarClasse(array $source): array
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

    private function formatarAssuntos(array $assuntos): array
    {
        if (empty($assuntos)) {
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

    private function formatarMovimentos(array $movimentos): array
    {
        if (empty($movimentos)) {
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
            ];
        }
        return $resultado;
    }

    private function formatarOrgao($orgao): array
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

    private function formatarCodigoNome($obj): array
    {
        if (!is_array($obj)) {
            return [];
        }
        return [
            'codigo' => $obj['codigo'] ?? null,
            'descricao' => $obj['nome'] ?? $obj['descricao'] ?? null,
        ];
    }

    private function formatarPrioridades(array $prioridades): array
    {
        if (empty($prioridades)) {
            return [];
        }
        $lista = [];
        foreach ($prioridades as $item) {
            if (is_string($item)) {
                $lista[] = $item;
            } elseif (is_array($item)) {
                $lista[] = $item['descricaoPrioridade'] ?? $item['descricao'] ?? implode(', ', $item);
            }
        }
        return array_values(array_filter($lista));
    }

    private function formatarPolo(array $polos): array
    {
        $passivo = [];
        foreach ($polos as $polo) {
            $tipo = $polo['polo'] ?? ($polo['tipoPolo'] ?? null);
            if ($tipo !== 'PA' && strtolower($tipo ?? '') !== 'polo passivo') {
                continue;
            }
            $partes = (array) ($polo['parte'] ?? $polo['partes'] ?? []);
            foreach ($partes as $parte) {
                $nome = $parte['nome'] ?? ($parte['descricao'] ?? null);
                if ($nome) {
                    $passivo[] = trim($nome);
                }
            }
        }
        return array_values(array_unique($passivo));
    }
}
