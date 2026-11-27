<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/ProfileNormalizer.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/ProcessoSetorAuthorization.php';
require_once __DIR__ . '/../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../helpers/ProcessoPrioridadeOrigem.php';
require_once __DIR__ . '/../repositories/ProcessoNivelSigiloRepository.php';

final class ProcessoSigiloCadastroErro extends RuntimeException
{
    public function __construct(public readonly int $http, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}

final class ProcessoSigiloCadastroService
{
    public function __construct(private PDO $db)
    {
    }

    public static function perfilPermitido(array $perfis): bool
    {
        $normalizados = ProfileNormalizer::normalize($perfis);
        return ProfileNormalizer::isElevated($normalizados) || ProfileNormalizer::hasAnyCargoAreaComposite($normalizados);
    }

    /** Retorna as limitações da edição. A avaliação é repetida na transação de escrita. */
    public function preflight(string $id): array
    {
        if (!(new ProcessoNivelSigiloRepository($this->db))->disponivel()) {
            throw new ProcessoSigiloCadastroErro(503, 'Registro do nível indisponível até a atualização do banco.');
        }
        $this->validarAuditoria();
        return ['protecao' => $this->haProtecao($id, false)];
    }

    public function alterar(string $id, mixed $classificacao, mixed $confirmacao, string $ator, array $perfis, string $ip): array
    {
        if ($ator === '' || !self::perfilPermitido($perfis)) {
            throw new ProcessoSigiloCadastroErro(403, 'Acesso negado ao processo.');
        }
        if (preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $id) !== 1) {
            throw new ProcessoSigiloCadastroErro(400, 'Identificador de processo inválido.');
        }
        $publico = $classificacao === 'publico';
        $nivel = ProcessoNivelSigilo::classificado($classificacao);
        if (!$publico && $nivel === null) {
            throw new ProcessoSigiloCadastroErro(400, 'Selecione explicitamente Público ou um nível de 1 a 5.');
        }
        if ($this->db->inTransaction() || !$this->db->beginTransaction()) {
            throw new RuntimeException('Não foi possível iniciar a transação de sigilo.');
        }

        try {
            if (!ProcessoSetorAuthorization::canModifyProcess($this->db, $ator, $id, true)) {
                throw new ProcessoSigiloCadastroErro(403, 'Acesso negado ao processo.');
            }
            if (!(new ProcessoNivelSigiloRepository($this->db))->disponivel()) {
                throw new ProcessoSigiloCadastroErro(503, 'Registro do nível indisponível até a atualização do banco.');
            }
            $this->validarAuditoria();
            $antes = $this->estado($id);
            $retirandoSigilo = $publico && $antes['sigiloso'];
            if ($retirandoSigilo && $confirmacao !== true) {
                throw new ProcessoSigiloCadastroErro(400, 'Confirme que conferiu no PJe antes de marcar o processo como público.');
            }
            $alerta = $this->haProtecao($id, true);
            $depois = ['sigiloso' => !$publico, 'nivel_sigilo' => $nivel];
            $reduz = $publico || ($antes['sigiloso'] && $antes['nivel_sigilo'] !== null && $nivel < $antes['nivel_sigilo']);
            if ($reduz && $alerta && $antes !== $depois) {
                throw new ProcessoSigiloCadastroErro(409, 'O processo tem uma proteção independente. Não é possível reduzir o sigilo pela ficha.');
            }
            if ($antes !== $depois) {
                $write = $this->db->prepare('UPDATE processos SET sigiloso = :sigilo, nivel_sigilo = :nivel,
                    updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $write->bindValue(':sigilo', $depois['sigiloso'] ? 1 : 0, PDO::PARAM_INT);
                $write->bindValue(':nivel', $nivel, $nivel === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $write->bindValue(':id', $id);
                if (!$write->execute() || $write->rowCount() !== 1) throw new RuntimeException('Sigilo não confirmado.');
                $auditoriaNova = $depois;
                if ($retirandoSigilo) $auditoriaNova['conferencia_pje_declarada'] = true;
                $this->auditar($id, $ator, $ip, $antes, $auditoriaNova);
            }
            if (!$this->db->commit()) throw new RuntimeException('Sigilo não confirmado.');
            return ['success'=>true, 'alterado'=>$antes !== $depois] + $depois;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $erro;
        }
    }

    private function estado(string $id): array
    {
        $sql = 'SELECT sigiloso, nivel_sigilo FROM processos WHERE id = :id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $select = $this->db->prepare($sql);
        $select->execute([':id'=>$id]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        $select->closeCursor();
        if ($row === false) throw new RuntimeException('Processo não disponível após autorização.');
        $nivel = ProcessoNivelSigilo::classificado($row['nivel_sigilo']);
        if ($row['nivel_sigilo'] !== null && $nivel === null) {
            throw new ProcessoSigiloCadastroErro(503, 'O nível cadastrado não pôde ser validado. Nenhuma alteração foi feita.');
        }
        return ['sigiloso'=>(bool) $row['sigiloso'], 'nivel_sigilo'=>$nivel];
    }

    private function validarAuditoria(): void
    {
        try {
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $stmt = $this->db->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('processos', 'logs_sistema') AND ENGINE = 'InnoDB'");
                if ((int) $stmt->fetchColumn() !== 2) throw new RuntimeException('Auditoria não transacional.');
                $stmt->closeCursor();
            }
            $stmt = $this->db->query('SELECT id_log_sistema, usuario, modulo, acao, tabela_afetada,
                registro_id, dados_anteriores, dados_novos, ip, data_hora FROM logs_sistema WHERE 1 = 0');
            if (!$stmt) throw new RuntimeException('Auditoria indisponível.');
            $stmt->closeCursor();
        } catch (Throwable $erro) {
            throw new ProcessoSigiloCadastroErro(503, 'A alteração está indisponível porque a auditoria não pode ser verificada.');
        }
    }

    private function auditar(string $id, string $ator, string $ip, array $antes, array $depois): void
    {
        $logId = IdGenerator::generateUniquePrefixedId($this->db, 'logs_sistema', 'id_log_sistema', 'LGS', 30);
        $insert = $this->db->prepare("INSERT INTO logs_sistema
            (id_log_sistema, usuario, modulo, acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip, data_hora)
            VALUES (:log, :ator, 'processos', 'sigilo-cadastral', 'processos', :id, :anterior, :novo, :ip, CURRENT_TIMESTAMP)");
        if (!$insert->execute([
            ':log'=>$logId, ':ator'=>$ator, ':id'=>$id,
            ':anterior'=>json_encode($antes, JSON_THROW_ON_ERROR), ':novo'=>json_encode($depois, JSON_THROW_ON_ERROR),
            ':ip'=>$ip,
        ]) || $insert->rowCount() !== 1) throw new RuntimeException('Auditoria de sigilo não registrada.');
    }

    private function haProtecao(string $id, bool $bloquear): bool
    {
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $lock = $bloquear && $mysql ? ' FOR UPDATE' : '';
        $consultar = function (string $sql) use ($id, $lock): array {
            $stmt = $this->db->prepare($sql . $lock);
            if (!$stmt->execute([':id'=>$id])) throw new RuntimeException('Fonte de proteção indisponível.');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        try {
            $classe = $consultar('SELECT p.classe_id, c.codigo FROM processos p LEFT JOIN classes c ON c.id = p.classe_id WHERE p.id = :id');
            if (!$classe || (!empty($classe[0]['classe_id']) && ($classe[0]['codigo'] ?? null) === null)) {
                throw new RuntimeException('Classe não verificável.');
            }
            $prioridades = $consultar('SELECT codigo, origem FROM processo_prioridades WHERE processo_id = :id');
            $idade = $mysql
                ? 'TIMESTAMPDIFF(YEAR, pessoa.data_nascimento, CURDATE()) < 18'
                : "date(pessoa.data_nascimento, '+18 years') > date('now')";
            $partes = $consultar("SELECT pessoa.id AS pessoa_id, CASE
                WHEN pp.prioridade_crianca_adolescente = 1 OR ($idade) THEN 1 ELSE 0 END AS menor
                FROM processos_partes pp LEFT JOIN partes pessoa ON pessoa.id = pp.parte_id WHERE pp.processo_id = :id");
            $pauta = $consultar("SELECT pp.maioridade FROM sala_audiencias_pauta pa
                JOIN sala_audiencias_pauta_parte pp ON pp.pauta = pa.id_pauta
                WHERE pa.processo = :id AND pa.ativo = 1 AND pp.ativo = 1");
        } catch (Throwable $erro) {
            throw new ProcessoSigiloCadastroErro(503, 'Não foi possível confirmar todas as fontes de proteção do processo. Nenhuma alteração foi feita.');
        }

        if (trim((string) ($classe[0]['codigo'] ?? '')) === '1268') return true;
        foreach ($prioridades as $item) {
            $raw = trim((string) $item['codigo']);
            $minusculo = mb_strtolower($raw, 'UTF-8');
            $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo($raw)
                ?? PrioridadesProcessuaisCatalog::normalizeToCodigo($minusculo);
            $rotuloLegado = preg_replace('/[-_ ]+/', ' ', $minusculo);
            if (in_array($rotuloLegado, [
                'segredo de justiça', 'segredo de justica', 'sigilo', 'sigilo mínimo', 'sigilo minimo',
                'sigilo médio', 'sigilo medio', 'sigilo intenso', 'sigilo absoluto', 'violência doméstica',
                'criança ou adolescente', 'menor de idade',
            ], true)) return true;
            if ($codigo === PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor()
                || in_array($codigo, PrioridadesProcessuaisCatalog::codigosQueDerivamSigiloso(), true)
                || ($codigo === PrioridadesProcessuaisCatalog::codigoSigiloso()
                    && $item['origem'] !== ProcessoPrioridadeOrigem::REGRA_SIGILOSO)) return true;
        }
        foreach ($partes as $item) {
            if ($item['pessoa_id'] === null) {
                throw new ProcessoSigiloCadastroErro(503, 'Não foi possível verificar uma parte vinculada. Nenhuma alteração foi feita.');
            }
            if ((int) $item['menor'] === 1) return true;
        }
        foreach ($pauta as $item) {
            if (mb_strtolower(trim((string) $item['maioridade']), 'UTF-8') === 'menor') return true;
        }
        return false;
    }
}
