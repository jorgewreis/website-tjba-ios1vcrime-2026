<?php
declare(strict_types=1);

if (getenv('REI88_TEST_DISPOSABLE') !== '1' || getenv('REI88_MYSQL_DSN') !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4' || getenv('REI88_TEST_REPO_ROOT') !== '/repo') throw new RuntimeException('Fixture não descartável recusada');
$marker = (string) getenv('REI88_TEST_DISPOSABLE_MARKER');
if (!preg_match('/^[a-f0-9]{64}$/', $marker)) throw new RuntimeException('Marcador inválido');
$pdo = new PDO((string) getenv('REI88_MYSQL_DSN'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei88_test' || !str_starts_with((string) $pdo->query('SELECT VERSION()')->fetchColumn(), '8.4.')) throw new RuntimeException('Banco inválido');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($tables !== ['rei88_test_guard'] || !hash_equals($marker, (string) $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn())) throw new RuntimeException('Sentinela inválida');
$mode = getenv('REI148_EXPECT_LOWER_CASE');
if (!in_array($mode, ['0','1'], true) || (string) $pdo->query('SELECT @@lower_case_table_names')->fetchColumn() !== $mode) throw new RuntimeException('Modo de nomes de tabela inesperado');
require_once '/repo/app/services/UsuariosService.php';
function expectRei148(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo->exec('CREATE TABLE usuarios (
    id_usuario VARCHAR(128) PRIMARY KEY,
    nome VARCHAR(255),
    sobrenome VARCHAR(255),
    nome_completo VARCHAR(255),
    cpf VARCHAR(255),
    email VARCHAR(255),
    cargo VARCHAR(255),
    instituicao VARCHAR(255),
    telefone VARCHAR(255),
    ultimo_acesso VARCHAR(255),
    cadastro_ativo INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE usuario_perfil (usuario VARCHAR(255), perfil VARCHAR(255)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE tentativas_login (cpf VARCHAR(255), bloqueado INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$users = [
    ['student', 'Ana', 'Estudante', 'Estudante'],
    ['internal', 'Bruno', 'Servidor', 'Servidor'],
    ['mixed', 'Carla', 'Interna', 'Servidor'],
    ['mixed-external', 'Dora', 'Estudante Consultora', 'Estudante'],
    ['consultant-only', 'Eva', 'Consultora', ''],
    ['legacy-only', 'Gabi', 'Perfil Legado', ''],
    ['legacy-student', 'Hugo', 'Estudante Perfil Legado', 'Estudante'],
    ['unprofiled', 'Fabi', 'Sem Perfil', ''],
];
$insertUser = $pdo->prepare('INSERT INTO usuarios
    (id_usuario, nome, sobrenome, nome_completo, cpf, email, cargo, instituicao, telefone, ultimo_acesso, cadastro_ativo)
    VALUES (:id, :nome, :sobrenome, :nome_completo, :cpf, :email, :cargo, :instituicao, :telefone, NULL, 1)');
foreach ($users as [$id, $name, $surname, $cargo]) {
    $insertUser->execute([
        ':id' => $id,
        ':nome' => $name,
        ':sobrenome' => $surname,
        ':nome_completo' => $name . ' ' . $surname,
        ':cpf' => 'CPF-' . $id,
        ':email' => $id . '@example.invalid',
        ':cargo' => $cargo,
        ':instituicao' => '',
        ':telefone' => '',
    ]);
}

$insertProfile = $pdo->prepare('INSERT INTO usuario_perfil (usuario, perfil) VALUES (?, ?)');
foreach ([
    ['student', 'ESTUDANTE'],
    ['internal', 'SERVIDOR'],
    // Mesmo usuário sob collation MySQL case-insensitive.
    ['INTERNAL', 'SERVIDOR'],
    // Associação órfã não representa conta listável.
    ['orphan', 'SERVIDOR'],
    // Perfil composto e caixa/espaço divergentes exercitam a normalização.
    ['mixed', ' estudante '],
    ['mixed', ' servidor-gabinete '],
    ['mixed-external', 'ESTUDANTE'],
    ['mixed-external', 'CONSULTOR'],
    ['consultant-only', 'CONSULTOR'],
    ['legacy-only', 'PERFIL-LEGADO'],
    ['legacy-student', 'ESTUDANTE'],
    ['legacy-student', 'PERFIL-LEGADO'],
] as $profile) {
    $insertProfile->execute($profile);
}

// A view sintética duplica intencionalmente a identidade "mixed", como pode
// ocorrer se a fonte de listagem produzir mais de uma linha para o mesmo usuário.
$pdo->exec("CREATE VIEW v_usuarios_completo AS
    SELECT u.id_usuario, u.nome, u.sobrenome, u.nome_completo, u.cpf, u.email,
           u.cargo, u.instituicao, u.telefone, u.ultimo_acesso, '' AS perfis,
           u.instituicao AS setores
      FROM usuarios u
    UNION ALL
    SELECT u.id_usuario, u.nome, u.sobrenome, u.nome_completo, u.cpf, u.email,
           u.cargo, u.instituicao, u.telefone, u.ultimo_acesso, '' AS perfis,
           u.instituicao AS setores
      FROM usuarios u WHERE u.id_usuario = 'mixed'");

$service = new UsuariosService($pdo);
$all = $service->list([], 2, 0);
expectRei148($all['total'] === 8, 'Lista unificada deve contar uma vez cada usuário elegível, mesmo com duplicata na view.');
expectRei148(count($all['rows']) === 2, 'Primeira página deve respeitar o limite.');

$secondPage = $service->list([], 2, 2);
expectRei148($secondPage['total'] === 8 && count($secondPage['rows']) === 2, 'Total e paginação devem permanecer consistentes na segunda página.');
$pageIds = array_column(array_merge(
    $all['rows'],
    $secondPage['rows'],
    $service->list([], 2, 4)['rows'],
    $service->list([], 2, 6)['rows']
), 'id_usuario');
expectRei148(count($pageIds) === 8 && count(array_unique($pageIds)) === 8, 'Uma identidade id_usuario não pode reaparecer em páginas diferentes.');

$internal = $service->list(['tipo' => 'interno'], 20, 0);
$external = $service->list(['tipo' => 'externo'], 20, 0);
$unprofiled = $service->list(['tipo' => 'sem_perfil'], 20, 0);
expectRei148($internal['total'] === 3, 'Perfis internos e perfis desconhecidos sem ESTUDANTE/CONSULTOR devem permanecer na listagem interna.');
expectRei148($external['total'] === 4, 'Estudantes e consultores sem perfil operacional interno devem entrar na lista externa.');
expectRei148($unprofiled['total'] === 1, 'Conta sem perfil deve continuar na categoria própria.');
expectRei148(in_array('mixed', array_column($internal['rows'], 'id_usuario'), true), 'Perfil misto deve aparecer na lista interna.');
expectRei148(in_array('legacy-only', array_column($internal['rows'], 'id_usuario'), true), 'Perfil legado sem perfil externo não pode desaparecer da listagem.');
expectRei148(!in_array('mixed', array_column($external['rows'], 'id_usuario'), true), 'Perfil misto não pode também aparecer na lista externa.');
expectRei148(in_array('mixed-external', array_column($external['rows'], 'id_usuario'), true), 'ESTUDANTE + CONSULTOR, sem perfil operacional, permanece externo.');
expectRei148(in_array('consultant-only', array_column($external['rows'], 'id_usuario'), true), 'CONSULTOR sem ESTUDANTE deve permanecer na lista externa.');
expectRei148(in_array('legacy-student', array_column($external['rows'], 'id_usuario'), true), 'Perfil legado acompanhado de ESTUDANTE deve seguir a classificação externa existente.');
$consultant = array_values(array_filter($external['rows'], static fn (array $row): bool => $row['id_usuario'] === 'consultant-only'))[0];
expectRei148($consultant['cargo'] === 'Consultor', 'CONSULTOR sem cargo deve usar o rotulo externo Consultor.');

$typeCounts = $service->getTipoCounts([]);
expectRei148($typeCounts === ['interno' => 3, 'externo' => 4, 'sem_perfil' => 1], 'Contagens por tipo devem usar a mesma regra de classificação da lista.');
$profileCounts = $service->getPerfilCounts([]);
expectRei148($profileCounts['servidor'] === 2 && $profileCounts['estudante'] === 4, 'Contagens por perfil devem preservar ESTUDANTE em conta mista sem duplicar identidade.');
expectRei148($service->getPerfilCounts(['tipo' => 'interno'])['estudante'] === 1, 'Contagem interna de estudante deve incluir a conta mista, classificada como interna.');
expectRei148($service->getPerfilCounts(['tipo' => 'externo'])['estudante'] === 3, 'Contagem externa de estudante deve excluir perfis operacionais internos e manter estudantes sem perfil operacional.');

$studentAll = $service->list(['perfil' => 'estudante'], 20, 0);
expectRei148($studentAll['total'] === 4, 'Filtro de perfil estudante deve preservar todos os usuários com esse perfil.');
expectRei148(count(array_filter($studentAll['rows'], static fn (array $row): bool => $row['id_usuario'] === 'mixed' && $row['tipo'] === 'interno')) === 1, 'Conta mista filtrada por estudante deve continuar classificada como interna.');

$serverPage = $service->list(['tipo' => 'interno', 'perfil' => 'servidor'], 1, 1);
expectRei148($serverPage['total'] === 2 && count($serverPage['rows']) === 1, 'Filtro de perfil interno deve manter o total correto ao paginar.');
expectRei148($serverPage['rows'][0]['id_usuario'] === 'mixed', 'Perfil misto deve permanecer selecionável pelo filtro SERVIDOR.');

$studentInternals = $service->list(['tipo' => 'interno', 'perfil' => 'estudante'], 20, 0);
$studentExternals = $service->list(['tipo' => 'externo', 'perfil' => 'estudante'], 20, 0);
expectRei148($studentInternals['total'] === 1, 'Filtro ESTUDANTE em tipo interno deve manter a conta mista interna.');
expectRei148($studentInternals['rows'][0]['id_usuario'] === 'mixed', 'A conta mista deve ser filtrável por seu perfil individual ESTUDANTE.');
expectRei148($studentExternals['total'] === 3, 'Filtro ESTUDANTE em tipo externo deve listar apenas estudantes externos.');
$studentTypeCounts = $service->getTipoCounts(['perfil' => 'estudante']);
expectRei148($studentTypeCounts['interno'] === 1 && $studentTypeCounts['externo'] === 3, 'Contagens por tipo no filtro estudante devem separar a conta mista dos estudantes externos.');

// A collation também pode equiparar acento, além de caixa; o bucket usa o ID da tabela usuarios.
$insertUser->execute([':id'=>'café', ':nome'=>'Pessoa', ':sobrenome'=>'Acento', ':nome_completo'=>'Pessoa Acento', ':cpf'=>'CPF-acento', ':email'=>'acento@example.invalid', ':cargo'=>'Servidor', ':instituicao'=>'', ':telefone'=>'']);
$insertProfile->execute(['café', 'SERVIDOR']);
$insertProfile->execute(['CAFE', 'SERVIDOR']);
expectRei148($service->getPerfilCounts([])['servidor'] === 3, 'Variações de acento e caixa não podem duplicar a contagem da identidade canônica.');
expectRei148($service->list(['tipo'=>'interno','perfil'=>'servidor'], 20, 0)['total'] === 3, 'Contagem canônica deve coincidir com a lista filtrada.');
$pdo->exec("DELETE FROM usuario_perfil WHERE usuario='café'");
$pdo->exec("DELETE FROM usuarios WHERE id_usuario='café'");

// A ordenação deve ser independente da ordem que o banco entrega em empate.
$ties = [
    ['id_usuario'=>'tie-z','tipo'=>'interno','setores'=>'Cartório','cargo'=>'Servidor','nome_completo'=>'Pessoa Igual'],
    ['id_usuario'=>'tie-a','tipo'=>'interno','setores'=>'Cartório','cargo'=>'Servidor','nome_completo'=>'Pessoa Igual'],
];
$sort = new ReflectionMethod(UsuariosService::class, 'sortUnified');
$otherOrder = array_reverse($ties);
$sort->invokeArgs($service, [&$ties]);
$sort->invokeArgs($service, [&$otherOrder]);
expectRei148(array_column($ties, 'id_usuario') === ['tie-a', 'tie-z'] && $ties === $otherOrder, 'Empates devem ter ordenação determinística pelo identificador, independente da ordem de entrada.');

// Mesmo empate por meio do serviço real e páginas de tamanho um.
foreach (['tie-z', 'tie-a'] as $id) {
    $insertUser->execute([':id'=>$id, ':nome'=>'Pessoa', ':sobrenome'=>'Igual', ':nome_completo'=>'Pessoa Igual', ':cpf'=>'CPF-'.$id, ':email'=>$id.'@example.invalid', ':cargo'=>'Servidor', ':instituicao'=>'Cartório', ':telefone'=>'']);
    $insertProfile->execute([$id, 'SERVIDOR']);
}
$first = $service->list(['tipo'=>'interno','perfil'=>'servidor'], 1, 0);
$second = $service->list(['tipo'=>'interno','perfil'=>'servidor'], 1, 1);
expectRei148($first['total'] === 4 && $second['total'] === 4 && $first['rows'][0]['id_usuario'] === 'tie-a' && $second['rows'][0]['id_usuario'] === 'tie-z', 'Empates devem ocupar páginas estáveis e distintas.');

// View do schema versionado: joins reais de perfil/setor e GROUP_CONCAT.
$pdo->exec('CREATE TABLE perfis (id_perfil VARCHAR(255) PRIMARY KEY, descricao VARCHAR(255)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE usuario_setor (usuario VARCHAR(255), setor VARCHAR(255)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE setores (id_setor VARCHAR(255) PRIMARY KEY, nome VARCHAR(255)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec("INSERT INTO perfis VALUES ('SERVIDOR','Servidor'),('ESTUDANTE','Estudante'),('CONSULTOR','Consultor')");
$pdo->exec("INSERT INTO setores VALUES ('CT','Cartório'),('GB','Gabinete')");
$pdo->exec("INSERT INTO usuario_setor VALUES ('internal','CT'),('internal','GB')");
$pdo->exec('DROP VIEW v_usuarios_completo');
$pdo->exec("CREATE VIEW v_usuarios_completo AS SELECT u.id_usuario,u.nome,u.sobrenome,CONCAT(u.nome, ' ', u.sobrenome) AS nome_completo,u.cpf,u.email,u.cargo,u.instituicao,u.telefone,u.ultimo_acesso,GROUP_CONCAT(DISTINCT p.descricao ORDER BY p.descricao SEPARATOR ', ') AS perfis,GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS setores FROM usuarios u LEFT JOIN usuario_perfil up ON u.id_usuario COLLATE utf8mb4_unicode_ci = up.usuario COLLATE utf8mb4_unicode_ci LEFT JOIN perfis p ON up.perfil COLLATE utf8mb4_unicode_ci = p.id_perfil COLLATE utf8mb4_unicode_ci LEFT JOIN usuario_setor us ON u.id_usuario COLLATE utf8mb4_unicode_ci = us.usuario COLLATE utf8mb4_unicode_ci LEFT JOIN setores s ON us.setor COLLATE utf8mb4_unicode_ci = s.id_setor COLLATE utf8mb4_unicode_ci GROUP BY u.id_usuario");
$actual = $service->list([], 100, 0);
expectRei148($actual['total'] === 10 && count(array_unique(array_column($actual['rows'], 'id_usuario'))) === 10, 'View versionada deve manter identidades únicas');
$internalRow = array_values(array_filter($actual['rows'], static fn(array $row): bool => $row['id_usuario'] === 'internal'))[0];
expectRei148($internalRow['perfis'] === 'Servidor' && $internalRow['setores'] === 'Cartório, Gabinete', 'Agregação de perfil e setores falhou');
expectRei148($service->getTipoCounts([]) === ['interno'=>5, 'externo'=>4, 'sem_perfil'=>1], 'Contagens divergiram com view versionada');

echo "PASS REI-148: perfis mistos, classificação, deduplicação por id_usuario, filtros, contagens e paginação isolada em MySQL.\n";
