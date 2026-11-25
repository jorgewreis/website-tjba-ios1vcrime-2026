<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/Guias.php';

final class Rei152ResultadoPdo extends PDO
{
    public function __construct() {}
}

$pdo = new Rei152ResultadoPdo();
$class = new ReflectionClass(Guias::class);
$service = $class->newInstanceWithoutConstructor();
$property = $class->getProperty('pdo');
$property->setValue($service, $pdo);
$method = $class->getMethod('resultadoFalhaCoordenacao');

$before = $method->invoke($service, 'cadastrar', null, null);
if ($before['ok'] !== false || $before['id'] !== null || isset($before['resultado'])) {
    throw new RuntimeException('Falha antes do callback não deve afirmar resultado incerto.');
}

ParteDocumentoLock::markConnectionUnsafe($pdo);
$confirmed = $method->invoke($service, 'cadastrar', null, ['ok' => true, 'id' => 'GUI-SYNTHETIC']);
if ($confirmed['ok'] !== false || $confirmed['id'] !== null
    || $confirmed['resultado'] !== 'verificacao_necessaria'
    || $confirmed['estado_gravacao'] !== 'confirmada'
    || $confirmed['id_referencia'] !== 'GUI-SYNTHETIC') {
    throw new RuntimeException('Commit confirmado deve preservar referência ao falhar encerramento do lock.');
}

$unknown = $method->invoke($service, 'cadastrar', null, [
    'ok' => false,
    'resultado' => 'verificacao_necessaria',
    'id_referencia' => 'GUI-SYNTHETIC',
]);
if ($unknown['ok'] !== false || $unknown['id'] !== null
    || $unknown['estado_gravacao'] !== 'nao_confirmada'
    || $unknown['id_referencia'] !== 'GUI-SYNTHETIC') {
    throw new RuntimeException('Commit incerto deve preservar referência sem afirmar persistência.');
}

echo "PASS: contrato sintético de resultado da coordenação\n";
