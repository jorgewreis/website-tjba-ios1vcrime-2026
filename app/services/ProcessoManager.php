<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/HelperUtils.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/ProcessoPrioridadesService.php';

/**
 * Facade legada para processos. Prioridades processuais usam {@see ProcessoPrioridadesService} / `processo_prioridades`.
 */
class ProcessoManager
{
    private PDO $pdo;
    private ?ProcessoPrioridadesService $prioridadesService = null;
    private ?ProcessosRepository $processosRepository = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function prioridades(): ProcessoPrioridadesService
    {
        if ($this->prioridadesService === null) {
            $this->prioridadesService = new ProcessoPrioridadesService($this->pdo);
        }

        return $this->prioridadesService;
    }

    private function processosRepo(): ProcessosRepository
    {
        if ($this->processosRepository === null) {
            $this->processosRepository = new ProcessosRepository($this->pdo);
        }

        return $this->processosRepository;
    }

    /**
     * Busca um processo pelo número
     */
    public function buscarProcesso(string $numero): ?array
    {
        $sql = "SELECT id_proc, numero, classe, data_cadastro FROM processos WHERE numero = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numero]);

        $processo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $processo ?: null;
    }

    /**
     * Cadastra um novo processo
     */
    public function cadastrarProcesso(string $numero, string $classe): bool
    {
        $existe = $this->buscarProcesso($numero);
        if ($existe) {
            if ($existe['classe'] !== $classe) {
                return $this->atualizarClasseProcesso($numero, $classe);
            }

            return true;
        }

        $novoId = Helper::gerarId();
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');

        $sql = 'INSERT INTO processos (id_proc, numero, classe, data_cadastro) VALUES (?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$novoId, $numero, $classe, $agora]);
    }

    /**
     * Substitui prioridades manuais/cadastro guias; regras automáticas são reavaliadas após.
     *
     * @param list<string> $prioridades códigos, rótulos ou tokens legados (ex.: reu-preso)
     */
    public function cadastrarPrioridades(string $numeroProcesso, array $prioridades): bool
    {
        try {
            return $this->prioridades()->substituirPrioridadesCadastroGuias($numeroProcesso, $prioridades);
        } catch (Throwable $e) {
            error_log('Erro ao cadastrar prioridades: ' . $e->getMessage());

            return false;
        }
    }

    /** @return list<string> rótulos ordenados */
    public function listarPrioridades(string $numeroProcesso): array
    {
        $proc = $this->processosRepo()->findByNumero($numeroProcesso);
        if ($proc === null) {
            return [];
        }
        $id = trim((string) ($proc['id'] ?? ''));
        if ($id === '') {
            return [];
        }

        return $this->prioridades()->listarRotulosPorProcessoId($id);
    }

    public function adicionarPrioridadeSeNaoExistir(string $numeroProcesso, string $prioridade): bool
    {
        $proc = $this->processosRepo()->findByNumero($numeroProcesso);
        if ($proc === null) {
            return false;
        }
        $id = trim((string) ($proc['id'] ?? ''));
        if ($id === '') {
            return false;
        }

        return $this->prioridades()->garantirPorRotuloOuCodigo($id, $prioridade);
    }

    public function removerPrioridade(string $numeroProcesso, string $prioridade): bool
    {
        $proc = $this->processosRepo()->findByNumero($numeroProcesso);
        if ($proc === null) {
            return false;
        }
        $id = trim((string) ($proc['id'] ?? ''));
        if ($id === '') {
            return false;
        }

        try {
            $this->prioridades()->removerPorCodigoOuRotulo($id, $prioridade, 0);

            return true;
        } catch (Throwable $e) {
            error_log('ProcessoManager::removerPrioridade: ' . $e->getMessage());

            return false;
        }
    }

    private function atualizarClasseProcesso(string $numero, string $classe): bool
    {
        $sql = 'UPDATE processos SET classe = ? WHERE numero = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$classe, $numero]);
    }
}
