<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/FeriadosService.php';
require_once __DIR__ . '/../services/UsuariosService.php';
require_once __DIR__ . '/../services/GuiasService.php';
require_once __DIR__ . '/../services/CejuscService.php';
require_once __DIR__ . '/../services/CeapaService.php';
require_once __DIR__ . '/../services/EditaisService.php';
require_once __DIR__ . '/../services/MedidasProtetivasService.php';
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
require_once __DIR__ . '/../services/ProdutividadeService.php';
require_once __DIR__ . '/../repositories/SalaAudienciasAtaRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';

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
            'cejusc' => [self::class, 'cejusc'],
            'ceapa' => [self::class, 'ceapa'],
            'editais' => [self::class, 'editais'],
            'medidasprotetivas' => [self::class, 'medidasProtetivas'],
            'oficioscartorio' => [self::class, 'oficiosCartorio'],
            'oficiosgabinete' => [self::class, 'oficiosGabinete'],
            'monitoramentogabinete' => [self::class, 'monitoramentoGabinete'],
            'reuspresos' => [self::class, 'reusPresos'],
            'cartasexpedidas' => [self::class, 'cartasExpedidas'],
            'cartasrecebidas' => [self::class, 'cartasRecebidas'],
            'salapauta' => [self::class, 'salaPauta'],
            'salapainel' => [self::class, 'salaPauta'],
            'salaata' => [self::class, 'salaAtas'],
            'consultapartes' => [self::class, 'consultaPartes'],
            'consultaprocessos' => [self::class, 'consultaProcessos'],
            'instituicoespublicas' => [self::class, 'instituicoesPublicas'],
            'processosparteslist' => [self::class, 'processosPartes'],
            'partesinstituicoeslist' => [self::class, 'partesInstituicoes'],
            'produtividaderegras' => [self::class, 'produtividadeRegras'],
            'produtividademetas' => [self::class, 'produtividadeMetas'],
            'produtividadebadges' => [self::class, 'produtividadeBadges'],
        ];
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
                'id' => $r['id'] ?? '',
                'data_inicio' => $r['data_inicio'] ?? '',
                'data_fim' => $r['data_fim'] ?? '',
                'tipo_feriado' => $r['tipo_feriado'] ?? '',
                'descricao' => $r['descricao'] ?? '',
            ];
        }

        return [
            'title' => 'Feriados',
            'slug' => 'feriados',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
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
            $out[] = [
                'nome_completo' => $r['nome_completo'] ?? '',
                'tipo' => ($r['tipo'] ?? '') === 'externo' ? 'Externo' : 'Interno',
                'setores' => str_replace('AUDIENCIA', 'Sala de Audiências', trim((string) ($r['setores'] ?? ''))),
                'cargo' => $r['cargo'] ?? '',
                'status' => $r['status'] ?? '',
                'id_usuario' => $r['id_usuario'] ?? $r['id'] ?? '',
            ];
        }

        return [
            'title' => 'Usuários',
            'slug' => 'usuarios',
            'columns' => [
                ['key' => 'id_usuario', 'label' => 'ID'],
                ['key' => 'nome_completo', 'label' => 'Nome'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'setores', 'label' => 'Setor'],
                ['key' => 'cargo', 'label' => 'Cargo'],
                ['key' => 'status', 'label' => 'Status'],
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
        $rows = $service->listGuias($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Guias',
            'slug' => 'guias',
            'columns' => [
                ['key' => 'IdGuia', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'Classe', 'label' => 'Classe'],
                ['key' => 'Tipo', 'label' => 'Tipo'],
                ['key' => 'Denunciado', 'label' => 'Denunciado'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function cejusc(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new CejuscService($pdo);
        $rows = $service->listTarefas($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'CEJUSC',
            'slug' => 'cejusc',
            'columns' => [
                ['key' => 'IdCarta', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function ceapa(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
            'tipo_suspensao' => trim((string) ($get['tipo_suspensao'] ?? '')),
        ];
        $service = new CeapaService($pdo);
        $rows = $service->listTarefas($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'CEAPA',
            'slug' => 'ceapa',
            'columns' => [
                ['key' => 'IdCarta', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
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
        $rows = $service->listEditais($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Editais',
            'slug' => 'editais',
            'columns' => [
                ['key' => 'IdEdital', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function medidasProtetivas(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'status' => trim((string) ($get['status'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new MedidasProtetivasService($pdo);
        $rows = $service->listMedidasProtetivas($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Medidas protetivas',
            'slug' => 'medidas-protetivas',
            'columns' => [
                ['key' => 'IdMedida', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
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
            'processo' => trim((string) ($get['processo'] ?? '')),
            'ano' => self::resolveAnoOficios($anos, $get),
        ];
        $rows = $service->listOficios($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Ofícios (cartório)',
            'slug' => 'oficios-cartorio',
            'columns' => [
                ['key' => 'IdOficio', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
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
            'processo' => trim((string) ($get['processo'] ?? '')),
            'ano' => self::resolveAnoOficios($anos, $get),
        ];
        $rows = $service->listOficios($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Ofícios (gabinete)',
            'slug' => 'oficios-gabinete',
            'columns' => [
                ['key' => 'IdOficio', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
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
        $rows = $service->listMonitoramentos($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Monitoramento eletrônico',
            'slug' => 'monitoramento-eletronico',
            'columns' => [
                ['key' => 'IdMon', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'ReuNome', 'label' => 'Réu'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'DataInicio', 'label' => 'Início'],
                ['key' => 'DuracaoLabel', 'label' => 'Duração'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function reusPresos(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'nome' => trim((string) ($get['nome'] ?? '')),
            'processo' => trim((string) ($get['processo'] ?? '')),
        ];
        $service = new GabineteReusPresosService($pdo);
        $rows = $service->listAtivos($filters, self::MAX_ROWS, 0)['rows'] ?? [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => $r['id'] ?? '',
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
            'title' => 'Réus presos',
            'slug' => 'reus-presos',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'reu_nome', 'label' => 'Nome'],
                ['key' => 'processo_principal', 'label' => 'Processo principal'],
                ['key' => 'data_prisao', 'label' => 'Data prisão'],
                ['key' => 'tempo_custodia', 'label' => 'Tempo custódia'],
                ['key' => 'proxima_revisao', 'label' => 'Próxima revisão'],
                ['key' => 'revisao_hint', 'label' => 'Prazo revisão'],
                ['key' => 'subtarefas_processos', 'label' => 'Subtarefas processos'],
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
        $rows = $service->listCartas($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Cartas expedidas',
            'slug' => 'cartas-expedidas',
            'columns' => [
                ['key' => 'IdCarta', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'Finalidade', 'label' => 'Finalidade'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
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
        $rows = $service->listCartas($filters, self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Cartas recebidas',
            'slug' => 'cartas-recebidas',
            'columns' => [
                ['key' => 'IdCarta', 'label' => 'ID'],
                ['key' => 'Processo', 'label' => 'Processo'],
                ['key' => 'ClasseNome', 'label' => 'Classe'],
                ['key' => 'Finalidade', 'label' => 'Finalidade'],
                ['key' => 'Status', 'label' => 'Status'],
                ['key' => 'Alocados', 'label' => 'Alocações'],
                ['key' => 'Subtarefas', 'label' => 'Subtarefas'],
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
                'id_pauta' => $r['id_pauta'] ?? '',
                'data_audiencia' => $r['data_audiencia'] ?? '',
                'hora_audiencia' => $r['hora_audiencia'] ?? '',
                'numero_processo' => $r['numero_processo'] ?? '',
                'classe_nome' => $r['classe_nome'] ?? '',
                'tipo_audiencia' => $r['tipo_audiencia'] ?? '',
                'status' => $r['status'] ?? '',
                'localizacao' => $r['localizacao'] ?? '',
                'partes_cumprimento' => $parts,
            ];
        }

        return [
            'title' => 'Pauta de audiências',
            'slug' => 'pauta-audiencias',
            'columns' => [
                ['key' => 'id_pauta', 'label' => 'ID'],
                ['key' => 'data_audiencia', 'label' => 'Data'],
                ['key' => 'hora_audiencia', 'label' => 'Hora'],
                ['key' => 'numero_processo', 'label' => 'Processo'],
                ['key' => 'classe_nome', 'label' => 'Classe'],
                ['key' => 'tipo_audiencia', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'localizacao', 'label' => 'Local'],
                ['key' => 'partes_cumprimento', 'label' => 'Partes/cumprimento'],
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
                'id_pauta' => $r['id_pauta'] ?? '',
                'data_audiencia' => $r['data_audiencia'] ?? '',
                'hora_audiencia' => $r['hora_audiencia'] ?? '',
                'numero_processo' => $r['numero_processo'] ?? '',
                'classe_nome' => $r['classe_nome'] ?? '',
                'tipo_audiencia' => $r['tipo_audiencia'] ?? '',
                'status' => $r['status'] ?? '',
                'localizacao' => $r['localizacao'] ?? '',
                'id_ata' => $r['id_ata'] ?? '',
                'arquivo_relativo' => $r['arquivo_relativo'] ?? '',
                'uploaded_at' => $r['uploaded_at'] ?? '',
            ];
        }

        return [
            'title' => 'Atas de audiências',
            'slug' => 'atas-audiencias',
            'columns' => [
                ['key' => 'id_pauta', 'label' => 'ID pauta'],
                ['key' => 'data_audiencia', 'label' => 'Data'],
                ['key' => 'hora_audiencia', 'label' => 'Hora'],
                ['key' => 'numero_processo', 'label' => 'Processo'],
                ['key' => 'classe_nome', 'label' => 'Classe'],
                ['key' => 'tipo_audiencia', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'localizacao', 'label' => 'Local'],
                ['key' => 'id_ata', 'label' => 'ID ata'],
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
                'title' => 'Consulta de partes',
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
                'id' => $row['id'] ?? $row['ID'] ?? '',
                'nome_completo' => $row['nome_completo'] ?? '',
                'cpf' => $row['cpf'] ?? '',
                'telefones' => $row['telefones'],
                'instituicoes' => $row['instituicoes'],
            ];
        }

        return [
            'title' => 'Consulta de partes',
            'slug' => 'consulta-partes',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
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
                'title' => 'Consulta de processos',
                'slug' => 'consulta-processos',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Aplique filtros na lista e exporte novamente.']],
            ];
        }
        $service = new ConsultaProcessosService($pdo);
        $rows = $service->list(['numero' => $numero, 'parte_nome' => $parteNome], self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Consulta de processos',
            'slug' => 'consulta-processos',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'numero_processo', 'label' => 'Número'],
                ['key' => 'classe_nome', 'label' => 'Classe'],
                ['key' => 'total_partes', 'label' => 'Qtd. partes'],
            ],
            'rows' => $rows,
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
                'title' => 'Instituições públicas',
                'slug' => 'instituicoes-publicas',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Aplique filtros na lista e exporte novamente.']],
            ];
        }
        $service = new InstituicoesPublicasService($pdo);
        $rows = $service->list(['nome' => $nome], self::MAX_ROWS, 0)['rows'] ?? [];

        return [
            'title' => 'Instituições públicas',
            'slug' => 'instituicoes-publicas',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'nome', 'label' => 'Nome'],
                ['key' => 'sigla', 'label' => 'Sigla'],
            ],
            'rows' => $rows,
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
                'title' => 'Vínculos processo-parte',
                'slug' => 'processo-partes',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Informe o ID do processo e consulte antes de exportar.']],
            ];
        }
        $service = new ConsultaProcessosService($pdo);
        $processo = $service->get($processoId);
        if ($processo === null) {
            return [
                'title' => 'Vínculos processo-parte',
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
                'parte_id' => $p['parte_id'] ?? '',
                'nome_completo' => $p['nome_completo'] ?? '',
                'cpf' => $p['cpf'] ?? '',
                'tipo_parte' => $p['tipo_parte'] ?? '',
                'polo' => $p['polo'] ?? '',
            ];
        }

        return [
            'title' => 'Vínculos processo-parte',
            'slug' => 'processo-partes',
            'columns' => [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'parte_id', 'label' => 'ID parte'],
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
                'title' => 'Vínculos pessoa-instituição',
                'slug' => 'partes-instituicoes',
                'columns' => [['key' => 'msg', 'label' => 'Mensagem']],
                'rows' => [['msg' => 'Informe ID da pessoa ou da instituição e consulte antes de exportar.']],
            ];
        }
        $repo = new PartesInstituicoesRepository($pdo);
        if ($parteId !== '') {
            $rows = $repo->findByParteId($parteId);
        } else {
            $rows = $repo->findByInstituicaoId($instituicaoId);
        }

        return [
            'title' => 'Vínculos pessoa-instituição',
            'slug' => 'partes-instituicoes',
            'columns' => [
                ['key' => 'id', 'label' => 'ID vínculo'],
                ['key' => 'parte_id', 'label' => 'ID pessoa'],
                ['key' => 'parte_nome', 'label' => 'Pessoa'],
                ['key' => 'parte_cpf', 'label' => 'CPF'],
                ['key' => 'instituicao_id', 'label' => 'ID instituição'],
                ['key' => 'instituicao_nome', 'label' => 'Instituição'],
                ['key' => 'cargo_funcao', 'label' => 'Cargo/função'],
                ['key' => 'profissao', 'label' => 'Profissão'],
                ['key' => 'ativo', 'label' => 'Ativo'],
                ['key' => 'data_inclusao', 'label' => 'Data inclusão'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function produtividadeRegras(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'q' => trim((string) ($get['q'] ?? '')),
            'ativo' => $get['ativo'] ?? null,
        ];
        if ($filters['ativo'] === '' || $filters['ativo'] === null) {
            unset($filters['ativo']);
        }
        $service = new ProdutividadeService($pdo);
        $rows = [];
        $offset = 0;
        while (count($rows) < self::MAX_ROWS) {
            $batch = $service->listRules($userId, $filters, self::CHUNK_200, $offset)['rows'] ?? [];
            if ($batch === []) {
                break;
            }
            foreach ($batch as $r) {
                $rows[] = $r;
            }
            if (count($batch) < self::CHUNK_200) {
                break;
            }
            $offset += self::CHUNK_200;
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        return [
            'title' => 'Regras de produtividade',
            'slug' => 'produtividade-regras',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'tipo_ato', 'label' => 'Tipo ato'],
                ['key' => 'tipo_documento', 'label' => 'Tipo documento'],
                ['key' => 'sistema_modulo', 'label' => 'Módulo'],
                ['key' => 'pontos_por_ato', 'label' => 'Pontos/ato'],
                ['key' => 'pontos_multiplicador', 'label' => 'Multiplicador'],
                ['key' => 'ativo', 'label' => 'Ativo'],
                ['key' => 'observacoes', 'label' => 'Observações'],
                ['key' => 'criado_em', 'label' => 'Criado em'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function produtividadeMetas(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'q' => trim((string) ($get['q'] ?? '')),
            'ativo' => $get['ativo'] ?? null,
            'tipo_alvo' => trim((string) ($get['tipo_alvo'] ?? '')),
            'periodo_tipo' => trim((string) ($get['periodo_tipo'] ?? '')),
        ];
        if ($filters['ativo'] === '' || $filters['ativo'] === null) {
            unset($filters['ativo']);
        }
        if ($filters['tipo_alvo'] === '') {
            unset($filters['tipo_alvo']);
        }
        if ($filters['periodo_tipo'] === '') {
            unset($filters['periodo_tipo']);
        }
        $service = new ProdutividadeService($pdo);
        $rows = [];
        $offset = 0;
        while (count($rows) < self::MAX_ROWS) {
            $batch = $service->listMetas($userId, $filters, self::CHUNK_200, $offset)['rows'] ?? [];
            if ($batch === []) {
                break;
            }
            foreach ($batch as $r) {
                $rows[] = $r;
            }
            if (count($batch) < self::CHUNK_200) {
                break;
            }
            $offset += self::CHUNK_200;
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        return [
            'title' => 'Metas de produtividade',
            'slug' => 'produtividade-metas',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'tipo_alvo', 'label' => 'Tipo alvo'],
                ['key' => 'alvo_valor', 'label' => 'Alvo'],
                ['key' => 'periodo_tipo', 'label' => 'Período'],
                ['key' => 'vigencia_inicio', 'label' => 'Início'],
                ['key' => 'vigencia_fim', 'label' => 'Fim'],
                ['key' => 'pontos_meta', 'label' => 'Meta pontos'],
                ['key' => 'ativo', 'label' => 'Ativo'],
                ['key' => 'criado_por_nome', 'label' => 'Criado por'],
                ['key' => 'criado_em', 'label' => 'Criado em'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $get
     */
    private static function produtividadeBadges(PDO $pdo, string $userId, array $get): array
    {
        $filters = [
            'q' => trim((string) ($get['q'] ?? '')),
            'ativo' => $get['ativo'] ?? null,
            'tipo' => trim((string) ($get['tipo'] ?? '')),
        ];
        if ($filters['ativo'] === '' || $filters['ativo'] === null) {
            unset($filters['ativo']);
        }
        if ($filters['tipo'] === '') {
            unset($filters['tipo']);
        }
        $service = new ProdutividadeService($pdo);
        $rows = [];
        $offset = 0;
        while (count($rows) < self::MAX_ROWS) {
            $batch = $service->listBadges($userId, $filters, self::CHUNK_200, $offset)['rows'] ?? [];
            if ($batch === []) {
                break;
            }
            foreach ($batch as $r) {
                $rows[] = $r;
            }
            if (count($batch) < self::CHUNK_200) {
                break;
            }
            $offset += self::CHUNK_200;
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        return [
            'title' => 'Badges de produtividade',
            'slug' => 'produtividade-badges',
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'nome', 'label' => 'Nome'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'regra_id', 'label' => 'Regra'],
                ['key' => 'valor_threshold', 'label' => 'Threshold'],
                ['key' => 'periodo', 'label' => 'Período'],
                ['key' => 'ativo', 'label' => 'Ativo'],
                ['key' => 'descricao', 'label' => 'Descrição'],
                ['key' => 'criado_em', 'label' => 'Criado em'],
            ],
            'rows' => $rows,
        ];
    }
}
