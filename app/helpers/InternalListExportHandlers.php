<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/FeriadosService.php';
require_once __DIR__ . '/../services/UsuariosService.php';
require_once __DIR__ . '/../services/GuiasService.php';
require_once __DIR__ . '/../services/EditaisService.php';
require_once __DIR__ . '/../services/OficiosCartorioService.php';
require_once __DIR__ . '/../services/OficiosGabineteService.php';
require_once __DIR__ . '/../services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/../services/GabineteReusPresosService.php';
require_once __DIR__ . '/../services/CartasExpedidasService.php';
require_once __DIR__ . '/../services/CartasRecebidasService.php';
require_once __DIR__ . '/../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../services/PessoasService.php';
require_once __DIR__ . '/../services/ConsultaProcessosService.php';
require_once __DIR__ . '/../services/InstituicoesPublicasService.php';
require_once __DIR__ . '/../repositories/SalaAudienciasAtaRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/ListExportPrincipalStatusTitle.php';

final class InternalListExportHandlers
{
    private const MAX_ROWS = 5000;
    private const CHUNK_200 = 200;

    /**
     * @param array<string, mixed> $get
     */
    public static function dispatch(string $listPage, PDO $pdo, string $userId, array $get): array
    {
        $map = self::handlersMap();

        if (!isset($map[$listPage])) {
            throw new InvalidArgumentException('Página de lista não suportada para exportação.');
        }

        return $map[$listPage]($pdo, $userId, $get);
    }

    /**
     * @return array<string, callable(PDO,string,array):array>
     */
    private static function handlersMap(): array
    {
        return [
            'feriadoslist' => [self::class, 'feriados'],
            'usuarioslist' => [self::class, 'usuarios'],
            'guias' => [self::class, 'guias'],
            'editais' => [self::class, 'editais'],
            'oficioscartorio' => [self::class, 'oficiosCartorio'],
            'oficiosgabinete' => [self::class, 'oficiosGabinete'],
            'monitoramentogabinete' => [self::class, 'monitoramentoGabinete'],
            'reuspresos' => [self::class, 'reusPresos'],
            'cartasexpedidas' => [self::class, 'cartasExpedidas'],
            'cartasrecebidas' => [self::class, 'cartasRecebidas'],
            'salapauta' => [self::class, 'salaPauta'],
            'salapainel' => [self::class, 'salaPauta'],
            'estudapauta' => [self::class, 'salaPautaEstudantes'],
            'salaata' => [self::class, 'salaAtas'],
            'consultapartes' => [self::class, 'consultaPartes'],
            'consultaprocessos' => [self::class, 'consultaProcessos'],
            'instituicoespublicas' => [self::class, 'instituicoesPublicas'],
            'processosparteslist' => [self::class, 'processosPartes'],
            'partesinstituicoeslist' => [self::class, 'partesInstituicoes'],
        ];
    }

    /**
     * Coluna de exportação: mesmo texto do title do status da tarefa principal na lista (ver views).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function withExportPrincipalStatusTitle(array $rows, string $module): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i]['StatusTituloPrincipal'] = ListExportPrincipalStatusTitle::forRow($module, $row);
        }

        return $rows;
    }

    private static function formatExportDateTime(?string $mysql): string
    {
        if ($mysql === null || $mysql === '') {
            return '';
        }
        $t = strtotime($mysql);

        return $t !== false ? date('d/m/Y H:i', $t) : $mysql;
    }

    /** Mesmo padrão visual das listas: "Cidade - UF". */
    private static function formatExportComarcaCidadeUf(string $cidade, string $uf): string
    {
        $c = trim($cidade);
        $u = trim($uf);
        if ($c === '' && $u === '') {
            return '';
        }
        if ($c === '') {
            return $u;
        }
        if ($u === '') {
            return $c;
        }

        return $c . ' - ' . $u;
    }

    /**
     * Alinhado à lista de editais: data final do decurso (d/m/Y) e complemento (dias restantes, vence hoje ou decorrido).
     *
     * @param array<string, mixed> $decursoInfo
     */
    private static function formatExportEditalDecursoResumo(array $decursoInfo): string
    {
        $dataFmt = trim((string) ($decursoInfo['data_limite_formatada'] ?? ''));
        if ($dataFmt === '') {
            $dataLimite = trim((string) ($decursoInfo['data_limite_decurso'] ?? ''));
            if ($dataLimite !== '') {
                $t = strtotime($dataLimite);
                $dataFmt = $t !== false ? date('d/m/Y', $t) : $dataLimite;
            }
        }
        if ($dataFmt === '') {
            return '—';
        }

        $libera = (bool) ($decursoInfo['libera_conclusao'] ?? false);
        $dias = (int) ($decursoInfo['dias_restantes'] ?? 0);
        $prazoCumprido = (bool) ($decursoInfo['prazo_cumprido'] ?? false);

        if ($libera) {
            return $dataFmt . ' (prazo decorrido)';
        }
        if ($prazoCumprido && !$libera) {
            return $dataFmt . ' (vence hoje)';
        }
        if ($dias > 0) {
            $suf = $dias === 1 ? '1 dia restante' : $dias . ' dias restantes';

            return $dataFmt . ' (' . $suf . ')';
        }

        return $dataFmt;
    }

    private static function labelFormaCumprimentoCartorio(string $v): string
    {
        return match ($v) {
            'ccm' => 'Oficial de Justiça (CCM)',
            'cartorio' => 'Pelo cartório',
            default => $v !== '' ? $v : '—',
        };
    }

    private static function labelMeioEnvioCartorio(string $v): string
    {
        return match ($v) {
            'email' => 'E-mail',
            'malote_digital' => 'Malote digital',
            'pessoalmente' => 'Pessoalmente',
            default => $v !== '' ? $v : '—',
        };
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function feriados(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'q' => trim((string) ($get['q'] ?? '')),
        ];
        $tipo = trim((string) ($get['tipo_feriado'] ?? ''));
        if ($tipo !== '') {
            $filters['tipo_feriado'] = $tipo;
        }
        $service = new FeriadosService($pdo);
        $rows = $service->list($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'data_inicio' => $r['data_inicio'] ?? '',
                'data_fim' => $r['data_fim'] ?? '',
                'tipo_feriado' => $r['tipo_feriado'] ?? '',
                'descricao' => $r['descricao'] ?? '',
            ];
        }

        return [
            'title' => 'Lista de feriados',
            'slug' => 'feriados',
            'columns' => [
                ['key' => 'data_inicio', 'label' => 'Data início'],
                ['key' => 'data_fim', 'label' => 'Data fim'],
                ['key' => 'tipo_feriado', 'label' => 'Tipo'],
                ['key' => 'descricao', 'label' => 'Descrição'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function usuarios(PDO $pdo, string $userId, array $get): array
    {
        $filters = [];
        $t = trim((string) ($get['tipo'] ?? ''));
        if ($t !== '') {
            $filters['tipo'] = $t;
        }
        $p = trim((string) ($get['perfil'] ?? ''));
        if ($p !== '') {
            $filters['perfil'] = $p;
        }
        $service = new UsuariosService($pdo);
        $rows = $service->list($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $uaRaw = trim((string) ($r['ultimo_acesso'] ?? ''));
            $ultimoAcessoLabel = '—';
            if ($uaRaw !== '') {
                $ts = strtotime($uaRaw);
                $ultimoAcessoLabel = $ts !== false ? date('d/m/Y H:i', $ts) : '—';
            }
            $out[] = [
                'nome_completo' => $r['nome_completo'] ?? '',
                'tipo' => ($r['tipo'] ?? '') === 'externo' ? 'Externo' : 'Interno',
                'setores' => str_replace('AUDIENCIA', 'Sala de Audiências', trim((string) ($r['setores'] ?? ''))),
                'cargo' => $r['cargo'] ?? '',
                'ultimo_acesso' => $ultimoAcessoLabel,
            ];
        }

        return [
            'title' => 'Lista de usuários',
            'slug' => 'usuarios',
            'columns' => [
                ['key' => 'nome_completo', 'label' => 'Nome'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'setores', 'label' => 'Setor'],
                ['key' => 'cargo', 'label' => 'Cargo'],
                ['key' => 'ultimo_acesso', 'label' => 'Último acesso'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function guias(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'tipo' => trim((string) ($get['tipo'] ?? '')),
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new GuiasService($pdo);
        $rows = self::withExportPrincipalStatusTitle(
            $service->listGuias($filters, self::MAX_ROWS, 0)['rows'] ?? [],
            ListExportPrincipalStatusTitle::MODULE_GUIAS
        );

        return [
            'title' => 'Lista de guias',
            'slug' => 'guias',
            'columns' => [
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'Tipo', 'label' => 'Tipo'],
                ['key' => 'Denunciado', 'label' => 'Denunciado'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function editais(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new EditaisService($pdo);
        $rows = self::withExportPrincipalStatusTitle(
            $service->listEditais($filters, self::MAX_ROWS, 0)['rows'] ?? [],
            ListExportPrincipalStatusTitle::MODULE_EDITAIS
        );
        foreach ($rows as $i => $row) {
            $info = $row['DecursoInfo'] ?? [];
            $rows[$i]['DecursoExport'] = is_array($info)
                ? self::formatExportEditalDecursoResumo($info)
                : '—';
        }

        return [
            'title' => 'Lista de editais',
            'slug' => 'editais',
            'columns' => [
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'TipoEdital', 'label' => 'Tipo de edital'],
                ['key' => 'DecursoExport', 'label' => 'Data final do prazo / dias restantes'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function resolveAnoOficios(array $anos, array $get): string
    {
        $anoFromGet = trim((string) ($get['ano'] ?? ''));
        if ($anoFromGet !== '' && ctype_digit($anoFromGet) && in_array((int) $anoFromGet, $anos, true)) {
            return $anoFromGet;
        }
        $anoAtual = (int) date('Y');
        if ($anos !== []) {
            return in_array($anoAtual, $anos, true) ? (string) $anoAtual : (string) $anos[0];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function oficiosCartorio(PDO $pdo, string $userId, array $get): array
    {
        $service = new OficiosCartorioService($pdo);
        $anos = $service->listAnosComOficios();
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'oficio' => trim((string) ($get['oficio'] ?? '')),
            'ano' => self::resolveAnoOficios($anos, $get),
        ];
        $rows = $service->listOficios($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'OficioNumero' => ((int) ($r['NumeroSeq'] ?? 0)) . '/' . ((int) ($r['Ano'] ?? 0)),
                'Processo' => (string) ($r['Processo'] ?? ''),
                'TipoOficio' => (string) ($r['TipoOficio'] ?? ''),
                'GeradoEm' => self::formatExportDateTime(isset($r['Data']) ? (string) $r['Data'] : null),
                'FormaCumprimento' => self::labelFormaCumprimentoCartorio((string) ($r['FormaCumprimento'] ?? '')),
                'MeioEnvio' => self::labelMeioEnvioCartorio((string) ($r['MeioEnvio'] ?? '')),
                'CriadoPor' => trim((string) ($r['CriadoPorNome'] ?? '')),
                'Alocados' => $r['Alocados'] ?? [],
                'StatusTituloPrincipal' => ListExportPrincipalStatusTitle::forRow(
                    ListExportPrincipalStatusTitle::MODULE_OFICIOS_CARTORIO,
                    $r
                ),
            ];
        }

        return [
            'title' => 'Lista de ofícios (cartório)',
            'slug' => 'oficios-cartorio',
            'columns' => [
                ['key' => 'OficioNumero', 'label' => 'Ofício'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'TipoOficio', 'label' => 'Tipo de ofício'],
                ['key' => 'GeradoEm', 'label' => 'Gerado em'],
                ['key' => 'FormaCumprimento', 'label' => 'Forma de cumprimento'],
                ['key' => 'MeioEnvio', 'label' => 'Meio de envio'],
                ['key' => 'CriadoPor', 'label' => 'Registrado por'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function oficiosGabinete(PDO $pdo, string $userId, array $get): array
    {
        $service = new OficiosGabineteService($pdo);
        $anos = $service->listAnosComOficios();
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'oficio' => trim((string) ($get['oficio'] ?? '')),
            'ano' => self::resolveAnoOficios($anos, $get),
        ];
        $rows = $service->listOficios($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'OficioNumero' => ((int) ($r['NumeroSeq'] ?? 0)) . '/' . ((int) ($r['Ano'] ?? 0)),
                'Processo' => (string) ($r['Processo'] ?? ''),
                'TipoOficio' => (string) ($r['TipoOficio'] ?? ''),
                'GeradoEm' => self::formatExportDateTime(isset($r['Data']) ? (string) $r['Data'] : null),
                'CriadoPor' => trim((string) ($r['CriadoPorNome'] ?? '')),
            ];
        }

        return [
            'title' => 'Lista de ofícios (gabinete)',
            'slug' => 'oficios-gabinete',
            'columns' => [
                ['key' => 'OficioNumero', 'label' => 'Ofício'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'TipoOficio', 'label' => 'Tipo de ofício'],
                ['key' => 'GeradoEm', 'label' => 'Gerado em'],
                ['key' => 'CriadoPor', 'label' => 'Registrado por'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function monitoramentoGabinete(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new GabineteMonitoramentoEletronicoService($pdo);
        $rows = self::withExportPrincipalStatusTitle(
            $service->listMonitoramentos($filters, self::MAX_ROWS, 0)['rows'] ?? [],
            ListExportPrincipalStatusTitle::MODULE_MONITORAMENTO_GABINETE
        );

        return [
            'title' => 'Lista de monitoramentos eletrônicos',
            'slug' => 'monitoramento-eletronico',
            'columns' => [
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'DataInicio', 'label' => 'Início'],
                ['key' => 'DuracaoLabel', 'label' => 'Duração'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function reusPresos(PDO $pdo, string $userId, array $get): array
    {
        $q = trim((string) ($get['q'] ?? ''));
        $filters = $q !== ''
            ? ['q' => $q]
            : [
                'nome' => trim((string) ($get['nome'] ?? '')),
                'processo' => trim((string) ($get['processo'] ?? '')),
            ];
        $service = new GabineteReusPresosService($pdo);
        $rows = $service->listAtivos($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'reu_nome' => $r['nome'] ?? '',
                'processo_principal' => $r['ProcessoPrincipalLabel'] ?? $r['processo_principal_numero'] ?? '',
                'data_prisao' => $r['DataPrisao'] ?? '',
                'tempo_custodia' => $r['TempoCustodiaLabel'] ?? '',
                'proxima_revisao' => $r['proxima_revisao'] ?? '',
                'revisao_hint' => $r['RevisaoDiasHint'] ?? '',
                'subtarefas_processos' => $r['SubtarefaProcessos'] ?? [],
            ];
        }

        return [
            'title' => 'Lista de réus presos',
            'slug' => 'reus-presos',
            'columns' => [
                ['key' => 'reu_nome', 'label' => 'Nome'],
                ['key' => 'processo_principal', 'label' => 'Processo principal'],
                ['key' => 'data_prisao', 'label' => 'Data prisão'],
                ['key' => 'tempo_custodia', 'label' => 'Tempo custódia'],
                ['key' => 'proxima_revisao', 'label' => 'Próxima revisão'],
                ['key' => 'revisao_hint', 'label' => 'Prazo revisão'],
                ['key' => 'subtarefas_processos', 'label' => 'Processos vinculados'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function cartasExpedidas(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'finalidade' => trim((string) ($get['finalidade'] ?? '')),
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new CartasExpedidasService($pdo);
        $rows = self::withExportPrincipalStatusTitle(
            $service->listCartas($filters, self::MAX_ROWS, 0)['rows'] ?? [],
            ListExportPrincipalStatusTitle::MODULE_CARTAS_EXPEDIDAS
        );
        foreach ($rows as $i => $row) {
            $rows[$i]['ComarcaDestino'] = self::formatExportComarcaCidadeUf(
                (string) ($row['Comarca'] ?? ''),
                (string) ($row['Estado'] ?? '')
            );
        }

        return [
            'title' => 'Lista de cartas expedidas',
            'slug' => 'cartas-expedidas',
            'columns' => [
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'Finalidade', 'label' => 'Finalidade'],
                ['key' => 'ComarcaDestino', 'label' => 'Comarca de destino'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function cartasRecebidas(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'finalidade' => trim((string) ($get['finalidade'] ?? '')),
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new CartasRecebidasService($pdo);
        $rows = self::withExportPrincipalStatusTitle(
            $service->listCartas($filters, self::MAX_ROWS, 0)['rows'] ?? [],
            ListExportPrincipalStatusTitle::MODULE_CARTAS_RECEBIDAS
        );
        foreach ($rows as $i => $row) {
            $rows[$i]['ComarcaOrigem'] = self::formatExportComarcaCidadeUf(
                (string) ($row['Comarca'] ?? ''),
                (string) ($row['Estado'] ?? '')
            );
        }

        return [
            'title' => 'Lista de cartas recebidas',
            'slug' => 'cartas-recebidas',
            'columns' => [
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'Finalidade', 'label' => 'Finalidade'],
                ['key' => 'ComarcaOrigem', 'label' => 'Comarca de origem'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'StatusTituloPrincipal', 'label' => 'Situação da tarefa'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function salaPauta(PDO $pdo, string $userId, array $get): array
    {
        $statusFilter = trim((string) ($get['status'] ?? ''));
        if ($statusFilter === 'agendada') {
            $statusFilter = 'designada';
        }
        if ($statusFilter === 'adiada') {
            $statusFilter = 'redesignada';
        }
        $filters = [
            'status' => $statusFilter,
            'periodo' => trim((string) ($get['periodo'] ?? '')),
            'tipo' => trim((string) ($get['tipo'] ?? '')),
            'search' => trim((string) ($get['search'] ?? '')),
        ];
        $service = new SalaAudienciasPautaService($pdo);
        $rows = $service->list($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $parts = $r['partes_cumprimento'] ?? [];
            $out[] = [
                'data_audiencia' => $r['data_audiencia'] ?? '',
                'hora_audiencia' => $r['hora_audiencia'] ?? '',
                'numero_processo' => $r['numero_processo'] ?? '',
                'tipo_audiencia' => $r['tipo_audiencia'] ?? '',
                'localizacao' => $r['localizacao'] ?? '',
                'partes_cumprimento' => is_array($parts) ? $parts : [],
            ];
        }

        return [
            'title' => 'Pauta de audiências',
            'slug' => 'pauta-audiencias',
            'columns' => [
                ['key' => 'data_audiencia', 'label' => 'Data'],
                ['key' => 'hora_audiencia', 'label' => 'Hora'],
                ['key' => 'numero_processo', 'label' => 'Processo'],
                ['key' => 'tipo_audiencia', 'label' => 'Tipo'],
                ['key' => 'localizacao', 'label' => 'Local'],
                ['key' => 'partes_cumprimento', 'label' => 'Partes'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * Exportação restrita da pauta para estudantes: mesmas colunas visíveis no
     * read-only de `sala-audiencias-pauta-list-estudantes.php`, com mascaramento
     * do número CNJ em processos sigilosos e sem o detalhamento de partes.
     *
     * @param array<string, mixed> $get
     */
    private static function salaPautaEstudantes(PDO $pdo, string $userId, array $get): array
    {
        require_once __DIR__ . '/ProcessoBlocoListagemHelper.php';

        $statusFilter = trim((string) ($get['status'] ?? ''));
        if ($statusFilter === 'agendada') {
            $statusFilter = 'designada';
        }
        if ($statusFilter === 'adiada') {
            $statusFilter = 'redesignada';
        }
        $filters = [
            'status' => $statusFilter,
            'periodo' => trim((string) ($get['periodo'] ?? '')),
            'tipo' => trim((string) ($get['tipo'] ?? '')),
            'search' => trim((string) ($get['search'] ?? '')),
        ];
        $service = new SalaAudienciasPautaService($pdo);
        $rows = $service->list($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $numero = (string) ($r['numero_processo'] ?? '');
            if ($numero !== '' && ProcessoBlocoListagemHelper::deveMascararNumeroNaVarianteEstudantes($r)) {
                $numero = ProcessoBlocoListagemHelper::mascararNumeroProcessoSigiloso($numero);
            }
            $out[] = [
                'data_audiencia' => $r['data_audiencia'] ?? '',
                'hora_audiencia' => $r['hora_audiencia'] ?? '',
                'numero_processo' => $numero,
                'tipo_audiencia' => $r['tipo_audiencia'] ?? '',
                'localizacao' => $r['localizacao'] ?? '',
            ];
        }

        return [
            'title' => 'Pauta de audiências (estudantes)',
            'slug' => 'pauta-audiencias-estudantes',
            'columns' => [
                ['key' => 'data_audiencia', 'label' => 'Data'],
                ['key' => 'hora_audiencia', 'label' => 'Hora'],
                ['key' => 'numero_processo', 'label' => 'Processo'],
                ['key' => 'tipo_audiencia', 'label' => 'Tipo'],
                ['key' => 'localizacao', 'label' => 'Local'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function salaAtas(PDO $pdo, string $userId, array $get): array
    {
        $repo = new SalaAudienciasAtaRepository($pdo);
        $rows = $repo->listRealizadasComAta(self::MAX_ROWS, 0);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'data_audiencia' => $r['data_audiencia'] ?? '',
                'hora_audiencia' => $r['hora_audiencia'] ?? '',
                'numero_processo' => $r['numero_processo'] ?? '',
                'tipo_audiencia' => $r['tipo_audiencia'] ?? '',
                'localizacao' => $r['localizacao'] ?? '',
                'arquivo_relativo' => $r['arquivo_relativo'] ?? '',
                'uploaded_at' => $r['uploaded_at'] ?? '',
            ];
        }

        return [
            'title' => 'Lista das atas de audiências',
            'slug' => 'atas-audiencias',
            'columns' => [
                ['key' => 'data_audiencia', 'label' => 'Data'],
                ['key' => 'hora_audiencia', 'label' => 'Hora'],
                ['key' => 'numero_processo', 'label' => 'Processo'],
                ['key' => 'tipo_audiencia', 'label' => 'Tipo'],
                ['key' => 'localizacao', 'label' => 'Local'],
                ['key' => 'arquivo_relativo', 'label' => 'Arquivo'],
                ['key' => 'uploaded_at', 'label' => 'Enviado em'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function consultaPartes(PDO $pdo, string $userId, array $get): array
    {
        $nome = trim((string) ($get['nome'] ?? ''));
        $cpf = trim((string) ($get['cpf'] ?? ''));
        $inst = trim((string) ($get['instituicao_id'] ?? ''));
        if ($nome === '' && $cpf === '' && $inst === '') {
            return [
                'title' => 'Lista da consulta de partes',
                'slug' => 'consulta-partes',
                'columns' => [
                    ['key' => 'msg', 'label' => 'Mensagem'],
                ],
                'rows' => [['msg' => 'Aplique filtros na lista e exporte novamente.']],
            ];
        }
        $filters = ['nome' => $nome, 'cpf' => $cpf, 'instituicao_id' => $inst];
        $service = new PessoasService($pdo);
        $rows = $service->list($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $telefonesRepo = new PartesTelefonesRepository($pdo);
        $vinculosInstRepo = new PartesInstituicoesRepository($pdo);
        $outRows = [];
        foreach ($rows as $row) {
            $parteId = (string) ($row['id'] ?? $row['ID'] ?? '');
            if ($parteId !== '') {
                $row['telefones'] = $telefonesRepo->findByParteId($parteId);
                $vinculos = $vinculosInstRepo->findByParteId($parteId, true);
                $row['instituicoes'] = array_map(static function (array $v): array {
                    return [
                        'nome' => $v['instituicao_nome'] ?? '',
                        'sigla' => $v['instituicao_sigla'] ?? '',
                        'cargo_funcao' => $v['cargo_funcao'] ?? '',
                    ];
                }, $vinculos);
            } else {
                $row['telefones'] = [];
                $row['instituicoes'] = [];
            }
            $outRows[] = [
                'nome_completo' => $row['nome_completo'] ?? '',
                'cpf' => $row['cpf'] ?? '',
                'telefones' => $row['telefones'],
                'instituicoes' => $row['instituicoes'],
            ];
        }

        return [
            'title' => 'Lista da consulta de partes',
            'slug' => 'consulta-partes',
            'columns' => [
                ['key' => 'nome_completo', 'label' => 'Nome'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'telefones', 'label' => 'Telefones'],
                ['key' => 'instituicoes', 'label' => 'Instituições'],
            ],
            'rows' => $outRows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function consultaProcessos(PDO $pdo, string $userId, array $get): array
    {
        $numero = trim((string) ($get['numero'] ?? ''));
        $parteNome = trim((string) ($get['parte_nome'] ?? ''));
        if ($numero === '' && $parteNome === '') {
            return [
                'title' => 'Lista da consulta de processos',
                'slug' => 'consulta-processos',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Aplique filtros na lista e exporte novamente.']],
            ];
        }
        $service = new ConsultaProcessosService($pdo);
        $rows = $service->list(['numero' => $numero, 'parte_nome' => $parteNome], self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'numero_processo' => $r['numero_processo'] ?? '',
                'total_partes' => $r['total_partes'] ?? '',
            ];
        }

        return [
            'title' => 'Lista da consulta de processos',
            'slug' => 'consulta-processos',
            'columns' => [
                ['key' => 'numero_processo', 'label' => 'Número'],
                ['key' => 'total_partes', 'label' => 'Qtd. partes'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function instituicoesPublicas(PDO $pdo, string $userId, array $get): array
    {
        $nome = trim((string) ($get['nome'] ?? ''));
        if ($nome === '') {
            return [
                'title' => 'Lista de instituições públicas',
                'slug' => 'instituicoes-publicas',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Aplique filtros na lista e exporte novamente.']],
            ];
        }
        $service = new InstituicoesPublicasService($pdo);
        $rows = $service->list(['nome' => $nome], self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'nome' => $r['nome'] ?? '',
                'sigla' => $r['sigla'] ?? '',
            ];
        }

        return [
            'title' => 'Lista de instituições públicas',
            'slug' => 'instituicoes-publicas',
            'columns' => [
                ['key' => 'nome', 'label' => 'Nome'],
                ['key' => 'sigla', 'label' => 'Sigla'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function processosPartes(PDO $pdo, string $userId, array $get): array
    {
        $processoId = trim((string) ($get['processo_id'] ?? ''));
        if ($processoId === '') {
            return [
                'title' => 'Lista de vínculos processo–parte',
                'slug' => 'processo-partes',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Informe o ID do processo e consulte antes de exportar.']],
            ];
        }
        $service = new ConsultaProcessosService($pdo);
        $processo = $service->get($processoId);
        if ($processo === null) {
            return [
                'title' => 'Lista de vínculos processo–parte',
                'slug' => 'processo-partes',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Processo não encontrado.']],
            ];
        }
        $partes = $processo['partes'] ?? [];
        $out = [];
        foreach ($partes as $p) {
            $out[] = [
                'processo' => $processo['numero_processo'] ?? $processoId,
                'nome_completo' => $p['nome_completo'] ?? '',
                'cpf' => $p['cpf'] ?? '',
                'tipo_parte' => $p['tipo_parte'] ?? '',
                'polo' => $p['polo'] ?? '',
            ];
        }

        return [
            'title' => 'Lista de vínculos processo–parte',
            'slug' => 'processo-partes',
            'columns' => [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'nome_completo', 'label' => 'Nome'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'tipo_parte', 'label' => 'Tipo parte'],
                ['key' => 'polo', 'label' => 'Polo'],
            ],
            'rows' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function partesInstituicoes(PDO $pdo, string $userId, array $get): array
    {
        $parteId = trim((string) ($get['parte_id'] ?? ''));
        $instituicaoId = trim((string) ($get['instituicao_id'] ?? ''));
        if ($parteId === '' && $instituicaoId === '') {
            return [
                'title' => 'Lista de vínculos pessoa–instituição',
                'slug' => 'partes-instituicoes',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Informe ID da pessoa ou da instituição e consulte antes de exportar.']],
            ];
        }
        require_once __DIR__ . '/../repositories/PartesRepository.php';

        $repo = new PartesInstituicoesRepository($pdo);
        if ($parteId !== '') {
            $rows = $repo->findByParteId($parteId);
        } else {
            $rows = $repo->findByInstituicaoId($instituicaoId);
        }

        $pessoaNomeCtx = '';
        $pessoaCpfCtx = '';
        if ($parteId !== '') {
            $pRow = (new PartesRepository($pdo))->findById($parteId);
            if ($pRow !== null) {
                $pessoaNomeCtx = trim((string) ($pRow['nome_completo'] ?? ''));
                $pessoaCpfCtx = trim((string) ($pRow['cpf'] ?? ''));
            }
        }

        $instituicaoCtx = '';
        if ($instituicaoId !== '' && $parteId === '') {
            $instSvc = new InstituicoesPublicasService($pdo);
            $iRow = $instSvc->find($instituicaoId);
            if ($iRow !== null) {
                $n = trim((string) ($iRow['nome'] ?? ''));
                $s = trim((string) ($iRow['sigla'] ?? ''));
                $instituicaoCtx = $n !== '' && $s !== '' ? $s . ' — ' . $n : ($n !== '' ? $n : $s);
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $instNome = trim((string) ($r['instituicao_nome'] ?? ''));
            $instSigla = trim((string) ($r['instituicao_sigla'] ?? ''));
            $instLabel = $instNome !== '' && $instSigla !== ''
                ? $instSigla . ' — ' . $instNome
                : ($instNome !== '' ? $instNome : $instSigla);
            $out[] = [
                'pessoa' => $parteId !== '' ? $pessoaNomeCtx : trim((string) ($r['parte_nome'] ?? '')),
                'cpf' => $parteId !== '' ? $pessoaCpfCtx : trim((string) ($r['parte_cpf'] ?? '')),
                'instituicao' => $parteId !== '' ? $instLabel : $instituicaoCtx,
                'cargo_funcao' => trim((string) ($r['cargo_funcao'] ?? '')),
                'profissao' => trim((string) ($r['profissao'] ?? '')),
                'data_inclusao' => trim((string) ($r['data_inclusao'] ?? '')),
            ];
        }

        return [
            'title' => 'Lista de vínculos pessoa–instituição',
            'slug' => 'partes-instituicoes',
            'columns' => [
                ['key' => 'pessoa', 'label' => 'Pessoa'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'instituicao', 'label' => 'Instituição'],
                ['key' => 'cargo_funcao', 'label' => 'Cargo/função'],
                ['key' => 'profissao', 'label' => 'Profissão'],
                ['key' => 'data_inclusao', 'label' => 'Data inclusão'],
            ],
            'rows' => $out,
        ];
    }

}
