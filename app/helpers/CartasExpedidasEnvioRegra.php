<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';

final class CartasExpedidasEnvioRegra
{
    public const NOME = 'ALLOCATE-CARTAS-EXPEDIDAS-ENVIO';

    public static function podeAlterar(array $perfis, bool $piloto = false): bool
    {
        $permitidos = $piloto ? ['ADMIN', 'CHEFIA'] : ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
        return array_intersect($perfis, $permitidos) !== [];
    }

    /** @return list<array{id:string,nome:string}> */
    public static function elegiveis(PDO $pdo, ?string $id = null): array
    {
        $sql = "SELECT u.id_usuario, u.nome, u.sobrenome
            FROM usuarios u
            WHERE COALESCE(u.cadastro_ativo, 1) = 1 AND EXISTS (
                SELECT 1 FROM usuario_perfil p WHERE p.usuario = u.id_usuario AND p.perfil <> 'ESTUDANTE'
            )";
        if ($id !== null) {
            $sql .= ' AND u.id_usuario = :id';
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY u.nome, u.sobrenome, u.id_usuario');
        $stmt->execute($id === null ? [] : [':id' => $id]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nome = trim(trim((string) $row['nome']) . ' ' . trim((string) $row['sobrenome']));
            if ($nome !== '') {
                $out[] = ['id' => (string) $row['id_usuario'], 'nome' => $nome];
            }
        }
        return $out;
    }

    /** @return array{id:string,nome:string}|null */
    public static function responsavel(PDO $pdo): ?array
    {
        $regra = (new AlocacoesRegrasRepository($pdo))->findByRegra(self::NOME);
        $id = trim((string) ($regra['usuario_id'] ?? ''));
        return $id !== '' ? (self::elegiveis($pdo, $id)[0] ?? null) : null;
    }

    public static function responsavelNaEtapa(string $etapa, ?array $responsavel): string
    {
        return $etapa === 'envio' ? (string) ($responsavel['nome'] ?? '') : '';
    }
}
