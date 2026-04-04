<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$p = $root . '/app/services/Guias.php';
$c = str_replace(["\r\n", "\r"], "\n", file_get_contents($p));

$c = str_replace(
    "require_once __DIR__ . '/ProdutividadeService.php';",
    "require_once __DIR__ . '/../helpers/CartorioProdutividade.php';",
    $c,
    $n0
);
if ($n0 !== 1) { fwrite(STDERR, "replace require failed n0=$n0\n"); exit(1); }

$c = str_replace(
    "    private ReuManager \$reuManager;\n    private ProdutividadeService \$produtividadeService;\n\n    public function __construct",
    "    private ReuManager \$reuManager;\n\n    public function __construct",
    $c,
    $n1
);
if ($n1 !== 1) { fwrite(STDERR, "replace property failed n1=$n1\n"); exit(1); }

$c = str_replace(
    "        \$this->reuManager = new ReuManager(\$pdo);\n        \$this->produtividadeService = new ProdutividadeService(\$pdo);",
    "        \$this->reuManager = new ReuManager(\$pdo);",
    $c,
    $n2
);
if ($n2 !== 1) { fwrite(STDERR, "replace ctor failed n2=$n2\n"); exit(1); }

$old = <<<'OLD'
                $pontosProd = $temDetalhamento ? 2.0 : 1.0;
                try {
                    $this->produtividadeService->registrarAuto($usuarioProd, [
                        'tipo_ato' => 'Cadastro de guia',
                        'tipo_documento' => 'Guia',
                        'sistema_modulo' => 'cartorio-guias',
                        'quantidade' => $temDetalhamento ? 2 : 1,
                        'referencia_item_id' => $idGuia,
                        'referencia_sistema' => 'guias',
                        'numero_processo' => (string)($dados['processo'] ?? ''),
                        'classe_processo' => (string)($dados['classe_desc'] ?? $dados['classe_cod'] ?? ''),
                        'observacoes' => 'Registro automático no cadastro de guia.',
                        'data_hora_ato' => $agora,
                    ]);
                } catch (Throwable $e) {
                    // Produtividade é acessória ao fluxo de cadastro da guia.
                    error_log('Falha ao registrar produtividade automática em guia: ' . $e->getMessage());
                }
OLD;

$new = <<<'NEW'
                $pontosProd = $temDetalhamento ? 2.0 : 1.0;
                CartorioProdutividade::record($this->pdo, $usuarioProd, 'cartorio-guias.create', [
                    'quantidade' => $temDetalhamento ? 2 : 1,
                    'referencia_item_id' => $idGuia,
                    'referencia_sistema' => 'cartorio-guias',
                    'numero_processo' => (string)($dados['processo'] ?? ''),
                    'classe_processo' => (string)($dados['classe_desc'] ?? $dados['classe_cod'] ?? ''),
                    'data_hora_ato' => $agora,
                ]);
NEW;

$c = str_replace($old, $new, $c, $n3);
if ($n3 !== 1) { fwrite(STDERR, "replace prod block failed n3=$n3\n"); exit(1); }

file_put_contents($p, $c);
echo "Guias.php patched OK\n";
