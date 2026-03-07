<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/Conection.php';

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

// Limites compatíveis com a tabela estudantes (evitar overflow no banco)
$limites = [
    'nome_completo' => 200,
    'telefone'      => 20,
    'faculdade'     => 150,
    'email'         => 255,
    'semestre'      => 2,
];

$nomeCompleto = trim((string) ($payload['nome_completo'] ?? ''));
$nomeCompleto = mb_convert_case($nomeCompleto, MB_CASE_TITLE, 'UTF-8');
$nomeCompleto = mb_substr($nomeCompleto, 0, $limites['nome_completo']);
// Primeiro bloco = nome; blocos seguintes = sobrenome
$partes = preg_split('/\s+/u', $nomeCompleto, 2, PREG_SPLIT_NO_EMPTY);
$nome = $partes[0] ?? '';
$sobrenome = isset($partes[1]) ? mb_substr($partes[1], 0, 200) : '';

$cpf       = trim((string) ($payload['cpf'] ?? ''));
$telefone  = mb_substr(trim((string) ($payload['telefone'] ?? '')), 0, $limites['telefone']);
$whatsapp  = !empty($payload['telefone_whatsapp']);
$faculdade = mb_substr(trim((string) ($payload['faculdade'] ?? '')), 0, $limites['faculdade']);
$email     = mb_strtolower(trim((string) ($payload['email'] ?? '')), 'UTF-8');
$email     = mb_substr($email, 0, $limites['email']);
$semestre  = mb_substr(trim((string) ($payload['semestre'] ?? '')), 0, $limites['semestre']);

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

if ($nomeCompleto === '' || strlen($nomeCompleto) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório (mínimo 3 caracteres).']);
    exit;
}

if ($faculdade === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione uma faculdade.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
if (strlen($senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 6 caracteres.']);
    exit;
}

// #region agent log
$logPath = __DIR__ . '/../../debug-267185.log';
file_put_contents($logPath, json_encode(['id'=>'entry','timestamp'=>time()*1000,'location'=>'cadastro_estudantes.php:try','message'=>'API try reached','data'=>['hasPayload'=>is_array($payload),'runId'=>'run1'],'hypothesisId'=>'H3']) . "\n", FILE_APPEND);
// #endregion
try {
    $pdo = Conection::getInstance()->getConnection();

    // Verificação de duplicidade: CPF é único no cadastro de estudantes
    $stmt = $pdo->prepare("SELECT id FROM estudantes WHERE cpf = ? LIMIT 1");
    $stmt->execute([$cpfFormatado]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Já existe um cadastro com este CPF. Não é possível cadastrar novamente. Se você já se cadastrou, utilize a área de login ou entre em contato com o setor responsável.',
        ]);
        exit;
    }

    // Verificação: CPF já em usuarios (acesso ao sistema)
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE cpf = ? LIMIT 1");
    $stmt->execute([$cpfFormatado]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Já existe um usuário com este CPF no sistema. Utilize a área de login ou recuperação de senha.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO estudantes (
            nome_completo, cpf, telefone, telefone_whatsapp, faculdade, email, semestre
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nomeCompleto,
        $cpfFormatado,
        $telefone,
        $whatsapp ? 1 : 0,
        $faculdade,
        $email === '' ? null : $email,
        $semestre === '' ? null : $semestre,
    ]);
    // #region agent log
    file_put_contents($logPath, json_encode(['id'=>'after_estudantes','timestamp'=>time()*1000,'location'=>'cadastro_estudantes.php:after_insert_estudantes','message'=>'INSERT estudantes OK','data'=>['runId'=>'run1'],'hypothesisId'=>'H3,H5']) . "\n", FILE_APPEND);
    // #endregion

    $idUsuario = 'USR-' . strtoupper(bin2hex(random_bytes(13)));
    $senhaHash = Security::hashPassword($senha);

    // #region agent log
    file_put_contents($logPath, json_encode(['id'=>'before_usuarios','timestamp'=>time()*1000,'location'=>'cadastro_estudantes.php:insert_usuarios','message'=>'Before INSERT usuarios','data'=>['nomeLen'=>strlen($nome),'sobrenomeLen'=>strlen($sobrenome),'runId'=>'run1'],'hypothesisId'=>'H3,H5']) . "\n", FILE_APPEND);
    // #endregion
    // Tabela usuarios: nome/sobrenome do nome completo; ultimo_acesso/token_* NULL (preenchidos no login)
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (
            id_usuario, nome, sobrenome, data_nascimento, cargo, instituicao, cpf, telefone, email,
            senha_hash, meta, ultimo_acesso, token_acesso, token_expira
        ) VALUES (
            ?, ?, ?, '-', '-', '-', ?, ?, ?,
            ?, '-', NULL, NULL, NULL
        )
    ");
    $emailOuTraco = $email !== '' ? $email : '-';
    $stmt->execute([$idUsuario, $nome, $sobrenome, $cpfFormatado, $telefone, $emailOuTraco, $senhaHash]);

    // Garantir que o perfil ESTUDANTE exista em perfis (evita erro de FK se a tabela tiver restrição)
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO perfis (id_perfil, descricao) VALUES ('ESTUDANTE', 'Estudante')");
        $stmt->execute();
    } catch (Throwable $e) {
        // ignora se a tabela perfis não existir ou tiver estrutura diferente
    }

    $stmt = $pdo->prepare("INSERT INTO usuario_perfil (usuario, perfil) VALUES (?, 'ESTUDANTE')");
    $stmt->execute([$idUsuario]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso. Use seu CPF e senha para acessar o sistema (audiências, pautas e atas).',
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() == '42S02') {
        error_log('Tabela estudantes ou usuarios não existe. Execute os SQL em docs/sql/ e database/schema.sql');
        echo json_encode(['success' => false, 'message' => 'Sistema em configuração. Tente novamente mais tarde.']);
    } elseif ($e->getCode() === '23000') {
        $detail = $e->getMessage();
        if (strpos($detail, 'foreign key') !== false || strpos($detail, 'Foreign key') !== false) {
            $msg = 'Configuração do banco: o perfil ESTUDANTE precisa existir na tabela perfis. Contate o suporte.';
        } elseif (strpos($detail, 'Duplicate') !== false) {
            $msg = 'Já existe um cadastro com este CPF. Utilize a área de login ou entre em contato com o setor responsável.';
        } else {
            $msg = 'Erro de restrição no banco. Detalhe: ' . $detail;
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    } else {
        $errMsg = $e->getMessage();
        $errCode = $e->getCode();
        error_log('cadastro_estudantes API: ' . $errMsg);
        // #region agent log
        file_put_contents(__DIR__ . '/../../debug-267185.log', json_encode(['id'=>'pdo_else','timestamp'=>time()*1000,'location'=>'cadastro_estudantes.php:catchPDO','message'=>'PDOException else branch','data'=>['code'=>$errCode,'message'=>$errMsg,'hasDefaultValue'=>strpos($errMsg,'default value')!==false,'runId'=>'run1'],'hypothesisId'=>'H1,H2,H5']) . "\n", FILE_APPEND);
        // #endregion
        $msg = 'Erro ao salvar cadastro. Tente novamente.';
        if (strpos($errMsg, 'doesn\'t have a default value') !== false) {
            // MySQL indica a coluna na mensagem; log completo para suporte
            error_log('Coluna sem valor (usuarios): ' . $errMsg);
            $msg = 'Erro de configuração do banco: tabela usuarios exige mais campos. Contate o suporte.';
        } else {
            $msg .= ' Detalhe: ' . $errMsg;
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} catch (Throwable $e) {
    // #region agent log
    file_put_contents(__DIR__ . '/../../debug-267185.log', json_encode(['id'=>'throwable','timestamp'=>time()*1000,'location'=>'cadastro_estudantes.php:catchThrowable','message'=>'Throwable caught','data'=>['class'=>get_class($e),'message'=>$e->getMessage(),'runId'=>'run1'],'hypothesisId'=>'H4']) . "\n", FILE_APPEND);
    // #endregion
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cadastro_estudantes API: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno. Detalhe: ' . $e->getMessage(),
    ]);
}
