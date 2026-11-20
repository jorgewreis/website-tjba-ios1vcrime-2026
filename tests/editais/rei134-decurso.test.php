<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/EditaisService.php';
// Extrai apenas o formatador puro, evitando carregar configuração ou conectar banco.
$source = file_get_contents(__DIR__ . '/../../app/helpers/InternalListExportHandlers.php');
$start = strpos($source, '    private static function formatExportEditalDecursoResumo(');
$end = strpos($source, '    private static function labelFormaCumprimentoCartorio(', $start);
if ($start === false || $end === false) throw new RuntimeException('Formatador não encontrado');
eval('class ExportadorDecursoFixture {' . substr($source, $start, $end - $start) . '}');

function check($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
date_default_timezone_set('America/Bahia');
$estado = new ReflectionMethod(EditaisService::class, 'estadoDecurso');
$rotulo = new ReflectionMethod(ExportadorDecursoFixture::class, 'formatExportEditalDecursoResumo');
$limite = new DateTimeImmutable('2026-11-16 18:37:00');
foreach (['00:00:00', '12:30:00', '23:59:59'] as $hora) {
    foreach ([
        ['2026-11-15', [false, false, 1], '16/11/2026 (1 dia restante)'],
        ['2026-11-16', [true, false, 0], '16/11/2026 (vence hoje)'],
        ['2026-11-17', [true, true, 0], '16/11/2026 (prazo decorrido)'],
    ] as [$dia, $esperado, $texto]) {
        $resultado = $estado->invoke(null, $limite, new DateTimeImmutable("$dia $hora"));
        check($resultado === $esperado, "estado incorreto $dia $hora");
        $info = ['data_limite_formatada' => '16/11/2026', 'prazo_cumprido' => $resultado[0], 'libera_conclusao' => $resultado[1], 'dias_restantes' => $resultado[2]];
        check($rotulo->invoke(null, $info) === $texto, "rótulo incorreto $dia $hora");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE editais (id TEXT PRIMARY KEY, processo_id TEXT, prazo_dias INTEGER, data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT)');
$pdo->exec('CREATE TABLE processos (id TEXT, classe_id TEXT, numero_processo TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT, nome TEXT, codigo TEXT)');
$stmt = $pdo->prepare('INSERT INTO editais VALUES (?, NULL, 1, ?, ?, ?)');
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$stmt->execute(['edital', $today, $today, $today]);
$info = (new EditaisService($pdo))->getDecursoInfo('edital');
check($info['prazo_cumprido'] === true && $info['libera_conclusao'] === false && $info['dias_restantes'] === 0, 'serviço não identifica vencimento hoje');
check(str_contains($rotulo->invoke(null, $info), '(vence hoje)'), 'exportação não indica vencimento hoje');
$update = $pdo->prepare('UPDATE editais SET data_decurso_prazo = ? WHERE id = ?');
foreach (['-1 day' => [true, true, 0], '+1 day' => [false, false, 1]] as $offset => $expected) {
    $update->execute([(new DateTimeImmutable('today'))->modify($offset)->format('Y-m-d'), 'edital']);
    $actual = (new EditaisService($pdo))->getDecursoInfo('edital');
    check([$actual['prazo_cumprido'], $actual['libera_conclusao'], $actual['dias_restantes']] === $expected, 'serviço: estado incorreto ' . $offset);
}
echo "REI-134: estados e exportação verificados em SQLite memória.\n";
