<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/rei152-auditoria-documentos.php';

$docs = [
    ['parte_id' => 'P1', 'tipo' => 'cpf', 'numero' => '529.982.247-25'],
    ['parte_id' => 'P2', 'tipo' => 'CPF', 'numero' => '52998224725'],
    ['parte_id' => 'P2', 'tipo' => 'CPF', 'numero' => '11144477735'],
    ['parte_id' => 'P3', 'tipo' => 'CPF', 'numero' => 'invalido'],
];
$r = rei152AuditarDocumentos($docs, [['id' => 'P1', 'cpf' => '52998224725'], ['id' => 'P2', 'cpf' => '52998224725']]);
$esperado = ['documentos_invalidos' => 1, 'nao_canonicos' => 2, 'documentos_duplicados' => 1, 'colisoes_numero_normalizado_vazio' => 0, 'tipos_repetidos_por_pessoa' => 1, 'cpf_legado_divergente' => 1, 'cpf_legado_invalido' => 0, 'cpf_legado_nao_canonico' => 0];
if ($r !== $esperado) throw new RuntimeException('Auditoria de fixture produziu contagens incorretas.');
if (rei152AuditarDocumentos([], [['id' => 'P1', 'cpf' => null]])['cpf_legado_divergente'] !== 0) throw new RuntimeException('CPF ausente não deve divergir.');
$invalidos = rei152AuditarDocumentos([
    ['parte_id' => 'P1', 'tipo' => 'CPF', 'numero' => '123'],
    ['parte_id' => 'P2', 'tipo' => 'CPF', 'numero' => '123'],
    ['parte_id' => 'P2', 'tipo' => 'CPF', 'numero' => '456'],
], [['id' => 'P3', 'cpf' => 'lixo'], ['id' => 'P4', 'cpf' => '529.982.247-25']]);
if ($invalidos['documentos_invalidos'] !== 3 || $invalidos['documentos_duplicados'] !== 1 || $invalidos['tipos_repetidos_por_pessoa'] !== 1 || $invalidos['cpf_legado_invalido'] !== 1 || $invalidos['cpf_legado_nao_canonico'] !== 2) throw new RuntimeException('Inválidos ou CPF legado não identificados.');
$vazios = rei152AuditarDocumentos([
    ['parte_id' => 'P1', 'tipo' => 'CPF', 'numero' => 'abc'],
    ['parte_id' => 'P2', 'tipo' => 'CPF', 'numero' => 'xyz'],
    ['parte_id' => 'P3', 'tipo' => 'CPF', 'numero' => null],
    ['parte_id' => 'P4', 'tipo' => 'RG', 'numero' => ''],
], []);
if ($vazios['colisoes_numero_normalizado_vazio'] !== 1) throw new RuntimeException('Colisão de valores não nulos normalizados vazios ausente ou NULL contado indevidamente.');
echo "REI-152 auditoria sintética: OK\n";
