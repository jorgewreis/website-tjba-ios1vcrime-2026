<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/CartasExpedidasUiCopy.php';
require_once __DIR__ . '/../repositories/OficiosCartorioSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/GabineteMonitoramentoEletronicoSubtarefasRepository.php';

/**
 * Texto do atributo title do bloco de status da tarefa principal nas listas internas,
 * espelhando a lógica das views PHP correspondentes (para CSV/PDF).
 */
final class ListExportPrincipalStatusTitle
{
    public const MODULE_GUIAS = 'guias';
    public const MODULE_EDITAIS = 'editais';
    public const MODULE_MEDIDAS = 'medidas';
    public const MODULE_OFICIOS_CARTORIO = 'oficios_cartorio';
    public const MODULE_MONITORAMENTO_GABINETE = 'monitoramento_gabinete';
    public const MODULE_CARTAS_EXPEDIDAS = 'cartas_expedidas';
    public const MODULE_CARTAS_RECEBIDAS = 'cartas_recebidas';

    /**
     * @param array<string, mixed> $row
     */
    public static function forRow(string $module, array $row): string
    {
        return match ($module) {
            self::MODULE_GUIAS => self::guias($row),
            self::MODULE_EDITAIS => self::editais($row),
            self::MODULE_MEDIDAS => self::medidas($row),
            self::MODULE_OFICIOS_CARTORIO => self::oficiosCartorio($row),
            self::MODULE_MONITORAMENTO_GABINETE => self::monitoramentoGabinete($row),
            self::MODULE_CARTAS_EXPEDIDAS => self::cartasExpedidas($row),
            self::MODULE_CARTAS_RECEBIDAS => self::cartasRecebidas($row),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function subtarefasAll(array $row): array
    {
        $all = $row['SubtarefasAll'] ?? null;
        if (is_array($all)) {
            return $all;
        }
        $sub = $row['Subtarefas'] ?? null;

        return is_array($sub) ? $sub : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function guias(array $row): string
    {
        $subtarefaLabels = [
            GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Detalhar data do fato e dias-multa',
            GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Calcular pena de multa',
            GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Expedir a Guia',
            GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Assinar a Guia no BNMP',
            GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Juntar a Guia aos autos',
            GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Gerar documentos em PDF',
            GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Enviar a Guia',
            GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Juntar o termo de envio',
        ];
        $ordemLabels = [
            1 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_1],
            2 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_2],
            3 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_3],
            4 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_4],
            5 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_5],
            6 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_6],
            7 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_7],
            8 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_8],
        ];
        $taskTitleByTipo = [
            GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando data do fato e dias-multa (detalhamento)',
            GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando valor da pena de multa',
            GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando expedição da Guia',
            GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando assinatura da Juíza',
            GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando confirmação da juntada dos autos',
            GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando geração dos documentos em PDF',
            GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando envio da Guia',
            GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Aguardando juntada do termo de envio',
        ];

        $st = (string) ($row['Status'] ?? '');
        $allSt = self::subtarefasAll($row);
        $current = '';
        foreach ($allSt as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                $tipo = (string) ($s['tipo'] ?? '');
                $current = $taskTitleByTipo[$tipo] ?? $subtarefaLabels[$tipo] ?? $tipo;
                break;
            }
        }
        if ($current === '' && $st === 'pendente') {
            $current = $taskTitleByTipo[GuiasSubtarefasRepository::TIPO_ORDEM_1] ?? $ordemLabels[1];
        } elseif ($current === '' && in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            $current = 'Todas as etapas concluídas';
        } elseif ($current === '') {
            $current = $st === 'cancelada' ? 'Cancelada' : 'Em andamento';
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function editais(array $row): string
    {
        $subtarefaLabels = [
            'expedicao_edital' => 'Expedição de Edital',
            'envio_djen' => 'Envio para publicação no DJEN',
            'certificacao_publicacao' => 'Data da publicação',
            'certificacao_decurso_prazo' => 'Aguardando decurso do prazo',
        ];
        $ordemLabels = [
            1 => $subtarefaLabels['expedicao_edital'],
            2 => $subtarefaLabels['envio_djen'],
            3 => $subtarefaLabels['certificacao_publicacao'],
            4 => $subtarefaLabels['certificacao_decurso_prazo'],
        ];

        $st = (string) ($row['Status'] ?? '');
        $allSt = self::subtarefasAll($row);
        $current = '';
        foreach ($allSt as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                $current = $subtarefaLabels[$s['tipo'] ?? ''] ?? (string) ($s['tipo'] ?? '');
                break;
            }
        }
        if ($current === '' && $st === 'pendente') {
            $current = $ordemLabels[1] ?? 'Expedição de Edital';
        } elseif ($current === '' && in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            $current = 'Todas as etapas concluídas';
        } elseif ($current === '') {
            $current = (string) ($row['Status'] ?? 'Em andamento');
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function medidas(array $row): string
    {
        $st = (string) ($row['Status'] ?? '');
        if ($st === 'cancelada') {
            return 'Cancelada';
        }
        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return 'Todas as etapas concluídas';
        }

        $subtarefaLabels = [
            'cadastro_partes_pje' => 'Cadastro de partes no PJe',
            'expedicao_mandados_intimacao' => 'Expedição de mandados de intimação',
            'cadastro_bnmp' => 'Cadastro no BNMP',
            'certidao_cumprimento' => 'Certidão de Cumprimento',
        ];
        $ordemLabels = [
            1 => $subtarefaLabels['cadastro_partes_pje'],
            2 => $subtarefaLabels['expedicao_mandados_intimacao'],
            3 => $subtarefaLabels['cadastro_bnmp'],
            4 => $subtarefaLabels['certidao_cumprimento'],
        ];

        $allSt = self::subtarefasAll($row);
        $current = '';
        foreach ($allSt as $subtarefa) {
            if (!is_array($subtarefa)) {
                continue;
            }
            if (trim((string) ($subtarefa['status'] ?? '')) === 'em_andamento') {
                $current = $subtarefaLabels[$subtarefa['tipo'] ?? ''] ?? '';
                break;
            }
        }
        if ($current === '' && $st === 'pendente') {
            return $ordemLabels[1];
        }
        if ($current === '') {
            return (string) ($row['Status'] ?? 'Em andamento');
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function oficiosCartorio(array $row): string
    {
        $statusTarefa = (string) ($row['Status'] ?? '');
        $formaC = (string) ($row['FormaCumprimento'] ?? '');
        $tarefaConcluida = in_array($statusTarefa, ['finalizada'], true);
        $tarefaCancelada = ($statusTarefa === 'cancelada');
        if ($tarefaCancelada) {
            return 'Cancelada';
        }
        if ($tarefaConcluida) {
            return 'Finalizada';
        }

        $subtarefaLabels = [
            OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Enviar ofício',
            OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Juntar termo de envio',
        ];
        $allSt = self::subtarefasAll($row);
        $current = '';
        foreach ($allSt as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                $tipoS = (string) ($s['tipo'] ?? '');
                $current = $subtarefaLabels[$tipoS] ?? $tipoS;
                break;
            }
        }
        if ($current === '' && $statusTarefa === 'pendente' && $formaC === 'cartorio') {
            $current = $subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_ENVIAR] ?? '';
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function monitoramentoGabinete(array $row): string
    {
        $statusTarefa = (string) ($row['Status'] ?? '');
        $tarefaCancelada = ($statusTarefa === 'cancelada');
        $tarefaFinalizada = ($statusTarefa === 'finalizada');
        if ($tarefaCancelada) {
            return 'Cancelada';
        }
        if ($tarefaFinalizada) {
            return 'Finalizada';
        }

        $subtarefaLabels = [
            GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR => 'Iniciar monitoramento',
            GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR => 'Encerrar monitoramento',
        ];
        $mostrarBarraStatus = !$tarefaCancelada && !$tarefaFinalizada;

        $allSt = self::subtarefasAll($row);
        $currentSubtarefaTitle = '';
        foreach ($allSt as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                $tipoS = (string) ($s['tipo'] ?? '');
                $currentSubtarefaTitle = $subtarefaLabels[$tipoS] ?? $tipoS;
                break;
            }
        }
        if ($currentSubtarefaTitle === '' && $statusTarefa === 'pendente') {
            $currentSubtarefaTitle = $subtarefaLabels[GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR] ?? '';
        }

        if ($statusTarefa === 'pendente' && $mostrarBarraStatus) {
            return 'Iniciar e encerrar monitoramento';
        }

        return $currentSubtarefaTitle;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function cartasExpedidas(array $row): string
    {
        return CartasExpedidasUiCopy::resolverTituloTarefaListagem(
            (string) ($row['Status'] ?? ''),
            self::subtarefasAll($row)
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function cartasRecebidas(array $row): string
    {
        require_once __DIR__ . '/CartasRecebidasUiCopy.php';

        return CartasRecebidasUiCopy::resolverTituloTarefaListagem(
            (string) ($row['Status'] ?? ''),
            self::subtarefasAll($row)
        );
    }
}
