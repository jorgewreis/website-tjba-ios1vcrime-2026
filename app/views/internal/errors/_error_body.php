<?php
declare(strict_types=1);

$validVariants = ['warning', 'danger', 'info', 'maintenance'];
$variant = isset($variant) && in_array($variant, $validVariants, true) ? $variant : 'warning';

$pageHeading = $pageHeading ?? '';
$description = $description ?? '';
$error_code = isset($error_code) ? (string) $error_code : '';
$icon_path = $icon_path ?? '/assets/icons/Alert-Triangle--Streamline-Ultimate.png';
$icon_alt = $icon_alt ?? 'Erro';
$info_text = $info_text ?? '';
/** @var array<string, string> $details */
$details = isset($details) && is_array($details) ? $details : [];

$support_text = $support_text ?? 'Se precisar de ajuda, entre em contato com o suporte técnico responsável pela gestão de usuários do sistema. Envie um print dessa tela junto com a descrição do problema.';
?>
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Sistema -</span>
            <span class="specific">Erro HTTP</span>
        </div>
        <h1 class="title-page"><?php echo htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full padding-x-md padding-y-lg border-bottom">
            <div class="error-page-hero">
                <img src="<?php echo htmlspecialchars($icon_path, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($icon_alt, ENT_QUOTES, 'UTF-8'); ?>" width="56" height="56">
                <span class="error-page-code error-page-code--<?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($error_code, ENT_QUOTES, 'UTF-8'); ?></span>
                <p class="error-page-lead"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
            <p class="display-flex row align-center justify-center padding-x-md padding-y-md error-page-prose">
                <?php echo htmlspecialchars($info_text, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
            <div class="display-flex col col-full">
                <h2 class="display-flex row align-center justify-center padding-x-md padding-y-md error-page-details-title">Detalhes técnicos</h2>
                <dl class="error-page-dl">
                    <?php foreach ($details as $label => $value): ?>
                        <dt><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></dt>
                        <dd><code><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></code></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-lg border-bottom">
            <div class="display-flex col col-full align-center">
                <p class="display-flex row align-center justify-center padding-x-md padding-y-md error-page-prose">
                    <?php echo htmlspecialchars($support_text, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>
    </div>
