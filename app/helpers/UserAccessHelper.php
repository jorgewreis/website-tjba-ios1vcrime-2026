<?php
declare(strict_types=1);

/**
 * Classificação de usuário interno vs externo (estudantes / consultores sem vínculo de servidor).
 */
final class UserAccessHelper
{
    /** Perfis que caracterizam acesso à área operacional interna (vara). */
    private const PERFIS_INTERNOS = [
        'ADMIN',
        'CHEFIA',
        'DIRETORA',
        'MAGISTRADA',
        'SERVIDOR',
        'ESTAGIARIO',
        'CARTORIO',
        'GABINETE',
        'AUDIENCIA',
    ];

    /**
     * Usuário externo: sem nenhum perfil interno e com ESTUDANTE e/ou CONSULTOR.
     * Estagiários e servidores com perfil interno nunca são tratados como externos.
     */
    public static function isUsuarioExterno(array $userPerfils): bool
    {
        $norm = [];
        foreach ($userPerfils as $p) {
            $u = strtoupper(trim((string) $p));
            if ($u !== '') {
                $norm[] = $u;
            }
        }

        foreach ($norm as $u) {
            if (in_array($u, self::PERFIS_INTERNOS, true)) {
                return false;
            }
        }

        return in_array('ESTUDANTE', $norm, true) || in_array('CONSULTOR', $norm, true);
    }
}
