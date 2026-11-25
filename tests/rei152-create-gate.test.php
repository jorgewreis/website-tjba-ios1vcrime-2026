<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';

$method = (new ReflectionClass(PessoasService::class))->getMethod('tabelasAdicionaisCadastro');
foreach ([
    [[], []],
    [['telefones' => [['numero' => '  ']]], []],
    [['telefones' => [['numero' => ' 123 ']]], ['partes_telefones']],
    [['telefones' => [['numero' => ''], ['numero' => '123']]], ['partes_telefones']],
] as [$payload, $expected]) {
    if ($method->invoke(null, $payload) !== $expected) {
        throw new RuntimeException('Seleção de tabela de telefones não corresponde às gravações.');
    }
}

final class Rei152UnsupportedDriverPdo extends PDO
{
    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'unsupported' : parent::getAttribute($attribute);
    }
}

$pdo = new Rei152UnsupportedDriverPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE partes (id TEXT PRIMARY KEY)');
$service = new PessoasService($pdo);
try {
    $service->create(['nome_completo' => 'Pessoa Sintetica']);
    throw new LogicException('Cadastro deveria recusar banco sem suporte transacional.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'Banco sem suporte transacional')) {
        throw $error;
    }
}
if ($pdo->inTransaction() || (int) $pdo->query('SELECT COUNT(*) FROM partes')->fetchColumn() !== 0) {
    throw new RuntimeException('Recusa do gate deixou transação ou registro residual.');
}
echo "PASS: cadastro recusa driver sem suporte antes da primeira escrita\n";
