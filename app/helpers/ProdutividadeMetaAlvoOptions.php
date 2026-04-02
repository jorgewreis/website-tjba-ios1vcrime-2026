<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/AccessAreas.php';

/**
 * Opções de alvo para metas de produtividade (setor / perfil).
 */
final class ProdutividadeMetaAlvoOptions
{
    /**
     * @return array<string, string> codigo_area => rótulo
     */
    public static function setorChoices(): array
    {
        return [
            AccessAreas::CARTORIO => 'Cartório',
            AccessAreas::GABINETE => 'Gabinete',
            AccessAreas::AUDIENCIAS => 'Sala de Audiências',
        ];
    }

    /**
     * Perfis compostos padrão + distintos já usados em usuario_perfil.
     *
     * @return list<string>
     */
    public static function perfilValuesForSelect(PDO $pdo): array
    {
        $static = [];
        foreach (['ESTAGIARIO', 'SERVIDOR'] as $cargo) {
            foreach (AccessAreas::ALL as $area) {
                $static[] = $cargo . '-' . $area;
            }
        }
        $fromDb = [];
        try {
            $statement = $pdo->query(
                'SELECT DISTINCT TRIM(`perfil`) AS `p` FROM `usuario_perfil` WHERE `perfil` IS NOT NULL AND TRIM(`perfil`) <> \'\''
            );
            if ($statement !== false) {
                while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $p = trim((string) ($row['p'] ?? ''));
                    if ($p !== '') {
                        $fromDb[] = $p;
                    }
                }
            }
        } catch (Throwable) {
            // mantém só estáticos
        }

        $merged = array_values(array_unique(array_merge($static, $fromDb)));
        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        return $merged;
    }

    /**
     * Rótulo para exibição; o valor persistido continua sendo o código exato de usuario_perfil.
     */
    public static function perfilLabel(string $perfil): string
    {
        $raw = trim($perfil);
        if ($raw === '') {
            return '';
        }

        $norm = strtoupper(str_replace([' ', '_'], ['', '-'], $raw));

        if (preg_match('/^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $norm, $m) === 1) {
            $cargo = $m[1] === 'ESTAGIARIO' ? 'Estagiário(a)' : 'Servidor(a)';
            $areaLabels = [
                'CARTORIO' => 'Cartório',
                'GABINETE' => 'Gabinete',
                'AUDIENCIAS' => 'Sala de Audiências',
            ];
            $area = $areaLabels[$m[2]] ?? $m[2];

            return $cargo . ' — ' . $area;
        }

        $singles = [
            'ADMIN' => 'Administrador(a)',
            'CHEFIA' => 'Chefia',
            'DIRETORA' => 'Diretoria',
            'MAGISTRADA' => 'Magistratura',
            'ESTUDANTE' => 'Estudante',
            'CONSULTOR' => 'Consultor(a)',
        ];
        if (isset($singles[$norm])) {
            return $singles[$norm];
        }

        return $raw;
    }
}
