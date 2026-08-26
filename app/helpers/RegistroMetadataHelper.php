<?php
declare(strict_types=1);

/**
 * Monta dados do bloco `container-data-create` nas listagens internas.
 */
final class RegistroMetadataHelper
{
    private const COLLATION = 'utf8mb4_unicode_ci';

    /** @var list<string> */
    private const TERMINAL_STATUSES = [
        'cancelada',
        'cancelado',
        'redesignada',
        'adiada',
        'concluída',
        'concluida',
        'finalizada',
    ];

    /** @var array<string, array<string, mixed>> */
    private const MODULES = [
        'cartas_expedidas' => [
            'id_keys' => ['IdCarta', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'cartasexpedidasedit',
            'cancel_endpoint' => '/api/cartorio-cartas-expedidas/cancel.php',
            'cancel_toast_key' => 'cartorio.cartas_expedidas.cancel.success',
            'cancel_label' => 'Cancelar carta expedida',
        ],
        'cartas_recebidas' => [
            'id_keys' => ['IdCarta', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'cartasrecebidasedit',
            'cancel_endpoint' => '/api/cartorio-cartas-recebidas/cancel.php',
            'cancel_toast_key' => 'cartorio.cartas_recebidas.cancel.success',
            'cancel_label' => 'Cancelar carta recebida',
        ],
        'editais' => [
            'id_keys' => ['IdEdital', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'editaisedit',
            'cancel_endpoint' => '/api/cartorio-editais/cancel.php',
            'cancel_toast_key' => 'cartorio.editais.cancel.success',
            'cancel_label' => 'Cancelar edital',
        ],
        'guias' => [
            'id_keys' => ['IdGuia', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'guiasedit',
            'cancel_endpoint' => '/api/cartorio-guias/cancel.php',
            'cancel_toast_key' => 'cartorio.guias.cancel.success',
            'cancel_label' => 'Cancelar guia',
        ],
        'cejusc' => [
            'id_keys' => ['id'],
            'status_keys' => ['status'],
            'data_keys' => ['created_at'],
            'updated_keys' => ['updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'cejuscedit',
            'show_cancel' => false,
            'cancel_endpoint' => '',
        ],
        'gabinete_monitoramento' => [
            'id_keys' => ['IdMon', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'monitoramentogabineteedit',
            'cancel_endpoint' => '/api/gabinete-monitoramento-eletronico/cancel.php',
            'cancel_toast_key' => 'gme.monitoring.cancel.success',
            'cancel_label' => 'Cancelar monitoramento',
            'terminal_extra' => ['finalizada'],
        ],
        'sala_pauta' => [
            'id_keys' => ['id_pauta', 'IdPauta'],
            'status_keys' => ['status', 'Status'],
            'data_keys' => ['Data', 'data_hora_designacao', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'salapautaedit',
            'cancel_endpoint' => '/api/sala-audiencias-pauta/cancel.php',
            'cancel_toast_key' => 'sala.pauta.cancel.success',
            'cancel_label' => 'Cancelar audiência',
        ],
        'sala_atas' => [
            'id_keys' => ['id_ata', 'id_pauta'],
            'status_keys' => ['status', 'Status'],
            'edit_page' => '',
            'cancel_endpoint' => '',
            'show_edit' => false,
            'show_cancel' => false,
        ],
    ];

    /**
     * @return array{usuario_id: string, perfis: list<string>}
     */
    public static function viewerContextFromSession(): array
    {
        require_once __DIR__ . '/OficioRegistroMetadataHelper.php';

        return OficioRegistroMetadataHelper::viewerContextFromSession();
    }

    /**
     * @param array<string, mixed> $row
     * @param array{usuario_id?: string, perfis?: list<string>}|null $viewerContext
     * @return array<string, mixed>
     */
    public static function fromListRow(array $row, string $modulo, ?array $viewerContext = null, ?PDO $connection = null): array
    {
        $modulo = strtolower(trim($modulo));
        if ($modulo === 'cartorio_oficios') {
            require_once __DIR__ . '/OficioRegistroMetadataHelper.php';

            return OficioRegistroMetadataHelper::fromListRow($row, 'cartorio', $viewerContext);
        }
        if ($modulo === 'gabinete_oficios') {
            require_once __DIR__ . '/OficioRegistroMetadataHelper.php';

            return OficioRegistroMetadataHelper::fromListRow($row, 'gabinete', $viewerContext);
        }
        if ($modulo === 'sala_atas') {
            return self::fromListRowSalaAtas($row);
        }

        $config = self::MODULES[$modulo] ?? null;
        if ($config === null) {
            return self::emptyPayload();
        }

        $status = strtolower(trim(self::pick($row, $config['status_keys'] ?? ['Status', 'status'])));
        $id = trim(self::pick($row, $config['id_keys'] ?? ['id']));
        $criador = trim(self::pick($row, $config['criador_keys'] ?? ['CriadoPorNome']));
        if ($criador === '' && $connection instanceof PDO) {
            $criadoPorId = trim(self::pick($row, ['CriadoPor', 'criado_por']));
            if ($criadoPorId !== '') {
                $criador = self::buscarNomeUsuarioPorId($connection, $criadoPorId);
            }
        }
        if ($criador === '') {
            $criador = self::resolverNomeCriadorFallback($row);
        }
        $editor = trim(self::pick($row, $config['editor_keys'] ?? ['UltimoEditorNome', 'AtualizadoPorNome']));
        $createdRaw = trim(self::pick($row, $config['data_keys'] ?? ['Data', 'created_at']));
        $updatedRaw = trim(self::pick($row, $config['updated_keys'] ?? ['DataAtualizacao', 'updated_at']));

        $cancelStatuses = $config['cancel_statuses'] ?? ['cancelada', 'cancelado'];
        $terminalStatuses = array_unique(array_merge(
            self::TERMINAL_STATUSES,
            is_array($config['terminal_extra'] ?? null) ? $config['terminal_extra'] : []
        ));

        $dataEventoClasse = '';
        $dataEventoTexto = '';
        $movementFromSubtarefa = !empty($config['movement_from_subtarefa']);
        if (in_array($status, $cancelStatuses, true)) {
            $dataEventoClasse = 'canceled';
            $ref = $updatedRaw !== '' ? $updatedRaw : $createdRaw;
            $dataEventoTexto = self::formatarEventoComAutor('Cancelado em', $ref, $editor);
        } elseif ($movementFromSubtarefa) {
            $subtarefasAll = $row['SubtarefasAll'] ?? [];
            if (!is_array($subtarefasAll)) {
                $subtarefasAll = [];
            }
            $ultimaSubtarefa = self::resolveUltimaSubtarefaConcluida($subtarefasAll, $connection);
            if ($ultimaSubtarefa !== null && trim($ultimaSubtarefa['data']) !== '') {
                $editorMovimentacao = trim($ultimaSubtarefa['editor']);
                if ($editorMovimentacao === '') {
                    $editorMovimentacao = self::compactarNome($editor);
                }
                if ($editorMovimentacao === '') {
                    $editorMovimentacao = self::compactarNome($criador);
                }
                $dataEventoClasse = 'updated';
                $dataEventoTexto = self::formatarEventoComAutor(
                    'Atualizado em',
                    $ultimaSubtarefa['data'],
                    $editorMovimentacao
                );
            }
        } elseif ($updatedRaw !== '' && $createdRaw !== '' && self::isUpdatedAfterCreated($createdRaw, $updatedRaw)) {
            $dataEventoClasse = 'updated';
            $dataEventoTexto = self::formatarEventoComAutor('Atualizado em', $updatedRaw, $editor);
        }

        $showEdit = !array_key_exists('show_edit', $config)
            ? ($id !== '' && ($config['edit_page'] ?? '') !== '' && !in_array($status, $terminalStatuses, true))
            : (bool) $config['show_edit'];
        $showCancel = !array_key_exists('show_cancel', $config)
            ? self::podeCancelarPadrao($status, $terminalStatuses, $cancelStatuses, trim((string) ($config['cancel_endpoint'] ?? '')))
            : (bool) $config['show_cancel'];

        $editHref = '';
        if ($showEdit && $id !== '' && ($config['edit_page'] ?? '') !== '') {
            $editHref = '/frame.php?page=' . rawurlencode((string) $config['edit_page']) . '&id=' . rawurlencode($id);
        }

        $userPrefix = trim((string) ($config['user_prefix'] ?? 'Cadastrado por'));

        return [
            'criador_nome' => $criador,
            'user_prefix' => $userPrefix,
            'data_cadastro_texto' => $createdRaw !== '' ? 'Em ' . self::formatarDataHora($createdRaw) : '',
            'data_evento_classe' => $dataEventoClasse,
            'data_evento_texto' => $dataEventoTexto,
            'id' => $id,
            'show_edit' => $showEdit,
            'edit_href' => $editHref,
            'show_cancel' => $showCancel,
            'cancel_endpoint' => trim((string) ($config['cancel_endpoint'] ?? '')),
            'cancel_toast_key' => trim((string) ($config['cancel_toast_key'] ?? '')),
            'cancel_label' => trim((string) ($config['cancel_label'] ?? 'Cancelar registro')),
            'edit_label' => trim((string) ($config['edit_label'] ?? 'Editar registro')),
        ];
    }

    /**
     * Metadados da listagem de atas: cadastro automático pelo sistema; atualização por upload de PDF ou edição da pauta.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function fromListRowSalaAtas(array $row): array
    {
        $id = trim(self::pick($row, ['id_ata', 'id_pauta']));
        $cadastroRaw = trim(self::pick($row, ['DataCadastroSistema', 'data_hora_fim_efetivo', 'Data']));

        $candidatosAtualizacao = [
            [
                'data' => trim(self::pick($row, ['DataUploadAta', 'uploaded_at'])),
                'editor' => trim(self::pick($row, ['UploadPorNome', 'CriadoPorNome'])),
            ],
            [
                'data' => trim(self::pick($row, ['DataAtualizacaoPauta', 'DataAtualizacao', 'updated_at'])),
                'editor' => trim(self::pick($row, ['UltimoEditorPautaNome', 'UltimoEditorNome'])),
            ],
        ];

        $ultimaAtualizacao = self::escolherUltimaAtualizacaoUsuario($cadastroRaw, $candidatosAtualizacao);

        $dataEventoClasse = '';
        $dataEventoTexto = '';
        if ($ultimaAtualizacao !== null) {
            $dataEventoClasse = 'updated';
            $dataEventoTexto = self::formatarEventoComAutor(
                'Atualizado em',
                $ultimaAtualizacao['data'],
                $ultimaAtualizacao['editor']
            );
        }

        return [
            'criador_nome' => 'sistema',
            'user_prefix' => 'Cadastrado por',
            'linha_autor' => 'Cadastrado pelo sistema',
            'data_cadastro_texto' => $cadastroRaw !== '' ? 'Em ' . self::formatarDataHora($cadastroRaw) : '',
            'data_evento_classe' => $dataEventoClasse,
            'data_evento_texto' => $dataEventoTexto,
            'id' => $id,
            'show_edit' => false,
            'edit_href' => '',
            'show_cancel' => false,
            'cancel_endpoint' => '',
            'cancel_toast_key' => '',
            'cancel_label' => 'Cancelar registro',
            'edit_label' => 'Editar registro',
        ];
    }

    /**
     * Última subtarefa concluída (data + autor) para a coluna de metadados na listagem.
     *
     * @param list<array<string, mixed>> $subtarefasAll
     * @return array{data: string, editor: string}|null
     */
    public static function resolveUltimaSubtarefaConcluida(array $subtarefasAll, ?PDO $connection = null): ?array
    {
        if ($subtarefasAll === []) {
            return null;
        }

        require_once __DIR__ . '/CartorioTimelineHelper.php';

        $melhor = null;
        $melhorTs = 0;
        foreach ($subtarefasAll as $subtarefa) {
            if (!is_array($subtarefa)) {
                continue;
            }
            if (!self::isSubtarefaStatusConcluida((string) ($subtarefa['status'] ?? ''))) {
                continue;
            }
            $data = trim((string) ($subtarefa['updated_at'] ?? ''));
            if ($data === '') {
                continue;
            }
            $ts = strtotime($data);
            if ($ts === false) {
                continue;
            }
            if ($melhor === null || $ts > $melhorTs) {
                $melhor = $subtarefa;
                $melhorTs = $ts;
            }
        }

        if ($melhor === null) {
            return null;
        }

        $usuarioNomesPorId = [];
        if ($connection instanceof PDO) {
            $usuarioNomesPorId = cartorioTimelineCollectUsuarioNomesPorId($connection, $subtarefasAll);
        }

        $dados = cartorioTimelineDecodeData($melhor);
        $editor = cartorioTimelineResolveUser($dados, $usuarioNomesPorId);
        if ($editor === '—') {
            $editor = '';
        }
        $editor = self::compactarNome($editor);

        return [
            'data' => trim((string) ($melhor['updated_at'] ?? '')),
            'editor' => $editor,
        ];
    }

    /**
     * @param list<array{data: string, editor: string}> $candidatos
     * @return array{data: string, editor: string}|null
     */
    private static function escolherUltimaAtualizacaoUsuario(string $cadastroRaw, array $candidatos): ?array
    {
        $melhor = null;
        $melhorTs = 0;

        foreach ($candidatos as $candidato) {
            $data = trim($candidato['data']);
            $editor = self::compactarNome(trim($candidato['editor']));
            if ($data === '' || $editor === '') {
                continue;
            }
            if ($cadastroRaw !== '' && !self::isUpdatedAfterCreated($cadastroRaw, $data)) {
                continue;
            }
            $ts = strtotime($data);
            if ($ts === false) {
                continue;
            }
            if ($melhor === null || $ts > $melhorTs) {
                $melhor = ['data' => $data, 'editor' => $editor];
                $melhorTs = $ts;
            }
        }

        return $melhor;
    }

    public static function formatarDataHora(?string $mysqlDt): string
    {
        require_once __DIR__ . '/OficioRegistroMetadataHelper.php';

        return OficioRegistroMetadataHelper::formatarDataHora($mysqlDt);
    }

    public static function formatarData(?string $mysqlDt): string
    {
        require_once __DIR__ . '/OficioRegistroMetadataHelper.php';

        return OficioRegistroMetadataHelper::formatarData($mysqlDt);
    }

    /** Campos extras para listagem de atas (pauta + upload). */
    public static function sqlSelectSalaAtasListagemExtra(): string
    {
        return ', COALESCE(a.`data_hora_fim_efetivo`, a.`data_hora_inicio_efetivo`, a.`updated_at`, a.`created_at`, a.`data_hora_designacao`) AS `DataCadastroSistema`,'
            . ' ata.`uploaded_at` AS `DataUploadAta`,'
            . ' TRIM(CONCAT(COALESCE(ucr_up.`nome`, \'\'), \' \', COALESCE(ucr_up.`sobrenome`, \'\'))) AS `UploadPorNome`,'
            . ' a.`updated_at` AS `DataAtualizacaoPauta`,'
            . ' TRIM(CONCAT(COALESCE(ued_a.`nome`, \'\'), \' \', COALESCE(ued_a.`sobrenome`, \'\'))) AS `UltimoEditorPautaNome`';
    }

    /** JOINs de auditoria da pauta (listagem de atas). */
    public static function sqlJoinSalaAtasListagem(): string
    {
        return self::sqlUsuarioAuditoria('a');
    }

    public static function sqlUsuarioAuditoria(string $alias, string $criadoPorCol = 'criado_por', string $atualizadoPorCol = 'atualizado_por'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';
        $coll = self::COLLATION;

        return sprintf(
            ' LEFT JOIN `usuarios` ucr_%1$s ON ucr_%1$s.`id_usuario` COLLATE %5$s = %2$s.`%3$s` COLLATE %5$s'
            . ' LEFT JOIN `usuarios` ued_%1$s ON ued_%1$s.`id_usuario` COLLATE %5$s = %2$s.`%4$s` COLLATE %5$s',
            $a,
            $a,
            $criadoPorCol,
            $atualizadoPorCol,
            $coll
        );
    }

    /** @var list<string> */
    private const PARTICULAS_NOME = [
        'de', 'da', 'do', 'dos', 'das', 'e', 'y', 'del', 'la', 'las', 'los',
    ];

    /**
     * Exibe até duas partes substantivas do nome (ex.: nome + sobrenome), incluindo
     * partículas entre elas (ex.: "Silvia de Jesus", "Maria da Silva").
     */
    public static function compactarNome(string $nomeCompleto, int $maxPartes = 2): string
    {
        $nome = preg_replace('/\s+/u', ' ', trim($nomeCompleto));
        if ($nome === '' || $nome === '—' || $nome === '--' || $nome === '-') {
            return '';
        }
        $partes = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY);
        if ($partes === false || $partes === []) {
            return '';
        }

        $resultado = [];
        $substantivas = 0;
        $total = count($partes);

        for ($i = 0; $i < $total && $substantivas < $maxPartes; $i++) {
            $palavra = $partes[$i];
            if (self::isParticulaNome($palavra)) {
                if ($resultado !== []) {
                    $resultado[] = $palavra;
                }
                continue;
            }
            $resultado[] = $palavra;
            $substantivas++;
        }

        while ($resultado !== [] && self::isParticulaNome($resultado[count($resultado) - 1])) {
            array_pop($resultado);
        }

        return implode(' ', $resultado);
    }

    private static function isParticulaNome(string $palavra): bool
    {
        $normalizada = mb_strtolower(trim($palavra), 'UTF-8');

        return in_array($normalizada, self::PARTICULAS_NOME, true);
    }

    /**
     * Texto da primeira linha do bloco de metadados (com ou sem nome do autor).
     */
    public static function formatarLinhaAutor(string $userPrefix, string $criadorNome): string
    {
        $prefix = trim($userPrefix);
        if ($prefix === '') {
            $prefix = 'Cadastrado por';
        }
        $nome = self::compactarNome($criadorNome);
        if ($nome === '') {
            $rotulo = preg_replace('/\s+por\s*$/iu', '', $prefix);

            return $rotulo !== '' ? $rotulo : 'Cadastrado';
        }

        return $prefix . ' ' . $nome;
    }

    /**
     * Linha de evento (atualização/cancelamento) com data (sem hora) e autor compacto.
     */
    public static function formatarEventoComAutor(string $rotuloEvento, string $dataRaw, string $editorNome): string
    {
        $rotulo = trim($rotuloEvento);
        if ($rotulo === '' || trim($dataRaw) === '') {
            return '';
        }
        $texto = $rotulo . ' ' . self::formatarData($dataRaw);
        $editor = self::compactarNome($editorNome);
        if ($editor !== '') {
            $texto .= ' por ' . $editor;
        }

        return $texto;
    }

    public static function sqlSelectAuditoria(string $alias, string $criadoPorCol = 'criado_por'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';

        return sprintf(
            ', %1$s.`created_at` AS `Data`, %1$s.`updated_at` AS `DataAtualizacao`, %1$s.`%2$s` AS `CriadoPor`,'
            . ' TRIM(CONCAT(COALESCE(ucr_%3$s.`nome`, \'\'), \' \', COALESCE(ucr_%3$s.`sobrenome`, \'\'))) AS `CriadoPorNome`,'
            . ' TRIM(CONCAT(COALESCE(ued_%3$s.`nome`, \'\'), \' \', COALESCE(ued_%3$s.`sobrenome`, \'\'))) AS `UltimoEditorNome`',
            $a,
            $criadoPorCol,
            $a
        );
    }

    /** Campos extras quando `Data` já está no SELECT. */
    public static function sqlSelectAuditoriaExtra(string $alias, string $criadoPorCol = 'criado_por'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';

        return sprintf(
            ', %1$s.`updated_at` AS `DataAtualizacao`, %1$s.`%3$s` AS `CriadoPor`,'
            . ' TRIM(CONCAT(COALESCE(ucr_%2$s.`nome`, \'\'), \' \', COALESCE(ucr_%2$s.`sobrenome`, \'\'))) AS `CriadoPorNome`,'
            . ' TRIM(CONCAT(COALESCE(ued_%2$s.`nome`, \'\'), \' \', COALESCE(ued_%2$s.`sobrenome`, \'\'))) AS `UltimoEditorNome`',
            $a,
            $a,
            $criadoPorCol
        );
    }

    /** Auditoria com `criado_em` / `atualizado_em` (sala mídias). */
    public static function sqlSelectAuditoriaEm(string $alias, string $criadoPorCol = 'criado_por'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';

        return sprintf(
            ', %1$s.`criado_em` AS `Data`, %1$s.`atualizado_em` AS `DataAtualizacao`,'
            . ' TRIM(CONCAT(COALESCE(ucr_%2$s.`nome`, \'\'), \' \', COALESCE(ucr_%2$s.`sobrenome`, \'\'))) AS `CriadoPorNome`,'
            . ' TRIM(CONCAT(COALESCE(ued_%2$s.`nome`, \'\'), \' \', COALESCE(ued_%2$s.`sobrenome`, \'\'))) AS `UltimoEditorNome`',
            $a,
            $a
        );
    }

    /**
     * Quando `criado_por` não está no banco, usa o alocado tipo `all` (quem cadastrou) ou o primeiro nome disponível.
     *
     * @param array<string, mixed> $row
     */
    private static function resolverNomeCriadorFallback(array $row): string
    {
        $alocados = $row['Alocados'] ?? [];
        if (!is_array($alocados)) {
            return '';
        }

        foreach (['all', 'rule'] as $tipoPreferido) {
            foreach ($alocados as $alocado) {
                if (!is_array($alocado)) {
                    continue;
                }
                if (trim((string) ($alocado['tipo'] ?? '')) !== $tipoPreferido) {
                    continue;
                }
                $nome = trim((string) ($alocado['nome_completo'] ?? ''));
                if ($nome !== '') {
                    return $nome;
                }
            }
        }

        foreach ($alocados as $alocado) {
            if (!is_array($alocado)) {
                continue;
            }
            $nome = trim((string) ($alocado['nome_completo'] ?? ''));
            if ($nome !== '') {
                return $nome;
            }
        }

        return '';
    }

    private static function buscarNomeUsuarioPorId(PDO $connection, string $usuarioId): string
    {
        $usuarioId = trim($usuarioId);
        if ($usuarioId === '') {
            return '';
        }

        static $cache = [];
        if (array_key_exists($usuarioId, $cache)) {
            return $cache[$usuarioId];
        }

        $coll = self::COLLATION;
        $statement = $connection->prepare(
            'SELECT TRIM(CONCAT(COALESCE(`nome`, \'\'), \' \', COALESCE(`sobrenome`, \'\'))) AS `nome_completo`'
            . ' FROM `usuarios` WHERE `id_usuario` COLLATE ' . $coll . ' = :id COLLATE ' . $coll . ' LIMIT 1'
        );
        $statement->bindValue(':id', $usuarioId);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $nome = is_array($row) ? trim((string) ($row['nome_completo'] ?? '')) : '';
        $cache[$usuarioId] = $nome;

        return $nome;
    }

    /**
     * @param list<string> $keys
     */
    private static function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param list<string> $terminalStatuses
     * @param list<string> $cancelStatuses
     */
    private static function podeCancelarPadrao(string $status, array $terminalStatuses, array $cancelStatuses, string $cancelEndpoint): bool
    {
        if ($cancelEndpoint === '') {
            return false;
        }
        if ($status === '' || in_array($status, $cancelStatuses, true)) {
            return false;
        }

        return !in_array($status, $terminalStatuses, true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPayload(): array
    {
        return [
            'criador_nome' => '',
            'user_prefix' => 'Cadastrado por',
            'data_cadastro_texto' => '',
            'data_evento_classe' => '',
            'data_evento_texto' => '',
            'id' => '',
            'show_edit' => false,
            'edit_href' => '',
            'show_cancel' => false,
            'cancel_endpoint' => '',
            'cancel_toast_key' => '',
            'cancel_label' => 'Cancelar registro',
            'edit_label' => 'Editar registro',
        ];
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

    private static function isSubtarefaStatusConcluida(string $status): bool
    {
        $normalizado = strtolower(trim($status));

        return $normalizado === 'concluida' || $normalizado === 'concluída';
    }

    /**
     * Data/hora curta para histórico v2 (ex.: 19.05 - 12:00).
     */
    public static function formatarDataHoraCurtaV2(?string $mysqlDt): string
    {
        $raw = trim((string) ($mysqlDt ?? ''));
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }

        return date('d.m', $ts) . ' - ' . date('H:i', $ts);
    }

    /**
     * Linhas de histórico no formato do card v2 (listagem cartas expedidas teste).
     *
     * @return list<array{data_hora: string, rotulo: string, autor: string, content_class: string}>
     */
    public static function linhasHistoricoV2CartasExpedidas(array $row, ?PDO $connection = null): array
    {
        require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';

        $linhas = [];
        $createdRaw = trim(self::pick($row, ['Data', 'created_at']));
        $meta = self::fromListRow($row, 'cartas_expedidas', null, $connection);
        $criador = self::compactarNome(trim((string) ($meta['criador_nome'] ?? '')));
        if ($createdRaw !== '') {
            $linhas[] = [
                'data_hora' => self::formatarDataHoraCurtaV2($createdRaw),
                'rotulo' => 'Cadastro',
                'autor' => $criador,
                'content_class' => '',
            ];
        }

        $status = strtolower(trim(self::pick($row, ['Status', 'status'])));
        $editor = self::compactarNome(trim(self::pick($row, ['UltimoEditorNome', 'AtualizadoPorNome'])));
        $updatedRaw = trim(self::pick($row, ['DataAtualizacao', 'updated_at']));

        if ($status === 'cancelada') {
            $ref = $updatedRaw !== '' ? $updatedRaw : $createdRaw;
            if ($ref !== '') {
                $linhas[] = [
                    'data_hora' => self::formatarDataHoraCurtaV2($ref),
                    'rotulo' => 'Cancelamento',
                    'autor' => $editor !== '' ? $editor : $criador,
                    'content_class' => 'text-canceled',
                ];
            }

            return $linhas;
        }

        /** @var list<array<string, mixed>> $allSt */
        $allSt = is_array($row['SubtarefasAll'] ?? null) ? $row['SubtarefasAll'] : [];

        // "Envio" usa exclusivamente a subtarefa de ENVIO concluída (primeira após o
        // cadastro). A subtarefa de DEVOLUÇÃO concluída alimenta a linha "Juntada".
        $envioConcluida = null;
        $devolucaoConcluida = null;
        foreach ($allSt as $subtarefa) {
            if (!is_array($subtarefa)) {
                continue;
            }
            $tipo = trim((string) ($subtarefa['tipo'] ?? ''));
            $stStatus = strtolower(trim((string) ($subtarefa['status'] ?? '')));
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_ENVIO && self::isSubtarefaStatusConcluida($stStatus)) {
                $envioConcluida = $subtarefa;
            }
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO && self::isSubtarefaStatusConcluida($stStatus)) {
                $devolucaoConcluida = $subtarefa;
            }
        }

        if ($envioConcluida !== null) {
            $dataAtualizacao = trim((string) ($envioConcluida['updated_at'] ?? ''));
            $autorAtualizacao = '';
            if ($dataAtualizacao !== '') {
                require_once __DIR__ . '/CartorioTimelineHelper.php';
                $usuarioNomesPorId = $connection instanceof PDO
                    ? cartorioTimelineCollectUsuarioNomesPorId($connection, $allSt)
                    : [];
                $dados = cartorioTimelineDecodeData($envioConcluida);
                $autorAtualizacao = cartorioTimelineResolveUser($dados, $usuarioNomesPorId);
                if ($autorAtualizacao === '—') {
                    $autorAtualizacao = '';
                }
                $autorAtualizacao = self::compactarNome($autorAtualizacao);
                if ($autorAtualizacao === '') {
                    $autorAtualizacao = $editor !== '' ? $editor : $criador;
                }
                $linhas[] = [
                    'data_hora' => self::formatarDataHoraCurtaV2($dataAtualizacao),
                    'rotulo' => 'Envio',
                    'autor' => $autorAtualizacao,
                    'content_class' => '',
                ];
            }
        }

        if (in_array($status, ['concluída', 'concluida', 'finalizada'], true) && $devolucaoConcluida !== null) {
            $dataJuntada = trim((string) ($devolucaoConcluida['updated_at'] ?? ''));
            if ($dataJuntada !== '') {
                require_once __DIR__ . '/CartorioTimelineHelper.php';
                $usuarioNomesPorId = $connection instanceof PDO
                    ? cartorioTimelineCollectUsuarioNomesPorId($connection, $allSt)
                    : [];
                $dados = cartorioTimelineDecodeData($devolucaoConcluida);
                $autorJuntada = cartorioTimelineResolveUser($dados, $usuarioNomesPorId);
                if ($autorJuntada === '—') {
                    $autorJuntada = '';
                }
                $autorJuntada = self::compactarNome($autorJuntada);
                if ($autorJuntada === '') {
                    $autorJuntada = $editor !== '' ? $editor : $criador;
                }
                $linhas[] = [
                    'data_hora' => self::formatarDataHoraCurtaV2($dataJuntada),
                    'rotulo' => 'Juntada',
                    'autor' => $autorJuntada,
                    'content_class' => '',
                ];
            }
        }

        return $linhas;
    }
}
