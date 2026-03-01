<?php
declare(strict_types=1);

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
     * Gera as iniciais de um nome completo (sempre 2 letras)
     * Para nomes simples (1 palavra), retorna as 2 primeiras letras
     * Para nomes compostos (2+ palavras), retorna primeira letra do primeiro e último nome
     */
    public static function getInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '??';
        
        $parts = preg_split('/\s+/', $name);
        $parts = array_filter($parts);
        
        if (count($parts) === 0) return '??';
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }
        
        // Primeira letra do primeiro e último nome
        $first = reset($parts);
        $last = end($parts);
        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }
}
