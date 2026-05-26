<?php
declare(strict_types=1);

/**
 * Wrapper único de geração de PDF do projeto (Dompdf).
 *
 * Centraliza:
 * - Carregamento do `vendor/autoload.php` (instalado via `composer install`).
 * - Verificação da classe `\Dompdf\Dompdf`.
 * - Configuração padrão (paper/orientation, fonte com suporte a UTF-8).
 * - Cabeçalhos HTTP de download.
 *
 * Quem precisar gerar PDF a partir de HTML deve chamar
 * `PdfStreamHelper::streamHtml(...)` em vez de instanciar `Dompdf` diretamente,
 * para garantir comportamento consistente em toda a aplicação.
 */
final class PdfStreamHelper
{
    /**
     * Faz stream de um PDF gerado a partir de HTML para o navegador como download.
     *
     * @param string $filenameBase Nome do arquivo sem extensão; é sanitizado e
     *                             recebe sufixo `-YmdHis.pdf` automaticamente.
     * @param string $html         HTML completo (com `<!doctype>` e `<head>`).
     * @param array{
     *   paper?: string,
     *   orientation?: string,
     *   isRemoteEnabled?: bool,
     *   defaultFont?: string,
     *   filenameSuffix?: bool
     * } $opts
     *
     * @throws RuntimeException Quando o Dompdf não está disponível
     *                          (autoload ausente ou pacote não instalado).
     */
    public static function streamHtml(string $filenameBase, string $html, array $opts = []): void
    {
        self::loadDompdfAutoload();
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('Gerador de PDF não encontrado. Instale Dompdf para habilitar a exportação.');
        }

        $paper = isset($opts['paper']) && is_string($opts['paper']) && $opts['paper'] !== ''
            ? $opts['paper']
            : 'A4';
        $orientation = isset($opts['orientation']) && is_string($opts['orientation']) && $opts['orientation'] !== ''
            ? strtolower($opts['orientation'])
            : 'portrait';
        if (!in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }
        $isRemoteEnabled = (bool) ($opts['isRemoteEnabled'] ?? false);
        $defaultFont = isset($opts['defaultFont']) && is_string($opts['defaultFont']) && $opts['defaultFont'] !== ''
            ? $opts['defaultFont']
            : 'DejaVu Sans';
        $appendTimestamp = (bool) ($opts['filenameSuffix'] ?? true);

        $filename = self::buildFilename($filenameBase, $appendTimestamp);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => $isRemoteEnabled,
            'defaultFont' => $defaultFont,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        if (!headers_sent()) {
            header_remove('Content-Type');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo $dompdf->output();
    }

    /**
     * Retorna `true` quando o gerador de PDF está disponível.
     * Útil para falhar antes (com 500 plain text) sem buffer/headers já emitidos.
     */
    public static function isAvailable(): bool
    {
        self::loadDompdfAutoload();
        return class_exists(\Dompdf\Dompdf::class);
    }

    private static function loadDompdfAutoload(): void
    {
        if (class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    private static function buildFilename(string $base, bool $appendTimestamp): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
        if ($sanitized === null || $sanitized === '') {
            $sanitized = 'export';
        }
        if ($appendTimestamp) {
            $sanitized .= '-' . date('Ymd-His');
        }
        return $sanitized . '.pdf';
    }
}
