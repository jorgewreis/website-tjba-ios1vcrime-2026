<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/HelperUtils.php';
require_once __DIR__ . '/../helpers/AuditLoggerHelper.php';
require_once __DIR__ . '/../helpers/GuiasLegacyTransactionalGuard.php';
require_once __DIR__ . '/../helpers/ParteDocumentoLock.php';
require_once __DIR__ . '/ClasseManager.php';
require_once __DIR__ . '/ProcessoManager.php';
require_once __DIR__ . '/ReuManager.php';

class Guias
{
    private PDO $pdo;
    private ClasseManager $classeManager;
    private ProcessoManager $processoManager;
    private ReuManager $reuManager;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->classeManager = new ClasseManager($pdo);
        $this->processoManager = new ProcessoManager($pdo);
        $this->reuManager = new ReuManager($pdo);
    }

    private function calcularStatusGuiaPorSubtarefas(string $idGuia): string
    {
        $sql = "SELECT status, started_at, completed_at FROM guia_subtarefas WHERE id_guia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idGuia]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $temSubtarefa = false;
        $todasConcluidas = true;
        $temAndamento = false;
        $temConcluida = false;

        foreach ($rows as $row) {
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

        if (!$temSubtarefa) return 'Não iniciada';
        if ($todasConcluidas) return 'Concluída';
        if ($temAndamento || $temConcluida) return 'Em andamento';
        return 'Não iniciada';
    }

    private function atualizarStatusGuiaPorSubtarefas(string $idGuia): string
    {
        $novoStatus = $this->calcularStatusGuiaPorSubtarefas($idGuia);
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("UPDATE guia SET status = ?, data_atualizacao = ? WHERE id_guia = ?");
        $stmt->execute([$novoStatus, $agora, $idGuia]);

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
        if ($this->pdo->inTransaction()) {
            return ['ok' => false, 'id' => null, 'message' => 'Não foi possível cadastrar a guia.'];
        }
        $resultado = null;
        try {
            return $this->comLockCpfDaGuia($dados, function () use ($dados, &$resultado): array {
                return $resultado = $this->cadastrarGuiaComTransacao($dados);
            });
        } catch (Throwable $error) {
            error_log('Falha de coordenação no cadastro de guia: ' . get_class($error));
            return $this->resultadoFalhaCoordenacao('cadastrar', null, $resultado);
        }
    }

    private function cadastrarGuiaComTransacao(array $dados): array
    {
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $idGuia = null;
        
        try {
            if (!$this->pdo->beginTransaction()) {
                ParteDocumentoLock::markConnectionUnsafe($this->pdo);
                throw new RuntimeException('Não foi possível iniciar a transação da guia.');
            }
            GuiasLegacyTransactionalGuard::requireTransactionalTables($this->pdo);

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

            // 3. Cadastra o réu (com CPF se fornecido) e obtém parte_id para a guia (BD usa parte_id em guia)
            $idReu = null;
            $reuAuto = false;
            $reuRaw = trim((string)($dados['reu'] ?? ''));
            if ($reuRaw !== '') {
                // Se vier formato de ID (ex: PRT-xxx), usa como parte_id; senão cadastra por nome
                if (preg_match('/^[A-Z]{2,4}-[A-Za-z0-9\-]+$/', $reuRaw)) {
                    $idReu = $reuRaw;
                } else {
                    $cpf = $dados['cpf'] ?? null;
                    $reuExistia = $this->buscarReu($reuRaw);
                    $idReu = $this->cadastrarReu($reuRaw, $cpf);
                    if ($idReu === null || trim($idReu) === '') {
                        throw new RuntimeException('Não foi possível obter a parte informada para a guia.');
                    }
                    if (!$reuExistia) {
                        $reuAuto = true;
                    }
                }
            }

            // 4. Vincula réu ao processo
            if ($idReu && !empty($dados['processo'])) {
                if (!$this->vincularReuProcesso($idReu, $dados['processo'])) {
                    throw new RuntimeException('Não foi possível vincular a parte ao processo da guia.');
                }
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
                if (!$this->cadastrarPrioridades($dados['processo'], $prioridades)) {
                    throw new RuntimeException('Não foi possível substituir as prioridades do processo.');
                }
            }

            // 6. Cadastra a guia (tabela guia usa parte_id após migração alter-cejusc-guias-partes-drop-reus)
            $idGuia = $this->gerarId();
            $sql = "INSERT INTO guia 
                    (id_guia, tipo, processo, parte_id, etapa, status, regime, data_fato, dias_multa, observacoes, data_cadastro, data_atualizacao, user_cadastro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            // Preparar valores
            $regime = $dados['regime'] ?? '';
            $dataFato = $dados['data_fato'] ?? null;
            $diasMulta = !empty($dados['dias_multa']) ? (int)$dados['dias_multa'] : null;
            $observacoes = $dados['observacoes'] ?? null;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $idGuia,
                $dados['tipo'],
                $dados['processo'],
                $idReu,
                $dados['etapa'],
                $dados['status'],
                $regime,
                $dataFato,
                $diasMulta,
                $observacoes,
                $agora,
                $agora,
                $dados['user_cadastro']
            ]);

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
                    $stmtAloc = $this->pdo->prepare($sqlAloc);
                    
                    $dataCadastro = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d');
                    foreach ($servidoresAlocar as $userId) {
                        $idAlocacao = $this->gerarId();
                        $stmtAloc->execute([$idAlocacao, $userId, $idGuia, $dataCadastro]);
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
                $stmtSub = $this->pdo->prepare($sqlSub);

                $statusSub = 'A fazer';
                
                foreach ($subtarefasOrdenadas as $codigo) {
                    $titulo = $subtaskCatalog[$codigo];
                    $stmtSub->execute([$idGuia, $codigo, $titulo, $statusSub, $agora]);
                }
            }

            // 8.1 Força atualização do status da guia com base nas subtarefas recém-criadas
            $this->atualizarStatusGuiaPorSubtarefas($idGuia);

            ParteDocumentoLock::commit($this->pdo, 'cadastro da guia');
            // Auditoria: registra criação da guia
            try {
                $userCadastro = (string)($dados['user_cadastro'] ?? 'sistema');
                AuditLogger::log($this->pdo, $userCadastro, 'cadastrar_guia', ['id_guia' => $idGuia, 'processo' => $dados['processo'] ?? '', 'tipo' => $dados['tipo'] ?? '']);
            } catch (Throwable $e) {
                error_log('Audit logging failed: ' . $e->getMessage());
            }

            return [
                'ok' => true,
                'id' => $idGuia,
                'message' => 'Guia cadastrada com sucesso',
                'processo_auto' => $processoAuto,
                'reu_auto' => $reuAuto
            ];

        } catch (Throwable $e) {
            $this->rollbackTransacaoGuiaAposFalha('cadastro da guia');
            error_log('Erro ao cadastrar guia: ' . $e->getMessage());
            
            $resposta = [
                'ok' => false,
                'id' => null,
                'message' => $this->mensagemFalhaGuia('cadastrar')
            ];
            if (ParteDocumentoLock::isConnectionUnsafe($this->pdo)) {
                $resposta['resultado'] = 'verificacao_necessaria';
                $resposta['estado_gravacao'] = 'nao_confirmada';
                if (is_string($idGuia) && $idGuia !== '') $resposta['id_referencia'] = $idGuia;
            }
            return $resposta;
        }
    }

    /**
     * Atualiza uma guia existente com as informações relacionadas
     */
    public function atualizarGuia(array $dados): array
    {
        $idGuia = trim((string) ($dados['id_guia'] ?? ''));
        if ($idGuia === '') {
            return ['ok' => false, 'message' => 'ID da guia não informado.'];
        }
        if ($this->pdo->inTransaction()) {
            return ['ok' => false, 'id' => $idGuia, 'message' => 'Não foi possível atualizar a guia.'];
        }
        $resultado = null;
        try {
            return $this->comLockCpfDaGuia($dados, function () use ($dados, &$resultado): array {
                return $resultado = $this->atualizarGuiaComTransacao($dados);
            });
        } catch (Throwable $error) {
            error_log('Falha de coordenação na atualização de guia: ' . get_class($error));
            return $this->resultadoFalhaCoordenacao('atualizar', $idGuia, $resultado);
        }
    }

    private function atualizarGuiaComTransacao(array $dados): array
    {
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $idGuia = trim((string)($dados['id_guia'] ?? ''));
        if ($idGuia === '') {
            return ['ok' => false, 'message' => 'ID da guia não informado.'];
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                ParteDocumentoLock::markConnectionUnsafe($this->pdo);
                throw new RuntimeException('Não foi possível iniciar a transação da guia.');
            }
            GuiasLegacyTransactionalGuard::requireTransactionalTables($this->pdo);

            // 1. Cadastra/atualiza a classe
            if (!empty($dados['classe_cod']) && !empty($dados['classe_desc'])) {
                $this->cadastrarClasse($dados['classe_cod'], $dados['classe_desc']);
            }

            // 2. Cadastra/atualiza o processo
            if (!empty($dados['processo']) && !empty($dados['classe_cod'])) {
                $this->cadastrarProcesso($dados['processo'], $dados['classe_cod']);
            }

            // 3. Cadastra/atualiza réu e obtém parte_id (BD usa parte_id em guia)
            $idReu = null;
            $reuRaw = trim((string)($dados['reu'] ?? ''));
            if ($reuRaw !== '') {
                if (preg_match('/^[A-Z]{2,4}-[A-Za-z0-9\-]+$/', $reuRaw)) {
                    $idReu = $reuRaw;
                } else {
                    $cpf = $dados['cpf'] ?? null;
                    $idReu = $this->cadastrarReu($reuRaw, $cpf);
                    if ($idReu === null || trim($idReu) === '') {
                        throw new RuntimeException('Não foi possível obter a parte informada para a guia.');
                    }
                }
            }

            // 4. Vincula réu ao processo
            if ($idReu && !empty($dados['processo'])) {
                if (!$this->vincularReuProcesso($idReu, $dados['processo'])) {
                    throw new RuntimeException('Não foi possível vincular a parte ao processo da guia.');
                }
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
                if (!$this->cadastrarPrioridades($dados['processo'], $prioridades)) {
                    throw new RuntimeException('Não foi possível substituir as prioridades do processo.');
                }
            }

            // 6. Atualiza dados da guia
            $regime = $dados['regime'] ?? '';
            $dataFato = $dados['data_fato'] ?? null;
            $diasMulta = $dados['dias_multa'] !== '' ? (int)$dados['dias_multa'] : null;
            $observacoes = $dados['observacoes'] ?? null;
            $userCadastro = $dados['user_cadastro'] ?? 'sistema';

            $sql = "UPDATE guia
                    SET tipo = ?, processo = ?, parte_id = ?, regime = ?, data_fato = ?, dias_multa = ?, observacoes = ?, data_atualizacao = ?, user_cadastro = ?
                    WHERE id_guia = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $dados['tipo'],
                $dados['processo'],
                $idReu,
                $regime,
                $dataFato,
                $diasMulta,
                $observacoes,
                $agora,
                $userCadastro,
                $idGuia
            ]);

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

                $stmtSeeu = $this->pdo->prepare("SELECT dados_extra FROM guia_subtarefas WHERE id_guia = ? AND codigo = 'expedir-guia' LIMIT 1");
                $stmtSeeu->execute([$idGuia]);
                $row = $stmtSeeu->fetch(PDO::FETCH_ASSOC);
                $rawExtra = '';
                if ($row) {
                    $rawExtra = (string)($row['dados_extra'] ?? '');
                }

                $dadosExtra = [];
                if ($rawExtra !== '') {
                    $parsed = json_decode($rawExtra, true);
                    if (is_array($parsed)) {
                        $dadosExtra = $parsed;
                    }
                }
                $dadosExtra['verificacao_seeu'] = $valorVerificacao;
                $jsonExtra = json_encode($dadosExtra, JSON_UNESCAPED_UNICODE);

                $stmtUpdSeeu = $this->pdo->prepare("UPDATE guia_subtarefas SET checkbox_reu_provisoria = ?, destino_guia = ?, dados_extra = ? WHERE id_guia = ? AND codigo = 'expedir-guia'");
                $stmtUpdSeeu->execute([$checkboxReuProvisoria, $destinoGuia, $jsonExtra, $idGuia]);
            }

            // 7. Atualiza alocação
            $stmtAlocDelete = $this->pdo->prepare('DELETE FROM guiasUsuarios WHERE id_guia = ?');
            $stmtAlocDelete->execute([$idGuia]);

            $alocacao = $dados['alocacao'] ?? 'nao-alocar';
            $servidoresAlocar = $dados['servidores'] ?? [];
            if (($alocacao === 'manual' || $alocacao === 'sorteio') && is_array($servidoresAlocar) && count($servidoresAlocar) > 0) {
                if ($alocacao === 'sorteio' && count($servidoresAlocar) > 1) {
                    $chaveAleatoria = array_rand($servidoresAlocar);
                    $servidoresAlocar = [$servidoresAlocar[$chaveAleatoria]];
                }

                $sqlAloc = "INSERT INTO guiasUsuarios (id_alocacao, id_usuario, id_guia, data_cadastro)
                            VALUES (?, ?, ?, ?)";
                $stmtAloc = $this->pdo->prepare($sqlAloc);

                if ($stmtAloc) {
                    $dataCadastro = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d');
                    foreach ($servidoresAlocar as $userId) {
                        $idAlocacao = $this->gerarId();
                        $stmtAloc->execute([$idAlocacao, $userId, $idGuia, $dataCadastro]);
                    }
                }
            }

            // 7.1 Força atualização do status da guia com base nas subtarefas atuais
            $this->atualizarStatusGuiaPorSubtarefas($idGuia);

            ParteDocumentoLock::commit($this->pdo, 'atualização da guia');

            return [
                'ok' => true,
                'id' => $idGuia,
                'message' => 'Guia atualizada com sucesso'
            ];

        } catch (Throwable $e) {
            $this->rollbackTransacaoGuiaAposFalha('atualização da guia');
            error_log('Erro ao atualizar guia: ' . $e->getMessage());
            $resposta = [
                'ok' => false,
                'id' => $idGuia,
                'message' => $this->mensagemFalhaGuia('atualizar')
            ];
            if (ParteDocumentoLock::isConnectionUnsafe($this->pdo)) {
                $resposta['resultado'] = 'verificacao_necessaria';
                $resposta['estado_gravacao'] = 'nao_confirmada';
                $resposta['id_referencia'] = $idGuia;
            }
            return $resposta;
        }
    }

    private function resultadoFalhaCoordenacao(string $operacao, ?string $id, ?array $resultado): array
    {
        $resposta = ['ok' => false, 'id' => $id, 'message' => 'Não foi possível ' . $operacao . ' a guia.'];
        if ($resultado === null) {
            return $resposta;
        }
        if (($resultado['ok'] ?? false) === true) {
            $resposta['resultado'] = 'verificacao_necessaria';
            $resposta['estado_gravacao'] = 'confirmada';
            $resposta['message'] = 'A gravação foi confirmada, mas não foi possível confirmar o encerramento da operação. Consulte a guia antes de tentar novamente.';
            if (!empty($resultado['id'])) $resposta['id_referencia'] = $resultado['id'];
            return $resposta;
        }
        if (($resultado['resultado'] ?? '') === 'verificacao_necessaria') {
            $resposta['resultado'] = 'verificacao_necessaria';
            $resposta['estado_gravacao'] = 'nao_confirmada';
            $resposta['message'] = $this->mensagemFalhaGuia($operacao);
            if (!empty($resultado['id_referencia'])) $resposta['id_referencia'] = $resultado['id_referencia'];
        }
        return $resposta;
    }

    private function mensagemFalhaGuia(string $operacao): string
    {
        if (ParteDocumentoLock::isConnectionUnsafe($this->pdo)) {
            return 'O resultado da operação não pôde ser confirmado. Consulte a guia antes de tentar novamente.';
        }
        return 'Não foi possível ' . $operacao . ' a guia.';
    }

    /** Mantém o lock CPF até o commit da transação completa da guia. */
    private function comLockCpfDaGuia(array $dados, callable $operacao): array
    {
        if (ParteDocumentoLock::isConnectionUnsafe($this->pdo)) {
            throw new RuntimeException('Conexão indisponível para alteração de guia.');
        }
        $reu = trim((string) ($dados['reu'] ?? ''));
        $ehIdExistente = $reu !== '' && preg_match('/^[A-Z]{2,4}-[A-Za-z0-9\\-]+$/', $reu) === 1;
        $cpf = trim((string) ($dados['cpf'] ?? ''));
        if ($reu === '' || $ehIdExistente || $cpf === '') {
            return $operacao();
        }
        return ParteDocumentoLock::run($this->pdo, 'CPF', $cpf, $operacao);
    }

    private function rollbackTransacaoGuiaAposFalha(string $operacao): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }

        try {
            if (!$this->pdo->rollBack()) {
                ParteDocumentoLock::markConnectionUnsafe($this->pdo);
                error_log('Rollback não confirmado em ' . $operacao . '; conexão isolada.');
            }
        } catch (Throwable $rollbackError) {
            ParteDocumentoLock::markConnectionUnsafe($this->pdo);
            error_log('Falha no rollback de ' . $operacao . '; conexão isolada: ' . get_class($rollbackError));
        }
    }

    /**
     * Retorna os dados completos de uma guia (incluindo dados auxiliares de réu e classe)
     */
    public function obterGuia(string $idGuia): ?array
    {
        // BD alterado: guia usa parte_id (FK partes.id). Nome do denunciado vem de partes.nome_completo.
        $sql = "SELECT 
                    g.*, 
                    proc.classe AS processo_classe,
                    c.descricao AS classe_desc,
                    pt.nome_completo AS denunciado_nome,
                    pt.cpf AS reu_cpf,
                    (SELECT ptt.numero FROM partes_telefones ptt WHERE ptt.parte_id = pt.id AND ptt.principal = 1 LIMIT 1) AS reu_telefone
                FROM guia g
                LEFT JOIN processos proc ON g.processo = proc.numero
                LEFT JOIN classes c ON proc.classe = c.cod
                LEFT JOIN partes pt ON g.parte_id = pt.id
                WHERE g.id_guia = ?
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idGuia]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
            'denunciado' => (string)($row['denunciado_nome'] ?? $row['denunciado'] ?? ''),
            'parte_id' => (string)($row['parte_id'] ?? ''),
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
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Lista paginada
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM guia" . $whereSql . " ORDER BY data_cadastro DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        
        $guias = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'data' => $guias
        ];
    }
}
