<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Conection.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/SmtpTransactionalMailer.php';

final class PasswordResetService
{
    private const TABLE = 'recuperacao_senha';
    private const PASSWORD_HISTORY_LIMIT = 5;

    private PDO $pdo;
    private bool $storageChecked = false;
    private bool $storageReady = false;

    public function __construct()
    {
        $this->pdo = Conection::getInstance()->getConnection();
    }

    /**
     * Mantido por compatibilidade com o endpoint antigo.
     *
     * @return array{success:bool,emailSent:bool,message:string}
     */
    public function process(string $cpf, string $email): array
    {
        return $this->requestReset($cpf, $email);
    }

    /**
     * @return array{success:bool,emailSent:bool,message:string}
     */
    public function requestReset(string $cpf, string $email): array
    {
        $cpf = Security::normalizeCPF($cpf);
        $email = strtolower(trim($email));

        if (!Security::validarCPF($cpf) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'emailSent' => false,
                'message' => 'Não foi possível processar. Verifique CPF e e-mail e tente novamente.',
            ];
        }

        if (!$this->ensureStorage()) {
            return [
                'success' => false,
                'emailSent' => false,
                'message' => 'Fluxo de recuperação indisponível nesta instalação. Contate o suporte.',
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
                'success' => false,
                'emailSent' => false,
                'message' => 'CPF e e-mail não conferem. Verifique os dados e tente novamente.',
            ];
        }

        $plainToken = Security::generateSecureToken(32);
        $tokenHash = Security::hashAccessToken($plainToken);
        $resetId = $this->generateId('REC');
        $resetUrl = $this->buildResetUrl($plainToken);
        $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_TTL_SECONDS);
        $ip = Security::getClientIp();

        $this->pdo->beginTransaction();

        try {
            $invalidateStmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                   SET usado = 1
                 WHERE usuario = ?
                   AND usado = 0
            ");
            $invalidateStmt->execute([$user['id_usuario']]);

            $insertStmt = $this->pdo->prepare("
                INSERT INTO " . self::TABLE . " (
                    id_recuperacao, usuario, token, expira_em, usado, ip, data_solicitacao
                ) VALUES (
                    ?, ?, ?, ?, 0, ?, NOW()
                )
            ");
            $insertStmt->execute([$resetId, $user['id_usuario'], $tokenHash, $expiresAt, $ip]);

            $sendResult = $this->sendEmail((string) $user['email'], (string) $user['nome'], $resetUrl);
            if (!$sendResult['ok']) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'emailSent' => false,
                    'message' => 'Não foi possível enviar a recuperação de senha. Tente novamente mais tarde.',
                ];
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'emailSent' => $sendResult['sent'],
                'message' => $sendResult['sent']
                    ? 'Enviamos um link de redefinição para o e-mail informado. Verifique sua caixa de entrada.'
                    : 'A solicitação foi registrada, mas o envio de e-mail não está ativo neste ambiente. Contate o suporte.',
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('PasswordResetService::requestReset: ' . $e->getMessage());
            return [
                'success' => false,
                'emailSent' => false,
                'message' => 'Não foi possível processar a recuperação de senha. Tente novamente mais tarde.',
            ];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function validateResetToken(string $plainToken): ?array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !$this->ensureStorage()) {
            return null;
        }

        $tokenHash = Security::hashAccessToken($plainToken);
        $stmt = $this->pdo->prepare("
            SELECT r.id_recuperacao, r.usuario, r.expira_em, u.email, u.nome
              FROM " . self::TABLE . " r
              INNER JOIN usuarios u ON u.id_usuario = r.usuario
             WHERE r.token = ?
               AND r.usado = 0
               AND r.expira_em > NOW()
             LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function resetPassword(string $plainToken, string $novaSenha): array
    {
        $resetRequest = $this->validateResetToken($plainToken);
        if ($resetRequest === null) {
            return ['success' => false, 'message' => 'O link de redefinição é inválido ou expirou.'];
        }

        $policy = Security::validatePasswordPolicy($novaSenha);
        if (($policy['valid'] ?? false) !== true) {
            return ['success' => false, 'message' => $policy['message'] ?? 'Senha inválida.'];
        }

        $userId = trim((string) ($resetRequest['usuario'] ?? ''));
        if ($userId === '') {
            return ['success' => false, 'message' => 'Usuário da redefinição não encontrado.'];
        }

        if ($this->hasRecentPasswordReuse($userId, $novaSenha)) {
            return ['success' => false, 'message' => 'A nova senha não pode repetir nenhuma das últimas senhas utilizadas.'];
        }

        $tokenHash = Security::hashAccessToken($plainToken);
        $passwordHash = Security::hashPassword($novaSenha);

        $this->pdo->beginTransaction();

        try {
            $this->updateUserPassword($userId, $passwordHash, true);

            $markUsedStmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                   SET usado = 1
                 WHERE token = ?
                   AND usado = 0
                 LIMIT 1
            ");
            $markUsedStmt->execute([$tokenHash]);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'Senha redefinida com sucesso. Faça login com a nova senha.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('PasswordResetService::resetPassword: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Não foi possível redefinir a senha. Tente novamente.'];
        }
    }

    private function ensureStorage(): bool
    {
        if ($this->storageChecked) {
            return $this->storageReady;
        }

        $this->storageChecked = true;

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                    id_recuperacao CHAR(30) NOT NULL PRIMARY KEY,
                    usuario CHAR(30) NOT NULL,
                    token VARCHAR(100) NOT NULL UNIQUE,
                    expira_em DATETIME NOT NULL,
                    usado TINYINT(1) NOT NULL DEFAULT 0,
                    ip VARCHAR(45) NOT NULL,
                    data_solicitacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_usuario (usuario),
                    INDEX idx_token (token),
                    INDEX idx_expira (expira_em)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $this->upgradeRecuperacaoSenhaSchemaIfNeeded();

            $this->storageReady = true;
        } catch (Throwable $e) {
            error_log('PasswordResetService: armazenamento indisponível: ' . $e->getMessage());
            $this->storageReady = false;
        }

        return $this->storageReady;
    }

    /**
     * Instalações antigas podem ter criado `recuperacao_senha` antes do hash SHA-256 (64 hex)
     * ou sem a coluna `ip`. `CREATE TABLE IF NOT EXISTS` não corrige isso — o INSERT quebra
     * e o usuário vê apenas a mensagem genérica de "processar recuperação".
     */
    private function upgradeRecuperacaoSenhaSchemaIfNeeded(): void
    {
        try {
            $dbName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($dbName) || $dbName === '') {
                return;
            }

            $stmt = $this->pdo->prepare(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = ?'
            );
            $stmt->execute([$dbName, self::TABLE]);
            $cols = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (is_array($row) && isset($row['COLUMN_NAME'])) {
                    $cols[(string) $row['COLUMN_NAME']] = $row;
                }
            }

            if ($cols === []) {
                return;
            }

            if (!isset($cols['usado'])) {
                $this->pdo->exec(
                    'ALTER TABLE ' . self::TABLE . ' ADD COLUMN usado TINYINT(1) NOT NULL DEFAULT 0'
                );
            }

            if (!isset($cols['ip'])) {
                $after = isset($cols['usado']) ? ' AFTER usado' : '';
                $this->pdo->exec(
                    'ALTER TABLE ' . self::TABLE . " ADD COLUMN ip VARCHAR(45) NOT NULL DEFAULT ''" . $after
                );
            }

            if (!isset($cols['data_solicitacao'])) {
                $this->pdo->exec(
                    'ALTER TABLE ' . self::TABLE
                    . ' ADD COLUMN data_solicitacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
                );
            }

            $tokenMeta = $cols['token'] ?? null;
            if (is_array($tokenMeta)) {
                $dataType = strtolower((string) ($tokenMeta['DATA_TYPE'] ?? ''));
                $maxLen = $tokenMeta['CHARACTER_MAXIMUM_LENGTH'];
                $maxLenInt = ($maxLen !== null && $maxLen !== '') ? (int) $maxLen : 0;
                $tooShortChar = ($dataType === 'varchar' || $dataType === 'char') && $maxLenInt > 0 && $maxLenInt < 64;
                if ($tooShortChar) {
                    $this->pdo->exec(
                        'ALTER TABLE ' . self::TABLE . ' MODIFY COLUMN token VARCHAR(191) NOT NULL'
                    );
                }
            }
        } catch (Throwable $e) {
            error_log('PasswordResetService: upgrade schema recuperacao_senha: ' . $e->getMessage());
        }
    }

    private function generateId(string $prefix): string
    {
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(13)));
    }

    private function buildResetUrl(string $plainToken): string
    {
        $appUrl = trim((string) envValue('APP_URL', ''));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/') . '/redefinir_senha.php?token=' . urlencode($plainToken);
        }

        $scheme = appIsHttpsRequest() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return $scheme . '://' . $host . '/redefinir_senha.php?token=' . urlencode($plainToken);
    }

    /**
     * @return array{ok:bool,sent:bool}
     */
    private function sendEmail(string $to, string $nome, string $resetUrl): array
    {
        $subject = 'Redefinição de senha – Primeira Vara Crime de Ilhéus';
        $htmlBody = '
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <title>Redefinição de senha</title>
            </head>
            <body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:20px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                                <tr>
                                    <td style="background:#686BFB;padding:20px;text-align:center;color:#ffffff;font-size:18px;font-weight:bold;">
                                        SGC - Primeira Vara Crime de Ilhéus
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:30px;color:#333333;font-size:14px;line-height:1.6;">
                                        <p>Olá, <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
                                        <p>Recebemos uma solicitação para redefinição de senha do seu acesso ao sistema.</p>
                                        <p>Para continuar, clique no botão abaixo. O link expira em 1 hora e pode ser usado uma única vez.</p>
                                        <p style="margin:30px 0;text-align:center;">
                                            <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#ff0040;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:600;">
                                                Redefinir senha
                                            </a>
                                        </p>
                                        <p>Se o botão não funcionar, copie e cole este endereço no navegador:</p>
                                        <p style="word-break:break-all;color:#555555;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>
                                        <p style="margin-top:30px;font-size:12px;color:#777777;">
                                            Caso você não tenha solicitado esta recuperação, ignore esta mensagem.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background:#f0f0f0;padding:15px;text-align:center;font-size:11px;color:#666666;">
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
        $altBody =
            "Olá, {$nome}.\n\n" .
            "Use o link abaixo para redefinir sua senha (válido por 1 hora):\n{$resetUrl}\n\n" .
            "Caso você não tenha solicitado esta recuperação, ignore esta mensagem.\n";

        $mailer = new SmtpTransactionalMailer();
        return $mailer->sendHtml(
            [['email' => $to, 'name' => $nome]],
            $subject,
            $htmlBody,
            $altBody,
            [
                'log_context' => 'RESET SENHA',
                'log_message' => $to . ' | link: ' . $resetUrl,
            ]
        );
    }

    private function hasRecentPasswordReuse(string $userId, string $novaSenha): bool
    {
        $stmtAtual = $this->pdo->prepare('SELECT senha_hash FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmtAtual->execute([$userId]);
        $atual = $stmtAtual->fetchColumn();
        if (is_string($atual) && $atual !== '' && Security::verifyPassword($novaSenha, $atual)) {
            return true;
        }

        $skipHistorico = function_exists('isPasswordHistoryCheckEnabled') && !isPasswordHistoryCheckEnabled();
        if ($skipHistorico) {
            return false;
        }

        try {
            $stmtHistorico = $this->pdo->prepare('
                SELECT senha_hash
                  FROM historico_senha
                 WHERE usuario = ?
              ORDER BY data_alteracao DESC
                 LIMIT ' . self::PASSWORD_HISTORY_LIMIT
            );
            $stmtHistorico->execute([$userId]);
            $historico = $stmtHistorico->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return false;
        }

        foreach ($historico as $hash) {
            if (is_string($hash) && $hash !== '' && Security::verifyPassword($novaSenha, $hash)) {
                return true;
            }
        }

        return false;
    }

    private function updateUserPassword(string $userId, string $passwordHash, bool $invalidateSession): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios
                   SET senha_hash = ?,
                       token_acesso = " . ($invalidateSession ? 'NULL' : 'token_acesso') . ",
                       token_expira = " . ($invalidateSession ? 'NULL' : 'token_expira') . ",
                       updated_at = NOW()
                 WHERE id_usuario = ?
                 LIMIT 1
            ");
            $stmt->execute([$passwordHash, $userId]);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'updated_at') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                $stmt = $this->pdo->prepare("
                    UPDATE usuarios
                       SET senha_hash = ?,
                           token_acesso = " . ($invalidateSession ? 'NULL' : 'token_acesso') . ",
                           token_expira = " . ($invalidateSession ? 'NULL' : 'token_expira') . "
                     WHERE id_usuario = ?
                     LIMIT 1
                ");
                $stmt->execute([$passwordHash, $userId]);
                return;
            }

            throw $e;
        }
    }
}
