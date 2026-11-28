<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/access/PageAccessRegistry.php';

$frame = file_get_contents($root . '/public/frame.php');
$view = file_get_contents($root . '/app/views/internal/skills-pdf-intelligence.php');
$route = PageAccessRegistry::ruleForPage('skillspdfintelligence');
$originalRoute = PageAccessRegistry::ruleForPage('skillspdfmarkdown');

if (!is_array($route) || ($route['kind'] ?? null) !== 'all_internal') {
    throw new RuntimeException('A rota experimental deve exigir acesso interno.');
}
if ($route !== $originalRoute) {
    throw new RuntimeException('A rota experimental deve preservar a política da ferramenta atual.');
}
if (!is_string($frame) || !str_contains($frame, "'skillspdfintelligence' => \$PAGES . '/skills-pdf-intelligence.php'")) {
    throw new RuntimeException('A rota experimental precisa resolver para sua view própria.');
}
if (!is_string($view) || !str_contains($view, 'Security::requireAuthentication();')) {
    throw new RuntimeException('A view experimental deve exigir autenticação.');
}

echo "PASS PDF Intelligence access policy\n";
