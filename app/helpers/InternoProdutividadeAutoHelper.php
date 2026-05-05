<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ProdutividadeService.php';

/**
 * Lançamentos automáticos de produtividade para fluxos internos (fora do registry cartório).
 */
final class InternoProdutividadeAutoHelper
{
    /**
     * @param PDO    $connection
     * @param string $usuarioId      id_usuario da sessão
     * @param string $ruleKey        chave em app/config/interno-produtividade-regras.php
     * @param string $referenciaItemId identificador curto para dedupe (hash inclui minuto no serviço)
     * @param string $observacoes      texto para listagem (opcional)
     */
    public static function tryRegistrar(PDO $connection, string $usuarioId, string $ruleKey, string $referenciaItemId, string $observacoes = ''): void
    {
        $usuarioId = trim($usuarioId);
        $referenciaItemId = trim($referenciaItemId);
        if ($usuarioId === '' || $referenciaItemId === '') {
            return;
        }

        static $configs = null;
        if ($configs === null) {
            $path = __DIR__ . '/../config/interno-produtividade-regras.php';
            $configs = is_file($path) ? require $path : [];
        }

        if (!is_array($configs)) {
            return;
        }

        $def = $configs[$ruleKey] ?? null;
        if (!is_array($def)) {
            return;
        }

        $regraId = trim((string) ($def['regra_id'] ?? ''));
        if ($regraId === '') {
            return;
        }

        $obs = trim($observacoes);

        try {
            $service = new ProdutividadeService($connection);
            $payload = [
                'regra_id' => $regraId,
                'tipo_ato' => trim((string) ($def['tipo_ato'] ?? '')),
                'tipo_documento' => trim((string) ($def['tipo_documento'] ?? '')),
                'sistema_modulo' => trim((string) ($def['sistema_modulo'] ?? '')),
                'quantidade' => 1,
                'referencia_item_id' => $referenciaItemId,
                'referencia_sistema' => trim((string) ($def['referencia_sistema'] ?? $def['sistema_modulo'] ?? '')),
            ];
            if ($obs !== '') {
                $payload['observacoes'] = $obs;
            }
            $service->registrarAuto($usuarioId, $payload);
        } catch (Throwable $ignored) {
            // Não interromper o fluxo principal.
        }
    }
}
