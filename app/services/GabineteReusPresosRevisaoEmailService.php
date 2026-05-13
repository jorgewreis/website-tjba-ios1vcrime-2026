<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GabineteReuPresoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoMovimentacaoRepository.php';
require_once __DIR__ . '/GabineteReusPresosService.php';
require_once __DIR__ . '/SmtpTransactionalMailer.php';

final class GabineteReusPresosRevisaoEmailService
{
    private const TO_ENV = 'GABINETE_REUS_PRESOS_EMAIL_REVISAO_TO';
    private const BCC_ENV = 'GABINETE_REUS_PRESOS_EMAIL_REVISAO_BCC';
    private const HORIZONTE_ENV = 'GABINETE_REUS_PRESOS_EMAIL_REVISAO_HORIZONTE_DIAS';
    private const DEFAULT_TO = 'jorgew.reis@outlook.com';

    private PDO $connection;
    private GabineteReuPresoRepository $reuRepo;
    private GabineteReuPresoMovimentacaoRepository $movRepo;
    private GabineteReusPresosService $helperService;
    private SmtpTransactionalMailer $mailer;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->reuRepo = new GabineteReuPresoRepository($connection);
        $this->movRepo = new GabineteReuPresoMovimentacaoRepository($connection);
        $this->helperService = new GabineteReusPresosService($connection);
        $this->mailer = new SmtpTransactionalMailer();
    }

    /**
     * @return array{success:bool,sent:bool,count:int,message:string}
     */
    public function sendResumo(): array
    {
        $to = $this->parseEmailList((string) envValue(self::TO_ENV, self::DEFAULT_TO));
        if ($to === []) {
            return [
                'success' => false,
                'sent' => false,
                'count' => 0,
                'message' => 'Nenhum destinatário configurado para o resumo de réus presos.',
            ];
        }
        $bcc = $this->parseEmailList((string) envValue(self::BCC_ENV, ''));
        $horizonteDias = $this->resolveHorizonDays();
        $today = new DateTimeImmutable('today');
        $hojeYmd = $today->format('Y-m-d');
        $limiteYmd = $today->modify('+' . $horizonteDias . ' days')->format('Y-m-d');

        $rows = $this->reuRepo->fetchAtivosPresosComRevisaoEntre($hojeYmd, $limiteYmd);
        $count = count($rows);
        $reuIds = array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['id'] ?? ''));
        }, $rows), static function (string $v): bool {
            return $v !== '';
        }));
        $decisoesPorReu = $this->movRepo->fetchUltimasPorReuIdsComPrefixos(
            $reuIds,
            GabineteReusPresosService::PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS,
            3
        );

        $subject = 'Resumo reavaliação — ' . date('d/m/Y');
        $htmlBody = $this->buildHtmlBody($rows, $decisoesPorReu, $horizonteDias);
        $altBody = $this->buildAltBody($rows, $decisoesPorReu, $horizonteDias);

        $sendResult = $this->mailer->sendHtml(
            array_map(static function (string $email): array {
                return ['email' => $email];
            }, $to),
            $subject,
            $htmlBody,
            $altBody,
            [
                'bcc' => $bcc,
                'log_context' => 'REUS PRESOS REVISAO',
            ]
        );

        if (!$sendResult['ok']) {
            return [
                'success' => false,
                'sent' => false,
                'count' => $count,
                'message' => 'Falha ao enviar o resumo de réus presos por e-mail.',
            ];
        }
        if (!$sendResult['sent']) {
            return [
                'success' => true,
                'sent' => false,
                'count' => $count,
                'message' => 'Resumo processado, mas o envio de e-mail não está ativo neste ambiente.',
            ];
        }

        return [
            'success' => true,
            'sent' => true,
            'count' => $count,
            'message' => $count > 0
                ? 'Resumo enviado com sucesso.'
                : 'Resumo enviado com sucesso (sem registros no período).',
        ];
    }

    private function resolveHorizonDays(): int
    {
        $raw = (string) envValue(self::HORIZONTE_ENV, (string) GabineteReusPresosService::DIAS_REVISAO_ATENCAO);
        $days = (int) $raw;
        if ($days <= 0) {
            $days = GabineteReusPresosService::DIAS_REVISAO_ATENCAO;
        }
        return $days;
    }

    /**
     * @return list<string>
     */
    private function parseEmailList(string $csv): array
    {
        $items = array_map('trim', explode(',', $csv));
        $emails = [];
        foreach ($items as $item) {
            if ($item === '' || !filter_var($item, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = strtolower($item);
        }
        return array_values(array_unique($emails));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string, list<array<string,mixed>>> $decisoesPorReu
     */
    private function buildHtmlBody(array $rows, array $decisoesPorReu, int $horizonteDias): string
    {
        $linhas = '';
        if ($rows === []) {
            $linhas = '<tr><td colspan="5" style="border:1px solid #dcdcdc;padding:8px;">Nenhum registro no período.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $rid = trim((string) ($row['id'] ?? ''));
                $nome = $this->escape((string) ($row['nome'] ?? '—'));
                $processo = trim((string) ($row['processo_principal_numero'] ?? ''));
                $processo = $processo !== '' ? $processo : '—';
                $dataPrisaoRaw = trim((string) ($row['principal_data_prisao'] ?? ''));
                $dataPrisao = $this->formatDateBr($dataPrisaoRaw);
                $tempoPrisao = $this->helperService->formatDuracaoCustodia($dataPrisaoRaw !== '' ? $dataPrisaoRaw : null);
                $proximaRaw = trim((string) ($row['proxima_revisao'] ?? ''));
                $proxima = $this->formatDateBr($proximaRaw);
                $proximaTdStyle = 'border:1px solid #dcdcdc;padding:8px;vertical-align:top;font-weight:600;';
                if ($this->isProximaRevisaoUrgenteParaEmail($proximaRaw)) {
                    $proximaTdStyle .= 'color:crimson;';
                }
                $decisoes = $this->buildDecisoesHtml($decisoesPorReu[$rid] ?? []);
                $ultimaMov = $this->formatUltimaMovHtml((string) ($row['ultima_mov_raw'] ?? ''));

                $tdNome = 'border:1px solid #dcdcdc;padding:8px;vertical-align:top;font-weight:600;';
                $tdProcesso = 'border:1px solid #dcdcdc;padding:8px;vertical-align:top;font-weight:600;';

                $linhas .= '<tr>'
                    . '<td style="' . $tdNome . '">' . $nome . '</td>'
                    . '<td style="' . $tdProcesso . '">' . $this->escape($processo) . '</td>'
                    . '<td style="border:1px solid #dcdcdc;padding:8px;vertical-align:top;">' . $this->escape($dataPrisao) . '</td>'
                    . '<td style="border:1px solid #dcdcdc;padding:8px;vertical-align:top;">' . $this->escape($tempoPrisao) . '</td>'
                    . '<td style="' . $proximaTdStyle . '">' . $this->escape($proxima) . '</td>'
                    . '</tr>'
                    . '<tr>'
                    . '<td colspan="3" style="border:1px solid #dcdcdc;padding:8px;vertical-align:top;background:#fafafa;">' . $decisoes . '</td>'
                    . '<td colspan="2" style="border:1px solid #dcdcdc;padding:8px;vertical-align:top;background:#fafafa;">' . $ultimaMov . '</td>'
                    . '</tr>';
            }
        }

        $geradoEm = $this->formatGeradoEmLegivel();

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Resumo reavaliação</title>
</head>
<body style="margin:0;padding:16px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:1150px;margin:0 auto;background:#ffffff;border-collapse:collapse;">
        <tr>
            <td style="padding:16px;border-bottom:1px solid #eaeaea;">
                <p style="margin:0 0 4px 0;font-size:14px;color:#333;line-height:1.4;">Primeira Vara Crime da Comarca de Ilhéus</p>
                <p style="margin:0 0 14px 0;font-size:14px;color:#333;line-height:1.4;">Sistema de Gestão do Cartório</p>
                <p style="margin:0 0 10px 0;font-size:15px;color:#111;line-height:1.4;"><strong>Resumo semanal para Reavaliação de Prisões</strong></p>
                <p style="margin:0 0 8px 0;font-size:10px;color:#666;line-height:1.4;">Documento gerado automaticamente em ' . $this->escape($geradoEm) . '</p>
                <p style="margin:0;font-size:10px;color:#555;line-height:1.45;">Obs.: Essa lista mostra somente os processos com data próxima dos ' . (string) $horizonteDias . ' dias de vencimento e os já vencidos.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;color:#222;">
                    <thead>
                        <tr style="background:#f1f1f1;">
                            <th style="border:1px solid #dcdcdc;padding:8px;text-align:left;">Réu</th>
                            <th style="border:1px solid #dcdcdc;padding:8px;text-align:left;">Processo principal</th>
                            <th style="border:1px solid #dcdcdc;padding:8px;text-align:left;">Data da prisão</th>
                            <th style="border:1px solid #dcdcdc;padding:8px;text-align:left;">Tempo de prisão</th>
                            <th style="border:1px solid #dcdcdc;padding:8px;text-align:left;">Próxima reavaliação</th>
                        </tr>
                    </thead>
                    <tbody>' . $linhas . '</tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string, list<array<string,mixed>>> $decisoesPorReu
     */
    private function buildAltBody(array $rows, array $decisoesPorReu, int $horizonteDias): string
    {
        $lines = [];
        $lines[] = 'Primeira Vara Crime da Comarca de Ilhéus';
        $lines[] = 'Sistema de Gestão do Cartório';
        $lines[] = '';
        $lines[] = 'Resumo semanal para Reavaliação de Prisões';
        $lines[] = '';
        $lines[] = 'Documento gerado automaticamente em ' . $this->formatGeradoEmLegivel();
        $lines[] = 'Obs.: Essa lista mostra somente os processos com data próxima dos ' . (string) $horizonteDias . ' dias de vencimento e os já vencidos.';
        $lines[] = '';
        if ($rows === []) {
            $lines[] = 'Nenhum registro no período.';
            return implode("\n", $lines);
        }

        foreach ($rows as $row) {
            $rid = trim((string) ($row['id'] ?? ''));
            $lines[] = 'Réu: ' . trim((string) ($row['nome'] ?? '—'));
            $lines[] = 'Processo: ' . trim((string) ($row['processo_principal_numero'] ?? '—'));
            $dataPrisaoRaw = trim((string) ($row['principal_data_prisao'] ?? ''));
            $lines[] = 'Data prisão: ' . $this->formatDateBr($dataPrisaoRaw);
            $lines[] = 'Tempo prisão: ' . $this->helperService->formatDuracaoCustodia($dataPrisaoRaw !== '' ? $dataPrisaoRaw : null);
            $lines[] = 'Próxima reavaliação: ' . $this->formatDateBr((string) ($row['proxima_revisao'] ?? ''));
            $lines[] = 'Últimas decisões: ' . $this->buildDecisoesText($decisoesPorReu[$rid] ?? []);
            $lines[] = 'Última movimentação: ' . $this->formatUltimaMovPlain((string) ($row['ultima_mov_raw'] ?? ''));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private function buildDecisoesHtml(array $items): string
    {
        if ($items === []) {
            return '—';
        }
        $parts = [];
        foreach ($items as $item) {
            $data = $this->formatDateBr((string) ($item['data_movimentacao'] ?? ''));
            $label = $this->helperService->labelTipoMovimentacao((string) ($item['tipo_codigo'] ?? ''));
            $linha = $data . ' — ' . $label;
            $obs = trim((string) ($item['observacoes'] ?? ''));
            if ($obs !== '') {
                $linha .= ' — Obs.: ' . $obs;
            }
            $parts[] = $this->escape($linha);
        }
        return implode('<br>', $parts);
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private function buildDecisoesText(array $items): string
    {
        if ($items === []) {
            return '—';
        }
        $parts = [];
        foreach ($items as $item) {
            $data = $this->formatDateBr((string) ($item['data_movimentacao'] ?? ''));
            $label = $this->helperService->labelTipoMovimentacao((string) ($item['tipo_codigo'] ?? ''));
            $linha = $data . ' — ' . $label;
            $obs = trim((string) ($item['observacoes'] ?? ''));
            if ($obs !== '') {
                $linha .= ' — Obs.: ' . $obs;
            }
            $parts[] = $linha;
        }
        return implode('; ', $parts);
    }

    /**
     * Formato: `tipo_codigo|data_movimentacao` (legado) ou `tipo_codigo|data_movimentacao|observacoes`.
     *
     * @return array{label:string,data_br:string,obs:string}
     */
    private function parseUltimaMovRaw(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $parts = explode('|', $raw, 3);
        $tipo = trim((string) ($parts[0] ?? ''));
        $data = trim((string) ($parts[1] ?? ''));
        $obs = trim((string) ($parts[2] ?? ''));
        $label = $tipo !== '' ? $this->helperService->labelTipoMovimentacao($tipo) : '—';
        $dataBr = $data !== '' ? $this->formatDateBr($data) : '—';

        return ['label' => $label, 'data_br' => $dataBr, 'obs' => $obs];
    }

    private function formatUltimaMovPlain(string $raw): string
    {
        $p = $this->parseUltimaMovRaw($raw);
        if ($p === null) {
            return '—';
        }
        $out = $p['label'] . ' — ' . $p['data_br'];
        if ($p['obs'] !== '') {
            $out .= ' — Obs.: ' . $p['obs'];
        }

        return $out;
    }

    private function formatUltimaMovHtml(string $raw): string
    {
        $p = $this->parseUltimaMovRaw($raw);
        if ($p === null) {
            return '—';
        }
        $linha = $p['label'] . ' — ' . $p['data_br'];
        if ($p['obs'] !== '') {
            $linha .= "\nObs.: " . $p['obs'];
        }

        return nl2br($this->escape($linha), false);
    }

    /**
     * Data de reavaliação em destaque (crimson) no e-mail: vencida ou nos próximos 5 dias (inclusive hoje).
     */
    private function isProximaRevisaoUrgenteParaEmail(string $proximaYmd): bool
    {
        $proximaYmd = trim($proximaYmd);
        if ($proximaYmd === '') {
            return false;
        }
        $t = strtotime($proximaYmd);
        if ($t === false) {
            return false;
        }
        $hoje = strtotime('today');
        if ($hoje === false) {
            return false;
        }
        $diffDays = (int) floor(($t - $hoje) / 86400);
        if ($diffDays < 0) {
            return true;
        }

        return $diffDays <= 5;
    }

    private function formatDateBr(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '—';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '—';
        }
        return date('d/m/Y', $ts);
    }

    /** Data e hora da geração do documento (fuso já definido em `config/config.php`, ex. APP_TIMEZONE). */
    private function formatGeradoEmLegivel(): string
    {
        $agora = new DateTimeImmutable('now');

        return $agora->format('d/m/Y') . ' às ' . $agora->format('H:i:s');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
