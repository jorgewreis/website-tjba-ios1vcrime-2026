<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers/ParteDocumentoLock.php';

final class Rei152CommitFailurePdo extends PDO
{
    public bool $throwError = false;
    public function __construct() {}
    public function commit(): bool
    {
        if ($this->throwError) throw new RuntimeException('falha sintética');
        return false;
    }
}

foreach ([false, true] as $throwError) {
    $pdo = new Rei152CommitFailurePdo();
    $pdo->throwError = $throwError;
    try {
        ParteDocumentoLock::commit($pdo, 'teste');
        throw new LogicException('Commit deveria falhar.');
    } catch (DocumentoResultadoIncerto $error) {
        if ($error->estadoGravacao !== 'nao_confirmada' || !ParteDocumentoLock::isConnectionUnsafe($pdo)) {
            throw new RuntimeException('Falha de commit não foi classificada como incerta.');
        }
    }
}
echo "PASS: commit falso ou excepcional exige verificação\n";
