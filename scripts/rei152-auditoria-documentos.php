<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/ParteDocumentoHelper.php';

/**
 * Inspeciona apenas arrays fornecidos pelo chamador; não conecta nem emite dados pessoais.
 * O resultado não valida collation, locks, cobertura de escritores ou esquema real.
 * @param list<array{parte_id:mixed,tipo:mixed,numero:mixed}> $documentos
 * @param list<array{id:mixed,cpf:mixed}> $partes
 * @return array<string,int>
 */
function rei152AuditarDocumentos(array $documentos, array $partes): array
{
    $resultado = ['documentos_invalidos' => 0, 'nao_canonicos' => 0, 'documentos_duplicados' => 0, 'colisoes_numero_normalizado_vazio' => 0, 'tipos_repetidos_por_pessoa' => 0, 'cpf_legado_divergente' => 0, 'cpf_legado_invalido' => 0, 'cpf_legado_nao_canonico' => 0];
    $porDocumento = [];
    $vaziosPorTipo = [];
    $porPessoaTipo = [];
    $cpfPorPessoa = [];
    foreach ($documentos as $doc) {
        $pessoa = trim((string) ($doc['parte_id'] ?? ''));
        $tipoOriginal = (string) ($doc['tipo'] ?? '');
        $numeroOriginal = (string) ($doc['numero'] ?? '');
        $tipo = ParteDocumentoHelper::normalizarTipo($tipoOriginal);
        $numero = ParteDocumentoHelper::normalizarNumero($tipo, $numeroOriginal);
        if ($tipo !== $tipoOriginal || $numero !== $numeroOriginal) $resultado['nao_canonicos']++;
        // Arrays aninhados evitam colisões por separadores em valores livres.
        if ($tipo !== '' && $numero !== '') {
            if (isset($porDocumento[$tipo][$numero])) $resultado['documentos_duplicados']++;
            $porDocumento[$tipo][$numero] = true;
        }
        // NULL não equivale necessariamente a string vazia em índices SQL.
        if ($tipo !== '' && $numero === '' && array_key_exists('numero', $doc) && $doc['numero'] !== null) {
            if (isset($vaziosPorTipo[$tipo])) $resultado['colisoes_numero_normalizado_vazio']++;
            $vaziosPorTipo[$tipo] = true;
        }
        if ($pessoa !== '' && $tipo !== '') {
            if (isset($porPessoaTipo[$pessoa][$tipo])) $resultado['tipos_repetidos_por_pessoa']++;
            $porPessoaTipo[$pessoa][$tipo] = true;
            if ($tipo === 'CPF') $cpfPorPessoa[$pessoa][] = $numero;
        }
        if ($pessoa === '' || !ParteDocumentoHelper::isTipoValido($tipo) || $numero === '') {
            $resultado['documentos_invalidos']++;
            continue;
        }
        try { ParteDocumentoHelper::validarNumero($tipo, $numero); }
        catch (InvalidArgumentException) { $resultado['documentos_invalidos']++; }
    }
    foreach ($partes as $parte) {
        $id = trim((string) ($parte['id'] ?? ''));
        $cpfOriginal = (string) ($parte['cpf'] ?? '');
        $cpf = ParteDocumentoHelper::normalizarNumero('CPF', $cpfOriginal);
        if ($cpfOriginal !== '' && $cpf !== $cpfOriginal) $resultado['cpf_legado_nao_canonico']++;
        if ($cpfOriginal !== '') {
            try { ParteDocumentoHelper::validarNumero('CPF', $cpf); }
            catch (InvalidArgumentException) { $resultado['cpf_legado_invalido']++; }
        }
        $documentosCpf = $cpfPorPessoa[$id] ?? [];
        if (count($documentosCpf) > 1 || ($cpf !== '' ? $documentosCpf !== [$cpf] : $documentosCpf !== [])) {
            $resultado['cpf_legado_divergente']++;
        }
    }
    return $resultado;
}
