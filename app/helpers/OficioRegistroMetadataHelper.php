<?php
declare(strict_types=1);

/**
 * Monta dados do bloco `container-data-create` nas listagens de ofícios.
 */
final class OficioRegistroMetadataHelper
{
    /**
     * @param array<string, mixed> $row Linha de fetchList (IdOficio, Data, DataAtualizacao, Status, CriadoPorNome, …)
     * @return array{
     *   criador_nome: string,
     *   data_cadastro_texto: string,
     *   data_evento_classe: string,
     *   data_evento_texto: string,
     *   id: string,
     *   show_edit: bool,
     *   edit_href: string,
     *   show_cancel: bool,
     *   cancel_endpoint: string
     * }
     */
    public static function fromListRow(array $row, string $modulo = 'gabinete'): array
    {
        $modulo = strtolower(trim($modulo));
        $status = strtolower(trim((string) ($row['Status'] ?? '')));
        $id = trim((string) ($row['IdOficio'] ?? ''));
        $criador = trim((string) ($row['CriadoPorNome'] ?? ''));
        $createdRaw = trim((string) ($row['Data'] ?? ''));
        $updatedRaw = trim((string) ($row['DataAtualizacao'] ?? ''));

        $dataEventoClasse = '';
        $dataEventoTexto = '';
        if ($status === 'cancelada') {
            $dataEventoClasse = 'canceled';
            $ref = $updatedRaw !== '' ? $updatedRaw : $createdRaw;
            $dataEventoTexto = 'Cancelado em ' . self::formatarDataHora($ref);
        } elseif ($updatedRaw !== '' && $createdRaw !== '' && self::isUpdatedAfterCreated($createdRaw, $updatedRaw)) {
            $dataEventoClasse = 'updated';
            $dataEventoTexto = 'Atualizado em ' . self::formatarDataHora($updatedRaw);
        }

        $showEdit = $modulo === 'cartorio';
        $editHref = $showEdit && $id !== ''
            ? '/frame.php?page=oficioscartorioedit&id=' . rawurlencode($id)
            : '';

        $showCancel = $status !== 'cancelada';
        if ($modulo === 'cartorio' && $status === 'finalizada') {
            $showCancel = false;
        }

        $cancelEndpoint = $modulo === 'cartorio'
            ? '/api/cartorio-oficios/cancel.php'
            : '/api/gabinete-oficios/cancel.php';

        return [
            'criador_nome' => $criador !== '' ? $criador : '—',
            'data_cadastro_texto' => 'Em ' . self::formatarDataHora($createdRaw),
            'data_evento_classe' => $dataEventoClasse,
            'data_evento_texto' => $dataEventoTexto,
            'id' => $id,
            'show_edit' => $showEdit,
            'edit_href' => $editHref,
            'show_cancel' => $showCancel,
            'cancel_endpoint' => $cancelEndpoint,
        ];
    }

    public static function formatarDataHora(?string $mysqlDt): string
    {
        if ($mysqlDt === null || trim($mysqlDt) === '') {
            return '—';
        }
        $t = strtotime($mysqlDt);

        return $t !== false ? date('d/m/Y \à\s H:i:s', $t) : trim($mysqlDt);
    }

    private static function isUpdatedAfterCreated(string $createdRaw, string $updatedRaw): bool
    {
        $created = strtotime($createdRaw);
        $updated = strtotime($updatedRaw);
        if ($created === false || $updated === false) {
            return $updatedRaw !== '' && $updatedRaw !== $createdRaw;
        }

        return $updated > $created + 1;
    }
}
