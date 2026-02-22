    <!-- Detalhes informacionais do erro -->
    <div class="container-fluid my-4 mx-4">
        <div class="container-sm">
            <span class="fs-6 d-flex flex-row text-center">
                <?php
                // Texto informacional específico por erro: esperado em $info_text quando necessário.
                if (isset($info_text)) {
                    echo $info_text;
                }
                ?>
            </span>
        </div>
    </div>

    <!-- Detalhes técnicos do erro -->
    <?php
    $http_code_text = $http_code_text ?? ('HTTP ' . (isset($error_code) ? $error_code : '0'));
    $request_fullpath = $request_fullpath ?? ($_SERVER['REQUEST_URI'] ?? '');
    $request_method = $request_method ?? ($_SERVER['REQUEST_METHOD'] ?? '');
    $current_datetime = $current_datetime ?? date('Y-m-d H:i:s');

    // Campos utilitários extraídos do ambiente
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $protocol = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http'));
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['HTTP_X_CORRELATION_ID'] ?? '');
    $user_id = $_SESSION['id_usuario'] ?? ($_SESSION['user_id'] ?? '');

    // Texto curto/legível do status (opcional)
    $status_texts = [
        '401' => 'Não autorizado',
        '403' => 'Proibido',
        '404' => 'Não encontrado',
        '500' => 'Erro interno do servidor',
        '503' => 'Serviço indisponível',
    ];
    $status_label = isset($error_code) && isset($status_texts[(string)$error_code]) ? $status_texts[(string)$error_code] : '';

    // Campos padrão exibidos (serão sobrescritos por chaves em $details se fornecidas)
    $defaults = [
        'Código' => $protocol . ' - ' . $http_code_text,
        'Status' => $status_label,
        'Endpoint' => $host . $request_fullpath,
        'Método' => $request_method,
        'Query string' => $query_string,
        'IP do cliente' => $client_ip,
        'Usuário' => $user_id,
        'Data/Hora' => $current_datetime,
    ];

    // Merge mantendo a prioridade para valores já fornecidos em $details
    $details = $defaults;
?>

    <div class="container-fluid my-4 mx-4">
        <div class="container-sm d-flex justify-content-center align-items-center text-center">
            <div class="m-4 text-start">
                <h6 class="mb-3 fw-semibold">Detalhes técnicos</h6>
                <dl class="row mb-0">
                    <?php foreach ($details as $k => $v): ?>
                        <dt class="col-sm-3 text-truncate"><?php echo htmlspecialchars((string)$k); ?></dt>
                        <dd class="col-sm-9"><code class="text-break"><?php echo htmlspecialchars((string)$v); ?></code></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Informações do suporte -->
    <div class="container-fluid my-4 mx-4">
        <div class="container-md d-flex justify-content-center align-items-center flex-column text-center">
            <span class="fs-6 mb-2 mt-4"><?php echo isset($support_text) ? $support_text : 'Se precisar de ajuda, entre em contato com o suporte técnico responsável pela gestão de usuários do sistema. Envie um print dessa tela junto com a descrição do problema.'; ?></span>
            <span class="fs-7 mb-2">E-mail: <?php echo isset($support_email) ? htmlspecialchars($support_email) : 'jorgew.reis@outlook.com'; ?></span>
            <span class="fs-7 mb-2">Telefone: <?php echo isset($support_phone) ? htmlspecialchars($support_phone) : '(73) 99838-2911'; ?></span>
        </div>
    </div>

    
 