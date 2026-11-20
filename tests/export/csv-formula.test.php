<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/ListExportHelper.php';

ob_start();
ListExportHelper::streamCsv('teste', '=TITULO', "\t=FILTRO", [
    ['key'=>'texto','label'=>'+CABECALHO'], ['key'=>'numero','label'=>'Número'],
], [
    ['texto'=>' =1+1','numero'=>-42],
    ['texto'=>'-42','numero'=>0],
    ['texto'=>"\t@SUM(A1)", 'numero'=>-1.5],
    ['texto'=>'Nome normal','numero'=>10],
]);
$csv = ob_get_clean();
$file = fopen('php://temp', 'w+');
fwrite($file, substr($csv, 3));
rewind($file);
$rows = [];
while (($row = fgetcsv($file, 0, ';')) !== false) $rows[] = $row;
fclose($file);
if ($rows[4][0] !== "'=TITULO" || $rows[5][0] !== "'\t=FILTRO" || $rows[8][0] !== "'+CABECALHO") {
    throw new RuntimeException('Cabeçalho ou filtros não foram protegidos.');
}
if ($rows[9] !== ["'=1+1", '-42'] || $rows[10][0] !== "'-42" || $rows[11][0] !== "'@SUM(A1)" || $rows[11][1] !== '-1.5' || $rows[12][0] !== 'Nome normal') {
    throw new RuntimeException('Células não corresponderam à política.');
}
if (ListExportPresentation::csvText("\xff=1") !== "'\xff=1") throw new RuntimeException('Texto inválido não foi protegido.');
foreach (["=1", "+1", "-1", "@SUM(A1)", "\rtexto", "\ntexto", "\xc2\xa0=1"] as $unsafe) {
    if (ListExportPresentation::csvText($unsafe) !== "'" . $unsafe) throw new RuntimeException('Prefixo perigoso não protegido.');
}
echo "PASS serialização CSV de textos e números tipados\n";
