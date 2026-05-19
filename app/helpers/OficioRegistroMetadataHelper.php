<?php
declare(strict_types=1);

/**
 * Monta dados do bloco `container-data-create` nas listagens de ofícios.
 */
final class OficioRegistroMetadataHelper
{
    private const CANCEL_GABINETE_MAX_DIAS_DESDE_CRIACAO = 3;

    /** @var list<string> */
    private const PERFIS_CANCEL_GABINETE_PRIVILEGIADOS = ['ADMIN', 'CHEFIA'];

    /**
     * @return array{usuario_id: string, perfis: list<string>}
     */
    public static function viewerContextFromSession(): array
    {
        $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
        $perfis = [];
        if ($usuarioId !== '') {
            require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';
            $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
        }

        return [
            'usuario_id' => $usuarioId,
            'perfis' => $perfis,
        ];
    }

    /**
     * Cartório: pendente (não cancelado nem finalizado).
     *
     * @param array<string, mixed> $row Status ou status
     */
    public static function podeCancelarCartorio(array $row): bool
    {
        $status = strtolower(trim((string) ($row['Status'] ?? $row['status'] ?? '')));

        return $status !== 'cancelada' && $status !== 'finalizada';
    }

    /**
     * Cancelar no Gabinete: ofício ativo, criado há menos de 3 dias e (autor logado ou ADMIN/CHEFIA).
     *
     * @param array<string, mixed> $row Status, Data (created_at), CriadoPor
     * @param array{usuario_id?: string, perfis?: list<string>} $viewerContext
     */
    public static function podeCancelarGabinete(array $row, array $viewerContext): bool
    {
        $status = strtolower(trim((string) ($row['Status'] ?? $row['status'] ?? '')));
        if ($status === 'cancelada') {
            return false;
        }

        $createdRaw = trim((string) ($row['Data'] ?? $row['created_at'] ?? ''));
        if (!self::criadoHaMenosDeDias($createdRaw, self::CANCEL_GABINETE_MAX_DIAS_DESDE_CRIACAO)) {
            return false;
        }

        if (self::viewerTemPerfilPrivilegiadoCancelGabinete($viewerContext)) {
            return true;
        }

        $viewerId = trim((string) ($viewerContext['usuario_id'] ?? ''));
        $criadoPor = trim((string) ($row['CriadoPor'] ?? $row['criado_por'] ?? ''));

        return $viewerId !== '' && $criadoPor !== '' && $viewerId === $criadoPor;
    }

    /**
     * @param array<string, mixed> $row Linha de fetchList
     * @param array{usuario_id?: string, perfis?: list<string>}|null $viewerContext
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
    public static function fromListRow(array $row, string $modulo = 'gabinete', ?array $viewerContext = null): array
    {
        $modulo = strtolower(trim($modulo));
        $status = strtolower(trim((string) ($row['Status'] ?? '')));
        $id = trim((string) ($row['IdOficio'] ?? ''));
        $criador = trim((string) ($row['CriadoPorNome'] ?? ''));
        $editor = trim((string) ($row['UltimoEditorNome'] ?? ''));
        $createdRaw = trim((string) ($row['Data'] ?? ''));
        $updatedRaw = trim((string) ($row['DataAtualizacao'] ?? ''));

        require_once __DIR__ . '/RegistroMetadataHelper.php';

        $criadorCompacto = RegistroMetadataHelper::compactarNome($criador);
        $editorCompacto = RegistroMetadataHelper::compactarNome($editor);

        $dataEventoClasse = '';
        $dataEventoTexto = '';
        if ($status === 'cancelada') {
            $dataEventoClasse = 'canceled';
            $ref = $updatedRaw !== '' ? $updatedRaw : $createdRaw;
            $dataEventoTexto = RegistroMetadataHelper::formatarEventoComAutor('Cancelado em', $ref, $editorCompacto);
        } elseif ($updatedRaw !== '' && $createdRaw !== '' && self::isUpdatedAfterCreated($createdRaw, $updatedRaw)) {
            $dataEventoClasse = 'updated';
            $dataEventoTexto = RegistroMetadataHelper::formatarEventoComAutor('Atualizado em', $updatedRaw, $editorCompacto);
        }

        $editHref = '';
        if ($modulo === 'gabinete') {
            $showCancel = self::podeCancelarGabinete($row, $viewerContext ?? []);
            $showEdit = false;
        } else {
            $showCancel = self::podeCancelarCartorio($row);
            $showEdit = $status !== 'cancelada';
            if ($showEdit && $id !== '') {
                $editHref = '/frame.php?page=oficioscartorioedit&id=' . rawurlencode($id);
            }
        }

        $cancelEndpoint = $modulo === 'cartorio'
            ? '/api/cartorio-oficios/cancel.php'
            : '/api/gabinete-oficios/cancel.php';

        return [
            'criador_nome' => $criadorCompacto !== '' ? $criadorCompacto : $criador,
            'user_prefix' => 'Cadastrado por',
            'data_cadastro_texto' => $createdRaw !== '' ? 'Em ' . self::formatarDataHora($createdRaw) : '',
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

    /** Data sem hora (linha de atualização/cancelamento na listagem). */
    public static function formatarData(?string $mysqlDt): string
    {
        if ($mysqlDt === null || trim($mysqlDt) === '') {
            return '—';
        }
        $t = strtotime($mysqlDt);

        return $t !== false ? date('d/m/Y', $t) : trim($mysqlDt);
    }

    /**
     * @param array{usuario_id?: string, perfis?: list<string>} $viewerContext
     */
    private static function viewerTemPerfilPrivilegiadoCancelGabinete(array $viewerContext): bool
    {
        $perfis = $viewerContext['perfis'] ?? [];
        if (!is_array($perfis) || $perfis === []) {
            return false;
        }
        $normalizados = [];
        foreach ($perfis as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if ($p !== '') {
                $normalizados[] = $p;
            }
        }

        return count(array_intersect($normalizados, self::PERFIS_CANCEL_GABINETE_PRIVILEGIADOS)) > 0;
    }

    private static function criadoHaMenosDeDias(string $createdRaw, int $dias): bool
    {
        if ($dias < 1) {
            return false;
        }
        $created = strtotime($createdRaw);
        if ($created === false) {
            return false;
        }
        $limite = strtotime('-' . $dias . ' days');

        return $limite !== false && $created >= $limite;
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
