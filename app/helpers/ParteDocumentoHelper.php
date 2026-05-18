<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/security.php';

final class ParteDocumentoHelper
{
    /** @var list<string> */
    public const TIPOS = ['CPF', 'RG', 'CIN', 'CAD', 'MAT', 'CNH', 'OAB'];

    public static function normalizarTipo(string $tipo): string
    {
        return strtoupper(trim($tipo));
    }

    public static function isTipoValido(string $tipo): bool
    {
        return in_array(self::normalizarTipo($tipo), self::TIPOS, true);
    }

    public static function normalizarNumero(string $tipo, string $numero): string
    {
        $tipo = self::normalizarTipo($tipo);
        $numero = trim($numero);
        if ($numero === '') {
            return '';
        }
        if ($tipo === 'CPF') {
            return preg_replace('/\D+/', '', $numero) ?? '';
        }

        return preg_replace('/\s+/', '', $numero) ?? '';
    }

    public static function validarNumero(string $tipo, string $numeroNormalizado): void
    {
        $tipo = self::normalizarTipo($tipo);
        if ($numeroNormalizado === '') {
            throw new InvalidArgumentException('Número do documento é obrigatório.');
        }
        if ($tipo === 'CPF') {
            if (strlen($numeroNormalizado) !== 11) {
                throw new InvalidArgumentException('CPF deve conter 11 dígitos.');
            }
            if (!Security::validarCPF($numeroNormalizado)) {
                throw new InvalidArgumentException('CPF inválido. Verifique os dígitos.');
            }

            return;
        }
        if (strlen($numeroNormalizado) > 60) {
            throw new InvalidArgumentException('Número do documento excede o tamanho máximo.');
        }
    }

    public static function formatarExibicao(string $tipo, string $numero): string
    {
        $tipo = self::normalizarTipo($tipo);
        $numero = trim($numero);
        if ($numero === '') {
            return '';
        }
        if ($tipo === 'CPF' && strlen(preg_replace('/\D+/', '', $numero) ?? '') === 11) {
            return Security::formatCPF(preg_replace('/\D+/', '', $numero) ?? '');
        }

        return $numero;
    }

    /**
     * @param list<array<string, mixed>> $documentos
     */
    public static function resolverCpfParaColunaLegada(array $documentos): ?string
    {
        foreach ($documentos as $doc) {
            $tipo = self::normalizarTipo((string) ($doc['tipo'] ?? ''));
            if ($tipo !== 'CPF') {
                continue;
            }
            $num = self::normalizarNumero('CPF', (string) ($doc['numero'] ?? ''));
            if ($num !== '') {
                return $num;
            }
        }

        return null;
    }

    public static function rotuloCargoAdvogado(?string $profissao, ?string $sexo = null): string
    {
        $profissao = trim((string) $profissao);
        if ($profissao !== '') {
            if (function_exists('mb_stripos') && mb_stripos($profissao, 'advogada', 0, 'UTF-8') !== false) {
                return 'Advogada';
            }
            if (stripos($profissao, 'advogada') !== false) {
                return 'Advogada';
            }
            if (function_exists('mb_stripos') && mb_stripos($profissao, 'advogado', 0, 'UTF-8') !== false) {
                return 'Advogado';
            }
            if (stripos($profissao, 'advogado') !== false) {
                return 'Advogado';
            }
        }
        if (trim((string) $sexo) === 'Feminino') {
            return 'Advogada';
        }

        return 'Advogado';
    }

    /**
     * Documento para exibição ao lado do advogado (defensor privado): OAB, depois CPF, depois demais tipos.
     *
     * @param list<array<string, mixed>> $documentos
     * @return array{tipo: string, numero: string}|null
     */
    public static function resolverDocumentoDefensorPrivado(array $documentos, ?string $cpfLegado = null): ?array
    {
        $porTipo = [];
        foreach ($documentos as $doc) {
            $tipo = self::normalizarTipo((string) ($doc['tipo'] ?? ''));
            $numero = self::normalizarNumero($tipo, (string) ($doc['numero'] ?? ''));
            if ($tipo !== '' && $numero !== '') {
                $porTipo[$tipo] = $numero;
            }
        }

        foreach (['OAB', 'CPF', 'RG', 'CIN', 'CAD', 'MAT', 'CNH'] as $tipoPrioridade) {
            if (!isset($porTipo[$tipoPrioridade])) {
                continue;
            }

            return [
                'tipo' => $tipoPrioridade,
                'numero' => self::formatarExibicao($tipoPrioridade, $porTipo[$tipoPrioridade]),
            ];
        }

        $cpfLegadoNorm = $cpfLegado !== null && trim($cpfLegado) !== ''
            ? self::normalizarNumero('CPF', $cpfLegado)
            : '';
        if ($cpfLegadoNorm !== '') {
            return [
                'tipo' => 'CPF',
                'numero' => self::formatarExibicao('CPF', $cpfLegadoNorm),
            ];
        }

        return null;
    }

    /**
     * Ex.: "Advogada: Laura Adriana Vieira (OAB: 456522)" — sem sufixo para defensor público ou nome livre sem cadastro.
     *
     * @param list<array<string, mixed>>|null $documentos
     */
    public static function montarRotuloDefensorPrivado(string $nome, ?array $parteCadastro = null, ?array $documentos = null): string
    {
        $nome = trim($nome);
        $rotulo = self::rotuloCargoAdvogado(
            isset($parteCadastro['profissao']) ? (string) $parteCadastro['profissao'] : null,
            isset($parteCadastro['sexo']) ? (string) $parteCadastro['sexo'] : null
        );
        if ($nome === '') {
            return $rotulo . ': —';
        }

        $docs = $documentos ?? [];
        $cpfLegado = isset($parteCadastro['cpf']) ? (string) $parteCadastro['cpf'] : null;
        $docExib = self::resolverDocumentoDefensorPrivado($docs, $cpfLegado);
        if ($docExib === null) {
            return $rotulo . ': ' . $nome;
        }

        return sprintf('%s: %s (%s: %s)', $rotulo, $nome, $docExib['tipo'], $docExib['numero']);
    }
}
