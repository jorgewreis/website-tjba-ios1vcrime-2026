<?php
declare(strict_types=1);

/**
 * Mapeia código CNJ → endpoint MNI de cada tribunal.
 *
 * v1 cobre só o TJBA (única integração com credenciais). Para os demais tribunais
 * o resolve() devolve null e a façade ignora MNI (mantém apenas DataJud).
 *
 * Estrutura do número CNJ (Resolução 65/2008):
 *   NNNNNNN-DD.AAAA.J.TR.OOOO  (sem máscara: 20 dígitos)
 *   - dígito 13   → segmento de Justiça (J): 8 = Justiça Estadual
 *   - dígitos 14-15 → tribunal (TR): 05 = TJBA
 */
final class MniTribunalResolver
{
    /**
     * @return array{endpoint: string, version: string, tribunal: string}|null
     */
    public static function resolveFromDigits(string $digits20, MniCredentials $tjbaCreds): ?array
    {
        $digits = preg_replace('/\D+/', '', $digits20) ?? '';
        if (strlen($digits) !== 20) {
            return null;
        }

        $segmentoJ = $digits[13];   // 1 dígito
        $tribunalTR = substr($digits, 14, 2);

        if ($segmentoJ === '8' && $tribunalTR === '05') {
            if (!$tjbaCreds->isComplete()) {
                return null;
            }
            return [
                'endpoint' => $tjbaCreds->endpoint(),
                'version'  => $tjbaCreds->version(),
                'tribunal' => 'tjba',
            ];
        }

        return null;
    }

    /**
     * Extrai pares (justica, tribunal) para diagnóstico/log — sem expor endpoint.
     *
     * @return array{justica: string, tribunal: string}|null
     */
    public static function describeDigits(string $digits20): ?array
    {
        $digits = preg_replace('/\D+/', '', $digits20) ?? '';
        if (strlen($digits) !== 20) {
            return null;
        }
        return [
            'justica'  => $digits[13],
            'tribunal' => substr($digits, 14, 2),
        ];
    }
}
