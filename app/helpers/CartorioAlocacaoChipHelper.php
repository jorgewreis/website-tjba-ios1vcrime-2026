<?php
declare(strict_types=1);

require_once __DIR__ . '/CartorioAlocacaoTipo.php';

final class CartorioAlocacaoChipHelper
{
    /**
     * @param list<array<string,mixed>> $alocados
     * @return list<array<string,mixed>>
     */
    public static function ordenarAlocadosPorTipo(array $alocados): array
    {
        usort($alocados, static function (array $a, array $b): int {
            $pa = CartorioAlocacaoTipo::prioridade((string) ($a['tipo'] ?? ''));
            $pb = CartorioAlocacaoTipo::prioridade((string) ($b['tipo'] ?? ''));
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $ia = strtoupper(trim((string) ($a['iniciais'] ?? '')));
            $ib = strtoupper(trim((string) ($b['iniciais'] ?? '')));
            if ($ia !== $ib) {
                return strcmp($ia, $ib);
            }
            return strcmp((string) ($a['usuario_id'] ?? ''), (string) ($b['usuario_id'] ?? ''));
        });

        return $alocados;
    }

    /**
     * @param array<string,mixed> $alocado
     * @param array{entity_id?:string,origem?:string} $contexto
     * @return array{tipo:string,classe_tipo:string,title:string,aria_label:string,iniciais:string,usuario_id:string,alocado_id:string}|null
     */
    public static function buildChipData(array $alocado, array $contexto = []): ?array
    {
        $tipo = strtolower(trim((string) ($alocado['tipo'] ?? '')));
        if (!CartorioAlocacaoTipo::valido($tipo)) {
            self::logTipoInvalido($tipo, $alocado, $contexto);
            return null;
        }

        $nomeCompleto = trim((string) ($alocado['nome_completo'] ?? ''));
        $nomeLabel = $nomeCompleto !== '' ? $nomeCompleto : trim((string) ($alocado['iniciais'] ?? '—'));
        $title = self::buildTitle($tipo, $nomeLabel);
        return [
            'tipo' => $tipo,
            'classe_tipo' => ' ' . $tipo,
            'title' => $title,
            'aria_label' => $title,
            'iniciais' => (string) ($alocado['iniciais'] ?? '—'),
            'usuario_id' => (string) ($alocado['usuario_id'] ?? ''),
            'alocado_id' => (string) ($alocado['id'] ?? $alocado['alocado_id'] ?? ''),
        ];
    }

    private static function buildTitle(string $tipo, string $nomeLabel): string
    {
        if ($tipo === CartorioAlocacaoTipo::ALL) {
            return 'Responsável pelo cadastro: ' . $nomeLabel . ' - entrou automaticamente ao criar a tarefa';
        }
        if ($tipo === CartorioAlocacaoTipo::RULE) {
            return 'Alocação por regra do cartório: ' . $nomeLabel . ' - definido pela regra ativa do módulo';
        }
        return 'Alocação direta: ' . $nomeLabel . ' - incluído manualmente por alocação direcionada';
    }

    /**
     * @param array<string,mixed> $alocado
     * @param array{entity_id?:string,origem?:string} $contexto
     */
    private static function logTipoInvalido(string $tipo, array $alocado, array $contexto): void
    {
        $payload = [
            'tipo' => $tipo,
            'entity_id' => (string) ($contexto['entity_id'] ?? ''),
            'usuario_id' => (string) ($alocado['usuario_id'] ?? ''),
            'origem' => (string) ($contexto['origem'] ?? ''),
        ];
        error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

