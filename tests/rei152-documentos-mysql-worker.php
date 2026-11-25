<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';
require_once __DIR__ . '/../app/repositories/PartesRepository.php';

[$script, $case, $type, $number, $mode, $barrier, $index] = array_pad($argv, 7, '');
if (!preg_match('/^[a-z]{3,12}$/D', $case) || !in_array($type, ParteDocumentoHelper::TIPOS, true)
    || !preg_match('/^[A-Za-z0-9-]{1,60}$/D', $number)
    || !in_array($mode, ['service', 'repository'], true) || !preg_match('/^[01]$/D', $index)
    || !preg_match('~^/tmp/rei152-[a-f0-9]{24}$~D', $barrier)) {
    fwrite(STDERR, "Argumentos inválidos.\n");
    exit(2);
}
$runId = basename($barrier);
$readyPath = $barrier . '/' . $case . '-ready-' . $index;
$goPath = $barrier . '/' . $case . '-go';
file_put_contents($readyPath, $runId, LOCK_EX);
$deadline = microtime(true) + 15;
while (microtime(true) < $deadline) {
    if (is_file($goPath)) {
        break;
    }
    usleep(10000);
}
if (!is_file($goPath)) {
    fwrite(STDERR, "Timeout na barreira.\n");
    exit(3);
}

try {
    $pdo = new PDO(
        (string) getenv('REI152_MYSQL_DSN'),
        (string) (getenv('REI152_MYSQL_USER') ?: 'root'),
        (string) (getenv('REI152_MYSQL_PASSWORD') ?: ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ($mode === 'service') {
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
    } else {
        if ($type !== 'CPF') {
            throw new InvalidArgumentException('Modo repositório exige cenário CPF.');
        }
        $id = (new PartesRepository($pdo))->ensureParteByNomeOuCpf('Pessoa Concorrente ' . $case . ' ' . $index, $number);
        fwrite(STDOUT, 'part:' . $id . "\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
