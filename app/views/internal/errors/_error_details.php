    <!-- Detalhes informacionais do erro -->
    <div class="container-fluid my-4 mx-4">
        <span class="container-md fs-7 d-flex flex-row text-start">
            <?php
            // Texto informacional padrão (pode ser sobrescrito por $info_text)
            if (isset($info_text)) {
                echo $info_text;
            } else {
            ?>
            O erro <?php echo isset($error_code) ? htmlspecialchars($error_code) : '401'; ?> significa que o sistema exige identificação válida para permitir o acesso ao conteúdo solicitado. Em regra, ocorre quando a sessão expirou, o usuário não realizou login ou as credenciais informadas não foram reconhecidas. É um mecanismo de proteção, semelhante ao controle de acesso a áreas restritas: é necessário possuir autorização formal para ingressar. Para resolver, realize novo login e verifique se seu perfil está adequado às permissões exigidas. Persistindo o problema, contate o suporte responsável pela gestão de usuários do sistema.
            <?php
            }
            ?>
        </span>
    </div>

    <!-- Detalhes técnicos do erro -->
    <div class="container-fluid my-4 mx-4">
        <div class="container-md d-flex justify-content-center align-items-start flex-column text-start">
            <span class="fs-7 mb-2 mt-4">Código de erro: <code><?php echo isset($http_code_text) ? htmlspecialchars($http_code_text) : 'HTTP 401 - Unauthorized'; ?></code></span>
            <span class="fs-7 mb-2">Endpoint: <code><?php echo isset($request_fullpath) ? htmlspecialchars($request_fullpath) : '<%= request.fullpath %>'; ?></code></span>
            <span class="fs-7 mb-2">Método HTTP: <code><?php echo isset($request_method) ? htmlspecialchars($request_method) : '<%= request.method %>'; ?></code></span>
            <span class="fs-7 mb-2">Data/hora: <code><?php echo isset($current_datetime) ? htmlspecialchars($current_datetime) : '<%= Time.current.strftime("%Y-%m-%d %H:%M:%S") %>'; ?></code></span>
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
 