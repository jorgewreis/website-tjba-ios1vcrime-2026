<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';
require_once __DIR__ . '/../app/repositories/PartesRepository.php';
require_once __DIR__ . '/../app/services/Guias.php';

[$script, $case, $type, $number, $mode, $barrier, $index] = array_pad($argv, 7, '');
if (!preg_match('/^[a-z]{3,12}$/D', $case) || !in_array($type, ParteDocumentoHelper::TIPOS, true)
    || !preg_match('/^[A-Za-z0-9-]{1,60}$/D', $number)
    || !in_array($mode, ['service', 'repository', 'repository_outer', 'legacy_guide', 'legacy_update', 'lock_many'], true) || !preg_match('/^[01]$/D', $index)
    || !preg_match('~^/tmp/rei152-[a-f0-9]{24}$~D', $barrier)) {
    fwrite(STDERR, "Argumentos inválidos.\n");
    exit(2);
}
if (getenv('REI152_CI_REQUIRED') === '1'
    && (getenv('REI152_MYSQL_DSN') !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4'
        || (getenv('REI152_MYSQL_USER') ?: 'root') !== 'root' || (getenv('REI152_MYSQL_PASSWORD') ?: '') !== ''
        || !preg_match('/^[a-f0-9]{64}$/D', (string) getenv('REI152_CI_MARKER')))) {
    throw new RuntimeException('Identidade CI do worker recusada.');
}
$runId = basename($barrier);
$readyPath = $barrier . '/' . $case . '-ready-' . $index;
$goPath = $barrier . '/' . $case . '-go';
$goIndexPath = $barrier . '/' . $case . '-go-' . $index;
file_put_contents($readyPath, $runId, LOCK_EX);
$deadline = microtime(true) + 15;
while (microtime(true) < $deadline) {
    if (is_file($goPath) || is_file($goIndexPath)) {
        break;
    }
    usleep(10000);
}
if (!is_file($goPath) && !is_file($goIndexPath)) {
    fwrite(STDERR, "Timeout na barreira.\n");
    exit(3);
}
file_put_contents($barrier . '/' . $case . '-started-' . $index, $runId, LOCK_EX);

try {
    $pdo = new PDO(
        (string) getenv('REI152_MYSQL_DSN'),
        (string) (getenv('REI152_MYSQL_USER') ?: 'root'),
        (string) (getenv('REI152_MYSQL_PASSWORD') ?: ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if (getenv('REI152_CI_REQUIRED') === '1') {
        $marker = (string) getenv('REI152_CI_MARKER');
        if (getenv('REI152_MYSQL_DSN') !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4'
            || (getenv('REI152_MYSQL_USER') ?: 'root') !== 'root'
            || (getenv('REI152_MYSQL_PASSWORD') ?: '') !== '' || !preg_match('/^[a-f0-9]{64}$/D', $marker)) {
            throw new RuntimeException('Identidade CI do worker recusada.');
        }
        $tokens = $pdo->query('SELECT token FROM `_sandbox_proof`')->fetchAll(PDO::FETCH_COLUMN);
        if (count($tokens) !== 1 || !hash_equals($marker, (string) $tokens[0])) {
            throw new RuntimeException('Marcador CI do worker recusado.');
        }
    }
    $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    if ($connectionId <= 0 || file_put_contents($barrier . '/' . $case . '-connection-' . $index, (string) $connectionId, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível identificar a conexão do worker.');
    }
    if ($mode === 'lock_many') {
        $documents = [
            ['tipo' => 'RG', 'numero' => 'a152'],
            ['tipo' => 'CPF', 'numero' => '52998224725'],
            ['tipo' => 'RG', 'numero' => 'b152'],
        ];
        if ($index === '1') $documents = array_reverse($documents);
        ParteDocumentoLock::runMany($pdo, $documents, static function (): void { usleep(100000); });
        fwrite(STDOUT, "locks-ok\n");
    } elseif ($mode === 'legacy_guide' || $mode === 'legacy_update') {
        if ($type !== 'CPF') {
            throw new InvalidArgumentException('Modo guia legado exige cenário CPF.');
        }
        $guiaDados = [
            'tipo' => 'Teste REI-152',
            'processo' => '',
            'reu' => 'Pessoa Guia Legada ' . $case . ' ' . $index,
            'cpf' => $number,
            'etapa' => 'Teste',
            'status' => 'Não iniciada',
            'dias_multa' => '',
            'user_cadastro' => 'rei152-fixture',
        ];
        if ($mode === 'legacy_update') {
            $guiaDados['id_guia'] = 'REI152-LEGACY-UPDATE';
            $result = (new Guias($pdo))->atualizarGuia($guiaDados);
        } else {
            $result = (new Guias($pdo))->cadastrarGuia($guiaDados);
        }
        if (empty($result['ok'])) {
            throw new RuntimeException('Cadastro legado da guia falhou: ' . (string) ($result['message'] ?? 'sem mensagem'));
        }
        fwrite(STDOUT, ($mode === 'legacy_update' ? 'legacy-update:' : 'legacy-guide:') . (string) $result['id'] . "\n");
    } elseif ($mode === 'service') {
        try {
            $id = (new PessoasService($pdo))->create([
                'nome_completo' => 'Pessoa Concorrente ' . $case . ' ' . $index,
                'documentos' => [['tipo' => $type, 'numero' => $number]],
            ]);
            fwrite(STDOUT, 'created:' . $id . "\n");
        } catch (InvalidArgumentException $error) {
            if (!str_contains($error->getMessage(), 'Já existe outra pessoa cadastrada')) {
                throw $error;
            }
            fwrite(STDOUT, "duplicate\n");
        }
    } elseif ($mode === 'repository') {
        if ($type !== 'CPF') {
            throw new InvalidArgumentException('Modo repositório exige cenário CPF.');
        }
        $id = (new PartesRepository($pdo))->ensureParteByNomeOuCpf('Pessoa Concorrente ' . $case . ' ' . $index, $number);
        fwrite(STDOUT, 'part:' . $id . "\n");
    } else {
        if ($type !== 'CPF') {
            throw new InvalidArgumentException('Modo repositório externo exige cenário CPF.');
        }
        $id = ParteDocumentoLock::run($pdo, 'CPF', $number, function () use ($pdo, $case, $index, $number): string {
            if (!$pdo->beginTransaction()) {
                throw new RuntimeException('Não foi possível iniciar transação externa de fixture.');
            }
            try {
                $parteId = (new PartesRepository($pdo))->ensureParteByNomeOuCpf('Pessoa Concorrente ' . $case . ' ' . $index, $number);
                usleep(750000);
                if (!$pdo->commit()) {
                    throw new RuntimeException('Não foi possível confirmar transação externa de fixture.');
                }
                return $parteId;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        });
        fwrite(STDOUT, 'outer:' . $id . "\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
