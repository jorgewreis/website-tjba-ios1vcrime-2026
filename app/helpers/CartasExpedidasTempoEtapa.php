<?php
declare(strict_types=1);

final class CartasExpedidasTempoEtapa
{
    /** Para cumprimento, a conclusão da subtarefa de envio é a origem, como no prazo da carta. */
    public static function inicio(string $etapa, mixed $cadastro, array $subtarefas): ?int
    {
        if ($etapa === 'envio') return self::timestamp($cadastro);
        if ($etapa !== 'devolucao') return null;

        $envios = array_values(array_filter($subtarefas, static fn ($item): bool =>
            is_array($item) && ($item['tipo'] ?? '') === CartasExpedidasSubtarefasRepository::TIPO_ENVIO
        ));
        if (count($envios) !== 1
            || !in_array(CartasExpedidasEstadoFluxo::normalizar((string) ($envios[0]['status'] ?? '')), ['concluida', 'concluída'], true)) {
            return null;
        }
        // Não usar a modificação da carta como substituto para uma data de envio ausente.
        return self::timestamp($envios[0]['updated_at'] ?? null);
    }

    private static function timestamp(mixed $valor): ?int
    {
        $raw = is_string($valor) ? trim($valor) : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $raw || $date->getTimestamp() <= 0) return null;
        return $date->getTimestamp();
    }

    public static function formatar(int $inicio, int $agora): string
    {
        $minutos = intdiv(max(0, $agora - $inicio), 60);
        if ($minutos === 0) return '<1min';
        if ($minutos < 60) return $minutos . 'min';
        $horas = intdiv($minutos, 60);
        if ($horas < 24) return $horas . 'h';
        $dias = intdiv($horas, 24);
        if ($dias < 30) return $dias . 'd';
        $meses = intdiv($dias, 30);
        return $meses . ($meses === 1 ? 'mês' : 'meses');
    }
}
