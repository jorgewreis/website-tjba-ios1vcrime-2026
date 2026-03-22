<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Conection.php';
require_once __DIR__ . '/../../config/security.php';

final class PasswordResetService
{
    private PDO $pdo;

    /** @param array<string, mixed> $data */
    private static function debugLog(string $message, array $data = [], string $hypothesisId = ''): void
    {
        $path = __DIR__ . '/../../debug-5b84a2.log';
        $payload = [
            'sessionId' => '5b84a2',
            'runId' => $data['runId'] ?? 'run1',
            'hypothesisId' => $hypothesisId,
            'location' => 'PasswordResetService',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        @file_put_contents($path, json_encode($payload) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function __construct()
    {
        $this->pdo = Conection::getInstance()->getConnection();
    }

    /**
     * Processa recuperação de senha. Retorna array com success, emailSent e message.
     * - success: dados válidos, usuário encontrado e senha atualizada no banco
     * - emailSent: e-mail foi de fato enviado (SMTP). Com MAIL_DRIVER=log não envia e-mail real.
     */
    public function process(string $cpf, string $email): array
    {
        $cpf = Security::normalizeCPF($cpf);
        $email = strtolower(trim($email));

        if (!Security::validarCPF($cpf) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success'   => false,
                'emailSent' => false,
                'message'   => 'Não foi possível processar. Verifique CPF e e-mail e tente novamente.'
            ];
        }

        $cpfFormatado = Security::formatCPF($cpf);

        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nome, email
            FROM usuarios
            WHERE cpf = ?
              AND LOWER(email) = ?
            LIMIT 1
        ");
        $stmt->execute([$cpfFormatado, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success'   => false,
                'emailSent' => false,
                'message'   => 'CPF e e-mail não conferem. Verifique os dados e tente novamente.'
            ];
        }

        $senhaTemp = Security::generateTempPassword(10);
        $senhaHash = Security::hashPassword($senhaTemp);

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare("
            UPDATE usuarios
               SET senha_hash   = ?,
                   token_acesso = NULL,
                   token_expira = NULL
             WHERE id_usuario = ?
             LIMIT 1
        ");
        $stmt->execute([$senhaHash, $user['id_usuario']]);

        if ($stmt->rowCount() < 1) {
            $this->pdo->rollBack();
            return [
                'success'   => false,
                'emailSent' => false,
                'message'   => 'Não foi possível atualizar a senha. Tente novamente.'
            ];
        }

        $sendResult = $this->sendEmail($user['email'], $user['nome'], $senhaTemp);

        if (!$sendResult['ok']) {
            $this->pdo->rollBack();
            return [
                'success'   => false,
                'emailSent' => false,
                'message'   => 'Senha redefinida, mas o e-mail não pôde ser enviado. Contate o suporte.'
            ];
        }

        $this->pdo->commit();

        return [
            'success'   => true,
            'emailSent' => $sendResult['sent'],
            'message'   => $sendResult['sent']
                ? 'Um e-mail foi enviado para o endereço informado com a nova senha. Verifique sua caixa de entrada.'
                : 'Senha redefinida. O envio de e-mail não está ativo neste ambiente. Contate o suporte para obter sua nova senha.'
        ];
    }

    /* ================= UTILITÁRIOS ================= */

    /** Garante que .env foi carregado. Tenta vários caminhos (project root, document root). */
    private function loadEnvOnce(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $candidates = [
            __DIR__ . '/../../.env',
            __DIR__ . '/../../../.env',
        ];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = $_SERVER['DOCUMENT_ROOT'] . '/../.env';
            $candidates[] = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        }
        $envPath = null;
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $envPath = $path;
                break;
            }
        }
        if ($envPath === null) {
            self::debugLog('loadEnvOnce: .env não encontrado em nenhum candidato', ['candidates' => $candidates], 'A');
            $loaded = true;
            return;
        }
        self::debugLog('loadEnvOnce: .env carregado', ['envPath' => $envPath], 'A');
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($key !== '') {
                $value = trim($value, " \t\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
        $loaded = true;
    }

    /** Lê variável de ambiente (getenv ou $_ENV). Aceita MAIL_USER/MAIL_PASS como alias. */
    private function env(string $key, ?string $default = null): ?string
    {
        $this->loadEnvOnce();
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return trim((string) $v);
        }
        $v = $_ENV[$key] ?? null;
        return $v !== null && $v !== '' ? trim((string) $v) : $default;
    }

    /**
     * Envia e-mail com a senha temporária. Retorna ['ok' => bool, 'sent' => bool].
     * ok: operação concluída (senha pode ser commitada). sent: e-mail foi de fato enviado (SMTP).
     */
    private function sendEmail(string $to, string $nome, string $senhaTemp): array
    {
        $driver = strtolower(trim((string) ($this->env('MAIL_DRIVER') ?? '')));
        if ($driver === '' && $this->env('MAIL_HOST') !== null) {
            $driver = 'smtp';
        }
        if ($driver === '') {
            $driver = 'log';
        }

        // ======================
        // Fallback (LOG): apenas registra no servidor; nenhum e-mail real enviado
        // ======================
        if ($driver !== 'smtp') {
            self::debugLog('sendEmail: branch log (driver não é smtp)', ['driver' => $driver], 'B');
            error_log("[RESET SENHA] {$to} | senha temporária: {$senhaTemp}");
            return ['ok' => true, 'sent' => false];
        }

        // ======================
        // SMTP (Hostinger, Microsoft 365, etc.)
        // ======================
        $host = $this->env('MAIL_HOST') ?? '';
        $user = $this->env('MAIL_USERNAME') ?? $this->env('MAIL_USER') ?? '';
        self::debugLog('sendEmail: após ler env', ['driver' => $driver, 'hasHost' => $host !== '', 'hasUser' => $user !== ''], 'B');
        if ($host === '' || $user === '') {
            self::debugLog('sendEmail: retorno early (host ou user vazio)', ['hostEmpty' => $host === '', 'userEmpty' => $user === ''], 'B');
            error_log('[RESET SENHA] SMTP não configurado: MAIL_HOST ou MAIL_USER vazio após carregar .env');
            return ['ok' => true, 'sent' => false];
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
            self::debugLog('sendEmail: PHPMailer não encontrado', ['autoloadsChecked' => $autoloads], 'E');
            error_log('[RESET SENHA] PHPMailer não encontrado. Execute: composer require phpmailer/phpmailer (na raiz do projeto)');
            return ['ok' => true, 'sent' => false];
        }
        require_once $autoload;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = (int) ($this->env('MAIL_PORT') ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $this->env('MAIL_PASSWORD') ?? $this->env('MAIL_PASS') ?? '';

            $encryption = strtolower(trim((string) ($this->env('MAIL_ENCRYPTION') ?? '')));
            if ($encryption === '' && $mail->Port === 465) {
                $encryption = 'ssl';
            }
            $mail->SMTPSecure = $encryption === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->CharSet = 'UTF-8';

            // Evita falha de SSL em alguns servidores (certificado)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $fromAddress = $this->env('MAIL_FROM_ADDRESS') ?? $user;
            $fromName    = $this->env('MAIL_FROM_NAME') ?: 'Sistema';

                $mail->setFrom($fromAddress, $fromName);
                $mail->addAddress($to, $nome);

                $html = '
                    <!DOCTYPE html>
                    <html lang="pt-BR">
                    <head>
                    <meta charset="UTF-8">
                    <title>Recuperação de Senha</title>
                    </head>
                    <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif;">

                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:20px 0;">
                    <tr>
                        <td align="center">

                        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; overflow:hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">

                            <tr>
                            <td style="background-color:#686BFB; padding:20px; text-align:center;">
                                <span style="color:#ffffff; font-size:18px; font-weight:bold;">
                                SGC - Primeira Vara Crime de Ilhéus
                                </span>
                            </td>
                            </tr>

                            <tr>
                            <td style="padding:30px; color:#333333; font-size:14px; line-height:1.6;">
                                <p>Olá, <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>.</p>

                                <p>Recebemos uma solicitação para redefinição de senha do seu acesso ao sistema.</p>

                                <p style="margin:25px 0; text-align:center;">
                                <span style="display:inline-block; background-color:#ff0040; color:#ffffff; padding:12px 20px; font-size:18px; font-weight: 600; letter-spacing:1px; border-radius:6px;">
                                    ' . htmlspecialchars($senhaTemp, ENT_QUOTES, 'UTF-8') . '
                                </span>
                                </p>

                                <p>Essa é uma <strong>senha temporária</strong>. Recomendamos que você a altere após realizar o login.</p>

                                <p style="margin-top:30px; font-size:12px; color:#777777;">
                                Caso você não tenha solicitado esta recuperação, ignore esta mensagem.
                                </p>
                            </td>
                            </tr>

                            <tr>
                            <td style="background-color:#f0f0f0; padding:15px; text-align:center; font-size:11px; color:#666666;">
                                © ' . date('Y') . ' Primeira Vara Crime de Ilhéus<br>
                                Este é um e-mail automático. Não responda.
                            </td>
                            </tr>

                        </table>

                        </td>
                    </tr>
                    </table>

                    </body>
                    </html>';


                $mail->isHTML(true);
                $mail->Subject = 'Recuperação de senha – Primeira Vara Crime de Ilhéus';
                $mail->Body    = $html;

                $mail->AltBody =
                    "Olá, {$nome}.\n\n" .
                    "Sua senha temporária é: {$senhaTemp}\n\n" .
                    "Recomendamos que você altere a senha após o login.\n";

                self::debugLog('sendEmail: chamando mail->send()', [], 'C');
                $mail->send();
                self::debugLog('sendEmail: mail->send() ok', ['sent' => true], 'C');
                return ['ok' => true, 'sent' => true];

        } catch (\Throwable $e) {
            self::debugLog('sendEmail: exceção em send', ['error' => $e->getMessage(), 'ok' => false, 'sent' => false], 'C');
            error_log('Erro SMTP: ' . $e->getMessage());
            return ['ok' => false, 'sent' => false];
        }
    }


}
