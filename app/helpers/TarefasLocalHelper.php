<?php
declare(strict_types=1);

final class TarefasLocalHelper
{
    private const AREAS = ['CARTORIO' => 'Cartório', 'GABINETE' => 'Gabinete', 'AUDIENCIAS' => 'Sala de Audiências'];

    public static function localPadrao(PDO $db, string $userId, array $profiles): string
    {
        $stmt = $db->prepare('SELECT instituicao FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$userId]);
        $label = trim((string) $stmt->fetchColumn());
        foreach (self::AREAS as $location) {
            if (strcasecmp($label, $location) === 0) return $location;
        }

        $stmt = $db->prepare('SELECT s.codigo_area FROM usuario_setor us JOIN setores s ON s.id_setor = us.setor WHERE us.usuario = ? AND s.ativo = 1 ORDER BY us.data_alocacao DESC, s.nome ASC');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $area) {
            $key = strtoupper(trim((string) $area));
            if (isset(self::AREAS[$key])) return self::AREAS[$key];
        }

        $areas = [];
        foreach ($profiles as $profile) {
            $profile = strtoupper(trim((string) $profile));
            if (preg_match('/^(?:SERVIDOR|ESTAGIARIO)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $profile, $match)) $areas[$match[1]] = true;
        }
        if (count($areas) === 1) return self::AREAS[array_key_first($areas)];
        return 'Cartório';
    }
}
