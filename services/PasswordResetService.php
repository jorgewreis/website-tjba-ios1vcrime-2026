<?php
declare(strict_types=1);

require_once __DIR__ . '/../bd/Conection.php';
require_once __DIR__ . '/../config/security.php';

final class PasswordResetService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conection::getInstance()->getConnection();
    }

    public function process(string $cpf, string $email): void
    {
        $cpf = $this->normalizeCPF($cpf);
        $email = strtolower(trim($email));

        if (!$this->validarCPF($cpf) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // resposta sempre neutra
        }

        $cpfFormatado = $this->formatCPF($cpf);

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
            return;
        }

        $senhaTemp = $this->generateTempPassword();
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
            return;
        }

        if (!$this->sendEmail($user['email'], $user['nome'], $senhaTemp)) {
            $this->pdo->rollBack();
            return;
        }

        $this->pdo->commit();
    }

    /* ================= UTILITÁRIOS ================= */

    private function normalizeCPF(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    private function validarCPF(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($c = 0; $c < $t; $c++) {
                $soma += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $dig = ((10 * $soma) % 11) % 10;
            if ((int)$cpf[$t] !== $dig) {
                return false;
            }
        }
        return true;
    }

    private function formatCPF(string $cpf): string
    {
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' .
               substr($cpf, 9, 2);
    }

    private function generateTempPassword(int $len = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXY23456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    private function sendEmail(string $to, string $nome, string $senhaTemp): bool
    {
        $driver = getenv('MAIL_DRIVER') ?: 'log';

        // ======================
        // Fallback seguro (LOG)
        // ======================
        if ($driver === 'log') {
            error_log("[RESET SENHA] {$to} | senha temporária: {$senhaTemp}");
            return true;
        }

        // ======================
        // SMTP Microsoft 365
        // ======================
        if ($driver === 'smtp') {

            require_once __DIR__ . '/../vendor/autoload.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = getenv('MAIL_HOST');
                $mail->Port       = (int) getenv('MAIL_PORT');
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('MAIL_USERNAME');
                $mail->Password   = getenv('MAIL_PASSWORD');
                
                $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') === 'ssl'
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

                $mail->CharSet    = 'UTF-8';

                $fromAddress = getenv('MAIL_FROM_ADDRESS');
                $fromName    = getenv('MAIL_FROM_NAME') ?: 'Sistema';

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
                return true;

            } catch (\Throwable $e) {
                error_log('Erro SMTP: ' . $e->getMessage());
                return false;
            }
        }

        error_log('MAIL_DRIVER inválido ou não suportado');
        return false;
    }


}
