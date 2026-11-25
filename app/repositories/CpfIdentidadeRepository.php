<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/ParteDocumentoHelper.php';

/** Busca conservadora das identidades legadas; nao altera nem reconcilia dados. */
final class CpfIdentidadeRepository
{
    private string $partes;
    private string $documentos;

    public function __construct(private PDO $connection, string $partes = 'partes', string $documentos = 'partes_documentos')
    {
        $this->partes = self::quoteTable($partes);
        $this->documentos = self::quoteTable($documentos);
    }

    private static function quoteTable(string $table): string
    {
        if ($table === '' || str_contains($table, "\0")) throw new InvalidArgumentException('Nome de tabela inválido.');
        return '`' . str_replace('`', '``', $table) . '`';
    }

    /** Retorna o unico dono ou recusa identidade ambigua/orfa. */
    public function findOwner(string $cpf): ?string
    {
        $cpf = ParteDocumentoHelper::normalizarNumero('CPF', $cpf);
        if ($cpf === '') return null;
        $owners = [];
        $partes = $this->connection->query('SELECT `id`, `cpf` FROM ' . $this->partes . ' WHERE `cpf` IS NOT NULL');
        while ($row = $partes->fetch(PDO::FETCH_ASSOC)) {
            if (ParteDocumentoHelper::normalizarNumero('CPF', (string) $row['cpf']) === $cpf) {
                $owners[(string) $row['id']] = true;
            }
        }
        $partes->closeCursor();
        $docs = $this->connection->query('SELECT `parte_id`, `tipo`, `numero` FROM ' . $this->documentos);
        while ($row = $docs->fetch(PDO::FETCH_ASSOC)) {
            if (ParteDocumentoHelper::normalizarTipo((string) $row['tipo']) === 'CPF'
                && ParteDocumentoHelper::normalizarNumero('CPF', (string) ($row['numero'] ?? '')) === $cpf) {
                $owners[(string) ($row['parte_id'] ?? '')] = true;
            }
        }
        $docs->closeCursor();
        if (count($owners) > 1 || isset($owners[''])) {
            throw new InvalidArgumentException('Identidade CPF ambígua; cadastro requer reconciliação.');
        }
        $id = array_key_first($owners);
        if ($id === null) return null;
        $this->assertCompatible($id, $cpf);
        return $id;
    }

    /** Recusa conflito sem sobrescrever dados legados de uma pessoa. */
    public function assertCompatible(string $id, string $cpf): void
    {
        $check = $this->connection->prepare('SELECT `cpf` FROM ' . $this->partes . ' WHERE `id` = :id');
        $check->execute([':id' => $id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $check->closeCursor();
        if ($row === false) throw new InvalidArgumentException('Identidade CPF inconsistente; cadastro requer reconciliação.');
        $legacy = ParteDocumentoHelper::normalizarNumero('CPF', (string) ($row['cpf'] ?? ''));
        if ($legacy !== '' && $legacy !== $cpf) {
            throw new InvalidArgumentException('Identidade CPF inconsistente; cadastro requer reconciliação.');
        }
        $check = $this->connection->prepare('SELECT `tipo`, `numero` FROM ' . $this->documentos . ' WHERE `parte_id` = :id');
        $check->execute([':id' => $id]);
        $count = 0;
        while ($doc = $check->fetch(PDO::FETCH_ASSOC)) {
            if (ParteDocumentoHelper::normalizarTipo((string) $doc['tipo']) !== 'CPF') continue;
            $count++;
            if ($count > 1 || ParteDocumentoHelper::normalizarNumero('CPF', (string) ($doc['numero'] ?? '')) !== $cpf) {
                $check->closeCursor();
                throw new InvalidArgumentException('Identidade CPF inconsistente; cadastro requer reconciliação.');
            }
        }
        $check->closeCursor();
    }
}
