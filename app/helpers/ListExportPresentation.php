<?php
declare(strict_types=1);

/**
 * Cabeçalho institucional e formatação de células para exportações CSV/PDF.
 */
final class ListExportPresentation
{
    public const HEADER_LINE_1 = 'Poder Judiciário do Estado da Bahia';
    public const HEADER_LINE_2 = 'Primeira Vara Crime da Comarca de Ilhéus';
    public const HEADER_LINE_3 = 'Sistema de Gestão do Cartório - WebApp';

    /**
     * @return list<string>
     */
    public static function institutionalHeaderLines(): array
    {
        return [
            self::HEADER_LINE_1,
            self::HEADER_LINE_2,
            self::HEADER_LINE_3,
        ];
    }

    public static function buildResultsMetaLine(int $resultCount): string
    {
        $dateBr = date('d/m/Y');
        $timeBr = date('H:i:s');
        $n = max(0, $resultCount);
        $word = $n === 1 ? 'resultado' : 'resultados';

        return sprintf(
            'Documento gerado em %s às %s horas, retornando %d %s de acordo com os filtros acima.',
            $dateBr,
            $timeBr,
            $n,
            $word
        );
    }

    /**
     * Linhas 1–3: timbre; 4: em branco; 5: título da sessão; 6: filtros; 7: data/hora e quantidade; 8: em branco.
     *
     * @param resource $output
     */
    public static function writeCsvPreamble(
        $output,
        int $columnCount,
        string $sessionTitle,
        string $filterSummary,
        int $resultCount
    ): void {
        $n = max(1, $columnCount);
        foreach (self::institutionalHeaderLines() as $line) {
            $row = array_fill(0, $n, '');
            $row[0] = $line;
            fputcsv($output, $row, ';');
        }
        fputcsv($output, array_fill(0, $n, ''), ';');

        $titleRow = array_fill(0, $n, '');
        $titleRow[0] = $sessionTitle;
        fputcsv($output, $titleRow, ';');

        $filterRow = array_fill(0, $n, '');
        $filterRow[0] = $filterSummary;
        fputcsv($output, $filterRow, ';');

        $metaRow = array_fill(0, $n, '');
        $metaRow[0] = self::buildResultsMetaLine($resultCount);
        fputcsv($output, $metaRow, ';');

        fputcsv($output, array_fill(0, $n, ''), ';');
    }

    public static function htmlInstitutionalBlock(): string
    {
        $lines = self::institutionalHeaderLines();
        $out = '<div class="export-inst" style="text-align:center;margin:0 0 10px;font-weight:bold;font-size:11px;line-height:1.35;">';
        foreach ($lines as $line) {
            $out .= '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Após o timbre: espaço em branco, título da sessão, linha de filtros, linha de data/quantidade, espaço antes da tabela.
     */
    public static function htmlReportIntro(string $sessionTitle, string $filterSummary, int $resultCount): string
    {
        $meta = htmlspecialchars(self::buildResultsMetaLine($resultCount), ENT_QUOTES, 'UTF-8');

        return '<div style="height:10px;"></div>'
            . '<h1 style="text-align:center;margin:0 0 6px;font-size:16px;">'
            . htmlspecialchars($sessionTitle, ENT_QUOTES, 'UTF-8')
            . '</h1>'
            . '<p class="export-filters" style="text-align:center;margin:0 0 6px;font-size:10px;line-height:1.35;">'
            . htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '<p class="export-meta-line" style="text-align:center;margin:0 0 12px;font-size:10px;color:#333;line-height:1.35;">'
            . $meta
            . '</p>'
            . '<div style="height:6px;"></div>';
    }

    public static function subtarefaLabelFromTipo(string $tipo): string
    {
        $tipo = trim($tipo);
        static $map = [
            // Guias
            'detalhamento_informacoes' => 'Detalhar data do fato e dias-multa',
            'calculo_pena_multa' => 'Calcular pena de multa',
            'expedicao_guia' => 'Expedir a Guia',
            'juntada_automatica' => 'Juntar a Guia aos autos',
            'gerar_pdf' => 'Gerar documentos em PDF',
            'geracao_pdf' => 'Gerar documentos em PDF',
            'pdf' => 'Gerar documentos em PDF',
            'envio_guia' => 'Enviar a Guia',
            'juntada_termo_envio' => 'Juntar o termo de envio',
            // CEJUSC / ofícios cartório (mesmo código em alguns fluxos)
            'gerar_pdf_decisao' => 'Gerar PDF da decisão',
            'enviar_decisao' => 'Envio da decisão por e-mail',
            'juntar_termo_envio' => 'Juntar termo de envio',
            // Cartas expedidas + guias (tipo compartilhado)
            'aguardando_assinatura' => 'Assinatura pela Magistrada',
            // Cartas expedidas
            'aguardando_gerar_pdf' => 'Imprimir peças em PDF',
            'aguardando_envio' => 'Enviar Carta expedida',
            'aguardando_juntada_termo' => 'Juntar comprovante de envio',
            'aguardando_informacoes' => 'Informar detalhes',
            'aguardando_devolucao' => 'Juntar carta devolvida',
            // Cartas recebidas
            'ato_ordinatorio_cumprimento' => 'Expedir Ato Ordinatório - Cumprimento',
            'expedicao_documento' => 'Expedição de mandado/ofício',
            'juntada_certidao_oficial' => 'Juntar certidão do oficial de justiça',
            'ato_ordinatorio_devolucao' => 'Expedir Ato Ordinatório - Devolução',
            'envio_processo' => 'Devolver Carta ao Juízo Deprecante',
            'arquivamento_definitivo' => 'Arquivar definitivamente com baixa',
            'devolucao_origem' => 'Aguardando devolução',
            'devolução_origem' => 'Aguardando devolução',
            // Medidas protetivas
            'cadastro_partes_pje' => 'Cadastro de partes no PJe',
            'expedicao_mandados_intimacao' => 'Expedição de mandados de intimação',
            // Editais
            'expedicao_edital' => 'Expedição de Edital',
            'envio_djen' => 'Envio para publicação no DJEN',
            'certificacao_publicacao' => 'Data da publicação',
            'certificacao_decurso_prazo' => 'Aguardando decurso do prazo',
            // Monitoramento eletrônico
            'iniciar_monitoramento' => 'Iniciar monitoramento',
            'encerrar_monitoramento' => 'Encerrar monitoramento',
            // Ofícios cartório
            'enviar_oficio' => 'Enviar ofício',
        ];

        if (isset($map[$tipo])) {
            return $map[$tipo];
        }

        return $tipo === ''
            ? ''
            : mb_convert_case(str_replace('_', ' ', $tipo), MB_CASE_TITLE, 'UTF-8');
    }

    public static function formatCell(string $columnKey, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($columnKey === 'Alocados' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $ini = trim((string) ($item['iniciais'] ?? ''));
                if ($ini !== '') {
                    $lines[] = $ini;
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'Subtarefas' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $tipo = (string) ($item['tipo'] ?? '');
                $lines[] = self::subtarefaLabelFromTipo($tipo);
            }
            $lines = array_values(array_filter($lines, static fn (string $s): bool => $s !== ''));

            return implode("\n", $lines);
        }

        if ($columnKey === 'subtarefas_processos' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $num = trim((string) ($item['numero_processo'] ?? ''));
                if ($num !== '' && $num !== '—') {
                    $lines[] = $num;
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'partes_cumprimento' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nome = trim((string) ($item['nome_parte'] ?? ''));
                if ($nome !== '') {
                    $lines[] = $nome;
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'telefones' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $num = trim((string) ($item['numero'] ?? ''));
                    if ($num !== '') {
                        $lines[] = $num;
                    }
                } else {
                    $t = trim((string) $item);
                    if ($t !== '') {
                        $lines[] = $t;
                    }
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'instituicoes' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nome = trim((string) ($item['nome'] ?? ''));
                $sigla = trim((string) ($item['sigla'] ?? ''));
                if ($nome !== '' && $sigla !== '') {
                    $lines[] = $sigla . ' — ' . $nome;
                } elseif ($nome !== '') {
                    $lines[] = $nome;
                } elseif ($sigla !== '') {
                    $lines[] = $sigla;
                }
            }

            return implode("\n", $lines);
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
