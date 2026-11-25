<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$p = dirname(__DIR__) . '/app/services/Guias.php';
$c = file_get_contents($p);
$c = str_replace("\r\n", "\n", $c);
$c = str_replace(
    "require_once __DIR__ . '/../services/ProdutividadeService.php';\n\nclass Guias",
    "require_once __DIR__ . '/../helpers/CartorioProdutividade.php';\n\nclass Guias",
    $c,
    $n0
);
if ($n0 !== 1) exit("req $n0");
$c = str_replace(
    "    private ProdutividadeService \$produtividadeService;\n\n    public function __construct(PDO \$pdo)",
    "    public function __construct(PDO \$pdo)",
    $c,
    $n1
);
$c = str_replace(
    "        \$this->produtividadeService = new ProdutividadeService(\$pdo);\n    }",
    "    }",
    $c,
    $n2
);
$old = <<<'OLD'
            // 9. Registra na produtividade (somente se houver usuário válido)
            $usuarioProd = trim((string)($dados['user_cadastro'] ?? ''));
            $pontosProd = null;
            if ($usuarioProd !== '' && $usuarioProd !== 'sistema') {
                $temDetalhamento = false;
                $camposDetalhe = [
                    $dados['regime'] ?? '',
                    $dados['cpf'] ?? '',
                    $dados['telefone'] ?? '',
                    $dados['data_fato'] ?? '',
                    $dados['dias_multa'] ?? '',
                    $dados['observacoes'] ?? ''
                ];
                foreach ($camposDetalhe as $campo) {
                    if (trim((string)$campo) !== '') {
                        $temDetalhamento = true;
                        break;
                    }
                }

                $servidores = $dados['servidores'] ?? [];
                if (!$temDetalhamento && is_array($servidores) && count($servidores) > 0) {
                    $temDetalhamento = true;
                }

                $prioridades = $dados['prioridades'] ?? [];
                if (!$temDetalhamento && is_array($prioridades) && count($prioridades) > 0) {
                    $temDetalhamento = true;
                }

                $subtarefas = $dados['subtarefas'] ?? [];
                if (!$temDetalhamento && is_array($subtarefas) && count($subtarefas) > 0) {
                    $temDetalhamento = true;
                }

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
            }
OLD;
$new = <<<'NEW'
            // 9. Produtividade (cartório) — alinhado ao GuiasService / cartorio-guias
            $usuarioProd = trim((string)($dados['user_cadastro'] ?? ''));
            $pontosProd = null;
            if ($usuarioProd !== '' && $usuarioProd !== 'sistema') {
                $temDetalhamento = false;
                $camposDetalhe = [
                    $dados['regime'] ?? '',
                    $dados['cpf'] ?? '',
                    $dados['telefone'] ?? '',
                    $dados['data_fato'] ?? '',
                    $dados['dias_multa'] ?? '',
                    $dados['observacoes'] ?? ''
                ];
                foreach ($camposDetalhe as $campo) {
                    if (trim((string)$campo) !== '') {
                        $temDetalhamento = true;
                        break;
                    }
                }
                $servidores = $dados['servidores'] ?? [];
                if (!$temDetalhamento && is_array($servidores) && count($servidores) > 0) {
                    $temDetalhamento = true;
                }
                $prioridades = $dados['prioridades'] ?? [];
                if (!$temDetalhamento && is_array($prioridades) && count($prioridades) > 0) {
                    $temDetalhamento = true;
                }
                $subtarefas = $dados['subtarefas'] ?? [];
                if (!$temDetalhamento && is_array($subtarefas) && count($subtarefas) > 0) {
                    $temDetalhamento = true;
                }
                $q = $temDetalhamento ? 2 : 1;
                $pontosProd = (float) $q;
                try {
                    CartorioProdutividade::record($this->pdo, $usuarioProd, 'cartorio-guias.create', [
                        'quantidade' => $q,
                        'referencia_item_id' => $idGuia,
                        'referencia_sistema' => 'cartorio-guias',
                        'numero_processo' => (string)($dados['processo'] ?? ''),
                        'classe_processo' => (string)($dados['classe_desc'] ?? $dados['classe_cod'] ?? ''),
                        'data_hora_ato' => $agora,
                    ]);
                } catch (Throwable $e) {
                    error_log('Falha ao registrar produtividade automática em guia: ' . $e->getMessage());
                }
            }
NEW;
$c = str_replace($old, $new, $c, $n3);
if ($n3 !== 1) exit("block $n3 n1=$n1 n2=$n2");
file_put_contents($p, $c);
echo "guias legacy ok\n";
