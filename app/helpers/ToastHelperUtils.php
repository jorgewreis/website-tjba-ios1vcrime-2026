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
}
