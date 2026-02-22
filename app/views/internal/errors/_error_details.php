    <!-- Detalhes informacionais do erro -->
    <div class="container-fluid my-4 mx-4">
        <span class="container-md fs-7 d-flex flex-row text-start">
            <?php
            // Texto informacional específico por erro: esperado em $info_text quando necessário.
            if (isset($info_text)) {
                echo $info_text;
            }
            ?>
        </span>
    </div>

    <!-- Detalhes técnicos do erro -->
    <?php
    // Valores técnicos reais (podem ser sobrescritos)
    $http_code_text = $http_code_text ?? ('HTTP ' . (isset($error_code) ? $error_code : '0'));
    $request_fullpath = $request_fullpath ?? ($_SERVER['REQUEST_URI'] ?? '');
    $request_method = $request_method ?? ($_SERVER['REQUEST_METHOD'] ?? '');
    $current_datetime = $current_datetime ?? date('Y-m-d H:i:s');

    // Detalhes adicionais (opcional): aceitar array associativo $details
    $details = isset($details) && is_array($details) ? $details : [];
    // Garantir que os campos básicos estejam sempre presentes
    $details = array_merge([
        'Código' => $http_code_text,
        'Endpoint' => $request_fullpath,
        'Método' => $request_method,
        'Data/hora' => $current_datetime,
    ], $details);
    ?>

    <div class="container-fluid my-4 mx-4">
        <div class="container-md d-flex justify-content-center align-items-start flex-column text-start">
            <div class="error-details">
                <div class="card border-0 shadow-sm">
                    <div class="card-body small">
                        <h6>Detalhes técnicos</h6>
                        <dl class="row mb-0">
                            <?php foreach ($details as $k => $v): ?>
                                <dt class="col-sm-3 text-truncate"><?php echo htmlspecialchars((string)$k); ?></dt>
                                <dd class="col-sm-9"><code class="text-break"><?php echo htmlspecialchars((string)$v); ?></code></dd>
                            <?php endforeach; ?>
                        </dl>
                        <p class="mb-0 text-muted">Compartilhe o conteúdo acima com a equipe de suporte para investigação.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informações do suporte -->
    <div class="container-fluid my-4 mx-4">
        <div class="container-md d-flex justify-content-center align-items-center flex-column text-center">
            <span class="fs-7 mb-2 mt-4"><?php echo isset($support_text) ? $support_text : 'Se precisar de ajuda, entre em contato com o suporte técnico responsável pela gestão de usuários do sistema. Envie um print dessa tela junto com a descrição do problema.'; ?></span>
            <span class="fs-7 mb-2">E-mail: <?php echo isset($support_email) ? htmlspecialchars($support_email) : 'jorgew.reis@outlook.com'; ?></span>
            <span class="fs-7 mb-2">Telefone: <?php echo isset($support_phone) ? htmlspecialchars($support_phone) : '(73) 99838-2911'; ?></span>
        </div>
    </div>

    
 