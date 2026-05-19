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
        'cejusc' => [
            'id_keys' => ['IdCarta', 'IdCejusc', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'cejuscedit',
            'cancel_endpoint' => '/api/cartorio-cejusc/cancel.php',
            'cancel_toast_key' => 'cartorio.cejusc.cancel.success',
            'cancel_label' => 'Cancelar tarefa CEJUSC',
        ],
        'ceapa' => [
            'id_keys' => ['IdCeapa', 'id'],
            'status_keys' => ['StatusCumprimento', 'Status', 'status'],
            'data_keys' => ['DataCadastro', 'Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'ceapaedit',
            'cancel_endpoint' => '/api/cartorio-ceapa/cancel.php',
            'cancel_toast_key' => 'cartorio.ceapa.cancel.success',
            'cancel_label' => 'Cancelar tarefa CEAPA',
            'cancel_statuses' => ['cancelado'],
            'terminal_extra' => ['finalizada'],
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
        'medidas_protetivas' => [
            'id_keys' => ['IdMedida', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'movement_from_subtarefa' => true,
            'edit_page' => 'medidasprotetivasedit',
            'cancel_endpoint' => '/api/cartorio-medidas-protetivas/cancel.php',
            'cancel_toast_key' => 'cartorio.medidas_protetivas.cancel.success',
            'cancel_label' => 'Cancelar medida protetiva',
        ],
        'gabinete_monitoramento' => [
            'id_keys' => ['IdMon', 'id'],
            'status_keys' => ['Status', 'status'],
            'data_keys' => ['Data', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'monitoramentogabineteedit',
            'cancel_endpoint' => '/api/gabinete-monitoramento-eletronico/cancel.php',
            'cancel_toast_key' => 'gabinete.monitoramento.cancel.success',
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
        'sala_midias' => [
            'id_keys' => ['id', 'IdMidia'],
            'status_keys' => ['status', 'Status'],
            'data_keys' => ['Data', 'criado_em', 'created_at'],
            'updated_keys' => ['DataAtualizacao', 'atualizado_em', 'updated_at'],
            'criador_keys' => ['CriadoPorNome'],
            'edit_page' => 'salamidiasedit',
            'cancel_endpoint' => '',
            'cancel_toast_key' => '',
            'cancel_label' => '',
            'show_cancel' => false,
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
            if ($ultimaSubtarefa !== null
                && ($createdRaw === '' || self::isUpdatedAfterCreated($createdRaw, $ultimaSubtarefa['data']))) {
                $dataEventoClasse = 'updated';
                $dataEventoTexto = self::formatarEventoComAutor(
                    'Atualizado em',
                    $ultimaSubtarefa['data'],
                    $ultimaSubtarefa['editor']
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
     * Metadados da listagem de atas: cadastro automático pelo sistema; atualização por upload de PDF ou subtarefa de mídias.
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
                'data' => trim(self::pick($row, ['DataUltimaSubtarefa'])),
                'editor' => trim(self::pick($row, ['UltimaSubtarefaPorNome'])),
            ],
            [
                'data' => trim(self::pick($row, ['DataAtualizacaoMidias', 'atualizado_em'])),
                'editor' => trim(self::pick($row, ['UltimoEditorMidiasNome', 'UltimoEditorNome'])),
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
            $status = strtolower(trim((string) ($subtarefa['status'] ?? '')));
            if ($status !== 'concluida') {
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

    /** Campos extras para listagem de atas (pauta + upload + subtarefa de mídias). */
    public static function sqlSelectSalaAtasListagemExtra(): string
    {
        $coll = self::COLLATION;

        return sprintf(
            ', COALESCE(a.`data_hora_fim_efetivo`, a.`data_hora_inicio_efetivo`, a.`updated_at`, a.`created_at`, a.`data_hora_designacao`) AS `DataCadastroSistema`,'
            . ' ata.`uploaded_at` AS `DataUploadAta`,'
            . ' TRIM(CONCAT(COALESCE(ucr_up.`nome`, \'\'), \' \', COALESCE(ucr_up.`sobrenome`, \'\'))) AS `UploadPorNome`,'
            . ' a.`updated_at` AS `DataAtualizacaoPauta`,'
            . ' TRIM(CONCAT(COALESCE(ued_a.`nome`, \'\'), \' \', COALESCE(ued_a.`sobrenome`, \'\'))) AS `UltimoEditorPautaNome`,'
            . ' mt.`atualizado_em` AS `DataAtualizacaoMidias`,'
            . ' TRIM(CONCAT(COALESCE(ued_mt.`nome`, \'\'), \' \', COALESCE(ued_mt.`sobrenome`, \'\'))) AS `UltimoEditorMidiasNome`,'
            . ' (SELECT st.`updated_at` FROM `sala_midias_subtarefa` st'
            . ' INNER JOIN `sala_midias_tarefa` mt_sub ON mt_sub.`id` = st.`midia_id` AND mt_sub.`pauta_id` = a.`id_pauta` AND mt_sub.`ativo` = 1'
            . ' WHERE TRIM(COALESCE(st.`finalizado_por`, \'\')) <> \'\''
            . ' ORDER BY st.`updated_at` DESC LIMIT 1) AS `DataUltimaSubtarefa`,'
            . ' (SELECT TRIM(CONCAT(COALESCE(u_st.`nome`, \'\'), \' \', COALESCE(u_st.`sobrenome`, \'\')))'
            . ' FROM `sala_midias_subtarefa` st'
            . ' INNER JOIN `sala_midias_tarefa` mt_sub ON mt_sub.`id` = st.`midia_id` AND mt_sub.`pauta_id` = a.`id_pauta` AND mt_sub.`ativo` = 1'
            . ' LEFT JOIN `usuarios` u_st ON u_st.`id_usuario` COLLATE %1$s = st.`finalizado_por` COLLATE %1$s'
            . ' WHERE TRIM(COALESCE(st.`finalizado_por`, \'\')) <> \'\''
            . ' ORDER BY st.`updated_at` DESC LIMIT 1) AS `UltimaSubtarefaPorNome`',
            $coll
        );
    }

    /** JOINs de auditoria da pauta e da tarefa de mídias vinculada (listagem de atas). */
    public static function sqlJoinSalaAtasListagem(): string
    {
        $coll = self::COLLATION;

        return self::sqlUsuarioAuditoria('a')
            . sprintf(
                ' LEFT JOIN `sala_midias_tarefa` mt ON mt.`pauta_id` = a.`id_pauta` AND mt.`ativo` = 1'
                . ' LEFT JOIN `usuarios` ued_mt ON ued_mt.`id_usuario` COLLATE %1$s = mt.`atualizado_por` COLLATE %1$s',
                $coll
            );
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
     * Linha de evento (atualização/cancelamento) com data/hora e autor compacto.
     */
    public static function formatarEventoComAutor(string $rotuloEvento, string $dataRaw, string $editorNome): string
    {
        $rotulo = trim($rotuloEvento);
        if ($rotulo === '' || trim($dataRaw) === '') {
            return '';
        }
        $texto = $rotulo . ' ' . self::formatarDataHora($dataRaw);
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
            ', %1$s.`updated_at` AS `DataAtualizacao`,'
            . ' TRIM(CONCAT(COALESCE(ucr_%2$s.`nome`, \'\'), \' \', COALESCE(ucr_%2$s.`sobrenome`, \'\'))) AS `CriadoPorNome`,'
            . ' TRIM(CONCAT(COALESCE(ued_%2$s.`nome`, \'\'), \' \', COALESCE(ued_%2$s.`sobrenome`, \'\'))) AS `UltimoEditorNome`',
            $a,
            $a
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
}
