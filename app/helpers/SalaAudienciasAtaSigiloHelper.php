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
    public const MSG_PROCESSO_BLOQUEADO_ESTUDANTE = 'Esta audiência não está disponível para estudantes por envolver processo com prioridade bloqueante.';

    /**
     * Expressão SQL (0|1): processo sigiloso por flag em `processos` ou prioridade `sigiloso`.
     */
    public static function sqlProcessoSigilosoEfetivo(string $aliasProcesso = 'p', string $aliasPauta = 'a'): string
    {
        $codigo = PrioridadesProcessuaisCatalog::codigoSigiloso();

        return sprintf(
            'CASE WHEN COALESCE(%1$s.`sigiloso`, 0) = 1 OR EXISTS (
                SELECT 1 FROM `processo_prioridades` pri
                WHERE pri.`processo_id` = %2$s.`processo`
                  AND pri.`codigo` = %3$s
              ) THEN 1 ELSE 0 END',
            $aliasProcesso,
            $aliasPauta,
            self::quoteSqlLiteral($codigo)
        );
    }

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

    /**
     * Expressão SQL (0|1): processo com prioridade que bloqueia visibilidade/inscrição de estudantes.
     */
    public static function sqlPrioridadeBloqueanteEstudante(string $aliasPauta = 'a'): string
    {
        return sprintf(
            'CASE WHEN EXISTS (
                SELECT 1 FROM `processo_prioridades` pri
                WHERE pri.`processo_id` = %1$s.`processo`
                  AND pri.`codigo` IN (%2$s)
              ) THEN 1 ELSE 0 END',
            $aliasPauta,
            self::quoteSqlLiteralList(PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante())
        );
    }

    /**
     * Fragmento SQL: exclui pautas sigilosas ou com prioridade bloqueante para estudantes.
     */
    public static function sqlExcluirProcessoBloqueanteEstudante(string $aliasPauta = 'a', string $aliasProcesso = 'p'): string
    {
        return sprintf(
            ' AND COALESCE(%1$s.`sigiloso`, 0) = 0
              AND NOT EXISTS (
                SELECT 1 FROM `processo_prioridades` pri
                WHERE pri.`processo_id` = %2$s.`processo`
                  AND pri.`codigo` IN (%3$s)
              )',
            $aliasProcesso,
            $aliasPauta,
            self::quoteSqlLiteralList(PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante())
        );
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param list<string> $values
     */
    private static function quoteSqlLiteralList(array $values): string
    {
        $quoted = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $quoted[] = self::quoteSqlLiteral($value);
            }
        }

        return $quoted !== [] ? implode(', ', $quoted) : "''";
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

    /**
     * Bloqueia acesso de estudantes se a pauta tiver sigilo efetivo ou qualquer prioridade
     * do catálogo que impede inscrição/visibilidade externa.
     *
     * @param array<string, mixed> $pauta
     */
    public static function assertPautaPermiteAcessoEstudante(PDO $connection, array $pauta): void
    {
        self::assertPautaPermiteAtas($connection, $pauta);

        $processoId = trim((string) ($pauta['processo'] ?? $pauta['processo_id'] ?? ''));
        if ($processoId === '') {
            return;
        }

        require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
        $codigosBloqueantes = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
        $prioridadesRepo = new ProcessoPrioridadesRepository($connection);
        foreach ($prioridadesRepo->findByProcessoId($processoId) as $prioridade) {
            $codigo = trim((string) ($prioridade['codigo'] ?? ''));
            if ($codigo !== '' && in_array($codigo, $codigosBloqueantes, true)) {
                throw new InvalidArgumentException(self::MSG_PROCESSO_BLOQUEADO_ESTUDANTE);
            }
        }
    }
}
