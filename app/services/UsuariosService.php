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
            $perfilForServidores = ($perfilFilter === '' || $perfilFilter === 'estudante') ? null : $perfilFilter;
            if ($perfilFilter !== 'estudante') {
                $servidores = $repoServidores->listServidores($perfilForServidores);
            }
        }

        if ($tipoFilter !== 'interno') {
            $repoServidores = new UsuariosListRepository($this->connection);
            if ($perfilFilter === '' || $perfilFilter === 'estudante') {
                $externos = $repoServidores->listExternosEstudantes();
            }
        }

        $semPerfil = [];
        if ($tipoFilter === '' && $perfilFilter === '') {
            $semPerfil = (new UsuariosListRepository($this->connection))->listSemPerfil();
        }
        $merged = array_merge($servidores, $externos, $semPerfil);
        $this->sortUnified($merged);
        $total = count($merged);
        $rows = array_slice($merged, $offset, $limit);

        return ['rows' => $rows, 'total' => $total];
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
            return strcasecmp($nomeA, $nomeB);
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
            $externo = $repoServidores->countExternosEstudantes();
        } elseif ($perfilFilter === 'estudante') {
            $externo = $repoServidores->countExternosEstudantes();
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
        if ($tipoFilter !== 'interno' && $tipoFilter !== 'sem_perfil') {
            $counts['estudante'] = $repoServidores->countExternosEstudantes();
        }

        return $counts;
    }
}
