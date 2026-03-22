<?php
declare(strict_types=1);

/**
 * Lotação interna: rótulo de formulário ↔ codigo_area (CARTORIO | GABINETE | AUDIENCIAS).
 */
final class SetoresAccessHelper
{
    public const LABEL_TO_CODIGO_AREA = [
        'Cartório' => 'CARTORIO',
        'Gabinete' => 'GABINETE',
        'Sala de Audiências' => 'AUDIENCIAS',
    ];

    private const OPERATIONAL_AREAS = ['CARTORIO', 'GABINETE', 'AUDIENCIAS'];

    public static function isOperationalCodigoArea(string $area): bool
    {
        return in_array(strtoupper(trim($area)), self::OPERATIONAL_AREAS, true);
    }

    /**
     * @return array{id_setor: string, nome: string, codigo_area: string}|null
     */
    public static function resolveFromLabel(PDO $connection, string $label): ?array
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        $mappedArea = self::LABEL_TO_CODIGO_AREA[$label] ?? null;
        if ($mappedArea !== null) {
            $row = self::fetchByCodigoArea($connection, $mappedArea);
            if ($row !== null) {
                return $row;
            }
        }
        $stmt = $connection->prepare(
            'SELECT id_setor, nome, COALESCE(codigo_area, \'\') AS codigo_area FROM setores WHERE nome = ? AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([$label]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id_setor'])) {
            return self::rowToTuple($row);
        }
        return null;
    }

    /**
     * @return array{id_setor: string, nome: string, codigo_area: string}|null
     */
    public static function resolveFromCodigoArea(PDO $connection, string $codigoArea): ?array
    {
        return self::fetchByCodigoArea($connection, $codigoArea);
    }

    /**
     * Expande perfis antigos (ESTAGIARIO, SERVIDOR, CARTORIO…) para pré-marcar o formulário de edição.
     *
     * @param array<int, string> $perfisAtuais
     * @return list<string>
     */
    public static function expandPerfisForFormUi(array $perfisAtuais, PDO $connection, string $idUsuario): array
    {
        $perfis = [];
        foreach ($perfisAtuais as $p) {
            $u = strtoupper(trim((string) $p));
            if ($u !== '') {
                $perfis[] = $u;
            }
        }
        $perfis = array_values(array_unique($perfis));

        $out = [];
        $hasComposite = false;
        foreach ($perfis as $p) {
            if (preg_match('/^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $p) === 1) {
                $hasComposite = true;
                $out[] = $p;
            }
            if ($p === 'ADMIN') {
                $out[] = 'ADMIN';
            }
            if ($p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
                $out[] = 'CHEFIA';
            }
        }

        $areaFromSetor = null;
        if (!$hasComposite) {
            $stmt = $connection->prepare(
                'SELECT UPPER(TRIM(s.codigo_area)) AS ca FROM usuario_setor us
                 INNER JOIN setores s ON us.setor = s.id_setor
                 WHERE us.usuario = ? AND s.codigo_area IS NOT NULL AND TRIM(s.codigo_area) <> \'\' LIMIT 1'
            );
            $stmt->execute([$idUsuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['ca'])) {
                $areaFromSetor = strtoupper(trim((string) $row['ca']));
            }
        }

        if (!$hasComposite && $areaFromSetor !== null && self::isOperationalCodigoArea($areaFromSetor)) {
            if (in_array('ESTAGIARIO', $perfis, true)) {
                $out[] = 'ESTAGIARIO-' . $areaFromSetor;
            }
            if (in_array('SERVIDOR', $perfis, true)) {
                $out[] = 'SERVIDOR-' . $areaFromSetor;
            }
        }

        $areaOnly = [
            'CARTORIO' => 'CARTORIO',
            'GABINETE' => 'GABINETE',
            'AUDIENCIA' => 'AUDIENCIAS',
            'AUDIENCIAS' => 'AUDIENCIAS',
        ];
        foreach ($perfis as $p) {
            if (isset($areaOnly[$p])) {
                $out[] = 'SERVIDOR-' . $areaOnly[$p];
            }
        }

        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /**
     * @return array{id_setor: string, nome: string, codigo_area: string}|null
     */
    private static function fetchByCodigoArea(PDO $connection, string $codigoArea): ?array
    {
        $area = strtoupper(trim($codigoArea));
        if ($area === '') {
            return null;
        }
        $stmt = $connection->prepare(
            'SELECT id_setor, nome, COALESCE(codigo_area, \'\') AS codigo_area FROM setores WHERE codigo_area = ? AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([$area]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id_setor'])) {
            return self::rowToTuple($row);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id_setor: string, nome: string, codigo_area: string}
     */
    private static function rowToTuple(array $row): array
    {
        return [
            'id_setor' => trim((string) $row['id_setor']),
            'nome' => trim((string) $row['nome']),
            'codigo_area' => strtoupper(trim((string) ($row['codigo_area'] ?? ''))),
        ];
    }
}
