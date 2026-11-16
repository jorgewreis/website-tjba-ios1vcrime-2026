<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/access/AccessEvaluator.php';
$cases = [
    ['instituicoespublicas', ['CONSULTOR'], true],
    ['instituicoespublicas', ['ESTUDANTE'], false],
    ['instituicoespublicas', ['SERVIDOR-GABINETE'], true],
    ['instituicoespublicascreate', ['CONSULTOR'], false],
    ['instituicoespublicasedit', ['ESTUDANTE'], false],
    ['instituicoespublicasedit', ['SERVIDOR-GABINETE'], false],
    ['instituicoespublicascreate', ['SERVIDOR-CARTORIO'], true],
    ['instituicoespublicasedit', ['SERVIDOR-CARTORIO'], true],
];
foreach ($cases as [$page, $profiles, $expected]) {
    if (AccessEvaluator::canAccessPage($page, $profiles) !== $expected) {
        throw new RuntimeException("Política inesperada para {$page} e " . implode(',', $profiles));
    }
}
echo "PASS policy matrix (8 casos)\n";
