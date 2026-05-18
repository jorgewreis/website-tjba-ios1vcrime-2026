<?php
declare(strict_types=1);

require_once __DIR__ . '/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/ProcessoBlocoListagemHelper.php';

/**
 * Regras de atas: processos sigilosos não podem ser listados nem receber/enviar PDF de ata.
 */
final class SalaAudienciasAtaSigiloHelper
{
    public const MSG_PROCESSO_SIGILOSO = 'Processos sigilosos não permitem cadastro, envio nem consulta de atas de audiência.';

    /**
     * Fragmento SQL: exclui pautas cujo processo é sigiloso (flag ou prioridade).
     */
    public static function sqlExcluirProcessoSigiloso(string $aliasPauta = 'a', string $aliasProcesso = 'p'): string
    {
        $codigo = PrioridadesProcessuaisCatalog::codigoSigiloso();

        return sprintf(
            ' AND COALESCE(%1$s.`sigiloso`, 0) = 0
              AND NOT EXISTS (
                SELECT 1 FROM `processo_prioridades` pri
                WHERE pri.`processo_id` = %2$s.`processo`
                  AND pri.`codigo` = %3$s
              )',
            $aliasProcesso,
            $aliasPauta,
            self::quoteSqlLiteral($codigo)
        );
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function linhaIndicaProcessoSigiloso(array $row): bool
    {
        if (ProcessoBlocoListagemHelper::rawProcessoSigilosoFlag($row)) {
            return true;
        }

        $pps = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
        foreach ($pps as $p) {
            if (!is_array($p)) {
                continue;
            }
            if (trim((string) ($p['codigo'] ?? '')) === PrioridadesProcessuaisCatalog::codigoSigiloso()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $pauta Linha de pauta com `processo` / `processo_sigiloso`.
     */
    public static function assertPautaPermiteAtas(PDO $connection, array $pauta): void
    {
        if ((int) ($pauta['processo_sigiloso'] ?? 0) !== 0) {
            throw new InvalidArgumentException(self::MSG_PROCESSO_SIGILOSO);
        }

        $processoId = trim((string) ($pauta['processo'] ?? $pauta['processo_id'] ?? ''));
        if ($processoId === '') {
            return;
        }

        require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
        $prioridadesRepo = new ProcessoPrioridadesRepository($connection);
        foreach ($prioridadesRepo->findByProcessoId($processoId) as $prioridade) {
            if (trim((string) ($prioridade['codigo'] ?? '')) === PrioridadesProcessuaisCatalog::codigoSigiloso()) {
                throw new InvalidArgumentException(self::MSG_PROCESSO_SIGILOSO);
            }
        }
    }
}
