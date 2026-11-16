<?php
declare(strict_types=1);

/**
 * Adaptador legado: expõe réu como partes + processos_partes para compatibilidade com Guias (api/guia).
 * Não usa mais tabelas reus/processoReus.
 */
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

class ReuManager
{
    private PDO $pdo;
    private PartesRepository $partesRepository;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->partesRepository = new PartesRepository($pdo);
        $this->processosRepository = new ProcessosRepository($pdo);
        $this->processosPartesRepository = new ProcessosPartesRepository($pdo);
    }

    /**
     * Busca uma parte (réu) pelo nome – compatível com retorno antigo (id_reu, nome, cpf, statusPrisao).
     */
    public function buscarReu(string $nome): ?array
    {
        $parte = $this->partesRepository->findByNomeCompleto($nome);
        if ($parte === null) {
            return null;
        }
        return [
            'id_reu' => (string) $parte['id'],
            'nome' => (string) ($parte['nome_completo'] ?? ''),
            'cpf' => (string) ($parte['cpf'] ?? ''),
            'statusPrisao' => 0,
        ];
    }

    /**
     * Garante parte por nome e opcional CPF; retorna id da parte (compatível com id_reu).
     */
    public function cadastrarReu(string $nome, ?string $cpf = null): ?string
    {
        try {
            return $this->partesRepository->ensureParteByNomeOuCpf($nome, $cpf);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Lista partes do polo passivo vinculadas ao processo (numero CNJ).
     *
     * @return array<int, array{id_reu: string, nome: string}>
     */
    public function listarReusPorProcesso(string $numeroProcesso): array
    {
        $numero = trim($numeroProcesso);
        if ($numero === '') {
            return [];
        }
        $processo = $this->processosRepository->findByNumero($numero);
        if ($processo === null) {
            return [];
        }
        $partes = $this->processosPartesRepository->findByProcessoId((string) $processo['id']);
        $tiposPassivos = ['reu', 'indiciado', 'sentenciado', 'requerido'];
        $out = [];
        foreach ($partes as $pp) {
            $tipo = trim((string) ($pp['tipo_parte'] ?? ''));
            if (!in_array($tipo, $tiposPassivos, true)) {
                continue;
            }
            $nome = trim((string) ($pp['nome_completo'] ?? ''));
            if ($nome !== '') {
                $out[] = ['id_reu' => (string) ($pp['parte_id'] ?? ''), 'nome' => $nome];
            }
        }
        return $out;
    }

    /**
     * Vincula parte (réu) ao processo no polo passivo como Sentenciado.
     */
    public function vincularReuProcesso(string $idReu, string $numeroProcesso): bool
    {
        $idReu = trim($idReu);
        $numeroProcesso = trim($numeroProcesso);
        if ($idReu === '' || $numeroProcesso === '') {
            return false;
        }
        try {
            $processoId = $this->processosRepository->ensureProcess($numeroProcesso, null, null, null);
            $existente = $this->processosPartesRepository->findByProcessoAndParte($processoId, $idReu);
            if ($existente !== null) {
                return true;
            }
            $this->processosPartesRepository->addParte(
                $processoId,
                $idReu,
                ProcessosPartesRepository::TIPO_SENTENCIADO,
                ProcessosPartesRepository::POLO_PASSIVO
            );
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
