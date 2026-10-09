<?php
declare(strict_types=1);

/**
 * Texto da linha de filtros para exportações CSV/PDF (listagens internas).
 */
final class ListExportFilterSummary
{
    /**
     * @param array<string, mixed> $get
     */
    public static function forInternalList(string $listPage, array $get): string
    {
        $parts = [];
        switch ($listPage) {
            case 'feriadoslist':
                self::pair($parts, 'Busca', (string) ($get['q'] ?? ''));
                self::pair($parts, 'Tipo de feriado', (string) ($get['tipo_feriado'] ?? ''));
                break;

            case 'usuarioslist':
                self::pair($parts, 'Tipo de usuário', (string) ($get['tipo'] ?? ''));
                self::pair($parts, 'Perfil', (string) ($get['perfil'] ?? ''));
                break;

            case 'guias':
                self::pair($parts, 'Tipo', (string) ($get['tipo'] ?? ''));
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'editais':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'cejusc':
                self::pair($parts, 'Mês de envio', (string) ($get['mes_envio'] ?? ''));
                self::pair($parts, 'Busca', (string) ($get['q'] ?? ''));
                break;

            case 'oficioscartorio':
            case 'oficiosgabinete':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Ofício ou processo (busca)', (string) ($get['oficio'] ?? ''));
                self::pair($parts, 'Ano', (string) ($get['ano'] ?? ''));
                break;

            case 'monitoramentogabinete':
                self::pair(
                    $parts,
                    'Status',
                    self::mapMonitoramentoStatusDisplay((string) ($get['status'] ?? ''))
                );
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'reuspresos':
            case 'reuspresossubstituto':
                $qRp = (string) ($get['q'] ?? '');
                if ($qRp !== '') {
                    self::pair($parts, 'Busca', $qRp);
                } else {
                    self::pair($parts, 'Nome', (string) ($get['nome'] ?? ''));
                    self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                }
                break;

            case 'cartasexpedidas':
            case 'cartasrecebidas':
                self::pair($parts, 'Finalidade', (string) ($get['finalidade'] ?? ''));
                self::pair(
                    $parts,
                    'Status',
                    self::mapCartasStatusDisplay((string) ($get['status'] ?? ''))
                );
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'salapauta':
            case 'salapainel':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Período', (string) ($get['periodo'] ?? ''));
                self::pair($parts, 'Tipo de audiência', (string) ($get['tipo'] ?? ''));
                self::pair($parts, 'Busca', (string) ($get['search'] ?? ''));
                break;

            case 'salaata':
                $parts[] = 'Filtros: atas realizadas (lista padrão do sistema)';
                break;

            case 'consultapartes':
                self::pair($parts, 'Nome', (string) ($get['nome'] ?? ''));
                self::pair($parts, 'CPF', (string) ($get['cpf'] ?? ''));
                self::pair($parts, 'Instituição (ID)', (string) ($get['instituicao_id'] ?? ''));
                break;

            case 'consultaprocessos':
                self::pair($parts, 'Número do processo', (string) ($get['numero'] ?? ''));
                self::pair($parts, 'Nome da parte', (string) ($get['parte_nome'] ?? ''));
                break;

            case 'instituicoespublicas':
                self::pair($parts, 'Nome', (string) ($get['nome'] ?? ''));
                break;

            case 'processosparteslist':
                self::pair($parts, 'Processo (ID)', (string) ($get['processo_id'] ?? ''));
                break;

            case 'partesinstituicoeslist':
                self::pair($parts, 'Pessoa (ID)', (string) ($get['parte_id'] ?? ''));
                self::pair($parts, 'Instituição (ID)', (string) ($get['instituicao_id'] ?? ''));
                break;

            default:
                $parts[] = 'Filtros: conforme parâmetros informados na exportação.';
        }

        return implode(' | ', $parts);
    }

    /**
     * @param list<string> $parts
     */
    private static function pair(array &$parts, string $label, string $value): void
    {
        $v = trim($value);
        $parts[] = $label . ': ' . ($v === '' ? 'Todos' : $v);
    }

    /**
     * @param list<string> $parts
     */
    private static function pairAtivo(array &$parts, mixed $ativo): void
    {
        if ($ativo === null || $ativo === '') {
            $parts[] = 'Ativo: Todos';

            return;
        }
        if ($ativo === true) {
            $parts[] = 'Ativo: Sim';

            return;
        }
        if ($ativo === false) {
            $parts[] = 'Ativo: Não';

            return;
        }
        $s = trim((string) $ativo);
        if ($s === '1' || strcasecmp($s, 'true') === 0) {
            $parts[] = 'Ativo: Sim';
        } elseif ($s === '0' || strcasecmp($s, 'false') === 0) {
            $parts[] = 'Ativo: Não';
        } else {
            $parts[] = 'Ativo: ' . $s;
        }
    }

    private static function mapMonitoramentoStatusDisplay(string $value): string
    {
        $status = trim($value);
        if ($status === '') {
            return 'Em aberto (padrão)';
        }

        return match ($status) {
            'todos' => 'Todos os status',
            'pendente' => 'Pendente',
            'em_andamento' => 'Em andamento',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => $status,
        };
    }

    private static function mapCartasStatusDisplay(string $value): string
    {
        $status = trim($value);
        if ($status === '') {
            return '';
        }

        return match ($status) {
            'pendente' => 'Pendente',
            'em_andamento' => 'Em andamento',
            'concluída' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => $status,
        };
    }
}
