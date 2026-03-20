<?php
declare(strict_types=1);

Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Início — Área do estudante — Primeira Vara Crime de Ilhéus</title>
    <meta name="description" content="Painel inicial para usuários externos (estudantes e consultores).">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Área externa —</span>
            <span class="specific">Início</span>
        </div>
        <h1 class="title-page">Bem-vindo(a)</h1>
        <p class="description-page">Este é o seu painel de acesso ao sistema. Utilize o menu para consultar partes, processos e demais recursos liberados para o seu perfil.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>O que você pode fazer aqui</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md">
            <div class="col full-line">
                <ul class="minor-info" style="margin: 0; padding-left: 1.25rem; line-height: 1.6;">
                    <li><strong>Consultas:</strong> acesse <em>Consulta Partes</em>, <em>Consulta Processos</em>, <em>Instituições Públicas</em> e <em>CEP</em> pelo menu lateral.</li>
                    <li><strong>Dados pessoais:</strong> no ícone de configurações (engrenagem), altere senha ou dados cadastrais quando necessário.</li>
                    <li><strong>Limitação:</strong> tarefas de cartório, gabinete e gestão interna não estão disponíveis para usuários externos.</li>
                </ul>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md border-top">
            <div class="col full-line">
                <p class="minor-info" style="margin: 0;">Em caso de dúvida sobre liberação de acesso, procure a diretoria ou o administrador do sistema.</p>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
</body>
</html>
