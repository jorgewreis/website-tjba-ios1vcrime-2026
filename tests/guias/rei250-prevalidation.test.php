<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/GuiasService.php';
// Nenhuma transação pode começar para essas entradas: não há schema nesta fixture.
final class Rei250PrevalidationPDO extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function beginTransaction(): bool { throw new RuntimeException('Entrada inválida chegou à transação.'); }
}
$db = new Rei250PrevalidationPDO();
foreach ([
    [],
    ['numero_processo'=>'0000001-69.2020.8.05.0001'],
    ['numero_processo'=>'123', 'denunciado'=>'Pessoa Sintetica', 'confirmar_nova_pessoa'=>true],
    ['numero_processo'=>'0000001-00.2020.8.05.0001', 'denunciado'=>'Pessoa Sintetica', 'confirmar_nova_pessoa'=>true],
    ['numero_processo'=>'0000001-69.2020.8.05.0001', 'denunciado'=>'Pessoa Sintetica'],
] as $payload) {
    try { (new GuiasService($db))->create($payload); throw new RuntimeException('Entrada inválida aceita.'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS REI-250: entradas inválidas recusadas antes da transação.\n";
