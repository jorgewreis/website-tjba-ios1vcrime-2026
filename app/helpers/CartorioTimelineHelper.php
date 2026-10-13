<?php
declare(strict_types=1);

require_once __DIR__ . '/CartasExpedidasUiCopy.php';
require_once __DIR__ . '/CartasRecebidasUiCopy.php';
require_once __DIR__ . '/EditaisUiCopy.php';
require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';

if (!function_exists('cartorioTimelineGetConfig')) {
    function cartorioTimelineGetConfig(string $module): array
    {
        static $configs = null;

        if ($configs === null) {
            $configs = [
                'cartas_expedidas' => CartasExpedidasUiCopy::timelineModuleConfig(),
                'cartas_recebidas' => CartasRecebidasUiCopy::timelineModuleConfig(),
                'editais' => EditaisUiCopy::timelineModuleConfig(),
                'guias' => [
                    'cadastro_label' => 'Guia cadastrada',
                    'subtasks' => [
                        1 => [
                            'type' => 'detalhamento_informacoes',
                            'label' => 'Detalhamento de informacoes',
                            'task_title' => 'Aguardando detalhamento de informacoes',
                            'button_title' => 'Informar data do fato e dias-multa',
                            'pending_text' => 'Aguardando detalhamento de informacoes',
                        ],
                        2 => [
                            'type' => 'calculo_pena_multa',
                            'label' => 'Calculo da pena de multa',
                            'task_title' => 'Aguardando calculo da pena de multa',
                            'button_title' => 'Informar calculo da pena de multa',
                            'pending_text' => 'Aguardando calculo da pena de multa',
                        ],
                        3 => [
                            'type' => 'expedicao_guia',
                            'label' => 'Expedicao da Guia',
                            'task_title' => 'Aguardando expedicao da Guia',
                            'button_title' => 'Informar expedicao da Guia',
                            'pending_text' => 'Aguardando expedicao da Guia',
                        ],
                        4 => [
                            'type' => 'aguardando_assinatura',
                            'label' => 'Aguardando assinatura',
                            'task_title' => 'Guia aguardando assinatura',
                            'button_title' => 'Informar assinatura',
                            'pending_text' => 'Aguardando assinatura da Guia pela juiza',
                        ],
                        5 => [
                            'type' => 'juntada_automatica',
                            'label' => 'Juntada automatica',
                            'task_title' => 'Aguardando confirmacao de juntada automatica',
                            'button_title' => 'Confirmar juntada automatica',
                            'pending_text' => 'Aguardando juntada automatica da Guia pelo sistema',
                        ],
                        6 => [
                            'type' => 'gerar_pdf',
                            'label' => 'Gerar PDF',
                            'task_title' => 'Aguardando geracao de documentos em PDF',
                            'button_title' => 'Informar geracao em PDF',
                            'pending_text' => 'Aguardando geracao da Guia e documentos em PDF',
                        ],
                        7 => [
                            'type' => 'envio_guia',
                            'label' => 'Aguardando envio da Guia',
                            'task_title' => 'Aguardando envio da Guia',
                            'button_title' => 'Informar envio da Guia',
                            'pending_text' => 'Aguardando envio da Guia',
                        ],
                        8 => [
                            'type' => 'juntada_termo_envio',
                            'label' => 'Juntada de termo de envio',
                            'task_title' => 'Aguardando comprovante de envio da Guia',
                            'button_title' => 'Informar juntada de termo de envio',
                            'pending_text' => 'Aguardando juntada de termo de envio da Guia',
                        ],
                    ],
                ],
            ];
        }

        if (!isset($configs[$module])) {
            throw new InvalidArgumentException('Modulo de timeline nao suportado: ' . $module);
        }

        return $configs[$module];
    }

    function cartorioTimelineBuildViewData(string $module, array $options): array
    {
        $config = cartorioTimelineGetConfig($module);
        $task = $options['task'] ?? [];
        $subtarefas = $options['subtarefas'] ?? [];
        $connection = $options['connection'] ?? null;
        if ($connection instanceof PDO) {
            $options['usuarioNomesPorId'] = cartorioTimelineCollectUsuarioNomesPorId($connection, $subtarefas);
        } elseif (!isset($options['usuarioNomesPorId'])) {
            $options['usuarioNomesPorId'] = [];
        }
        $subtarefasPorOrdem = [];
        foreach ($subtarefas as $subtarefa) {
            $ordem = (int) ($subtarefa['ordem'] ?? 0);
            if ($module === 'editais') {
                $tipo = trim((string) ($subtarefa['tipo'] ?? ''));
                if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordem === 3) {
                    $ordem = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
                } elseif ($tipo === EditaisSubtarefasRepository::TIPO_DECURSO || $ordem === 4) {
                    $ordem = EditaisSubtarefasRepository::ORDEM_DECURSO;
                }
            }
            if ($ordem > 0) {
                $subtarefasPorOrdem[$ordem] = $subtarefa;
            }
        }

        $taskStatus = trim((string) ($options['task_status'] ?? ($task['status'] ?? 'pendente')));
        $statusMeta = cartorioTimelineTaskStatusMeta($taskStatus);
        $definitions = $config['subtasks'];
        $showProgress = $taskStatus !== '';
        $progressStatuses = [];
        $segments = [];

        $segments[] = [
            'class' => 'concluida',
            'title' => $config['cadastro_label'],
        ];

        foreach ($definitions as $ordem => $definition) {
            $status = trim((string) (($subtarefasPorOrdem[$ordem]['status'] ?? 'pendente')));
            $progressStatuses[$ordem] = $status;
            $segments[] = [
                'class' => cartorioTimelineSegmentClass($status),
                'title' => $definition['label'],
            ];
        }

        $rows = [];
        foreach ($definitions as $ordem => $definition) {
            $subtarefa = $subtarefasPorOrdem[$ordem] ?? null;
            $dados = cartorioTimelineDecodeData($subtarefa);
            $status = trim((string) (($subtarefa['status'] ?? 'pendente')));
            $movementRaw = (string) (($subtarefa['updated_at'] ?? $subtarefa['created_at'] ?? ''));
            $movementDate = cartorioTimelineFormatDateTime($movementRaw);
            $row = [
                'ordem' => $ordem,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'taskTitle' => $definition['task_title'],
                'buttonTitle' => $definition['button_title'],
                'minorText' => cartorioTimelinePendingText($module, $ordem, $definition, $subtarefasPorOrdem, $task),
                'majorText' => '',
                'dateLabel' => '-',
                'circleClass' => '',
                'rowClass' => 'timeline',
                'status' => $status,
            ];

            if ($status === 'concluida') {
                $content = cartorioTimelineCompletedContent($module, $ordem, $task, $subtarefasPorOrdem, $subtarefa, $dados, $movementDate, $options);
                $row['minorText'] = $content['minorText'];
                $row['majorText'] = $content['majorText'];
                $row['dateLabel'] = $content['dateLabel'];
                $row['circleClass'] = 'finished';
                $row['rowClass'] = 'timeline finished';
            } elseif ($status === 'em_andamento') {
                $row['circleClass'] = 'current';
                $row['rowClass'] = 'timeline current';
            } elseif ($status === 'cancelada') {
                $content = cartorioTimelineCancelledContent($module, $ordem, $definition, $movementDate);
                $row['minorText'] = $content['minorText'];
                $row['dateLabel'] = $content['dateLabel'];
                $row['circleClass'] = 'cancelada';
                $row['rowClass'] = 'timeline cancelada';
            } elseif ($status === 'pulada') {
                $content = cartorioTimelineSkippedContent($module, $ordem, $definition);
                $row['minorText'] = $content['minorText'];
                $row['dateLabel'] = $content['dateLabel'];
                $row['circleClass'] = 'pulada';
                $row['rowClass'] = 'timeline pulada';
            }

            $rows[] = $row;
        }

        $currentTitle = cartorioTimelineCurrentTaskTitle($definitions, $subtarefasPorOrdem, $taskStatus, $statusMeta['label']);
        $createdRaw = (string) (($task['created_at'] ?? ''));

        return [
            'statusLabel' => $statusMeta['label'],
            'statusClass' => $statusMeta['class'],
            'currentTaskTitle' => $currentTitle,
            'showProgress' => $showProgress,
            'progressValue' => 1 + count(array_filter($progressStatuses, static fn ($status): bool => $status === 'concluida' || $status === 'cancelada')),
            'progressMax' => count($definitions) + 1,
            'segments' => $segments,
            'cadastro' => cartorioTimelineCadastroContent($module, $task, $createdRaw, $options),
            'rows' => $rows,
        ];
    }

    function cartorioTimelineTaskStatusMeta(string $taskStatus): array
    {
        if ($taskStatus === 'concluida' || $taskStatus === 'concluída') {
            return ['label' => 'Finalizada', 'class' => 'status-finished'];
        }
        if ($taskStatus === 'cancelada') {
            return ['label' => 'Cancelada', 'class' => 'status-cancelled'];
        }
        if ($taskStatus === 'pendente') {
            return ['label' => 'Pendente', 'class' => 'status-pending'];
        }
        if ($taskStatus === CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO) {
            return ['label' => 'Aguardando cumprimento', 'class' => 'status-pending'];
        }
        if ($taskStatus === CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO) {
            return ['label' => 'Aguardando devolução', 'class' => 'status-current'];
        }
        if ($taskStatus === EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO) {
            return ['label' => 'Aguardando publicação', 'class' => 'status-current'];
        }
        if ($taskStatus === EditaisUiCopy::STATUS_AGUARDANDO_DECURSO) {
            return ['label' => 'Aguardando decurso', 'class' => 'status-current'];
        }

        return ['label' => 'Em andamento', 'class' => 'status-current'];
    }

    function cartorioTimelineSegmentClass(string $status): string
    {
        if ($status === 'concluida') {
            return 'concluida';
        }
        if ($status === 'em_andamento') {
            return 'em_andamento';
        }
        if ($status === 'cancelada') {
            return 'cancelada';
        }
        if ($status === 'pulada') {
            return 'pulada';
        }
        return 'pendente';
    }

    function cartorioTimelineCurrentTaskTitle(array $definitions, array $subtarefasPorOrdem, string $taskStatus, string $statusLabel): string
    {
        foreach ($definitions as $ordem => $definition) {
            $status = trim((string) (($subtarefasPorOrdem[$ordem]['status'] ?? '')));
            if ($status === 'em_andamento') {
                return $definition['task_title'];
            }
        }

        if ($taskStatus === 'pendente') {
            $first = reset($definitions);
            return is_array($first) ? (string) ($first['task_title'] ?? $statusLabel) : $statusLabel;
        }
        if ($taskStatus === 'concluída' || $taskStatus === 'concluida') {
            return CartasRecebidasUiCopy::TODAS_ETAPAS_CONCLUIDAS;
        }
        if ($taskStatus === CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO) {
            $def = $definitions[CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO] ?? null;

            return is_array($def) ? (string) ($def['task_title'] ?? $statusLabel) : $statusLabel;
        }
        if ($taskStatus === CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO) {
            $def = $definitions[CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO] ?? null;

            return is_array($def) ? (string) ($def['task_title'] ?? $statusLabel) : $statusLabel;
        }
        if ($taskStatus === EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO) {
            $def = $definitions[EditaisSubtarefasRepository::ORDEM_PUBLICACAO] ?? null;

            return is_array($def) ? (string) ($def['task_title'] ?? $statusLabel) : $statusLabel;
        }
        if ($taskStatus === EditaisUiCopy::STATUS_AGUARDANDO_DECURSO) {
            $def = $definitions[EditaisSubtarefasRepository::ORDEM_DECURSO] ?? null;

            return is_array($def) ? (string) ($def['task_title'] ?? $statusLabel) : $statusLabel;
        }

        return $statusLabel;
    }

    function cartorioTimelineCadastroContent(string $module, array $task, string $createdRaw, array $options): array
    {
        $createdAt = cartorioTimelineFormatDateTime($createdRaw);
        $label = cartorioTimelineGetConfig($module)['cadastro_label'];
        $dateLabel = $createdAt !== '' ? ('Cadastro realizado em ' . $createdAt) : 'Cadastro realizado';

        if ($module === 'cartas_expedidas') {
            $dateLabel = $createdAt !== '' ? ('Carta expedida cadastrada em ' . $createdAt) : 'Carta expedida cadastrada';
        } elseif ($module === 'cartas_recebidas') {
            $ruleServidor = trim((string) ($options['ruleServidor'] ?? ''));
            $dateLabel = $createdAt !== '' ? ('Documento cadastrado por ' . ($ruleServidor !== '' ? $ruleServidor : '—') . ' em ' . $createdAt) : 'Documento cadastrado';
        } elseif ($module === 'editais') {
            $dateLabel = $createdAt !== '' ? ('Edital cadastrado em ' . $createdAt) : 'Edital cadastrado';
        } elseif ($module === 'guias') {
            $dateLabel = $createdAt !== '' ? ('Guia cadastrada em ' . $createdAt) : 'Guia cadastrada';
        }

        return [
            'minorText' => $label,
            'majorText' => '',
            'dateLabel' => $dateLabel,
            'circleClass' => 'finished',
            'rowClass' => 'timeline finished',
        ];
    }

    function cartorioTimelinePendingText(string $module, int $ordem, array $definition, array $subtarefasPorOrdem, array $task): string
    {
        return $definition['pending_text'];
    }

    function cartorioTimelineCompletedContent(string $module, int $ordem, array $task, array $subtarefasPorOrdem, ?array $subtarefa, array $dados, string $movementDate, array $options): array
    {
        $user = cartorioTimelineResolveUser($dados, (array) ($options['usuarioNomesPorId'] ?? []));

        if ($module === 'cartas_expedidas') {
            if ($ordem === CartasExpedidasSubtarefasRepository::ORDEM_ENVIO) {
                $tribunal = cartorioTimelineResolveTribunalDestino($task, $dados, $options['tribunaisRepo'] ?? null, 'destino');
                $comarca = trim((string) ($dados['comarca_envio'] ?? $dados['cidade_destino'] ?? $task['cidade_destino'] ?? ''));
                $metodo = cartorioTimelineNormalizeEnvio((string) ($dados['metodo_envio'] ?? ''));
                $preposicao = cartorioTimelineMetodoPreposicao($metodo);
                $frase = 'Carta enviada para ' . ($tribunal !== '' ? $tribunal : '—');
                if ($comarca !== '') {
                    $frase .= ' - Comarca de ' . $comarca;
                }
                if ($metodo !== '') {
                    $frase .= ' ' . $preposicao . ' ' . $metodo;
                }
                return cartorioTimelineContent($frase, 'Envio realizado por ' . $user . ' em ' . $movementDate);
            }
            $resultado = cartorioTimelineResultadoDevolucao((string) ($dados['resultado_devolucao'] ?? $dados['status_devolucao'] ?? ''));
            $frase = $resultado !== '' ? 'Carta devolvida ' . $resultado : 'Carta devolvida';
            return cartorioTimelineContent($frase, 'Juntada de carta devolvida realizada por ' . $user . ' em ' . $movementDate);
        }

        if ($module === 'cartas_recebidas') {
            if ($ordem === CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO) {
                return cartorioTimelineContent('Cumprimento registrado', 'Informacao registrada por ' . $user . ' em ' . $movementDate);
            }

            return cartorioTimelineContent('Carta devolvida ao juizo deprecante', 'Devolucao registrada por ' . $user . ' em ' . $movementDate);
        }

        if ($module === 'editais') {
            require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
            if ($ordem === EditaisSubtarefasRepository::ORDEM_PUBLICACAO || $ordem === 3) {
                $dataPublicacao = cartorioTimelineFormatSimpleDate((string) ($dados['data_publicacao'] ?? ''));
                if ($dataPublicacao === '') {
                    $dataPublicacao = '—';
                }

                return cartorioTimelineContent('Publicacao do edital realizada em ' . $dataPublicacao, 'Certificacao realizada por ' . $user . ' em ' . $movementDate);
            }

            return cartorioTimelineContent('Decurso do prazo do edital certificado', 'Certificacao realizada por ' . $user . ' em ' . $movementDate);
        }

        if ($module === 'guias') {
            if ($ordem === 1) {
                $dataFato = cartorioTimelineFormatSimpleDate((string) ($dados['data_fato'] ?? ''));
                $diasMulta = trim((string) ($dados['dias_multa'] ?? ''));
                return cartorioTimelineContent('Data do fato: ' . ($dataFato !== '' ? $dataFato : '—') . ' | Quantidade de dias-multa: ' . ($diasMulta !== '' ? $diasMulta : '—'), 'Informacoes registradas por ' . $user . ' em ' . $movementDate);
            }
            if ($ordem === 2) {
                $valor = cartorioTimelineFormatMoney($dados['valor_multa'] ?? ($task['valor_multa'] ?? ''));
                return cartorioTimelineContent('Pena de multa calculada no valor de ' . ($valor !== '' ? $valor : '—'), 'Calculo automatico realizado em ' . $movementDate);
            }
            if ($ordem === 3) {
                $tipo = trim((string) ($task['tipo'] ?? ''));
                $dataExp = cartorioTimelineFormatSimpleDate((string) ($dados['data_expedicao'] ?? ''));
                $frase = 'Guia de ' . ($tipo !== '' ? $tipo : '—') . ' expedida em ' . ($dataExp !== '' ? $dataExp : '—');
                return cartorioTimelineContent($frase, 'Expedicao realizada por ' . $user . ' em ' . $movementDate);
            }
            if ($ordem === 4) {
                return cartorioTimelineContent('Guia assinada pela juiza em ' . $movementDate, 'Informacao confirmada por ' . $user . ' em ' . $movementDate);
            }
            if ($ordem === 5) {
                $tipoJuntada = trim((string) ($dados['tipo_juntada'] ?? ''));
                $frase = $tipoJuntada === 'manual'
                    ? 'Juntada da Guia realizada pelo servidor'
                    : 'Juntada automatica da Guia realizada pelo sistema';
                return cartorioTimelineContent($frase, 'Juntada confirmada/realizada por ' . $user . ' em ' . $movementDate);
            }
            if ($ordem === 6) {
                $qtd = (int) ($dados['qtd_docs'] ?? 0);
                return cartorioTimelineContent(($qtd > 0 ? $qtd : '—') . ' documentos gerados em PDF', 'Impressao realizada por ' . $user . ' em ' . $movementDate);
            }
            if ($ordem === 7) {
                $tipoEnvio = cartorioTimelineNormalizeEnvio((string) ($dados['tipo_envio'] ?? ''));
                $destinatario = trim((string) ($dados['destinatario'] ?? ''));
                return cartorioTimelineContent('Guia enviada por ' . ($tipoEnvio !== '' ? $tipoEnvio : '—') . ' para ' . ($destinatario !== '' ? $destinatario : '—'), 'Envio realizado por ' . $user . ' em ' . $movementDate);
            }
            return cartorioTimelineContent('Juntada do termo de envio da Guia realizada', 'Comprovante juntado por ' . $user . ' em ' . $movementDate);
        }

        if ($ordem === 1) {
            return cartorioTimelineContent('Decisao impressa em PDF', 'Impressao realizada por ' . $user . ' em ' . $movementDate);
        }
        if ($ordem === 2) {
            return cartorioTimelineContent('Decisao enviada por e-mail', 'Envio realizado por ' . $user . ' em ' . $movementDate);
        }
        return cartorioTimelineContent('Juntada do termo de envio da decisao realizada', 'Comprovante juntado por ' . $user . ' em ' . $movementDate);
    }

    function cartorioTimelineCancelledContent(string $module, int $ordem, array $definition, string $movementDate): array
    {
        if ($module === 'guias' && $ordem === 1) {
            return cartorioTimelineContent('Detalhamento de informacoes cancelado', '-');
        }
        return cartorioTimelineContent($definition['label'] . ' cancelada', $movementDate !== '' ? $movementDate : '-');
    }

    function cartorioTimelineSkippedContent(string $module, int $ordem, array $definition): array
    {
        if ($module === 'guias' && $ordem === 2) {
            return cartorioTimelineContent('Calculo da pena de multa nao aplicavel', '-');
        }
        return cartorioTimelineContent($definition['label'] . ' pulada', '-');
    }

    function cartorioTimelineContent(string $minorText, string $dateLabel, string $majorText = ''): array
    {
        return [
            'minorText' => $minorText,
            'dateLabel' => $dateLabel !== '' ? $dateLabel : '-',
            'majorText' => $majorText,
        ];
    }

    function cartorioTimelineDecodeData(?array $subtarefa): array
    {
        if ($subtarefa === null) {
            return [];
        }
        $dadosJson = trim((string) ($subtarefa['dados_especificos'] ?? ''));
        if ($dadosJson === '') {
            return [];
        }
        $decoded = json_decode($dadosJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<array<string, mixed>> $subtarefas
     * @return array<string, string>
     */
    function cartorioTimelineCollectUsuarioNomesPorId(PDO $connection, array $subtarefas): array
    {
        $ids = [];
        foreach ($subtarefas as $subtarefa) {
            if (!is_array($subtarefa)) {
                continue;
            }
            $dados = cartorioTimelineDecodeData($subtarefa);
            $uid = trim((string) ($dados['usuario_id'] ?? ''));
            if ($uid !== '') {
                $ids[$uid] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        $index = 0;
        foreach (array_keys($ids) as $uid) {
            $key = ':uid' . $index;
            $placeholders[] = $key;
            $params[$key] = $uid;
            $index++;
        }

        $sql = 'SELECT `id_usuario`, `nome`, `sobrenome`, `email`
                FROM `usuarios`
                WHERE `id_usuario` IN (' . implode(', ', $placeholders) . ')';
        $statement = $connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        $map = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = trim((string) ($row['id_usuario'] ?? ''));
            if ($id === '') {
                continue;
            }
            $map[$id] = cartorioTimelineFormatUsuarioNome($row);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    function cartorioTimelineFormatUsuarioNome(array $row): string
    {
        $nome = trim((string) ($row['nome'] ?? ''));
        $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
        $completo = $sobrenome !== '' ? trim($nome . ' ' . $sobrenome) : $nome;
        if ($completo !== '') {
            return $completo;
        }
        return trim((string) ($row['email'] ?? ''));
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, string> $usuarioNomesPorId
     */
    function cartorioTimelineResolveUser(array $dados, array $usuarioNomesPorId = []): string
    {
        $nome = trim((string) ($dados['nome'] ?? $dados['responsavel'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }
        $uid = trim((string) ($dados['usuario_id'] ?? ''));
        if ($uid !== '' && isset($usuarioNomesPorId[$uid])) {
            $resolved = trim((string) $usuarioNomesPorId[$uid]);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '—';
    }

    function cartorioTimelineFormatDateTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
        if ($date instanceof DateTime) {
            return $date->format('d/m/Y - H:i');
        }
        return $raw;
    }

    function cartorioTimelineFormatSimpleDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $date = DateTime::createFromFormat('Y-m-d', $raw);
        if ($date instanceof DateTime) {
            return $date->format('d/m/Y');
        }
        return $raw;
    }

    function cartorioTimelineFormatMoney($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }
            if (stripos($trimmed, 'R$') === 0) {
                return $trimmed;
            }
            $normalized = str_replace(['R$', '.', ','], ['', '', '.'], $trimmed);
            if (is_numeric($normalized)) {
                return 'R$ ' . number_format((float) $normalized, 2, ',', '.');
            }
            return $trimmed;
        }
        if (is_numeric($value)) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        }
        return (string) $value;
    }

    function cartorioTimelineNormalizeEnvio(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'Email' => 'E-mail',
            'email' => 'E-mail',
            'e-mail' => 'E-mail',
            'malote digital' => 'Malote Digital',
            'Malote digital' => 'Malote Digital',
            'pje' => 'PJe',
            'esaj' => 'eSAJ',
            'eproc' => 'ePROC',
        ];

        return $map[$value] ?? $map[strtolower($value)] ?? $value;
    }

    function cartorioTimelineMetodoPreposicao(string $metodo): string
    {
        $metodo = trim($metodo);
        if ($metodo === '') {
            return '';
        }
        return $metodo === 'E-mail' ? 'por' : 'pelo';
    }

    function cartorioTimelineResultadoCumprimento(string $value): string
    {
        $map = [
            'cumprido_parcialmente' => 'cumprido parcialmente',
            'cumprido_integralmente' => 'cumprido integralmente',
            'cumprido_negativamente' => 'cumprido negativamente',
            'nao_cumprido' => 'nao cumprido',
        ];
        $value = trim($value);
        return $map[$value] ?? $value;
    }

    function cartorioTimelineResultadoDevolucao(string $value): string
    {
        $map = [
            'devolvida_sem_cumprimento' => 'Nao cumprida',
            'cumprida_negativamente' => 'Cumprida negativamente',
            'cumprida_parcialmente' => 'Cumprida parcialmente',
            'cumprida_positivamente' => 'Cumprida integralmente',
        ];
        $value = trim($value);
        return $map[$value] ?? $value;
    }

    function cartorioTimelineResolveTribunalDestino(array $task, array $dados, $tribunaisRepo, string $origemOuDestino): string
    {
        $siglaRaw = '';
        if ($origemOuDestino === 'origem') {
            $uf = strtoupper(trim((string) ($task['uf_origem'] ?? '')));
            $siglaRaw = $uf !== '' ? ($uf === 'DF' ? 'TJDFT' : 'TJ' . $uf) : '';
        } else {
            $uf = strtoupper(trim((string) ($task['uf_destino'] ?? '')));
            $siglaRaw = $uf !== '' ? ($uf === 'DF' ? 'TJDFT' : 'TJ' . $uf) : '';
        }

        $nomeTribunal = trim((string) ($dados['tribunal_destino'] ?? $dados['tribunal_nome'] ?? ''));
        if ($nomeTribunal === '' && $siglaRaw !== '' && $tribunaisRepo !== null && method_exists($tribunaisRepo, 'findBySigla')) {
            $tribunalRow = $tribunaisRepo->findBySigla($siglaRaw);
            if (is_array($tribunalRow)) {
                $nomeTribunal = trim((string) ($tribunalRow['nome'] ?? ''));
            }
        }

        if ($siglaRaw === '' && $nomeTribunal === '') {
            return '';
        }
        if ($siglaRaw === '') {
            return $nomeTribunal;
        }
        if ($nomeTribunal === '') {
            return $siglaRaw;
        }
        return $siglaRaw . ' - ' . $nomeTribunal;
    }
}
