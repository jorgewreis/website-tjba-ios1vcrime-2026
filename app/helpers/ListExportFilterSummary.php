<?php
declare(strict_types=1);

/**
 * Texto da linha de filtros para exportações CSV/PDF (listagens internas e produtividade).
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

            case 'cejusc':
            case 'editais':
            case 'medidasprotetivas':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'ceapa':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                self::pair($parts, 'Tipo de suspensão', (string) ($get['tipo_suspensao'] ?? ''));
                break;

            case 'oficioscartorio':
            case 'oficiosgabinete':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Ofício (busca)', (string) ($get['oficio'] ?? ''));
                self::pair($parts, 'Ano', (string) ($get['ano'] ?? ''));
                break;

            case 'monitoramentogabinete':
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'reuspresos':
                self::pair($parts, 'Nome', (string) ($get['nome'] ?? ''));
                self::pair($parts, 'Processo', (string) ($get['processo'] ?? ''));
                break;

            case 'cartasexpedidas':
            case 'cartasrecebidas':
                self::pair($parts, 'Finalidade', (string) ($get['finalidade'] ?? ''));
                self::pair($parts, 'Status', (string) ($get['status'] ?? ''));
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

            case 'produtividaderegras':
                self::pair($parts, 'Busca', (string) ($get['q'] ?? ''));
                self::pairAtivo($parts, $get['ativo'] ?? null);
                break;

            case 'produtividademetas':
                self::pair($parts, 'Busca', (string) ($get['q'] ?? ''));
                self::pairAtivo($parts, $get['ativo'] ?? null);
                self::pair($parts, 'Tipo de alvo', (string) ($get['tipo_alvo'] ?? ''));
                self::pair($parts, 'Período (tipo)', (string) ($get['periodo_tipo'] ?? ''));
                break;

            case 'produtividadebadges':
                self::pair($parts, 'Busca', (string) ($get['q'] ?? ''));
                self::pairAtivo($parts, $get['ativo'] ?? null);
                self::pair($parts, 'Tipo', (string) ($get['tipo'] ?? ''));
                break;

            default:
                $parts[] = 'Filtros: conforme parâmetros informados na exportação.';
        }

        return implode(' | ', $parts);
    }

    /**
     * @param array<string, mixed> $get
     */
    public static function forProdutividadeLancamentos(array $get, ?float $sumPontos = null): string
    {
        $parts = [];
        $uid = trim((string) ($get['usuario_id'] ?? ''));
        $parts[] = 'Usuário: ' . ($uid === '' ? 'Todos' : $uid);

        $st = trim((string) ($get['status'] ?? 'ativo'));
        $parts[] = 'Status: ' . ($st === '' ? 'Todos' : $st);

        $tl = trim((string) ($get['tipo_lancamento'] ?? 'manual'));
        $parts[] = 'Tipo de lançamento: ' . ($tl === '' ? 'Todos' : $tl);

        $di = trim((string) ($get['data_inicio'] ?? ''));
        $df = trim((string) ($get['data_fim'] ?? ''));
        if ($di !== '' || $df !== '') {
            $parts[] = 'Período: ' . ($di !== '' ? $di : '…') . ' a ' . ($df !== '' ? $df : '…');
        } else {
            $parts[] = 'Período: Todos';
        }

        if ($sumPontos !== null) {
            $parts[] = 'Total de pontos (filtro): ' . number_format($sumPontos, 2, ',', '.');
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
}
