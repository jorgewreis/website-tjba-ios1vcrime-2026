<?php

declare(strict_types=1);

/**
 * Monta o JSON de dados_especificos nas APIs de concluir subtarefa do cartório,
 * sempre com usuario_id da sessão quando existir (obrigatório para produtividade automática).
 *
 * Antes, usuario_id só era enviado quando nome+sobrenome geravam texto não vazio,
 * o que impedia o lançamento para vários usuários (ex.: gerar PDF em guias).
 */
final class CartorioSubtarefaUsuarioSessao
{
    public static function mergeDadosEspecificosComSessao(PDO $connection, mixed $dadosRecebidos): ?string
    {
        $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
        $nomeUsuario = '';
        if ($usuarioId !== '') {
            $stmt = $connection->prepare('SELECT `nome`, `sobrenome`, `email` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1');
            $stmt->bindValue(':id', $usuarioId);
            $stmt->execute();
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u !== false) {
                $nome = trim((string) ($u['nome'] ?? ''));
                $sobrenome = trim((string) ($u['sobrenome'] ?? ''));
                $nomeUsuario = $sobrenome !== '' ? trim($nome . ' ' . $sobrenome) : $nome;
                if ($nomeUsuario === '') {
                    $nomeUsuario = trim((string) ($u['email'] ?? ''));
                }
            }
        }

        $merge = [];
        if ($usuarioId !== '') {
            $merge['usuario_id'] = $usuarioId;
            if ($nomeUsuario !== '') {
                $merge['nome'] = $nomeUsuario;
            }
        }

        if ($dadosRecebidos !== null && $dadosRecebidos !== '') {
            $arr = is_string($dadosRecebidos) ? json_decode($dadosRecebidos, true) : $dadosRecebidos;
            if (is_array($arr)) {
                $merge = array_merge($arr, $merge);
            }
        }

        if ($merge === []) {
            return null;
        }

        return json_encode($merge, JSON_UNESCAPED_UNICODE);
    }
}
