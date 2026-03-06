<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Conection.php';
require_once __DIR__ . '/../../config/security.php';

final class PasswordResetService
{
    private PDO $pdo;

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

    /** Lê variável de ambiente (getenv ou $_ENV). Aceita MAIL_USER/MAIL_PASS como alias. */
    private function env(string $key, ?string $default = null): ?string
    {
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
            error_log("[RESET SENHA] {$to} | senha temporária: {$senhaTemp}");
            return ['ok' => true, 'sent' => false];
        }

        // ======================
        // SMTP (Hostinger, Microsoft 365, etc.)
        // ======================
        require_once __DIR__ . '/../vendor/autoload.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->env('MAIL_HOST') ?? '';
            $mail->Port       = (int) ($this->env('MAIL_PORT') ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->env('MAIL_USERNAME') ?? $this->env('MAIL_USER') ?? '';
            $mail->Password   = $this->env('MAIL_PASSWORD') ?? $this->env('MAIL_PASS') ?? '';

            $encryption = strtolower(trim((string) ($this->env('MAIL_ENCRYPTION') ?? '')));
            if ($encryption === '' && $mail->Port === 465) {
                $encryption = 'ssl';
            }
            $mail->SMTPSecure = $encryption === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->CharSet = 'UTF-8';

            $fromAddress = $this->env('MAIL_FROM_ADDRESS') ?? $this->env('MAIL_USER') ?? '';
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

                        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:6px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.08);">

                            <tr>
                            <td style="background-color:#7a1f1f; padding:20px; text-align:center;">
                                <span style="color:#ffffff; font-size:18px; font-weight:bold;">
                                Primeira Vara Crime de Ilhéus
                                </span>
                            </td>
                            </tr>

                            <tr>
                            <td style="padding:30px; color:#333333; font-size:14px; line-height:1.6;">
                                <p>Olá, <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>.</p>

                                <p>Recebemos uma solicitação para redefinição de senha do seu acesso ao sistema.</p>

                                <p style="margin:25px 0; text-align:center;">
                                <span style="display:inline-block; background-color:#7a1f1f; color:#ffffff; padding:12px 20px; font-size:16px; letter-spacing:1px; border-radius:4px;">
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


                $mail->AltBody = $mail->Body;

                $mail->send();
                return ['ok' => true, 'sent' => true];

        } catch (\Throwable $e) {
            error_log('Erro SMTP: ' . $e->getMessage());
            return ['ok' => false, 'sent' => false];
        }
    }


}
