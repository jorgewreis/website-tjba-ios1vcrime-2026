<?php
declare(strict_types=1);

require_once __DIR__ . '/CnjValidator.php';
require_once __DIR__ . '/ProcessoNivelSigilo.php';
require_once __DIR__ . '/../DataJud/DataJudTribunalResolver.php';

final class ProcessoFicha
{
    private const POLOS = ['ativo'=>'Polo ativo', 'passivo'=>'Polo passivo', 'terceiros'=>'Terceiros', 'indefinido'=>'Polo não informado'];
    private const PAPEIS_PRINCIPAIS = [
        'autor' => ['ativo', 'Autor', 10],
        'requerente' => ['ativo', 'Requerente', 20],
        'reu' => ['passivo', 'Réu', 10],
        'vitima' => ['terceiros', 'Vítima', 10],
        'testemunha_denuncia' => ['terceiros', 'Testemunha da Denúncia', 20],
        'testemunha_defesa' => ['terceiros', 'Testemunha da Defesa', 30],
    ];
    private const PAPEIS_ADICIONAIS = ['advogado'=>'Advogado', 'defensor_publico'=>'Defensor Público',
        'indiciado'=>'Indiciado', 'sentenciado'=>'Sentenciado', 'requerido'=>'Requerido'];

    /** Ordenação visual; não altera o papel ou o polo persistido. */
    public static function partesPorPolo(array $partes): array
    {
        $grupos = array_fill_keys(array_keys(self::POLOS), []);
        foreach ($partes as $parte) {
            if (!is_array($parte)) continue;
            $tipo = trim((string) ($parte['tipo_parte'] ?? ''));
            $polo = trim((string) ($parte['polo'] ?? ''));
            [$grupo, $papel, $ordem] = self::PAPEIS_PRINCIPAIS[$tipo]
                ?? [isset(self::POLOS[$polo]) ? $polo : 'indefinido',
                    self::PAPEIS_ADICIONAIS[$tipo] ?? ($tipo !== '' ? ucfirst(str_replace('_', ' ', $tipo)) : 'Parte'), 90];
            if ($tipo === 'advogado' && $polo === 'passivo') {
                $papel = 'Advogado do réu';
                $ordem = 20;
            }
            $grupos[$grupo][] = [
                'registro' => $parte, 'papel' => $papel, 'ordem' => $ordem,
                'destaque' => in_array($tipo, ['reu', 'vitima'], true),
            ];
        }
        $resultado = [];
        foreach ($grupos as $polo => $itens) {
            if ($itens === []) continue;
            usort($itens, static fn(array $a, array $b): int => ($a['ordem'] <=> $b['ordem'])
                ?: strcasecmp((string) ($a['registro']['nome_completo'] ?? ''), (string) ($b['registro']['nome_completo'] ?? '')));
            $resultado[$polo] = ['rotulo' => self::POLOS[$polo], 'partes' => $itens];
        }
        return $resultado;
    }

    public static function origem(string $numero): array
    {
        $vazio = ['segmento' => null, 'tribunal' => null, 'unidade' => null, 'comarca' => null, 'ano' => null];
        if (!CnjValidator::isValid($numero)) {
            return $vazio;
        }
        $digitos = CnjValidator::onlyDigits($numero);
        $segmentos = ['1'=>'Supremo Tribunal Federal','2'=>'Conselho Nacional de Justiça','3'=>'Superior Tribunal de Justiça',
            '4'=>'Justiça Federal','5'=>'Justiça do Trabalho','6'=>'Justiça Eleitoral','7'=>'Justiça Militar da União',
            '8'=>'Justiça Estadual','9'=>'Justiça Militar Estadual'];
        $j = $digitos[13];
        $alias = DataJudTribunalResolver::resolveAliasFromDigits($digitos);
        $ufs = ['ac'=>'do Acre','al'=>'de Alagoas','am'=>'do Amazonas','ap'=>'do Amapá','ba'=>'da Bahia','ce'=>'do Ceará',
            'es'=>'do Espírito Santo','go'=>'de Goiás','ma'=>'do Maranhão','mt'=>'de Mato Grosso','ms'=>'de Mato Grosso do Sul',
            'mg'=>'de Minas Gerais','pa'=>'do Pará','pb'=>'da Paraíba','pr'=>'do Paraná','pe'=>'de Pernambuco','pi'=>'do Piauí',
            'rj'=>'do Rio de Janeiro','rn'=>'do Rio Grande do Norte','rs'=>'do Rio Grande do Sul','ro'=>'de Rondônia',
            'rr'=>'de Roraima','sc'=>'de Santa Catarina','sp'=>'de São Paulo','se'=>'de Sergipe','to'=>'do Tocantins',
            'df'=>'do Distrito Federal'];
        $tribunal = match (true) {
            $j === '1' => 'Supremo Tribunal Federal (STF)',
            $j === '2' => 'Conselho Nacional de Justiça (CNJ)',
            $alias === 'stj' => 'Superior Tribunal de Justiça (STJ)',
            $alias === 'tst' => 'Tribunal Superior do Trabalho (TST)',
            $alias === 'tse' => 'Tribunal Superior Eleitoral (TSE)',
            $alias === 'stm' => 'Superior Tribunal Militar (STM)',
            $alias === 'tjdft' => 'Tribunal de Justiça do Distrito Federal e dos Territórios (TJDFT)',
            $alias !== null && $j === '8' && isset($ufs[substr($alias, 2)]) => 'Tribunal de Justiça ' . $ufs[substr($alias, 2)] . ' (' . strtoupper($alias) . ')',
            $alias !== null && $j === '9' && isset($ufs[substr($alias, 3)]) => 'Tribunal de Justiça Militar ' . $ufs[substr($alias, 3)] . ' (' . strtoupper($alias) . ')',
            $alias !== null && preg_match('/^trf(\d+)$/D', $alias, $m) === 1 => 'Tribunal Regional Federal da ' . $m[1] . 'ª Região (' . strtoupper($alias) . ')',
            $alias !== null && preg_match('/^trt(\d+)$/D', $alias, $m) === 1 => 'Tribunal Regional do Trabalho da ' . $m[1] . 'ª Região (' . strtoupper($alias) . ')',
            $alias !== null && str_starts_with($alias, 'tre-') && isset($ufs[substr($alias, 4)]) => 'Tribunal Regional Eleitoral ' . $ufs[substr($alias, 4)] . ' (' . strtoupper($alias) . ')',
            default => null,
        };
        $unidade = substr($digitos, 16, 4);
        // A associação é da origem do número, nunca do órgão julgador após redistribuição.
        $comarcasVerificadas = ['tjba' => ['0103' => 'Ilhéus']];
        return ['segmento'=>$segmentos[$j] ?? null, 'tribunal'=>$tribunal, 'unidade'=>$unidade,
            'comarca'=>$comarcasVerificadas[$alias ?? ''][$unidade] ?? null, 'ano'=>substr($digitos, 9, 4)];
    }

    public static function retorno(string $valor): ?string
    {
        if ($valor === '' || strlen($valor) > 1500) {
            return null;
        }
        $url = parse_url($valor);
        if (!is_array($url) || ($url['path'] ?? '') !== '/frame.php'
            || array_diff(array_keys($url), ['path','query']) !== []) {
            return null;
        }
        parse_str($url['query'] ?? '', $params);
        $page = $params['page'] ?? null;
        if (!is_string($page) || preg_match('/^[a-z][a-z0-9]*$/D', $page) !== 1 || $page === 'consultaprocessosedit') {
            return null;
        }
        unset($params['voltar']);
        return '/frame.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function dataJudPublico(array $data): array
    {
        $nivel = ProcessoNivelSigilo::classificado($data['nivel_sigilo'] ?? null);
        if (!empty($data['sigilo_restrito'])) {
            return ['estado'=>'restrito', 'sigilo'=>$nivel !== null ? ProcessoNivelSigilo::ROTULOS[$nivel] : null];
        }
        if (!empty($data['indisponivel_na_base_publica'])
            || ($data['resultado_unico_verificado'] ?? false) !== true
            || ($data['sigilo_ausencia_verificada'] ?? false) !== true) {
            return ['estado'=>'indisponivel'];
        }

        $texto = static fn(mixed $v): string => is_string($v) ? trim($v) : '';
        $movimentos = [];
        foreach (($data['movimentos'] ?? []) as $mov) {
            if (!is_array($mov)) continue;
            $nome = $texto($mov['nome'] ?? null);
            $dataHora = $texto($mov['dataHora'] ?? null);
            if ($nome === '') continue;
            $movimentos[] = ['nome'=>$nome, 'data'=>$dataHora];
        }
        $assuntos = [];
        foreach (($data['assuntos'] ?? []) as $item) {
            $nome = is_array($item) ? $texto($item['descricao'] ?? null) : '';
            if ($nome !== '') $assuntos[] = $nome;
        }

        // Sem nomes de partes, documentos ou complementos de andamentos neste retorno.
        return ['estado'=>'disponivel', 'sigilo'=>'Público', 'vara'=>$texto($data['orgao_julgador']['nome'] ?? null),
            'grau'=>$texto($data['grau'] ?? null),
            'classe'=>$texto($data['classe']['descricao'] ?? null), 'assuntos'=>array_values(array_unique($assuntos)),
            'movimentos'=>$movimentos];
    }
}
