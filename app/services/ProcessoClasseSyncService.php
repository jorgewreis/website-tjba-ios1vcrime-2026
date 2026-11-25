<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/ProcessoPrioridadesService.php';

/**
 * Leitura de processo/classe por CNJ e sincronização da classe a partir do DataJud.
 */
final class ProcessoClasseSyncService
{
    private PDO $connection;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    /** Classe CNJ 1268 — Medida Protetiva: prioridades de violência doméstica e sigiloso (derivado). */
    private const CODIGO_CLASSE_MEDIDA_PROTETIVA = '1268';

    private function aplicarPrioridadesMedidaProtetivaSeClasse1268(string $processoId, string $codigoClasseNormalizado, string $numeroProcesso): void
    {
        if ($codigoClasseNormalizado !== self::CODIGO_CLASSE_MEDIDA_PROTETIVA) {
            return;
        }
        $prioridades = new ProcessoPrioridadesService($this->connection);
        $prioridades->registrarPrioridadesCadastroMedidaProtetiva($processoId);
        $prioridades->reconciliarAutomaticas($processoId, trim($numeroProcesso), 0);
    }

    /**
     * @return array{processo: ?array{id: string, numero_processo: string}, classe: ?array{id: string, codigo: string, nome: string}}
     */
    public function readByNumero20(string $digits20): array
    {
        $digits20 = CnjValidator::digits20OrThrow($digits20);

        $processo = $this->processosRepository->findByNumero($digits20);
        if ($processo === null) {
            return ['processo' => null, 'classe' => null];
        }

        $pid = trim((string) ($processo['id'] ?? ''));
        $numero = trim((string) ($processo['numero_processo'] ?? ''));
        $classeId = trim((string) ($processo['classe_id'] ?? ''));

        $outProcesso = ['id' => $pid, 'numero_processo' => $numero];
        $classe = null;
        if ($classeId !== '') {
            $row = $this->classesRepository->findById($classeId);
            if ($row !== null) {
                $classe = [
                    'id' => (string) $row['id'],
                    'codigo' => trim((string) ($row['codigo'] ?? '')),
                    'nome' => trim((string) ($row['nome'] ?? '')),
                ];
            }
        }

        return ['processo' => $outProcesso, 'classe' => $classe];
    }

    /**
     * Garante registro em `classes`, vincula a `processos` (cria processo se não existir).
     * Comparação de mudança por código normalizado.
     *
     * @return array{action: 'noop'|'updated'|'created', processo_id: string, classe_id: string}
     */
    public function syncFromDataJud(string $digits20, string $codigoRaw, string $descricaoRaw, bool $requireExistingProcess = false): array
    {
        $digits20 = CnjValidator::digits20OrThrow($digits20);

        $codigo = ClassesRepository::normalizeCodigo($codigoRaw);
        if ($codigo === '') {
            throw new InvalidArgumentException('Código da classe inválido.');
        }

        $numeroFormatado = $this->formatarCnj20($digits20);
        $processo = $this->processosRepository->findByNumero($digits20);
        if ($requireExistingProcess && $processo === null) {
            throw new InvalidArgumentException('Processo não encontrado no cadastro local.');
        }

        $desc = trim($descricaoRaw);
        $nome = $desc !== '' ? TextFormatter::formatProperName($desc, 200) : $codigo;
        $classeIdResolved = $this->classesRepository->ensureClass($codigo, $nome);

        if ($processo === null) {
            $pid = $this->processosRepository->ensureProcess($numeroFormatado, $classeIdResolved, null, null);
            $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($pid, $codigo, $numeroFormatado);

            return [
                'action' => 'created',
                'processo_id' => $pid,
                'classe_id' => $classeIdResolved,
            ];
        }

        $pid = trim((string) ($processo['id'] ?? ''));
        $existingClasseId = trim((string) ($processo['classe_id'] ?? ''));

        if ($existingClasseId === '') {
            $this->processosRepository->updateClasseForProcesso($pid, $classeIdResolved);
            $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($pid, $codigo, $numeroFormatado);

            return [
                'action' => 'updated',
                'processo_id' => $pid,
                'classe_id' => $classeIdResolved,
            ];
        }

        $existingClasse = $this->classesRepository->findById($existingClasseId);
        if ($existingClasse === null) {
            $this->processosRepository->updateClasseForProcesso($pid, $classeIdResolved);
            $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($pid, $codigo, $numeroFormatado);

            return [
                'action' => 'updated',
                'processo_id' => $pid,
                'classe_id' => $classeIdResolved,
            ];
        }

        $existingCodigo = ClassesRepository::normalizeCodigo((string) ($existingClasse['codigo'] ?? ''));
        if ($existingCodigo === $codigo) {
            return [
                'action' => 'noop',
                'processo_id' => $pid,
                'classe_id' => $existingClasseId,
            ];
        }

        $this->processosRepository->updateClasseForProcesso($pid, $classeIdResolved);
        $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($pid, $codigo, $numeroFormatado);

        return [
            'action' => 'updated',
            'processo_id' => $pid,
            'classe_id' => $classeIdResolved,
        ];
    }

    /**
     * Atualiza a classe vinculada ao processo de forma manual (fluxo da tela "editar processo").
     *
     * Regras:
     * - Se a classe (pelo código) já existe no BD, apenas vincula ao processo. O nome informado pelo
     *   usuário é ignorado para não permitir renomear classes em uso global.
     * - Se a classe não existe, exige o nome (será cadastrada via `ensureClass`) e vincula ao processo.
     *
     * @return array{action: 'linked'|'created_and_linked', processo_id: string, classe: array{id: string, codigo: string, nome: string}}
     */
    public function updateClasseManual(string $processoId, string $codigoRaw, ?string $nomeRaw): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            throw new InvalidArgumentException('Identificador do processo é obrigatório.');
        }

        $processo = $this->processosRepository->findById($processoId);
        if ($processo === null) {
            throw new InvalidArgumentException('Processo não encontrado.');
        }
        $numeroProcesso = trim((string) ($processo['numero_processo'] ?? ''));

        $codigo = ClassesRepository::normalizeCodigo($codigoRaw);
        if ($codigo === '') {
            throw new InvalidArgumentException('Código da classe é obrigatório.');
        }

        $existente = $this->classesRepository->findByCodigo($codigo);
        if ($existente !== null) {
            $classeId = (string) $existente['id'];
            $this->processosRepository->updateClasseForProcesso($processoId, $classeId);
            $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($processoId, $codigo, $numeroProcesso);

            return [
                'action' => 'linked',
                'processo_id' => $processoId,
                'classe' => [
                    'id' => $classeId,
                    'codigo' => (string) ($existente['codigo'] ?? $codigo),
                    'nome' => (string) ($existente['nome'] ?? ''),
                ],
            ];
        }

        $nome = trim((string) ($nomeRaw ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome da classe para cadastrar.');
        }

        $classeId = $this->classesRepository->ensureClass($codigo, $nome);
        $this->processosRepository->updateClasseForProcesso($processoId, $classeId);
        $this->aplicarPrioridadesMedidaProtetivaSeClasse1268($processoId, $codigo, $numeroProcesso);
        $criada = $this->classesRepository->findById($classeId);

        return [
            'action' => 'created_and_linked',
            'processo_id' => $processoId,
            'classe' => [
                'id' => $classeId,
                'codigo' => (string) ($criada['codigo'] ?? $codigo),
                'nome' => (string) ($criada['nome'] ?? $nome),
            ],
        ];
    }

    private function formatarCnj20(string $digits): string
    {
        if (strlen($digits) !== 20) {
            return $digits;
        }

        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4)
        );
    }
}
