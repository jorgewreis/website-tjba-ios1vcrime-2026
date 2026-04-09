<?php
declare(strict_types=1);

require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/AlocacaoRegrasUsuariosHelper.php';

/**
 * Regras de produto para alocação em tarefas:
 * perfis de chefia (CHEFIA, DIRETORA, MAGISTRADA) não recebem autoalocação (cadastro nem regra);
 * ADMIN e os mesmos perfis de chefia podem indicar outros usuários no cadastro em módulos que suportem alocação direcionada (não aplicável a ofícios do cartório).
 */
final class AlocacaoTarefasPerfisHelper
{
    /** Perfis tratados como “chefia” para autoalocação (não entram por regra nem como cadastrante). */
    private const PERFIS_SEM_AUTOALOCACAO = ['CHEFIA', 'DIRETORA', 'MAGISTRADA'];

    /** Perfis que podem alocar outros no cadastro (alocação direcionada), quando o módulo permitir. */
    private const PERFIS_PODE_ALOCAR_OUTROS_CADASTRO = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];

    /**
     * Quem tem perfil de chefia (CHEFIA, DIRETORA ou MAGISTRADA) não entra na tarefa por autoalocação (regra do sistema ou por ter cadastrado).
     */
    public static function usuarioComPerfilChefiaSemAutoalocacao(string $userId): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }
        foreach (UsuarioPerfisAutorizacao::perfisDoUsuario($userId) as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if (in_array($p, self::PERFIS_SEM_AUTOALOCACAO, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pode exibir no cadastro o bloco para alocar outros servidores (ADMIN ou perfis de chefia), quando o módulo tiver alocação direcionada.
     */
    public static function usuarioAdminOuChefiaPodeAlocarOutrosCadastro(string $userId): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }
        foreach (UsuarioPerfisAutorizacao::perfisDoUsuario($userId) as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if (in_array($p, self::PERFIS_PODE_ALOCAR_OUTROS_CADASTRO, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lê `alocar_usuario_ids` do payload (array ou JSON). Usado no cadastro de tarefas do cartório.
     *
     * @return list<string>
     */
    public static function parseAlocarUsuarioIdsDoPayload(array $payload): array
    {
        $raw = $payload['alocar_usuario_ids'] ?? null;
        if ($raw === null) {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $t = trim((string) $id);
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @param callable(string):void $addSpecific Função que adiciona alocado tipo específico (ex.: addAlocado(..., TIPO_SPECIFIC)).
     */
    public static function registrarAlocacoesDirecionadasNoCadastro(
        PDO $connection,
        string $usuarioCadastroId,
        array $payload,
        callable $addSpecific
    ): void {
        $usuarioCadastroId = trim($usuarioCadastroId);
        if ($usuarioCadastroId === '' || !self::usuarioAdminOuChefiaPodeAlocarOutrosCadastro($usuarioCadastroId)) {
            return;
        }
        foreach (self::parseAlocarUsuarioIdsDoPayload($payload) as $uidAlvo) {
            $uidAlvo = trim($uidAlvo);
            if ($uidAlvo === '' || $uidAlvo === $usuarioCadastroId) {
                continue;
            }
            if (!AlocacaoRegrasUsuariosHelper::isUsuarioElegivelParaRegraInterna($connection, $uidAlvo)) {
                continue;
            }
            $addSpecific($uidAlvo);
        }
    }
}
