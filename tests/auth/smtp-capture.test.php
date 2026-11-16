<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/services/SmtpTransactionalMailer.php';

$result = (new SmtpTransactionalMailer())->sendHtml(
    [['email' => 'redefinicao@auth-test.invalid', 'name' => 'Conta Sintética']],
    'Teste de captura SMTP',
    '<p>Mensagem sintética do sandbox.</p>',
    'Mensagem sintética do sandbox.',
    ['log_context' => 'AUTH TEST', 'require_delivery' => true]
);

if ($result !== ['ok' => true, 'sent' => true]) {
    throw new RuntimeException('A entrega sintética ao Mailpit falhou.');
}

echo "PASS smtp-capture\n";
