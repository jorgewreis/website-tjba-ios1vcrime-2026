<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = dirname(__DIR__);
$path = $base . '/app/repositories/ProdutividadeLancamentosRepository.php';
$c = file_get_contents($path);
$marker = '    public function anular(string $id, string $anuladoPor, string $motivo): bool';
$block = <<<'PHP'

    public function sumNetAutomaticoAtivo(string $referenciaItemId, string $referenciaSistema): float
    {
        $referenciaItemId = trim($referenciaItemId);
        $referenciaSistema = trim($referenciaSistema);
        if ($referenciaItemId === '' || $referenciaSistema === '') {
            return 0.0;
        }

        $sql = sprintf(
            'SELECT COALESCE(SUM(l.`pontos_calculados`), 0)
             FROM `%s` l
             WHERE l.`referencia_item_id` = :ref_id
               AND l.`referencia_sistema` = :ref_sys
               AND l.`tipo_lancamento` = \'automatico\'
               AND l.`status` = \'ativo\'',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':ref_id', $referenciaItemId);
        $statement->bindValue(':ref_sys', $referenciaSistema);
        $statement->execute();

        return (float) $statement->fetchColumn();
    }

PHP;
if (strpos($c, 'sumNetAutomaticoAtivo') !== false) {
    echo "already patched\n";
    exit(0);
}
$parts = explode($marker, $c, 2);
if (count($parts) !== 2) {
    fwrite(STDERR, "marker not found\n");
    exit(1);
}
$c = $parts[0] . rtrim($block) . "\n\n" . $marker . $parts[1];
file_put_contents($path, $c);
echo "repo ok\n";
