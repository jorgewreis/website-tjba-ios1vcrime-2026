<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$p = dirname(__DIR__) . '/app/services/Guias.php';
$c = str_replace(["\r\n", "\r"], "\n", file_get_contents($p));
$c = str_replace(
    "require_once __DIR__ . '/../services/ProdutividadeService.php';\n\nclass Guias",
    "require_once __DIR__ . '/../helpers/CartorioProdutividade.php';\n\nclass Guias",
    $c,
    $n0
);
if ($n0 !== 1) {
    fwrite(STDERR, "n0=$n0\n");
    exit(1);
}
$c = str_replace(
    "    private ProdutividadeService \$produtividadeService;\n\n    public function __construct(PDO \$pdo)",
    "    public function __construct(PDO \$pdo)",
    $c,
    $n1
);
$c = str_replace(
    "        \$this->produtividadeService = new ProdutividadeService(\$pdo);\n",
    "",
    $c,
    $n2
);
$old = file_get_contents(dirname(__DIR__) . '/scripts/guias-prod-block-old.txt');
$new = file_get_contents(dirname(__DIR__) . '/scripts/guias-prod-block-new.txt');
