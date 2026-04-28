<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/GuiasRepository.php';
require_once __DIR__ . '/../repositories/CeapaRepository.php';
require_once __DIR__ . '/../repositories/CejuscRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasRepository.php';
require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/MedidasProtetivasRepository.php';
require_once __DIR__ . '/../repositories/OficiosCartorioRepository.php';

/**
 * Resumos numéricos por módulo do cartório para o dashboard, conforme regras ALLOCATE-* em que o usuário está designado.
 */
final class DashboardInternoAlocacaoResumoHelper
{
    private const REGRA_POR_TABELA_ALOCADOS = [
        'guias_alocados' => 'ALLOCATE-GUIAS',
        'ceapa_alocados' => 'ALLOCATE-CEAPA',
        'cejusc_alocados' => 'ALLOCATE-CEJUSC',
        'cartas_expedidas_alocados' => 'ALLOCATE-CARTASEXPEDIDAS',
        'cartas_recebidas_alocados' => 'ALLOCATE-CARTASRECEBIDAS',
        'editais_alocados' => 'ALLOCATE-EDITAIS',
        'medidas_protetivas_alocados' => 'ALLOCATE-MEDIDASPROTETIVAS',
        'cartorio_oficios_alocados' => 'ALLOCATE-CARTORIOOFICIOS',
    ];

    private const ORDEM_REGRAS = [
        'ALLOCATE-GUIAS',
        'ALLOCATE-CEAPA',
        'ALLOCATE-CEJUSC',
        'ALLOCATE-CARTASEXPEDIDAS',
        'ALLOCATE-CARTASRECEBIDAS',
        'ALLOCATE-EDITAIS',
        'ALLOCATE-MEDIDASPROTETIVAS',
        'ALLOCATE-CARTORIOOFICIOS',
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
            $card = self::cardForRegra($connection, $code);
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
    private static function cardForRegra(PDO $connection, string $code): ?array
    {
        $filters = [];
        switch ($code) {
            case 'ALLOCATE-GUIAS':
                $repo = new GuiasRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
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

            case 'ALLOCATE-CEAPA':
                $repo = new CeapaRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'CEAPA',
                    'frame_page' => 'ceapa',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Registros CEAPA com status de cumprimento «em andamento».',
                    'status_blocos' => self::blocosCeapa($byStatus),
                ];

            case 'ALLOCATE-CEJUSC':
                $repo = new CejuscRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Cejusc',
                    'frame_page' => 'cejusc',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Tarefas Cejusc com status «em andamento».',
                    'status_blocos' => self::blocosCejusc($byStatus),
                ];

            case 'ALLOCATE-CARTASEXPEDIDAS':
                $repo = new CartasExpedidasRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Cartas expedidas',
                    'frame_page' => 'cartasexpedidas',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Cartas expedidas com status «em andamento».',
                    'status_blocos' => self::blocosCartasExpedidas($byStatus),
                ];

            case 'ALLOCATE-CARTASRECEBIDAS':
                $repo = new CartasRecebidasRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Cartas recebidas',
                    'frame_page' => 'cartasrecebidas',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Cartas recebidas com status «em andamento».',
                    'status_blocos' => self::blocosCartasRecebidas($byStatus),
                ];

            case 'ALLOCATE-EDITAIS':
                $repo = new EditaisRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
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

            case 'ALLOCATE-MEDIDASPROTETIVAS':
                $repo = new MedidasProtetivasRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
                $emAnd = (int) ($byStatus['em_andamento'] ?? 0);

                return [
                    'regra' => $code,
                    'titulo' => 'Medidas protetivas',
                    'frame_page' => 'medidasprotetivas',
                    'total' => $total,
                    'em_andamento' => $emAnd,
                    'em_andamento_label' => 'Em andamento',
                    'em_andamento_title' => 'Medidas protetivas com status «em andamento».',
                    'status_blocos' => self::blocosMedidas($byStatus),
                ];

            case 'ALLOCATE-CARTORIOOFICIOS':
                $repo = new OficiosCartorioRepository($connection);
                $byStatus = $repo->countByStatus($filters);
                $total = (int) array_sum($byStatus);
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
    private static function blocosCeapa(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'CEAPA com cumprimento «pendente».'],
            'em_andamento' => ['Em andamento', 'CEAPA com cumprimento «em andamento».'],
            'finalizada' => ['Finalizada', 'CEAPA com cumprimento «finalizada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosCejusc(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Tarefas Cejusc «pendente».'],
            'em_andamento' => ['Em andamento', 'Tarefas Cejusc «em andamento».'],
            'concluída' => ['Concluídas', 'Tarefas Cejusc «concluída».'],
            'cancelada' => ['Canceladas', 'Tarefas Cejusc «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosCartasExpedidas(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Cartas expedidas «pendente».'],
            'em_andamento' => ['Em andamento', 'Cartas expedidas «em andamento».'],
            'aguardando assinatura' => ['Aguardando assinatura', 'Aguardando assinatura da magistrada.'],
            'aguardando envio' => ['Aguardando envio', 'Aguardando envio da carta.'],
            'aguardando juntada do termo' => ['Aguardando juntada do termo', 'Aguardando juntada do termo de envio.'],
            'aguardando cumprimento' => ['Aguardando cumprimento', 'Aguardando cumprimento.'],
            'concluída' => ['Concluídas', 'Cartas expedidas concluídas.'],
            'cancelada' => ['Canceladas', 'Cartas expedidas canceladas.'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
    }

    /**
     * @param array<string, int> $byStatus
     * @return list<array{label:string,count:int,title:string}>
     */
    private static function blocosCartasRecebidas(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Cartas recebidas «pendente».'],
            'em_andamento' => ['Em andamento', 'Cartas recebidas «em andamento».'],
            'concluída' => ['Concluídas', 'Cartas recebidas «concluída».'],
            'cancelada' => ['Canceladas', 'Cartas recebidas «cancelada».'],
        ];

        return self::buildBlocosFromMap($byStatus, $map);
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
    private static function blocosMedidas(array $byStatus): array
    {
        $map = [
            'pendente' => ['Pendente', 'Medidas protetivas «pendente».'],
            'em_andamento' => ['Em andamento', 'Medidas protetivas «em andamento».'],
            'concluída' => ['Concluídas', 'Medidas protetivas «concluída».'],
            'cancelada' => ['Canceladas', 'Medidas protetivas «cancelada».'],
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
