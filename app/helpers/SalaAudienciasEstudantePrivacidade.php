<?php
declare(strict_types=1);

final class SalaAudienciasEstudantePrivacidade
{
    public static function indisponivel(array $row): bool
    {
        return !empty($row['processo_sigiloso'])
            || !empty($row['envolve_menor'])
            || (int) ($row['qtd_menor'] ?? 0) > 0
            || !empty($row['tem_prioridade_bloqueio']);
    }

    public static function respostaIndisponivel(string $id): array
    {
        return ['id_pauta' => $id, 'indisponivel' => true];
    }
}
