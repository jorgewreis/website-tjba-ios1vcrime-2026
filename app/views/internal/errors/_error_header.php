<?php
// Valores padrão (podem ser sobrescritos antes do include)
$error_code = isset($error_code) ? $error_code : '401';
$error_title = isset($error_title) ? $error_title : 'Acesso não autorizado';
$error_message = isset($error_message) ? $error_message : 'A página que você está tentando acessar requer autenticação.';
?>

    <!-- Info Error -->
    <div class="container-fluid mx-4">
        <div class="container-sm">
            <div class="d-flex flex-row justify-content-center align-items-center">
                <?php
                // Carrega mapa de ícones por código (permite sobrescrever via $error_icon_path / $error_icon_alt)
                $error_icons = [];
                $icons_file = __DIR__ . '/error_icons.php';
                if (is_file($icons_file)) {
                    $error_icons = include $icons_file;
                }
                $default_icon = '/assets/icons/Alert-Triangle--Streamline-Ultimate.png';
                $default_alt = 'Ícone de aviso';
                $error_icon_path = isset($error_icon_path) ? $error_icon_path : ($error_icons[$error_code]['path'] ?? $default_icon);
                $error_icon_alt = isset($error_icon_alt) ? $error_icon_alt : ($error_icons[$error_code]['alt'] ?? $default_alt);
                ?>
                <img class="icon-warning mt-2" src="<?php echo htmlspecialchars($error_icon_path); ?>" alt="<?php echo htmlspecialchars($error_icon_alt); ?>" width="48" height="48">
            </div>
            <div class="d-flex flex-row justify-content-center align-items-center my-2">            
                <span class="fw-bold ms-2 text-warning error-code"><?php echo htmlspecialchars($error_code); ?></span>
            </div>
            <div class="d-flex flex-row justify-content-center align-items-center">
                <span class="fs-4 fw-semibold"><?php echo htmlspecialchars($error_title); ?></span>
            </div>
            <div class="d-flex flex-row justify-content-center align-items-center my-5">
                <span class="fs-5 fw-normal text-center"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        </div>
    </div>

