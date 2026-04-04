<?php
$base = dirname(__DIR__);
$registry = require $base . '/app/config/cartorio-produtividade-registry.php';

function sqlEsc(string $s): string {
    return str_replace(["\\", "'"], ["\\\\", "''"], $s);
}

$lines = [];
$lines[] = '-- Seed regras cartório → produtividade (idempotente por id fixo derivado do event_code)';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = '';

$estornoId = 'PRG-CARTORIOESTORNO000000000001';
$lines[] = "INSERT IGNORE INTO `produtividade_regras` (`id`,`tipo_ato`,`tipo_documento`,`sistema_modulo`,`pontos_por_ato`,`pontos_multiplicador`,`ativo`,`observacoes`,`criado_por`) VALUES ('{$estornoId}','Estorno','Espelho cartório','cartorio-interno',1.00,1.00,1,'Regra técnica para estorno de saldo (pontos definidos no lançamento)',NULL);";
$lines[] = '';

foreach ($registry as $code => $def) {
    $id = 'PRG-' . strtoupper(substr(hash('sha256', $code), 0, 26));
    $ta = sqlEsc((string)($def['tipo_ato'] ?? 'Cartório'));
    $td = sqlEsc((string)($def['tipo_documento'] ?? $code));
    $sm = sqlEsc((string)($def['sistema_modulo'] ?? ''));
    $lines[] = "INSERT IGNORE INTO `produtividade_regras` (`id`,`tipo_ato`,`tipo_documento`,`sistema_modulo`,`pontos_por_ato`,`pontos_multiplicador`,`ativo`,`observacoes`,`criado_por`) VALUES ('{$id}','{$ta}','{$td}','{$sm}',1.00,1.00,1,NULL,NULL);";
}

file_put_contents($base . '/docs/database/seed-cartorio-produtividade-regras.sql', implode("\n", $lines) . "\n");
echo count($registry) + 1, " inserts\n";
