<?php
declare(strict_types=1);
require __DIR__ . '/../../app/services/TjbaCatalogo.php';

function verify(bool $ok): void {
    if (!$ok) throw new RuntimeException('Invariante do catálogo falhou.');
}

$path = tempnam(sys_get_temp_dir(), 'catalogo-tjba-test-');
if ($path === false) throw new RuntimeException('Sem arquivo temporário.');
unlink($path);
$c = [['idComarca'=>2146, 'dsComarca'=>'ILHEUS']];
for ($i = 1; $i <= 100; $i++) $c[] = ['idComarca'=>$i, 'dsComarca'=>'Comarca ' . $i];
$u = [['idUnidade'=>4701, 'dsUnidade'=>'1ª VARA CRIMINAL', 'idMunicipio'=>2146, 'flAtivo'=>true]];
for ($i = 1; $i <= 400; $i++) $u[] = ['idUnidade'=>$i, 'dsUnidade'=>'Unidade ' . $i, 'idMunicipio'=>1, 'flAtivo'=>true];
$fetch = static fn(string $key): array => $key === 'comarcas' ? $c : $u;
try {
    verify(TjbaCatalogo::ler($path) === null && !is_file($path));
    verify(TjbaCatalogo::atualizar($path, $fetch) === 'atualizado');
    $first = TjbaCatalogo::ler($path);
    verify($first['dados']['comarcas'][2146] === 'ILHEUS' && $first['dados']['unidades'][4701]['comarca'] === 2146);
    verify(TjbaCatalogo::atualizar($path, static fn($key) => array_reverse($fetch($key))) === 'sem_alteracoes');
    verify(TjbaCatalogo::atualizar($path, static fn($key) => $key === 'comarcas' ? $c : [...$u, $u[0]]) === 'sem_alteracoes');
    verify(TjbaCatalogo::atualizar($path, static fn($key) => $key === 'comarcas' ? $c : [...$u, ['idUnidade'=>0,'flAtivo'=>false]]) === 'sem_alteracoes');
    $good = hash_file('sha256', $path);
    foreach ([
        static fn($key) => $key === 'comarcas' ? $c : throw new LogicException('simulação offline'),
        static fn($key) => $key === 'comarcas' ? $c : [$u[0]],
        static fn($key) => $key === 'comarcas' ? $c : [...$u, array_replace($u[0], ['flAtivo'=>false])],
    ] as $bad) {
        $error = false;
        try { TjbaCatalogo::atualizar($path, $bad); } catch (RuntimeException|LogicException) { $error = true; }
        verify($error && hash_file('sha256', $path) === $good);
    }
    $lock = fopen($path . '.lock', 'c');
    verify(flock($lock, LOCK_EX | LOCK_NB));
    try { verify(TjbaCatalogo::atualizar($path, static fn() => throw new LogicException('não chamar fonte')) === 'ocupado'); }
    finally { fclose($lock); }
    $u[0]['dsUnidade'] = 'Nome alterado';
    verify(TjbaCatalogo::atualizar($path, static fn($key) => $key === 'comarcas' ? $c : $u) === 'atualizado');
    verify(TjbaCatalogo::ler($path)['dados']['unidades'][4701]['nome'] === 'Nome alterado');
    echo "PASS: carga, leitura, idempotência, falhas preservam cópia, trava e atualização.\n";
} finally {
    if (is_file($path)) unlink($path);
    if (is_file($path . '.lock')) unlink($path . '.lock');
}
