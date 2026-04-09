<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/CartorioAlocacaoChipHelper.php';

/**
 * Script de asserts simples para o helper de chips de alocação (sem PHPUnit).
 *
 * Uso:
 * php scripts/assert-cartorio-alocacao-chip-helper.php
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' | esperado: ' . var_export($expected, true) . ' | atual: ' . var_export($actual, true));
    }
}

try {
    $chipAll = CartorioAlocacaoChipHelper::buildChipData([
        'tipo' => 'all',
        'iniciais' => 'JD',
        'nome_completo' => 'Joao Dias',
        'usuario_id' => 'USR1',
        'alocado_id' => 'AL1',
    ], ['entity_id' => 'X1', 'origem' => 'teste']);
    assertTrue($chipAll !== null, 'Chip all deve ser válido.');
    assertTrue(str_contains((string) $chipAll['title'], 'Responsável pelo cadastro:'), 'Título do tipo all inválido.');
    assertTrue(str_contains((string) $chipAll['title'], ' - '), 'Título deve usar separador " - ".');
    assertSame($chipAll['title'], $chipAll['aria_label'], 'aria-label deve ser igual ao title.');

    $chipRule = CartorioAlocacaoChipHelper::buildChipData([
        'tipo' => 'rule',
        'iniciais' => 'AS',
        'nome_completo' => 'Ana Souza',
        'usuario_id' => 'USR2',
        'alocado_id' => 'AL2',
    ], ['entity_id' => 'X2', 'origem' => 'teste']);
    assertTrue($chipRule !== null && str_contains((string) $chipRule['title'], 'Alocação por regra do cartório:'), 'Título do tipo rule inválido.');

    $chipSpecific = CartorioAlocacaoChipHelper::buildChipData([
        'tipo' => 'specific',
        'iniciais' => 'BM',
        'nome_completo' => 'Bruna Maia',
        'usuario_id' => 'USR3',
        'alocado_id' => 'AL3',
    ], ['entity_id' => 'X3', 'origem' => 'teste']);
    assertTrue($chipSpecific !== null && str_contains((string) $chipSpecific['title'], 'Alocação direta:'), 'Título do tipo specific inválido.');

    $ord = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo([
        ['tipo' => 'specific', 'iniciais' => 'ZZ'],
        ['tipo' => 'all', 'iniciais' => 'CC'],
        ['tipo' => 'rule', 'iniciais' => 'BB'],
        ['tipo' => 'all', 'iniciais' => 'AA'],
    ]);
    assertSame('all', $ord[0]['tipo'], 'Primeiro deve ser all.');
    assertSame('AA', $ord[0]['iniciais'], 'Desempate por iniciais A-Z falhou.');
    assertSame('all', $ord[1]['tipo'], 'Segundo deve ser all.');
    assertSame('rule', $ord[2]['tipo'], 'Terceiro deve ser rule.');
    assertSame('specific', $ord[3]['tipo'], 'Quarto deve ser specific.');

    $chipInvalid = CartorioAlocacaoChipHelper::buildChipData([
        'tipo' => 'legacy',
        'iniciais' => 'LG',
        'nome_completo' => 'Legacy User',
        'usuario_id' => 'USR9',
        'alocado_id' => 'AL9',
    ], ['entity_id' => 'X9', 'origem' => 'teste']);
    assertSame(null, $chipInvalid, 'Tipo inválido deve retornar null.');

    echo "OK: CartorioAlocacaoChipHelper validado.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}

