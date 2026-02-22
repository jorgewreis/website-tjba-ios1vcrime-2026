<?php
// Valores padrão (podem ser sobrescritos antes do include)
$error_code = isset($error_code) ? $error_code : '401';
$error_title = isset($error_title) ? $error_title : 'Acesso não autorizado';
$error_message = isset($error_message) ? $error_message : 'A página que você está tentando acessar requer autenticação.';
?>

    <!-- Info Error -->
    <div class="container-fluid mx-4">
        <div class="d-flex flex-row justify-content-center align-items-center">
            <img class="icon-warning  mt-2" src="/assets/icons/Alert-Triangle--Streamline-Ultimate.png" alt="Ícone de aviso" width="48" height="48">
        </div>
        <div class="d-flex flex-row justify-content-center align-items-center my-2">            
            <span class="fw-bold ms-2 text-warning error-code"><?php echo htmlspecialchars($error_code); ?></span>
        </div>
        <div class="d-flex flex-row justify-content-center align-items-center">
            <span class="fs-4 fw-semibold"><?php echo htmlspecialchars($error_title); ?></span>
        </div>
        <div class="d-flex flex-row justify-content-center align-items-center">
            <span class="fs-5 fw-normal"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    </div>

