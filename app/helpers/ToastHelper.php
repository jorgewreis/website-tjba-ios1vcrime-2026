<?php
declare(strict_types=1);

/**
 * ============================================
 * SISTEMA DE NOTIFICAÇÕES TOAST - PHP HELPER
 * ============================================
 * Funções auxiliares para integração com PHP
 * ============================================
 */

class ToastHelper
{
    /**
     * Tipos de toast disponíveis
     */
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_ALERT = 'alert';
    const TYPE_INFO = 'info';
    const TYPE_TIP = 'tip';
    const TYPE_HINT = 'hint';
    const TYPE_PRODUCTIVITY = 'productivity';

    /**
     * Gera o script JavaScript para exibir um toast
     * 
     * @param string $type Tipo do toast
     * @param string $title Título
     * @param string $message Mensagem principal
     * @param string $details Detalhes complementares
     * @param int|null $duration Duração em ms (null para auto)
     * @return string Script HTML completo
     */
    public static function render(
        string $type,
        string $title,
        string $message,
        string $details = '',
        ?int $duration = null
    ): string {
        $data = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'details' => $details
        ];

        if ($duration !== null) {
            $data['duration'] = $duration;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return "<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.Toast) {
        Toast.show({$json});
    }
});
</script>";
    }

    /**
     * Toast de sucesso
     */
    public static function success(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_SUCCESS, $title, $message, $details, $duration);
    }

    /**
     * Toast de erro
     */
    public static function error(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_ERROR, $title, $message, $details, $duration);
    }

    /**
     * Toast de alerta/aviso
     */
    public static function warning(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_WARNING, $title, $message, $details, $duration);
    }

    /**
     * Toast de informação
     */
    public static function info(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_INFO, $title, $message, $details, $duration);
    }

    /**
     * Toast de dica
     */
    public static function tip(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_TIP, $title, $message, $details, $duration);
    }

    /**
     * Toast de produtividade
     */
    public static function productivity(string $title, string $message, string $details = '', ?int $duration = null): string
    {
        return self::render(self::TYPE_PRODUCTIVITY, $title, $message, $details, $duration);
    }

    /**
     * Renderiza toast a partir de parâmetros GET
     * Útil para redirects com mensagens
     * 
     * Exemplo de uso:
     * header('Location: guias.php?toast_type=success&toast_title=Sucesso&toast_message=Guia cadastrada');
     * 
     * @return string Script HTML ou string vazia se não houver parâmetros
     */
    public static function renderFromGet(): string
    {
        $type = trim((string)($_GET['toast_type'] ?? ''));
        $title = trim((string)($_GET['toast_title'] ?? ''));
        $message = trim((string)($_GET['toast_message'] ?? ''));
        $details = trim((string)($_GET['toast_details'] ?? ''));

        if ($type === '' || $title === '') {
            return '';
        }

        // Validar tipo
        $validTypes = [
            self::TYPE_SUCCESS,
            self::TYPE_ERROR,
            self::TYPE_WARNING,
            self::TYPE_ALERT,
            self::TYPE_INFO,
            self::TYPE_TIP,
            self::TYPE_HINT,
            self::TYPE_PRODUCTIVITY
        ];

        if (!in_array($type, $validTypes, true)) {
            $type = self::TYPE_INFO;
        }

        return self::render($type, $title, $message, $details);
    }

    /**
     * Armazena toast na sessão para exibição após redirect
     * 
     * @param string $type Tipo do toast
     * @param string $title Título
     * @param string $message Mensagem
     * @param string $details Detalhes
     */
    public static function flash(
        string $type,
        string $title,
        string $message,
        string $details = ''
    ): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['toast_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'details' => $details
        ];
    }

    /**
     * Renderiza toast armazenado na sessão e limpa
     * 
     * @return string Script HTML ou string vazia
     */
    public static function renderFlash(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['toast_flash'])) {
            return '';
        }

        $data = $_SESSION['toast_flash'];
        unset($_SESSION['toast_flash']);

        return self::render(
            $data['type'] ?? self::TYPE_INFO,
            $data['title'] ?? '',
            $data['message'] ?? '',
            $data['details'] ?? ''
        );
    }

    /**
     * Atalho para flash de sucesso
     */
    public static function flashSuccess(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_SUCCESS, $title, $message, $details);
    }

    /**
     * Atalho para flash de erro
     */
    public static function flashError(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_ERROR, $title, $message, $details);
    }

    /**
     * Atalho para flash de aviso
     */
    public static function flashWarning(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_WARNING, $title, $message, $details);
    }

    /**
     * Atalho para flash de info
     */
    public static function flashInfo(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_INFO, $title, $message, $details);
    }

    /**
     * Atalho para flash de dica
     */
    public static function flashTip(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_TIP, $title, $message, $details);
    }

    /**
     * Atalho para flash de produtividade
     */
    public static function flashProductivity(string $title, string $message, string $details = ''): void
    {
        self::flash(self::TYPE_PRODUCTIVITY, $title, $message, $details);
    }
}
