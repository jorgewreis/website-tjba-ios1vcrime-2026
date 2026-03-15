<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/UsuariosListRepository.php';
require_once __DIR__ . '/../repositories/EstudantesListRepository.php';

final class UsuariosService
{
    private PDO $connection;

    /** Conexão opcional para a tabela estudantes (outro banco). Se null, usa $connection. */
    private ?PDO $connectionEstudantes;

    /** Ordem de setor para ordenação: Cartório > Gabinete > Sala de audiências > — (interno pode ser AUDIENCIA) */
    private const SETOR_ORDER = [
        'cartório'            => 0,
        'cartorio'            => 0,
        'gabinete'            => 1,
        'sala de audiências'  => 2,
        'sala de audiencias'  => 2,
        'audiencia'           => 2,
    ];

    public function __construct(PDO $connection, ?PDO $connectionEstudantes = null)
    {
        $this->connection = $connection;
        $this->connectionEstudantes = $connectionEstudantes ?? $connection;
    }

    /**
     * Lista unificada: servidores (tabela usuarios) + estudantes (tabela estudantes).
     * Ordenação: tipo (interno primeiro), setor (Cartório > Gabinete > Sala de audiências), cargo.
     *
     * @return array{rows: array<int, array>, total: int}
     */
    public function list(array $filters, int $limit, int $offset): array
    {
        $tipoFilter = trim((string) ($filters['tipo'] ?? ''));
        $perfilFilter = trim((string) ($filters['perfil'] ?? ''));

        $servidores = [];
        $estudantes = [];

        if ($tipoFilter !== 'externo') {
            $repoServidores = new UsuariosListRepository($this->connection);
            $perfilForServidores = ($perfilFilter === '' || $perfilFilter === 'estudante') ? null : $perfilFilter;
            if ($perfilFilter !== 'estudante') {
                $servidores = $repoServidores->listServidores($perfilForServidores);
            }
        }

        if ($tipoFilter !== 'interno') {
            $repoEstudantes = new EstudantesListRepository($this->connectionEstudantes, $this->connection);
            if ($perfilFilter === '' || $perfilFilter === 'estudante') {
                $estudantes = $repoEstudantes->fetchAll();
            }
        }

        $merged = array_merge($servidores, $estudantes);
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
}
