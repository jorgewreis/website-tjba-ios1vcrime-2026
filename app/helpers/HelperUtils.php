<?php
declare(strict_types=1);

require_once __DIR__ . '/UserInitialsHelper.php';

class Helper
{
    /**
     * Gera um ID único para guias, processos e outras entidades
     */
    public static function gerarId(): string
    {
        static $ultimoTempo = 0;
        static $sequencia = 0;

        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $base = strlen($alfabeto);
        $atual = (int) floor(microtime(true) * 1_000_000);

        if ($atual === $ultimoTempo) {
            $sequencia++;
            if ($sequencia >= 1024) {
                do {
                    usleep(1);
                    $atual = (int) floor(microtime(true) * 1_000_000);
                } while ($atual <= $ultimoTempo);
                $sequencia = 0;
            }
        } else {
            $sequencia = 0;
        }

        $ultimoTempo = $atual;
        $combinado = ($atual << 10) | $sequencia;

        $cod = '';
        do {
            $cod = $alfabeto[$combinado % $base] . $cod;
            $combinado = intdiv($combinado, $base);
        } while ($combinado > 0);

        $len = strlen($cod);
        if ($len < 20) {
            for ($i = 0, $max = strlen($alfabeto) - 1; $i < 20 - $len; $i++) {
                $cod .= $alfabeto[random_int(0, $max)];
            }
        } elseif ($len > 20) {
            $cod = substr($cod, -20);
        }
        return $cod;
    }

    /**
     * Iniciais a partir de nome completo em uma string (mesma regra que UserInitialsHelper).
     */
    public static function getInitials(string $name): string
    {
        $s = UserInitialsHelper::fromFullName($name);
        return $s === '—' ? '??' : $s;
    }
}
