<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/HelperUtils.php';
require_once __DIR__ . '/../helpers/AuditLoggerHelper.php';
require_once __DIR__ . '/ClasseManager.php';
require_once __DIR__ . '/ProcessoManager.php';
require_once __DIR__ . '/ReuManager.php';
require_once __DIR__ . '/Produtividade.php';

class Guias
{
    private mysqli $connection;
    private ClasseManager $classeManager;
    private ProcessoManager $processoManager;
    private ReuManager $reuManager;
    private Produtividade $produtividade;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
        $this->connection->set_charset('utf8mb4');
        $this->classeManager = new ClasseManager($connection);
        $this->processoManager = new ProcessoManager($connection);
        $this->reuManager = new ReuManager($connection);
        $this->produtividade = new Produtividade($connection);
    }

    private function calcularStatusGuiaPorSubtarefas(string $idGuia): string
    {
        $sql = "SELECT status, started_at, completed_at FROM guia_subtarefas WHERE id_guia = ?";
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return 'Não iniciada';
        }
        $stmt->bind_param('s', $idGuia);
        $stmt->execute();
        $res = $stmt->get_result();

        $temSubtarefa = false;
        $todasConcluidas = true;
        $temAndamento = false;
        $temConcluida = false;

        while ($row = $res->fetch_assoc()) {
            $temSubtarefa = true;
            $statusRaw = trim((string)($row['status'] ?? ''));
            $statusNorm = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $statusRaw) ?: $statusRaw);
            $statusNorm = trim($statusNorm);

            $ehConcluida = str_contains($statusNorm, 'conclu') || str_contains($statusNorm, 'feito');
            if ($ehConcluida) {
                $temConcluida = true;
            } else {
                $todasConcluidas = false;
            }

            $temAndamento = $temAndamento
                || str_contains($statusNorm, 'andamento')
                || str_contains($statusNorm, 'aguardando')
                || str_contains($statusNorm, 'refazer')
                || !empty($row['started_at']);
        }
        $stmt->close();

        if (!$temSubtarefa) return 'Não iniciada';
        if ($todasConcluidas) return 'Concluída';
        if ($temAndamento || $temConcluida) return 'Em andamento';
        return 'Não iniciada';
    }

    private function atualizarStatusGuiaPorSubtarefas(string $idGuia): string
    {
        $novoStatus = $this->calcularStatusGuiaPorSubtarefas($idGuia);
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');

        $stmt = $this->connection->prepare("UPDATE guia SET status = ?, data_atualizacao = ? WHERE id_guia = ?");
        if ($stmt) {
            $stmt->bind_param('sss', $novoStatus, $agora, $idGuia);
            $stmt->execute();
            $stmt->close();
        }

        return $novoStatus;
    }

    /**
     * Gera um ID único para guias, processos e outras entidades
     */
    public function gerarId(): string
    {
        return Helper::gerarId();
    }

    /**
     * Lista todas as classes cadastradas
     */
    public function listarClasses(): array
    {
        return $this->classeManager->listarClasses();
    }

    /**
     * Busca uma classe específica pelo código
     */
    public function buscarClasse(string $codigo): ?array
    {
        return $this->classeManager->buscarClasse($codigo);
    }

    /**
     * Cadastra uma nova classe se não existir
     */
    public function cadastrarClasse(string $codigo, string $descricao): bool
    {
        return $this->classeManager->cadastrarClasse($codigo, $descricao);
    }

    /**
     * Busca um processo pelo número
     */
    public function buscarProcesso(string $numero): ?array
    {
        return $this->processoManager->buscarProcesso($numero);
    }

    /**
     * Cadastra um novo processo
     */
    public function cadastrarProcesso(string $numero, string $classe): bool
    {
        return $this->processoManager->cadastrarProcesso($numero, $classe);
    }

    /**
     * Busca um réu pelo nome
     */
    public function buscarReu(string $nome): ?array
    {
        return $this->reuManager->buscarReu($nome);
    }

    /**
     * Cadastra um novo réu
     */
    public function cadastrarReu(string $nome, ?string $cpf = null): ?string
    {
        return $this->reuManager->cadastrarReu($nome, $cpf);
    }

    /**
     * Cadastra prioridades de um processo
     */
    public function cadastrarPrioridades(string $numeroProcesso, array $prioridades): bool
    {
        return $this->processoManager->cadastrarPrioridades($numeroProcesso, $prioridades);
    }

    /**
     * Vincula um réu a um processo
     */
    public function vincularReuProcesso(string $idReu, string $numeroProcesso): bool
    {
        return $this->reuManager->vincularReuProcesso($idReu, $numeroProcesso);
    }

    /**
     * Cadastra uma nova guia com todas as informações relacionadas
     */
    public function cadastrarGuia(array $dados): array
    {
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        
        try {
            $this->connection->begin_transaction();

            // 1. Cadastra/atualiza a classe
            if (!empty($dados['classe_cod']) && !empty($dados['classe_desc'])) {
                $this->cadastrarClasse($dados['classe_cod'], $dados['classe_desc']);
            }

            // 2. Cadastra/atualiza o processo
            $processoAuto = false;
            if (!empty($dados['processo']) && !empty($dados['classe_cod'])) {
                $processoExistia = $this->buscarProcesso($dados['processo']);
                $processoOk = $this->cadastrarProcesso($dados['processo'], $dados['classe_cod']);
                if (!$processoExistia && $processoOk) {
                    $processoAuto = true;
                }
            }

            // 3. Cadastra o réu (com CPF se fornecido)
            $idReu = null;
            $reuAuto = false;
            if (!empty($dados['reu'])) {
                $cpf = $dados['cpf'] ?? null;
                $reuExistia = $this->buscarReu($dados['reu']);
                $idReu = $this->cadastrarReu($dados['reu'], $cpf);
                if (!$reuExistia && $idReu !== null) {
                    $reuAuto = true;
                }
            }

            // 4. Vincula réu ao processo
            if ($idReu && !empty($dados['processo'])) {
                $this->vincularReuProcesso($idReu, $dados['processo']);
            }

            // 5. Verifica se réu está preso e adiciona prioridade automaticamente
            $prioridades = !empty($dados['prioridades']) && is_array($dados['prioridades']) ? $dados['prioridades'] : [];
            if (!empty($dados['reu'])) {
                $reuInfo = $this->buscarReu($dados['reu']);
                if ($reuInfo && isset($reuInfo['statusPrisao']) && (int)$reuInfo['statusPrisao'] === 1) {
                    // Adiciona "reu-preso" se não estiver presente
                    if (!in_array('reu-preso', $prioridades, true)) {
                        $prioridades[] = 'reu-preso';
                    }
                }
            }

            // 6. Cadastra prioridades do processo
            if (!empty($prioridades)) {
                $this->cadastrarPrioridades($dados['processo'], $prioridades);
            }

            // 6. Cadastra a guia
            $idGuia = $this->gerarId();
            $sql = "INSERT INTO guia 
                    (id_guia, tipo, processo, denunciado, etapa, status, regime, data_fato, dias_multa, observacoes, data_cadastro, data_atualizacao, user_cadastro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar insert da guia: ' . $this->connection->error);
            }

            // Preparar valores
            $regime = $dados['regime'] ?? '';
            $dataFato = $dados['data_fato'] ?? null;
            $diasMulta = !empty($dados['dias_multa']) ? (int)$dados['dias_multa'] : null;
            $observacoes = $dados['observacoes'] ?? null;

            $stmt->bind_param(
                "ssssssssissss",
                $idGuia,
                $dados['tipo'],
                $dados['processo'],
                $dados['reu'],
                $dados['etapa'],
                $dados['status'],
                $regime,
                $dataFato,
                $diasMulta,
                $observacoes,
                $agora,
                $agora,
                $dados['user_cadastro']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao inserir guia: ' . $stmt->error);
            }
            $stmt->close();

            // 7. Cadastra alocação de usuários (se aplicável)
            $alocacao = $dados['alocacao'] ?? 'nao-alocar';
            if ($alocacao === 'manual' || $alocacao === 'sorteio') {
                $servidoresAlocar = $dados['servidores'] ?? [];
                if (is_array($servidoresAlocar) && count($servidoresAlocar) > 0) {
                    // Se for sorteio, escolhe apenas 1 aleatório
                    if ($alocacao === 'sorteio' && count($servidoresAlocar) > 1) {
                        $chaveAleatoria = array_rand($servidoresAlocar);
                        $servidoresAlocar = [$servidoresAlocar[$chaveAleatoria]];
                    }
                    
                    // INSERT para cada servidor alocado
                    $sqlAloc = "INSERT INTO guiasUsuarios (id_alocacao, id_usuario, id_guia, data_cadastro) 
                                VALUES (?, ?, ?, ?)";
                    $stmtAloc = $this->connection->prepare($sqlAloc);
                    
                    if ($stmtAloc) {
                        $dataCadastro = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d');
                        foreach ($servidoresAlocar as $userId) {
                            $idAlocacao = $this->gerarId();
                            $stmtAloc->bind_param('ssss', $idAlocacao, $userId, $idGuia, $dataCadastro);
                            $stmtAloc->execute();
                        }
                        $stmtAloc->close();
                    }
                }
            }

            // 8. Cadastra subtarefas selecionadas (se aplicável)
            // 
            // NOTA: Criação automática em cascata está implementada em:
            // - v2026/api/guia-subtarefa-update-status.php
            // - Documentação: docs/20-modules/subtask-cascade-rules.md
            // 
            // Regras de cascata (6 regras):
            // 1. "Cálculo da pena de multa" concluído → cria "Expedir Guia" automaticamente
            // 2. "Expedir Guia" concluído → cria "Assinar Guia" automaticamente
            // 3. "Assinar Guia" concluído → cria "Gerar PDF da documentação" automaticamente
            // 4. "Gerar PDF da documentação" concluído → cria "Enviar documentos" automaticamente
            // 5. "Enviar documentos" concluído → cria "Juntar termo de envio" automaticamente
            // 6. "Juntar termo de envio" concluído → cria "Expedir certidão" automaticamente
            //
            // Ordem recomendada das subtarefas (conforme fluxo natural):
            $subtaskOrder = [
                'calcular-pena-multa',
                'expedir-guia',
                'assinar-guia',
                'gerar-pdf',
                'enviar-documentos',
                'juntar-termo-envio',
                'expedir-certidao'
            ];

            $subtaskCatalog = [
                'calcular-pena-multa' => 'Cálculo da pena de multa',
                'expedir-guia' => 'Expedir Guia',
                'assinar-guia' => 'Assinar Guia',
                'gerar-pdf' => 'Gerar PDF da documentação',
                'enviar-documentos' => 'Enviar documentos',
                'juntar-termo-envio' => 'Juntar termo de envio',
                'expedir-certidao' => 'Expedir certidão'
            ];

            $subtarefas = is_array($dados['subtarefas'] ?? null) ? $dados['subtarefas'] : [];

            // Subtarefas obrigatórias no cadastro de qualquer guia
            $subtarefasObrigatorias = ['expedir-guia', 'enviar-documentos'];
            $subtarefas = array_merge($subtarefas, $subtarefasObrigatorias);

            // Validar subtarefas selecionadas (remover duplicatas)
            $subtarefasValidas = array_values(array_unique(array_filter($subtarefas, static function ($codigo) use ($subtaskCatalog) {
                $codigo = trim((string)$codigo);
                return $codigo !== '' && isset($subtaskCatalog[$codigo]);
            })));

            // Reordenar conforme a ordem recomendada
            $subtarefasOrdenadas = [];
            foreach ($subtaskOrder as $codigo) {
                if (in_array($codigo, $subtarefasValidas, true)) {
                    $subtarefasOrdenadas[] = $codigo;
                }
            }

            if (count($subtarefasOrdenadas) > 0) {
                // Inserção simplificada - apenas campos básicos
                $sqlSub = "INSERT INTO guia_subtarefas (id_guia, codigo, titulo, status, created_at) VALUES (?, ?, ?, ?, ?)";
                $stmtSub = $this->connection->prepare($sqlSub);
                if (!$stmtSub) {
                    throw new Exception('Erro ao preparar insert de subtarefas: ' . $this->connection->error);
                }

                $statusSub = 'A fazer';
                
                foreach ($subtarefasOrdenadas as $codigo) {
                    $titulo = $subtaskCatalog[$codigo];
                    
                    // Bind com variáveis locais
                    $bind_id_guia = $idGuia;
                    $bind_codigo = $codigo;
                    $bind_titulo = $titulo;
                    $bind_status = $statusSub;
                    $bind_created_at = $agora;
                    
                    $stmtSub->bind_param('sssss', $bind_id_guia, $bind_codigo, $bind_titulo, $bind_status, $bind_created_at);
                    
                    if (!$stmtSub->execute()) {
                        throw new Exception('Erro ao inserir subtarefa ' . $codigo . ': ' . $stmtSub->error);
                    }
                }

                $stmtSub->close();
            }

            // 8.1 Força atualização do status da guia com base nas subtarefas recém-criadas
            $this->atualizarStatusGuiaPorSubtarefas($idGuia);

            // 9. Registra na produtividade (somente se houver usuário válido)
            $usuarioProd = trim((string)($dados['user_cadastro'] ?? ''));
            $pontosProd = null;
            if ($usuarioProd !== '' && $usuarioProd !== 'sistema') {
                $temDetalhamento = false;
                $camposDetalhe = [
                    $dados['regime'] ?? '',
                    $dados['cpf'] ?? '',
                    $dados['telefone'] ?? '',
                    $dados['data_fato'] ?? '',
                    $dados['dias_multa'] ?? '',
                    $dados['observacoes'] ?? ''
                ];
                foreach ($camposDetalhe as $campo) {
                    if (trim((string)$campo) !== '') {
                        $temDetalhamento = true;
                        break;
                    }
                }

                $servidores = $dados['servidores'] ?? [];
                if (!$temDetalhamento && is_array($servidores) && count($servidores) > 0) {
                    $temDetalhamento = true;
                }

                $prioridades = $dados['prioridades'] ?? [];
                if (!$temDetalhamento && is_array($prioridades) && count($prioridades) > 0) {
                    $temDetalhamento = true;
                }

                $subtarefas = $dados['subtarefas'] ?? [];
                if (!$temDetalhamento && is_array($subtarefas) && count($subtarefas) > 0) {
                    $temDetalhamento = true;
                }

                $pontosProd = $temDetalhamento ? 2.0 : 1.0;
                $this->produtividade->registrarCadastroGuia(
                    $idGuia,
                    (string)($dados['processo'] ?? ''),
                    $usuarioProd,
                    $agora,
                    $temDetalhamento
                );
            }

            $this->connection->commit();
            // Auditoria: registra criação da guia
            try {
                $userCadastro = (string)($dados['user_cadastro'] ?? 'sistema');
                AuditLogger::log($this->connection, $userCadastro, 'cadastrar_guia', ['id_guia' => $idGuia, 'processo' => $dados['processo'] ?? '', 'tipo' => $dados['tipo'] ?? '']);
            } catch (Throwable $e) {
                error_log('Audit logging failed: ' . $e->getMessage());
            }

            return [
                'ok' => true,
                'id' => $idGuia,
                'message' => 'Guia cadastrada com sucesso',
                'prod_pontos' => $pontosProd,
                'processo_auto' => $processoAuto,
                'reu_auto' => $reuAuto
            ];

        } catch (Throwable $e) {
            $this->connection->rollback();
            error_log('Erro ao cadastrar guia: ' . $e->getMessage());
            
            return [
                'ok' => false,
                'id' => null,
                'message' => 'Erro ao cadastrar guia: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza uma guia existente com as informações relacionadas
     */
    public function atualizarGuia(array $dados): array
    {
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $idGuia = trim((string)($dados['id_guia'] ?? ''));
        if ($idGuia === '') {
            return ['ok' => false, 'message' => 'ID da guia não informado.'];
        }

        try {
            $this->connection->begin_transaction();

            // 1. Cadastra/atualiza a classe
            if (!empty($dados['classe_cod']) && !empty($dados['classe_desc'])) {
                $this->cadastrarClasse($dados['classe_cod'], $dados['classe_desc']);
            }

            // 2. Cadastra/atualiza o processo
            if (!empty($dados['processo']) && !empty($dados['classe_cod'])) {
                $this->cadastrarProcesso($dados['processo'], $dados['classe_cod']);
            }

            // 3. Cadastra/atualiza réu
            $idReu = null;
            if (!empty($dados['reu'])) {
                $cpf = $dados['cpf'] ?? null;
                $idReu = $this->cadastrarReu($dados['reu'], $cpf);
            }

            // 4. Vincula réu ao processo
            if ($idReu && !empty($dados['processo'])) {
                $this->vincularReuProcesso($idReu, $dados['processo']);
            }

            // 5. Prioridades (com regra automática de réu preso)
            $prioridades = !empty($dados['prioridades']) && is_array($dados['prioridades']) ? $dados['prioridades'] : [];
            if (!empty($dados['reu'])) {
                $reuInfo = $this->buscarReu($dados['reu']);
                if ($reuInfo && isset($reuInfo['statusPrisao']) && (int)$reuInfo['statusPrisao'] === 1) {
                    if (!in_array('reu-preso', $prioridades, true)) {
                        $prioridades[] = 'reu-preso';
                    }
                } else {
                    $prioridades = array_values(array_filter($prioridades, static function ($p) {
                        return $p !== 'reu-preso';
                    }));
                }
            }
            if (!empty($dados['processo'])) {
                $this->cadastrarPrioridades($dados['processo'], $prioridades);
            }

            // 6. Atualiza dados da guia
            $regime = $dados['regime'] ?? '';
            $dataFato = $dados['data_fato'] ?? null;
            $diasMulta = $dados['dias_multa'] !== '' ? (int)$dados['dias_multa'] : null;
            $observacoes = $dados['observacoes'] ?? null;
            $userCadastro = $dados['user_cadastro'] ?? 'sistema';

            $sql = "UPDATE guia
                    SET tipo = ?, processo = ?, denunciado = ?, regime = ?, data_fato = ?, dias_multa = ?, observacoes = ?, data_atualizacao = ?, user_cadastro = ?
                    WHERE id_guia = ?";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar atualização da guia: ' . $this->connection->error);
            }

            $stmt->bind_param(
                "ssssssssss",
                $dados['tipo'],
                $dados['processo'],
                $dados['reu'],
                $regime,
                $dataFato,
                $diasMulta,
                $observacoes,
                $agora,
                $userCadastro,
                $idGuia
            );

            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar guia: ' . $stmt->error);
            }
            $stmt->close();

            // 6.1 Atualiza consulta SEEU na subtarefa "expedir-guia" (checkbox_reu_provisoria + destino_guia)
            $verificacaoSeeuRaw = trim((string)($dados['verificacao_seeu'] ?? ''));
            if ($verificacaoSeeuRaw !== '') {
                $valorVerificacao = $verificacaoSeeuRaw;
                if (in_array($verificacaoSeeuRaw, ['verificado-consta', 'ja-existe-vep'], true)) {
                    $valorVerificacao = '2';
                } elseif (in_array($verificacaoSeeuRaw, ['verificado-nao-consta', 'nenhuma-vep'], true)) {
                    $valorVerificacao = '1';
                } elseif ($verificacaoSeeuRaw === 'nao-verificado') {
                    $valorVerificacao = '0';
                }

                $checkboxReuProvisoria = ($valorVerificacao === '2') ? 1 : 0;
                $destinoGuia = $checkboxReuProvisoria === 1 ? 'VEP' : 'SEEU';

                $stmtSeeu = $this->connection->prepare("SELECT dados_extra FROM guia_subtarefas WHERE id_guia = ? AND codigo = 'expedir-guia' LIMIT 1");
                if ($stmtSeeu) {
                    $stmtSeeu->bind_param('s', $idGuia);
                    $stmtSeeu->execute();
                    $resSeeu = $stmtSeeu->get_result();
                    $rawExtra = '';
                    if ($row = $resSeeu->fetch_assoc()) {
                        $rawExtra = (string)($row['dados_extra'] ?? '');
                    }
                    $stmtSeeu->close();

                    $dadosExtra = [];
                    if ($rawExtra !== '') {
                        $parsed = json_decode($rawExtra, true);
                        if (is_array($parsed)) {
                            $dadosExtra = $parsed;
                        }
                    }
                    $dadosExtra['verificacao_seeu'] = $valorVerificacao;
                    $jsonExtra = json_encode($dadosExtra, JSON_UNESCAPED_UNICODE);

                    $stmtUpdSeeu = $this->connection->prepare("UPDATE guia_subtarefas SET checkbox_reu_provisoria = ?, destino_guia = ?, dados_extra = ? WHERE id_guia = ? AND codigo = 'expedir-guia'");
                    if ($stmtUpdSeeu) {
                        $stmtUpdSeeu->bind_param('isss', $checkboxReuProvisoria, $destinoGuia, $jsonExtra, $idGuia);
                        $stmtUpdSeeu->execute();
                        $stmtUpdSeeu->close();
                    }
                }
            }

            // 7. Atualiza alocação
            $stmtAlocDelete = $this->connection->prepare('DELETE FROM guiasUsuarios WHERE id_guia = ?');
            if ($stmtAlocDelete) {
                $stmtAlocDelete->bind_param('s', $idGuia);
                $stmtAlocDelete->execute();
                $stmtAlocDelete->close();
            }

            $alocacao = $dados['alocacao'] ?? 'nao-alocar';
            $servidoresAlocar = $dados['servidores'] ?? [];
            if (($alocacao === 'manual' || $alocacao === 'sorteio') && is_array($servidoresAlocar) && count($servidoresAlocar) > 0) {
                if ($alocacao === 'sorteio' && count($servidoresAlocar) > 1) {
                    $chaveAleatoria = array_rand($servidoresAlocar);
                    $servidoresAlocar = [$servidoresAlocar[$chaveAleatoria]];
                }

                $sqlAloc = "INSERT INTO guiasUsuarios (id_alocacao, id_usuario, id_guia, data_cadastro)
                            VALUES (?, ?, ?, ?)";
                $stmtAloc = $this->connection->prepare($sqlAloc);

                if ($stmtAloc) {
                    $dataCadastro = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d');
                    foreach ($servidoresAlocar as $userId) {
                        $idAlocacao = $this->gerarId();
                        $stmtAloc->bind_param('ssss', $idAlocacao, $userId, $idGuia, $dataCadastro);
                        $stmtAloc->execute();
                    }
                    $stmtAloc->close();
                }
            }

            // 7.1 Força atualização do status da guia com base nas subtarefas atuais
            $this->atualizarStatusGuiaPorSubtarefas($idGuia);

            $this->connection->commit();

            return [
                'ok' => true,
                'id' => $idGuia,
                'message' => 'Guia atualizada com sucesso'
            ];

        } catch (Throwable $e) {
            $this->connection->rollback();
            error_log('Erro ao atualizar guia: ' . $e->getMessage());
            return [
                'ok' => false,
                'id' => $idGuia,
                'message' => 'Erro ao atualizar guia: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retorna os dados completos de uma guia (incluindo dados auxiliares de réu e classe)
     */
    public function obterGuia(string $idGuia): ?array
    {
        $sql = "SELECT 
                    g.*, 
                    p.classe AS processo_classe,
                    c.descricao AS classe_desc,
                    r.cpf AS reu_cpf,
                    r.telefone AS reu_telefone
                FROM guia g
                LEFT JOIN processos p ON g.processo = p.numero
                LEFT JOIN classes c ON p.classe = c.cod
                LEFT JOIN reus r ON g.denunciado = r.nome
                WHERE g.id_guia = ?
                LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $idGuia);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $idGuia = (string)($row['id_guia'] ?? '');
        if ($idGuia !== '') {
            $statusCalculado = $this->calcularStatusGuiaPorSubtarefas($idGuia);
            if ($statusCalculado !== (string)($row['status'] ?? '')) {
                $row['status'] = $statusCalculado;
                $this->atualizarStatusGuiaPorSubtarefas($idGuia);
            }
        }

        $diasMulta = null;
        if (array_key_exists('dias_multa', $row) && $row['dias_multa'] !== null && $row['dias_multa'] !== '') {
            $diasMulta = (int)$row['dias_multa'];
        }

        $classeCod = trim((string)($row['processo_classe'] ?? $row['classe_cod'] ?? ''));
        $classeDesc = trim((string)($row['classe_desc'] ?? $row['descricao'] ?? ''));

        return [
            'id_guia' => (string)($row['id_guia'] ?? ''),
            'tipo' => (string)($row['tipo'] ?? ''),
            'processo' => (string)($row['processo'] ?? ''),
            'denunciado' => (string)($row['denunciado'] ?? ''),
            'etapa' => (string)($row['etapa'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'regime' => (string)($row['regime'] ?? ''),
            'data_fato' => (string)($row['data_fato'] ?? ''),
            'dias_multa' => $diasMulta,
            'observacoes' => (string)($row['observacoes'] ?? ''),
            'classe_cod' => $classeCod,
            'classe_desc' => $classeDesc,
            'data_cadastro' => (string)($row['data_cadastro'] ?? ''),
            'data_atualizacao' => (string)($row['data_atualizacao'] ?? ''),
            'user_cadastro' => (string)($row['user_cadastro'] ?? ''),
            'cpf' => (string)($row['reu_cpf'] ?? $row['cpf'] ?? ''),
            'telefone' => (string)($row['reu_telefone'] ?? $row['telefone'] ?? '')
        ];
    }

    /**
     * Retorna as prioridades já cadastradas de um processo específico
     */
    public function listarPrioridadesProcesso(string $numeroProcesso): array
    {
        return $this->processoManager->listarPrioridades($numeroProcesso);
    }

    /**
     * Lista guias com filtros e paginação
     */
    public function listarGuias(array $filtros = [], int $page = 1, int $perPage = 25): array
    {
        $whereClauses = [];
        $params = [];
        $types = '';

        if (!empty($filtros['status'])) {
            $whereClauses[] = "status = ?";
            $params[] = $filtros['status'];
            $types .= 's';
        }

        if (!empty($filtros['tipo'])) {
            $whereClauses[] = "tipo = ?";
            $params[] = $filtros['tipo'];
            $types .= 's';
        }

        if (!empty($filtros['processo'])) {
            $whereClauses[] = "processo LIKE ?";
            $params[] = '%' . $filtros['processo'] . '%';
            $types .= 's';
        }

        $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

        // Count total
        $countSql = "SELECT COUNT(*) AS total FROM guia" . $whereSql;
        $stmt = $this->connection->prepare($countSql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();

        // Lista paginada
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM guia" . $whereSql . " ORDER BY data_cadastro DESC LIMIT ? OFFSET ?";
        $stmt = $this->connection->prepare($sql);
        
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $guias = [];
        while ($row = $result->fetch_assoc()) {
            $idGuia = (string)($row['id_guia'] ?? '');
            if ($idGuia !== '') {
                $statusCalculado = $this->calcularStatusGuiaPorSubtarefas($idGuia);
                if ($statusCalculado !== (string)($row['status'] ?? '')) {
                    $row['status'] = $statusCalculado;
                    $this->atualizarStatusGuiaPorSubtarefas($idGuia);
                }
            }
            $guias[] = $row;
        }
        $stmt->close();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'data' => $guias
        ];
    }
}
