<?php
$path = dirname(__DIR__) . '/app/views/internal/produtividade-list.php';
$c = file_get_contents($path);
$c = str_replace("\r\n", "\n", $c);
$old = <<<'OLD'
                $tipoDoc = trim((string) ($row['tipo_documento'] ?? ''));
                $tipoAto = trim((string) ($row['tipo_ato'] ?? ''));
                $sistemaModulo = trim((string) ($row['sistema_modulo'] ?? ''));
                $descricaoTarefa = implode(' - ', [
                    $tipoDoc !== '' ? $tipoDoc : 'Sem documento',
                    $tipoAto !== '' ? $tipoAto : 'Sem ato',
                    $sistemaModulo !== '' ? $sistemaModulo : 'Sem sistema',
                ]);
OLD;
$new = <<<'NEW'
                $tipoDoc = trim((string) ($row['tipo_documento'] ?? ''));
                $tipoAto = trim((string) ($row['tipo_ato'] ?? ''));
                $sistemaModulo = trim((string) ($row['sistema_modulo'] ?? ''));
                $obsRow = trim((string) ($row['observacoes'] ?? ''));
                $tipoLan = trim((string) ($row['tipo_lancamento'] ?? ''));
                if ($obsRow !== '' && $tipoLan === 'automatico') {
                    $descricaoTarefa = $obsRow;
                } else {
                    $descricaoTarefa = implode(' - ', [
                        $tipoDoc !== '' ? $tipoDoc : 'Sem documento',
                        $tipoAto !== '' ? $tipoAto : 'Sem ato',
                        $sistemaModulo !== '' ? $sistemaModulo : 'Sem sistema',
                    ]);
                }
NEW;
$c = str_replace($old, $new, $c, $n);
if ($n !== 1) {
    fwrite(STDERR, "list patch n=$n\n");
    exit(1);
}
file_put_contents($path, $c);
echo "list ok\n";
