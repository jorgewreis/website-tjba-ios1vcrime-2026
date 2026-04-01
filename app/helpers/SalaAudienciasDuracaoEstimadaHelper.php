<?php
declare(strict_types=1);

/**
 * Estimativa de duração da audiência (minutos) com base no tipo de audiência e nas partes ouvidas.
 * Valores orientativos para painel de pauta; ajuste os minutos conforme a prática local.
 */
final class SalaAudienciasDuracaoEstimadaHelper
{
    /** Minutos por tipo de parte (exceto exceções em minutosPartePorContexto). */
    private const MINUTOS_PADRAO_TIPO_PARTE = [
        'reu' => 15,
        'vitima' => 10,
        'testemunha_defesa' => 5,
        'testemunha_denuncia' => 10,
        'advogado' => 10,
        'defensor_publico' => 10,
        'defensoria_publica' => 10,
        'outro' => 5,
    ];

    /**
     * Minutos estimados para uma parte, considerando o nome do tipo de audiência (ex.: suspensão condicional).
     */
    public static function minutosPartePorContexto(string $tipoAudienciaNome, string $tipoParte): int
    {
        $tp = strtolower(trim($tipoParte));
        $ta = function_exists('mb_strtolower')
            ? mb_strtolower($tipoAudienciaNome, 'UTF-8')
            : strtolower($tipoAudienciaNome);

        if ($tp === 'reu' && self::audienciaEhSuspensaoCondicional($ta)) {
            return 5;
        }

        return self::MINUTOS_PADRAO_TIPO_PARTE[$tp] ?? 5;
    }

    private static function audienciaEhSuspensaoCondicional(string $taLower): bool
    {
        $temSusp = self::strHas($taLower, 'suspensão') || self::strHas($taLower, 'suspensao');
        $temCond = self::strHas($taLower, 'condicional') || self::strHas($taLower, 'proposta');

        return $temSusp && $temCond;
    }

    private static function strHas(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * Soma dos minutos das partes ativas com cumprimento "cumprido" (intimação cumprida).
     * Partes pendentes, não cumpridas ou parciais não entram na estimativa de duração.
     *
     * @param list<array<string, mixed>> $partesLinhas
     */
    public static function totalEstimadoMinutos(string $tipoAudienciaNome, array $partesLinhas): int
    {
        $total = 0;
        foreach ($partesLinhas as $p) {
            if (isset($p['ativo']) && (int) $p['ativo'] !== 1) {
                continue;
            }
            $cumpr = strtolower(trim((string) ($p['cumprimento'] ?? '')));
            if ($cumpr !== 'cumprido') {
                continue;
            }
            $tipo = trim((string) ($p['tipo_parte'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            $total += self::minutosPartePorContexto($tipoAudienciaNome, $tipo);
        }

        return $total;
    }

    /**
     * Normaliza hora para HH:MM:SS (a partir de HH:MM ou HH:MM:SS).
     */
    public static function normalizarHoraParaSql(string $horaRaw): ?string
    {
        $horaRaw = trim($horaRaw);
        if ($horaRaw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $horaRaw, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $horaRaw, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    public static function montarDataHoraDesignacao(string $dataYmd, string $horaRaw): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataYmd)) {
            return null;
        }
        $hora = self::normalizarHoraParaSql($horaRaw);
        if ($hora === null) {
            return null;
        }

        return $dataYmd . ' ' . $hora;
    }

    /**
     * Hora de fim no mesmo dia da data da audiência (início + minutos).
     */
    public static function horaFimApartirDeInicio(string $dataYmd, ?string $horaInicioHms, int $minutos): ?string
    {
        if ($horaInicioHms === null || $horaInicioHms === '') {
            return null;
        }
        if ($minutos <= 0) {
            return $horaInicioHms;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataYmd)) {
            return $horaInicioHms;
        }
        try {
            $dt = new \DateTimeImmutable($dataYmd . ' ' . substr($horaInicioHms, 0, 8));

            return $dt->modify('+' . $minutos . ' minutes')->format('H:i:s');
        } catch (\Throwable $e) {
            return $horaInicioHms;
        }
    }
}
