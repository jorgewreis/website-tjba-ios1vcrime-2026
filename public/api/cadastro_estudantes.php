<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/Conection.php';
require_once __DIR__ . '/../../app/helpers/TextFormatter.php';
require_once __DIR__ . '/../../app/helpers/SexoHelper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

// Limites compatíveis com o cadastro em usuarios
$limites = [
    'nome_completo' => 200,
    'telefone'      => 20,
    'faculdade'     => 150,
    'email'         => 255,
];

// Mesma regra do TextFormatter JS (de/da/do etc.) — alinhado a usuarios-create/edit.
$nomeCompleto = TextFormatter::formatProperName(trim((string) ($payload['nome_completo'] ?? '')), $limites['nome_completo']);
// Primeiro bloco = nome; blocos seguintes = sobrenome
$partes = preg_split('/\s+/u', $nomeCompleto, 2, PREG_SPLIT_NO_EMPTY);
$nome = $partes[0] ?? '';
$sobrenome = isset($partes[1]) ? TextFormatter::formatProperName($partes[1], 200) : '';

$cpf       = trim((string) ($payload['cpf'] ?? ''));
$telefone  = mb_substr(trim((string) ($payload['telefone'] ?? '')), 0, $limites['telefone']);
$sexo      = SexoHelper::normalize(trim((string) ($payload['sexo'] ?? '')));
$whatsapp  = !empty($payload['telefone_whatsapp']);
$faculdade = mb_substr(trim((string) ($payload['faculdade'] ?? '')), 0, $limites['faculdade']);
$email     = mb_strtolower(trim((string) ($payload['email'] ?? '')), 'UTF-8');
$email     = mb_substr($email, 0, $limites['email']);

$cpfNumerico = Security::normalizeCPF($cpf);
if (!Security::validarCPF($cpfNumerico)) {
    echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
    exit;
}
$cpfFormatado = Security::formatCPF($cpfNumerico);

$telefoneNumerico = preg_replace('/\D/', '', $telefone);
if (strlen($telefoneNumerico) < 10) {
    echo json_encode(['success' => false, 'message' => 'Informe o telefone com DDD (mínimo 10 dígitos).']);
    exit;
}

if (!SexoHelper::isValid($sexo)) {
    echo json_encode(['success' => false, 'message' => 'Selecione o sexo.']);
    exit;
}

if ($nomeCompleto === '' || strlen($nomeCompleto) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório (mínimo 3 caracteres).']);
    exit;
}

if ($faculdade === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione uma faculdade.']);
    exit;
}

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'E-mail é obrigatório (necessário para recuperação de senha).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

$aceiteTermosLgpd = !empty($payload['aceite_termos_lgpd']);
if (!$aceiteTermosLgpd) {
    echo json_encode([
        'success' => false,
        'message' => 'É necessário aceitar os termos sobre LGPD (uso dos dados pessoais) para realizar o cadastro.',
    ]);
    exit;
}

$senha = trim((string) ($payload['senha'] ?? ''));
if (($policy = Security::validatePasswordPolicy($senha))['valid'] !== true) {
    echo json_encode(['success' => false, 'message' => $policy['message'] ?? 'Senha inválida.']);
    exit;
}

// A resposta pública não confirma se o CPF já possui conta.
$respostaCadastro = [
    'success' => true,
    'message' => 'Se os dados puderem ser processados, utilize a área de acesso ou recuperação de senha.',
];
$lockCadastroObtido = false;
$lockCadastroNome = 'cadest:' . substr(hash('sha256', $cpfNumerico), 0, 48);
try {
    $pdo = Conection::getInstance()->getConnection();

    // Serializa este cadastro e a criação administrativa para o mesmo CPF.
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockCadastroNome]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Não foi possível iniciar o cadastro.');
    }
    $lockCadastroObtido = true;
    // Executar também para contas existentes reduz uma diferença óbvia de tempo.
    // Isto não constitui garantia de tempo constante entre os caminhos.
    $senhaHash = Security::hashPassword($senha);
    $pdo->beginTransaction();

    // Jamais altera conta existente sem um fluxo de comprovação de titularidade.
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$cpfNumerico]);
    if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
        $pdo->rollBack();
        echo json_encode($respostaCadastro);
        return;
    }

    $idUsuario = 'USR-' . strtoupper(bin2hex(random_bytes(13)));
    $stHasSexo = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sexo'"
    );
    $stHasSexo->execute();
    $hasSexoColumn = ((int) $stHasSexo->fetchColumn()) > 0;

    // Somente uma conta nova é criada por este fluxo público.
    if ($hasSexoColumn) {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                id_usuario, nome, sobrenome, sexo, data_nascimento, cargo, instituicao, cpf, telefone, email,
                senha_hash, meta, ultimo_acesso, token_acesso, token_expira, cadastro_ativo, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, '-', 'Estudante', ?, ?, ?, ?,
                ?, '-', NULL, NULL, NULL, 1, NOW(), NOW()
            )
        ");
        $stmt->execute([$idUsuario, $nome, $sobrenome, $sexo, $faculdade, $cpfFormatado, $telefone, $email, $senhaHash]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                id_usuario, nome, sobrenome, data_nascimento, cargo, instituicao, cpf, telefone, email,
                senha_hash, meta, ultimo_acesso, token_acesso, token_expira, cadastro_ativo, created_at, updated_at
            ) VALUES (
                ?, ?, ?, '-', 'Estudante', ?, ?, ?, ?,
                ?, '-', NULL, NULL, NULL, 1, NOW(), NOW()
            )
        ");
        $stmt->execute([$idUsuario, $nome, $sobrenome, $faculdade, $cpfFormatado, $telefone, $email, $senhaHash]);
    }

    // Garantir que o perfil ESTUDANTE exista em perfis (evita erro de FK se a tabela tiver restrição)
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO perfis (id_perfil, descricao) VALUES ('ESTUDANTE', 'Estudante')");
        $stmt->execute();
    } catch (Throwable $e) {
        // ignora se a tabela perfis não existir ou tiver estrutura diferente
    }

    $stPerfilExiste = $pdo->prepare("SELECT id_usu_perfil FROM usuario_perfil WHERE usuario = ? AND perfil = 'ESTUDANTE' LIMIT 1");
    $stPerfilExiste->execute([$idUsuario]);
    if (!$stPerfilExiste->fetch(PDO::FETCH_ASSOC)) {
        $idUsuPerfil = 'USP-' . strtoupper(bin2hex(random_bytes(13)));
        $stmt = $pdo->prepare("INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, 'ESTUDANTE')");
        $stmt->execute([$idUsuPerfil, $idUsuario]);
    }

    // Alocação automática: Setor "Sala de Audiências" (Tipo externo, Cargo Estudante já definidos acima)
    try {
        $stmt = $pdo->prepare("SELECT id_setor FROM setores WHERE codigo = 'AUDIENCIA' OR nome LIKE '%Sala%Audi%' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id_setor'])) {
            $stSetorExiste = $pdo->prepare("SELECT id_usu_setor FROM usuario_setor WHERE usuario = ? AND setor = ? LIMIT 1");
            $stSetorExiste->execute([$idUsuario, $row['id_setor']]);
            if (!$stSetorExiste->fetch(PDO::FETCH_ASSOC)) {
                $idUsuSetor = 'USO-' . strtoupper(bin2hex(random_bytes(13)));
                $stmt = $pdo->prepare("INSERT INTO usuario_setor (id_usu_setor, usuario, setor) VALUES (?, ?, ?)");
                $stmt->execute([$idUsuSetor, $idUsuario, $row['id_setor']]);
            }
        }
    } catch (Throwable $e) {
        // não falha o cadastro se setores/usuario_setor não existirem ou setor não estiver cadastrado
    }

    $pdo->commit();

    echo json_encode($respostaCadastro);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) ($e->errorInfo[1] ?? 0) === 1062 && $lockCadastroObtido) {
        // A mesma resposta para conflitos de CPF, e-mail ou outra unicidade
        // evita distinguir uma conta existente de uma solicitação não criada.
        echo json_encode($respostaCadastro);
        return;
    }
    error_log('cadastro_estudantes: falha de persistência.');
    echo json_encode(['success' => false, 'message' => 'Não foi possível concluir o cadastro. Tente novamente mais tarde.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cadastro_estudantes: falha interna.');
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível concluir o cadastro. Tente novamente mais tarde.',
    ]);
} finally {
    if ($lockCadastroObtido) {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockCadastroNome]);
        } catch (Throwable $e) {
            error_log('cadastro_estudantes: falha ao liberar bloqueio.');
        }
    }
}
