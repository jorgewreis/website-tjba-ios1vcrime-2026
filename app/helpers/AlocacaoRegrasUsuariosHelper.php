<?php
declare(strict_types=1);

/**
 * Lista e validação de usuários internos para selects de regras de alocação
 * (alinhado a {@see UsuariosListRepository}: perfil operacional, não apenas ESTUDANTE).
 */
final class AlocacaoRegrasUsuariosHelper
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public static function listUsuariosInternos(PDO $connection): array
    {
        $stmt = $connection->prepare("
            SELECT u.`id_usuario`, u.`nome`, u.`sobrenome`
            FROM `usuarios` u
            WHERE u.`id_usuario` IN (
                SELECT DISTINCT `usuario` FROM `usuario_perfil` WHERE `perfil` <> 'ESTUDANTE'
            )
            ORDER BY u.`nome`, u.`sobrenome`
        ");
        $stmt->execute();
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = trim((string) ($row['id_usuario'] ?? ''));
            $nome = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $name = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            if ($id !== '' && $name !== '') {
                $out[] = ['id' => $id, 'name' => $name];
            }
        }

        return $out;
    }

    public static function isUsuarioElegivelParaRegraInterna(PDO $connection, string $usuarioId): bool
    {
        $usuarioId = trim($usuarioId);
        if ($usuarioId === '') {
            return false;
        }
        $stmt = $connection->prepare("
            SELECT 1
            FROM `usuarios` u
            WHERE u.`id_usuario` = :id
              AND u.`id_usuario` IN (
                  SELECT DISTINCT `usuario` FROM `usuario_perfil` WHERE `perfil` <> 'ESTUDANTE'
              )
            LIMIT 1
        ");
        $stmt->bindValue(':id', $usuarioId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
