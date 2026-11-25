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

    /**
     * Alocação direcionada na edição: um usuário por vez, com as mesmas regras de elegibilidade do cadastro.
     *
     * @param callable(string):void $addSpecific Recebe o id do usuário alvo e persiste como alocado tipo específico.
     */
    public static function registrarAlocacaoDirecionadaNaEdicao(
        PDO $connection,
        string $actorId,
        string $targetUsuarioId,
        callable $addSpecific,
        bool $allowSelfAllocation = false
    ): void {
        $actorId = trim($actorId);
        $targetUsuarioId = trim($targetUsuarioId);
        if ($actorId === '' || $targetUsuarioId === '') {
            throw new \InvalidArgumentException('Usuário alvo é obrigatório.');
        }
        if (!$allowSelfAllocation && $targetUsuarioId === $actorId) {
            throw new \InvalidArgumentException('Não é possível alocar a si mesmo.');
        }
        if (!self::usuarioAdminOuChefiaPodeAlocarOutrosCadastro($actorId)) {
            throw new \InvalidArgumentException('Sem permissão para alocar servidores.');
        }
        if (!AlocacaoRegrasUsuariosHelper::isUsuarioElegivelParaRegraInterna($connection, $targetUsuarioId)) {
            throw new \InvalidArgumentException('Usuário selecionado não é elegível para alocação.');
        }
        try {
            $addSpecific($targetUsuarioId);
        } catch (LimiteAlocadosException) {
            throw new \InvalidArgumentException('Limite de alocações por guia atingido.');
        }
    }
}
