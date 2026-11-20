<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/UsuariosListRepository.php';

final class UsuariosService
{
    private PDO $connection;

    /** Ordem de setor para ordenação: Cartório > Gabinete > Sala de audiências > — (interno pode ser AUDIENCIA) */
    private const SETOR_ORDER = [
        'cartório'            => 0,
        'cartorio'            => 0,
        'gabinete'            => 1,
        'sala de audiências'  => 2,
        'sala de audiencias'  => 2,
        'audiencia'           => 2,
    ];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lista unificada a partir da tabela usuarios.
     * Ordenação: tipo (interno primeiro), setor (Cartório > Gabinete > Sala de audiências), cargo.
     *
     * @return array{rows: array<int, array>, total: int}
     */
    public function list(array $filters, int $limit, int $offset): array
    {
        $tipoFilter = trim((string) ($filters['tipo'] ?? ''));
        $perfilFilter = trim((string) ($filters['perfil'] ?? ''));

        if ($tipoFilter === 'sem_perfil') {
            $repo = new UsuariosListRepository($this->connection);
            $unassigned = $perfilFilter === '' ? $repo->listSemPerfil() : [];
            $this->sortUnified($unassigned);
            return ['rows' => array_slice($unassigned, $offset, $limit), 'total' => count($unassigned)];
        }

        $servidores = [];
        $externos = [];

        if ($tipoFilter !== 'externo') {
            $repoServidores = new UsuariosListRepository($this->connection);
            $perfilForServidores = $perfilFilter === '' ? null : $perfilFilter;
            $servidores = $repoServidores->listServidores($perfilForServidores);
        }

        if ($tipoFilter !== 'interno') {
            $repoServidores = new UsuariosListRepository($this->connection);
            if ($perfilFilter === '' || $perfilFilter === 'estudante') {
                $externos = $repoServidores->listExternos($perfilFilter);
            }
        }

        $semPerfil = [];
        if ($tipoFilter === '' && $perfilFilter === '') {
            $semPerfil = (new UsuariosListRepository($this->connection))->listSemPerfil();
        }
        $merged = $this->deduplicateByUserId(array_merge($servidores, $externos, $semPerfil));
        $this->sortUnified($merged);
        $total = count($merged);
        $rows = array_slice($merged, $offset, $limit);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * A fonte unificada é a identidade técnica id_usuario, não o perfil
     * que originou cada consulta. Se uma identidade aparecer em mais de uma
     * fonte, conserva uma linha e privilegia a classificação interna.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateByUserId(array $rows): array
    {
        $unique = [];
        $anonymous = 0;
        $typePriority = ['interno' => 0, 'externo' => 1, 'sem_perfil' => 2];

        foreach ($rows as $row) {
            $userId = trim((string) ($row['id_usuario'] ?? ''));
            if ($userId === '') {
                // Não colapsar linhas sem identidade técnica conhecida.
                $unique['__sem_id_' . $anonymous++] = $row;
                continue;
            }

            $key = 'usuario:' . $userId;
            if (!isset($unique[$key])) {
                $unique[$key] = $row;
                continue;
            }

            $currentPriority = $typePriority[(string) ($unique[$key]['tipo'] ?? '')] ?? PHP_INT_MAX;
            $candidatePriority = $typePriority[(string) ($row['tipo'] ?? '')] ?? PHP_INT_MAX;
            if ($candidatePriority < $currentPriority) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }

    /**
     * Ordena: tipo (interno 0, externo 1), setor (Cartório 0, Gabinete 1, Sala de audiências 2, — 3), cargo, nome.
     */
    private function sortUnified(array &$list): void
    {
        usort($list, function (array $a, array $b): int {
            $tipoA = ($a['tipo'] ?? '') === 'externo' ? 1 : 0;
            $tipoB = ($b['tipo'] ?? '') === 'externo' ? 1 : 0;
            if ($tipoA !== $tipoB) {
                return $tipoA - $tipoB;
            }
            $setorA = $this->setorOrder($a['setores'] ?? '');
            $setorB = $this->setorOrder($b['setores'] ?? '');
            if ($setorA !== $setorB) {
                return $setorA - $setorB;
            }
            $cargoA = trim((string) ($a['cargo'] ?? ''));
            $cargoB = trim((string) ($b['cargo'] ?? ''));
            $cmp = strcasecmp($cargoA, $cargoB);
            if ($cmp !== 0) {
                return $cmp;
            }
            $nomeA = trim((string) ($a['nome_completo'] ?? ''));
            $nomeB = trim((string) ($b['nome_completo'] ?? ''));
            $cmp = strcasecmp($nomeA, $nomeB);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp(trim((string) ($a['id_usuario'] ?? '')), trim((string) ($b['id_usuario'] ?? '')));
        });
    }

    private function setorOrder(string $setores): int
    {
        $setores = mb_strtolower(trim($setores), 'UTF-8');
        if ($setores === '' || $setores === '—') {
            return 3;
        }
        foreach (self::SETOR_ORDER as $key => $order) {
            if (strpos($setores, $key) !== false) {
                return $order;
            }
        }
        return 3;
    }

    /**
     * Contagens por tipo (interno / externo) para os filtros.
     * Respeita o filtro de perfil atual.
     *
     * @return array{interno: int, externo: int}
     */
    public function getTipoCounts(array $filters): array
    {
        $perfilFilter = trim((string) ($filters['perfil'] ?? ''));
        $repoServidores = new UsuariosListRepository($this->connection);

        $interno = 0;
        $externo = 0;

        if ($perfilFilter === '') {
            $interno = $repoServidores->countServidores(null);
            $externo = $repoServidores->countExternos();
        } elseif ($perfilFilter === 'estudante') {
            $interno = $repoServidores->countServidores('estudante');
            $externo = $repoServidores->countExternos('estudante');
        } else {
            $perfilForServ = $repoServidores::PERFIL_MAP[$perfilFilter] ?? null;
            if ($perfilForServ !== null) {
                $interno = $repoServidores->countServidores($perfilFilter);
            }
        }

        $semPerfil = $perfilFilter === '' ? $repoServidores->countSemPerfil() : 0;
        return ['interno' => $interno, 'externo' => $externo, 'sem_perfil' => $semPerfil];
    }

    /**
     * Contagens por perfil para os filtros.
     * Respeita o filtro de tipo atual.
     *
     * @return array<string, int> chaves: estagiario, servidor, diretora, magistrada, admin, estudante
     */
    public function getPerfilCounts(array $filters): array
    {
        $tipoFilter = trim((string) ($filters['tipo'] ?? ''));
        $repoServidores = new UsuariosListRepository($this->connection);

        $perfilOptions = ['estagiario', 'servidor', 'diretora', 'magistrada', 'admin', 'estudante'];
        $counts = array_fill_keys($perfilOptions, 0);

        if ($tipoFilter !== 'externo' && $tipoFilter !== 'sem_perfil') {
            $byPerfil = $repoServidores->countServidoresByPerfil();
            foreach ($byPerfil as $key => $n) {
                $counts[$key] = $n;
            }
        }
        if ($tipoFilter === 'interno') {
            $counts['estudante'] = $repoServidores->countServidores('estudante');
        } elseif ($tipoFilter === 'externo') {
            $counts['estudante'] = $repoServidores->countExternos('estudante');
        } elseif ($tipoFilter !== 'sem_perfil') {
            $counts['estudante'] = $repoServidores->countEstudantes();
        }

        return $counts;
    }
}
