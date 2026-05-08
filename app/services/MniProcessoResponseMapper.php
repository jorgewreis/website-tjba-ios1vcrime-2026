<?php
declare(strict_types=1);

final class MniProcessoResponseMapper
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function mapToOutput(string $cnjDigits, array $payload): array
    {
        $flat = self::flatten($payload);

        $basic = [
            'numero_consultado' => $cnjDigits,
            'numero_processo' => self::firstByPatterns($flat, ['numeroprocesso', 'nrprocesso']),
            'classe' => self::firstByPatterns($flat, ['classe.nome', 'classe.descricao', 'classe']),
            'assunto' => self::firstByPatterns($flat, ['assunto.descricao', 'assunto.nome', 'assunto']),
            'orgao_julgador' => self::firstByPatterns($flat, ['orgaojulgador.nome', 'orgaojulgador.descricao', 'orgaojulgador']),
            'comarca' => self::firstByPatterns($flat, ['comarca.nome', 'comarca.descricao', 'comarca']),
            'situacao' => self::firstByPatterns($flat, ['situacaoprocesso', 'situacao']),
            'fase' => self::firstByPatterns($flat, ['faseprocessual', 'fase']),
            'data_distribuicao' => self::firstByPatterns($flat, ['datadistribuicao', 'dt_distribuicao']),
            'ultima_atualizacao' => self::firstByPatterns($flat, ['dataultimomovimento', 'dt_ultimomovimento', 'dataatualizacao']),
        ];

        $partes = self::collectGrouped($flat, ['parte', 'polo', 'advogado']);
        $andamentos = self::collectGrouped($flat, ['movimento', 'andamento']);
        $documentos = self::collectGrouped($flat, ['documento', 'comunicacao', 'teor']);

        return [
            'resumo' => self::normalizeNullables($basic),
            'partes' => $partes,
            'andamentos' => $andamentos,
            'documentos' => $documentos,
            'dados_brutos' => $payload,
        ];
    }

    /**
     * @param array<string,mixed> $flat
     * @param list<string> $patterns
     */
    private static function firstByPatterns(array $flat, array $patterns): ?string
    {
        foreach ($flat as $path => $value) {
            $pathNorm = self::normalize($path);
            foreach ($patterns as $pattern) {
                if (str_contains($pathNorm, self::normalize($pattern))) {
                    return self::toScalarString($value);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $flat
     * @param list<string> $groupKeys
     * @return list<array{campo:string,valor:string}>
     */
    private static function collectGrouped(array $flat, array $groupKeys): array
    {
        $rows = [];

        foreach ($flat as $path => $value) {
            $pathNorm = self::normalize($path);
            foreach ($groupKeys as $groupKey) {
                if (!str_contains($pathNorm, self::normalize($groupKey))) {
                    continue;
                }

                $scalar = self::toScalarString($value);
                if ($scalar === null) {
                    continue;
                }

                $rows[] = [
                    'campo' => $path,
                    'valor' => $scalar,
                ];
                break;
            }
        }

        return $rows;
    }

    /**
     * @param mixed $value
     */
    private static function toScalarString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            $string = trim((string) $value);
            return $string === '' ? null : $string;
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function flatten($value, string $prefix = ''): array
    {
        $out = [];

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            if ($prefix !== '') {
                $out[$prefix] = $value;
            }
            return $out;
        }

        foreach ($value as $key => $item) {
            $segment = is_int($key) ? (string) $key : trim((string) $key);
            $path = $prefix === '' ? $segment : $prefix . '.' . $segment;

            if (is_array($item) || is_object($item)) {
                $out += self::flatten($item, $path);
                continue;
            }

            $out[$path] = $item;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    private static function normalizeNullables(array $summary): array
    {
        foreach ($summary as $key => $value) {
            if ($value === null || $value === '') {
                $summary[$key] = 'Não informado';
            }
        }
        return $summary;
    }

    private static function normalize(string $value): string
    {
        return strtolower(str_replace(['_', '-', ' '], '', $value));
    }
}
