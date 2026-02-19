<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';

Security::requireAuthentication();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

	<link rel=stylesheet href=/assets/css/shared/reset.css>
	<link rel=stylesheet href=/assets/css/shared/variables.css>
	<link rel=stylesheet href=/assets/css/shared/base.css>
	<link rel=stylesheet href=/assets/css/shared/components.css>
	<link rel=stylesheet href=/assets/css/shared/forms.css>
</head>
<body>
	<div class="container-main">
		<div class="title-page">
			<span class="title">Ofícios (Gabinete)</span>
			<span class="info-title">Aqui o Gabinete se manifesta de modo oficial, com numeração própria e registro histórico, para que cada ato tenha existência, contexto e lastro institucional.</span>
		</div>
	</div>
</body>
</html>