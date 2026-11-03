<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/access/AccessEvaluator.php';
foreach (['SERVIDOR', 'ESTAGIARIO', 'CARTORIO', 'GABINETE', 'AUDIENCIA', 'SERVIDOR-GABINETE'] as $perfil) {
    if (!AccessEvaluator::canAccessPage('dashboard', [$perfil]) || !AccessEvaluator::canAccessPage('tarefas', [$perfil])) throw new RuntimeException('Perfil interno sem acesso às próprias tarefas: ' . $perfil);
    if (AccessEvaluator::canAccessPage('tarefasrules', [$perfil])) throw new RuntimeException('Perfil operacional pode administrar regras: ' . $perfil);
}
foreach (['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'] as $perfil) {
    if (!AccessEvaluator::canAccessPage('tarefasrules', [$perfil])) throw new RuntimeException('Perfil de gestão sem acesso às regras: ' . $perfil);
}
foreach (['ESTUDANTE', 'CONSULTOR'] as $perfil) {
    if (AccessEvaluator::canAccessPage('tarefas', [$perfil]) || AccessEvaluator::canAccessPage('tarefasrules', [$perfil])) throw new RuntimeException('Perfil externo ganhou acesso às tarefas: ' . $perfil);
}
echo "Permissões de tarefas verificadas.\n";
