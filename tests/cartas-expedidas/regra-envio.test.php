<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/CartasExpedidasEnvioRegra.php';

function expectEqual(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException('Falhou: ' . var_export([$actual, $expected], true));
    }
}

$db = new PDO('sqlite::memory:');
$db->exec("CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT, cadastro_ativo INT);
CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);
CREATE TABLE alocacoes_regras (id TEXT, regra TEXT, descricao TEXT, usuario_id TEXT, usuario_atualiza_id TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO usuarios VALUES ('S','Pessoa','Responsável',1), ('E','Pessoa','Estudante',1), ('I','Pessoa','Inativa',0);
INSERT INTO usuario_perfil VALUES ('S','SERVIDOR'),('S','ESTUDANTE'),('E','ESTUDANTE'),('I','SERVIDOR');");
expectEqual(CartasExpedidasEnvioRegra::elegiveis($db), [['id'=>'S','nome'=>'Pessoa Responsável']]);
expectEqual(CartasExpedidasEnvioRegra::responsavel($db), null);
$stmt = $db->prepare("INSERT INTO alocacoes_regras VALUES ('R', ?, 'envio', 'S', 'A', '', '')");
$stmt->execute([CartasExpedidasEnvioRegra::NOME]);
$r = CartasExpedidasEnvioRegra::responsavel($db);
expectEqual($r, ['id'=>'S','nome'=>'Pessoa Responsável']);
expectEqual(CartasExpedidasEnvioRegra::responsavelNaEtapa('envio', $r), 'Pessoa Responsável');
foreach (['devolucao','encerrada','indeterminada'] as $etapa) {
    expectEqual(CartasExpedidasEnvioRegra::responsavelNaEtapa($etapa, $r), '');
}
$db->exec("UPDATE usuarios SET cadastro_ativo=0 WHERE id_usuario='S'");
expectEqual(CartasExpedidasEnvioRegra::responsavel($db), null);
expectEqual(CartasExpedidasEnvioRegra::podeAlterar(['ADMIN']), true);
expectEqual(CartasExpedidasEnvioRegra::podeAlterar(['DIRETORA']), true);
expectEqual(CartasExpedidasEnvioRegra::podeAlterar(['MAGISTRADA']), true);
expectEqual(CartasExpedidasEnvioRegra::podeAlterar(['SERVIDOR','ESTUDANTE']), false);
echo "PASS regra de envio, elegibilidade, perfis e fronteira entre envio e devolução\n";
