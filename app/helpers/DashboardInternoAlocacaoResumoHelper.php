<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/GuiasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasRepository.php';
require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/OficiosCartorioRepository.php';

/**
 * Resumos numéricos por módulo do cartório para o dashboard, conforme regras ALLOCATE-* em que o usuário está designado.
 */
final class DashboardInternoAlocacaoResumoHelper
{
    private const REGRA_POR_TABELA_ALOCADOS = [
        'guias_alocados' => 'ALLOCATE-GUIAS',
        'cartas_recebidas_alocados' => 'ALLOCATE-CARTASRECEBIDAS',
        'editais_alocados' => 'ALLOCATE-EDITAIS',
        'cartorio_oficios_alocados' => 'ALLOCATE-CARTORIOOFICIOS',
    ];

    private const ORDEM_REGRAS = [
        'ALLOCATE-GUIAS',
        'ALLOCATE-CARTASRECEBIDAS',
        'ALLOCATE-EDITAIS',
        'ALLOCATE-CARTORIOOFICIOS',
    ];

    /**
     * Mapeamento técnico para contar status apenas dos registros alocados ao usuário.
     *
     * @var array<string, array{main_table:string,main_pk:string,status_col:string,aloc_table:string,aloc_fk:string}>
     */
    private const STATUS_SOURCE_BY_REGRA = [
        'ALLOCATE-GUIAS' => [
            'main_table' => 'guias',
            'main_pk' => 'id',
            'status_col' => 'status',
            'aloc_table' => 'guias_alocados',
            'aloc_fk' => 'guia_id',
        ],
        'ALLOCATE-CARTASRECEBIDAS' => [
            'main_table' => 'cartas_recebidas',
            'main_pk' => 'id',
            'status_col' => 'status',
            'aloc_table' => 'cartas_recebidas_alocados',
            'aloc_fk' => 'carta_recebida_id',
        ],
        'ALLOCATE-EDITAIS' => [
            'main_table' => 'editais',
            'main_pk' => 'id',
            'status_col' => 'status',
            'aloc_table' => 'editais_alocados',
            'aloc_fk' => 'edital_id',
        ],
        'ALLOCATE-CARTORIOOFICIOS' => [
            'main_table' => 'oficios_cartorio',
            'main_pk' => 'id',
            'status_col' => 'status',
            'aloc_table' => 'cartorio_oficios_alocados',
            'aloc_fk' => 'oficio_id',
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function cardsParaUsuario(PDO $connection, string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return [];
        }

        $alocRepo = new AlocacoesRegrasRepository($connection);
        $regrasRows = $alocRepo->findByUsuarioId($userId);
        $rulesToRender = [];
        foreach ($regrasRows as $row) {
            $code = strtoupper(trim((string) ($row['regra'] ?? '')));
            if ($code !== '') {
                $rulesToRender[$code] = true;
            }
        }

        foreach (self::findRegrasComAlocacoesDiretasDoUsuario($connection, $userId) as $code) {
            $rulesToRender[$code] = true;
        }

        $cards = [];
        foreach (array_keys($rulesToRender) as $code) {
            $card = self::cardForRegra($connection, $code, $userId);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        $order = array_flip(self::ORDEM_REGRAS);
        usort($cards, static function (array $a, array $b) use ($order): int {
            $ka = $order[$a['regra'] ?? ''] ?? 99;
            $kb = $order[$b['regra'] ?? ''] ?? 99;
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return strcmp((string) ($a['titulo'] ?? ''), (string) ($b['titulo'] ?? ''));
        });

        return $cards;
    }

    /**
     * Descobre módulos em que o usuário possui registros em tabelas *_alocados
     * (alocação automática por regra, all, ou específica), mesmo quando ele
     * não é o usuário definido na regra ALLOCATE-*.
     *
     * @return list<string>
     */
    private static function findRegrasComAlocacoesDiretasDoUsuario(PDO $connection, string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return [];
        }

        $found = [];
        foreach (self::REGRA_POR_TABELA_ALOCADOS as $table => $regraCode) {
            $stmt = $connection->prepare(
                sprintf('SELECT 1 FROM `%s` WHERE `usuario_id` = :usuario_id LIMIT 1', $table)
            );
            $stmt->bindValue(':usuario_id', $userId);
            $stmt->execute();
            if ($stmt->fetchColumn() !== false) {
                $found[] = $regraCode;
            }
        }

        return $found;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cardForRegra(PDO $connection, string $code, string $userId): ?array
    {
        $byStatus = self::countByStatusForUsuario($connection, $code, $userId);
        $total = (int) array_sum($byStatus);
        switch ($code) {
            case 'ALLOCATE-GUIAS':
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Guias',
                    'frame_page' => 'guias',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Guias com status «em andamento» na tarefa principal.',
                    'status_blocos' => self::blocosGuias($byStatus),
                ];

            case 'ALLOCATE-CARTASRECEBIDAS':
                $aguardandoDevolucao = (int) ($byStatus['aguardando devolução'] ?? 0)
                    + (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Cartas recebidas',
                    'frame_page' => 'cartasrecebidas',
                    'total' => $total,
                    'em_andamento' => $aguardandoDevolucao,
                    'em_andamento_label' => 'Aguardando devolução',
                    'em_andamento_title' => 'Cartas recebidas com status «aguardando devolução».',
                    'status_blocos' => self::blocosCartasRecebidas($byStatus),
                ];

            case 'ALLOCATE-EDITAIS':
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Editais',
                    'frame_page' => 'editais',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Editais com status «em andamento».',
                    'status_blocos' => self::blocosEditais($byStatus),
                ];

            case 'ALLOCATE-CARTORIOOFICIOS':
                $pend = (int) ($byStatus['pendente'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Ofícios (cartório)',
                    'frame_page' => 'oficioscartorio',
                    'total' => $total,
                    'em_andamento' => $pend,
                    'em_andamento_label' => 'Pendentes',
                    'em_andamento_title' => 'Ofícios com status «pendente» (ainda não finalizados nem cancelados).',
                    'status_blocos' => self::blocosOficios($byStatus),
                ];

            default:
                return null;
        }
    }

    /**
     * @return array<string,int>
     */
    private static function countByStatusForUsuario(PDO $connection, string $regraCode, string $userId): array
    {
        $cfg = self::STATUS_SOURCE_BY_REGRA[$regraCode] ?? null;
        $userId = trim($userId);
        if ($cfg === null || $userId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT m.`%s` AS st, COUNT(DISTINCT m.`%s`) AS total
             FROM `%s` m
             INNER JOIN `%s` a ON a.`%s` = m.`%s`
             WHERE a.`usuario_id` = :usuario_id
               AND a.`tipo` IN (\'rule\', \'specific\')
             GROUP BY m.`%s`',
            $cfg['status_col'],
            $cfg['main_pk'],
            $cfg['main_table'],
            $cfg['aloc_table'],
            $cfg['aloc_fk'],
            $cfg['main_pk'],
            $cfg['status_col']
        );
        $stmt = $connection->prepare($sql);
        $stmt->bindValue(':usuario_id', $userId);
        $stmt->execute();

        $summary = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $status = strtolower(trim((string) ($row['st'] ?? '')));
            if ($status === '') {
                continue;
            }
            $summary[$status] = (int) ($row['total'] ?? 0);
        }

        return $summary;
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosGuias(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Guias com status «pendente» na tarefa principal.'],
            'em_andamento' => ['Em andamento', 'Guias com status «em andamento» na tarefa principal.'],
            'concluída' => ['Concluídas', 'Guias com status «concluída».'],
            'cancelada' => ['Canceladas', 'Guias com status «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosCartasRecebidas(array $byStatus): array
    {
        $byStatusNormalizado = $byStatus;
        if (isset($byStatus['em_andamento'])) {
            $byStatusNormalizado['aguardando devolução'] = (int) ($byStatusNormalizado['aguardando devolução'] ?? 0)
                + (int) $byStatus['em_andamento'];
            unset($byStatusNormalizado['em_andamento']);
        }

        $map = [
            'pendente' => ['Pendente', 'Cartas recebidas «pendente».'],
            'aguardando cumprimento' => ['Aguardando cumprimento', 'Cartas recebidas «aguardando cumprimento».'],
            'aguardando devolução' => ['Aguardando devolução', 'Cartas recebidas «aguardando devolução».'],
            'concluída' => ['Concluídas', 'Cartas recebidas «concluída».'],
            'cancelada' => ['Canceladas', 'Cartas recebidas «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatusNormalizado, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosEditais(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Editais «pendente».'],
            'em_andamento' => ['Em andamento', 'Editais «em andamento».'],
            'concluída' => ['Concluídos', 'Editais «concluída».'],
            'cancelada' => ['Cancelados', 'Editais «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosOficios(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Ofícios «pendente» (em tratamento).'],
            'finalizada' => ['Finalizados', 'Ofícios «finalizada».'],
            'cancelada' => ['Cancelados', 'Ofícios «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @param array<string, array{0:string,1:string}> $labelMap
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function buildBlocosFromMap(array $byStatus, array $labelMap): array
    {
        $out = [];
        foreach ($labelMap as $key => $pair) {
            $n = (int) ($byStatus[$key] ?? 0);
            $out[] = [
                'label' => $pair[0],
                'count' => $n,
                'title' => $pair[1],
            ];
        }
        foreach ($byStatus as $k => $n) {
            if (!array_key_exists($k, $labelMap) && (int) $n > 0) {
                $out[] = [
                    'label' => $k,
                    'count' => (int) $n,
                    'title' => 'Registros com status «' . $k . '».',
                ];
            }
        }

        return $out;
    }
}
