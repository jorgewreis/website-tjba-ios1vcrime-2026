<?php
declare(strict_types=1);

/** Origem persistida em `processo_prioridades.origem`. */
final class ProcessoPrioridadeOrigem
{
    public const MANUAL = 'manual';
    public const CADASTRO_GUIAS = 'cadastro_guias';
    public const REGRA_MENOR = 'regra_menor';
    public const REGRA_REU_PRESO = 'regra_reu_preso';
    public const REGRA_VIOLENCIA_DOMESTICA_MP = 'regra_violencia_domestica_mp';
    public const REGRA_SIGILOSO = 'regra_sigiloso';
    /** Sigiloso obrigatório quando existir prioridade de menor ou de violência doméstica. */
    public const REGRA_DERIVADA_MENOR_OU_VD = 'regra_derivada_menor_ou_vd';
    public const MIGRACAO_LEGADO = 'migracao_legado';
    public const MIGRACAO_PROCESSO_PRIORIDADES = 'migracao_processoPrioridades';

    public static function isRegraAutomatica(string $origem): bool
    {
        return str_starts_with(trim($origem), 'regra_');
    }
}
