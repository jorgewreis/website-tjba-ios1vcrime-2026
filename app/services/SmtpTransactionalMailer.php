<?php
declare(strict_types=1);

final class SmtpTransactionalMailer
{
    /**
     * @param list<array{email:string,name?:string}> $to
     * @param array{bcc?:list<string|array{email:string,name?:string}>,log_context?:string,log_message?:string,require_delivery?:bool} $options
     * @return array{ok:bool,sent:bool}
     */
    public function sendHtml(array $to, string $subject, string $htmlBody, string $altBody, array $options = []): array
    {
        $recipients = $this->normalizeRecipients($to);
        if ($recipients === []) {
            return ['ok' => false, 'sent' => false];
        }

        $driver = strtolower(trim((string) envValue('MAIL_DRIVER', '')));
        if ($driver === '' && envValue('MAIL_HOST') !== null) {
            $driver = 'smtp';
        }
        if ($driver === '') {
            $driver = 'log';
        }

        $logContext = trim((string) ($options['log_context'] ?? 'MAIL'));
        if ($driver !== 'smtp') {
            $extra = trim((string) ($options['log_message'] ?? ''));
            error_log('[' . $logContext . '] Entrega SMTP indisponível' . ($extra !== '' ? ': ' . $extra : '.'));
            return ['ok' => !($options['require_delivery'] ?? false), 'sent' => false];
        }

        $host = (string) envValue('MAIL_HOST', '');
        $user = (string) (envValue('MAIL_USERNAME') ?? envValue('MAIL_USER') ?? '');
        if ($host === '' || $user === '') {
            error_log('[' . $logContext . '] SMTP não configurado: MAIL_HOST ou MAIL_USER vazio.');
            return ['ok' => !($options['require_delivery'] ?? false), 'sent' => false];
        }

        $autoloads = [
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../vendor/autoload.php',
        ];
        $autoload = null;
        foreach ($autoloads as $path) {
            if (is_file($path)) {
                $autoload = $path;
                break;
            }
        }
        if ($autoload === null) {
            error_log('[' . $logContext . '] PHPMailer não encontrado. Execute: composer require phpmailer/phpmailer');
            return ['ok' => !($options['require_delivery'] ?? false), 'sent' => false];
        }
        require_once $autoload;

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            error_log('[' . $logContext . '] Pacote phpmailer/phpmailer ausente no vendor. Execute: composer install (ou composer require phpmailer/phpmailer).');
            return ['ok' => !($options['require_delivery'] ?? false), 'sent' => false];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) envValue('MAIL_PORT', '587');
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = (string) (envValue('MAIL_PASSWORD') ?? envValue('MAIL_PASS') ?? '');

            $encryption = strtolower(trim((string) envValue('MAIL_ENCRYPTION', '')));
            if ($encryption === '' && $mail->Port === 465) {
                $encryption = 'ssl';
            }

            $mail->SMTPSecure = $encryption === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->CharSet = 'UTF-8';
            $fromAddress = (string) (envValue('MAIL_FROM_ADDRESS') ?? $user);
            $fromName = (string) (envValue('MAIL_FROM_NAME') ?: 'Sistema');

            $mail->setFrom($fromAddress, $fromName);
            foreach ($recipients as $recipient) {
                $mail->addAddress(
                    (string) $recipient['email'],
                    trim((string) ($recipient['name'] ?? ''))
                );
            }
            foreach ($this->normalizeRecipients((array) ($options['bcc'] ?? [])) as $bcc) {
                $mail->addBCC(
                    (string) $bcc['email'],
                    trim((string) ($bcc['name'] ?? ''))
                );
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody;
            $mail->send();

            return ['ok' => true, 'sent' => true];
        } catch (Throwable $e) {
            error_log('Erro SMTP ' . $logContext . ': ' . $e->getMessage());
            return ['ok' => false, 'sent' => false];
        }
    }

    /**
     * @param list<array{email:string,name?:string}|string> $raw
     * @return list<array{email:string,name?:string}>
     */
    private function normalizeRecipients(array $raw): array
    {
        $out = [];
        foreach ($raw as $item) {
            $email = '';
            $name = '';
            if (is_string($item)) {
                $email = strtolower(trim($item));
            } elseif (is_array($item)) {
                $email = strtolower(trim((string) ($item['email'] ?? '')));
                $name = trim((string) ($item['name'] ?? ''));
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $out[] = $name !== '' ? ['email' => $email, 'name' => $name] : ['email' => $email];
        }
        return $out;
    }
}
