<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../helpers/ProcessoPrioridadeOrigem.php';
require_once __DIR__ . '/../helpers/SalaAudienciasReuPresoDetector.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';

/**
 * Prioridades processuais unificadas por `processo_id` (código estável + origem).
 */
final class ProcessoPrioridadesService
{
    private PDO $connection;
    private ProcessoPrioridadesRepository $repository;
    private ProcessosPartesRepository $partesRepository;
    private ProcessosRepository $processosRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new ProcessoPrioridadesRepository($connection);
        $this->partesRepository = new ProcessosPartesRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
    }

    /** @return list<string> rótulos ordenados para exibição */
    public function listarRotulosPorProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }
        $rows = $this->repository->findByProcessoId($processoId);
        $labels = [];
        foreach ($rows as $row) {
            $c = trim((string) ($row['codigo'] ?? ''));
            if ($c !== '') {
                $labels[] = PrioridadesProcessuaisCatalog::labelForCodigo($c);
            }
        }
        $labels = array_values(array_unique($labels, SORT_STRING));
        sort($labels, SORT_LOCALE_STRING);

        return $labels;
    }

    /**
     * Estado para UI (pauta ou ficha do processo).
     *
     * @return array{
     *   processo_id: string,
     *   prioridades: list<array{codigo: string, label: string, origem: string}>,
     *   catalogo: list<array{codigo: string, label: string}>,
     *   codigos_bloqueados_remocao: list<string>,
     *   has_parte_menor: bool,
     *   has_reu_preso: bool,
     *   codigo_bloqueado_menor: string,
     *   codigo_bloqueado_reu_preso: string,
     *   processo_sigiloso: bool,
     *   codigo_bloqueado_sigiloso: string,
     *   sigiloso_obrigatorio_menor_ou_vd: bool
     * }
     */
    public function getEstadoEdicaoPorProcessoId(string $processoId, int $partesMenoresNaPauta = 0): array
    {
        $processoId = trim($processoId);
        $numero = '';
        $proc = $this->processosRepository->findById($processoId);
        if ($proc !== null) {
            $numero = trim((string) ($proc['numero_processo'] ?? ''));
        }

        $this->reconciliarAutomaticas($processoId, $numero, $partesMenoresNaPauta);

        $hasMenor = $this->partesRepository->countMenoresPorIdadeOuFlagNoProcesso($processoId) > 0
            || $partesMenoresNaPauta > 0;
        $hasReu = SalaAudienciasReuPresoDetector::processoTemReuPreso($this->connection, $processoId, $numero);
        $hasSigiloProcesso = $proc !== null && !empty($proc['sigiloso']);

        $rows = $this->repository->findByProcessoId($processoId);
        $prioridades = [];
        foreach ($rows as $row) {
            $c = trim((string) ($row['codigo'] ?? ''));
            if ($c === '') {
                continue;
            }
            $prioridades[] = [
                'codigo' => $c,
                'label' => PrioridadesProcessuaisCatalog::labelForCodigo($c),
                'origem' => trim((string) ($row['origem'] ?? '')),
            ];
        }

        $bloqueados = [];
        if ($hasMenor) {
            $bloqueados[] = PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor();
        }
        if ($hasReu) {
            $bloqueados[] = PrioridadesProcessuaisCatalog::codigoAutomaticaReuPreso();
        }
        $codMenor = PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor();
        $codVd = PrioridadesProcessuaisCatalog::codigoViolenciaDomestica();
        $sigilosoObrigatorioMenorOuVd = false;
        foreach ($prioridades as $p) {
            $cc = (string) ($p['codigo'] ?? '');
            if ($cc === $codMenor || $cc === $codVd) {
                $sigilosoObrigatorioMenorOuVd = true;
                break;
            }
        }
        if ($hasSigiloProcesso || $sigilosoObrigatorioMenorOuVd) {
            $bloqueados[] = PrioridadesProcessuaisCatalog::codigoSigiloso();
        }

        return [
            'processo_id' => $processoId,
            'prioridades' => $prioridades,
            'catalogo' => PrioridadesProcessuaisCatalog::sortedCatalogoItens(),
            'codigos_bloqueados_remocao' => array_values(array_unique($bloqueados)),
            'has_parte_menor' => $hasMenor,
            'has_reu_preso' => $hasReu,
            'processo_sigiloso' => $hasSigiloProcesso,
            'sigiloso_obrigatorio_menor_ou_vd' => $sigilosoObrigatorioMenorOuVd,
            'codigo_bloqueado_menor' => PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor(),
            'codigo_bloqueado_reu_preso' => PrioridadesProcessuaisCatalog::codigoAutomaticaReuPreso(),
            'codigo_bloqueado_sigiloso' => PrioridadesProcessuaisCatalog::codigoSigiloso(),
        ];
    }

    /** Para fluxos legados (ex.: rótulo em português); não lança se código inválido — retorna false. */
    public function garantirPorRotuloOuCodigo(string $processoId, string $entrada): bool
    {
        $processoId = trim($processoId);
        $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo(trim($entrada));
        if ($processoId === '' || $codigo === null || !PrioridadesProcessuaisCatalog::isValidCodigo($codigo)) {
            return false;
        }
        if ($this->repository->findOneByProcessoAndCodigo($processoId, $codigo) !== null) {
            $this->reconciliarAutomaticas($processoId, $this->numeroDoProcesso($processoId), 0);

            return true;
        }
        $this->repository->insert($processoId, $codigo, ProcessoPrioridadeOrigem::MANUAL, null);
        $this->reconciliarAutomaticas($processoId, $this->numeroDoProcesso($processoId), 0);

        return true;
    }

    /**
     * @return bool true se a prioridade ainda não existia para o processo (linha nova); false se já existia (apenas reconciliação/atualização)
     */
    public function adicionarManualPorCodigoOuRotulo(string $processoId, string $entrada): bool
    {
        $processoId = trim($processoId);
        $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo(trim($entrada));
        if ($processoId === '' || $codigo === null || !PrioridadesProcessuaisCatalog::isValidCodigo($codigo)) {
            throw new InvalidArgumentException('Selecione uma prioridade válida do catálogo.');
        }

        $existente = $this->repository->findOneByProcessoAndCodigo($processoId, $codigo);
        $foiInclusaoNova = $existente === null;

        $this->repository->upsert($processoId, $codigo, ProcessoPrioridadeOrigem::MANUAL, null);
        $this->reconciliarAutomaticas($processoId, $this->numeroDoProcesso($processoId), 0);

        return $foiInclusaoNova;
    }

    public function removerPorCodigoOuRotulo(string $processoId, string $entrada, int $partesMenoresNaPauta = 0): void
    {
        $processoId = trim($processoId);
        $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo(trim($entrada));
        if ($processoId === '' || $codigo === null) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }

        $numero = $this->numeroDoProcesso($processoId);
        $this->reconciliarAutomaticas($processoId, $numero, $partesMenoresNaPauta);
        $hasMenor = $this->partesRepository->countMenoresPorIdadeOuFlagNoProcesso($processoId) > 0
            || $partesMenoresNaPauta > 0;
        $hasReu = SalaAudienciasReuPresoDetector::processoTemReuPreso($this->connection, $processoId, $numero);
        if ($hasMenor && $codigo === PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor()) {
            throw new InvalidArgumentException('Esta prioridade é mantida automaticamente enquanto houver parte menor no cadastro do processo.');
        }
        if ($hasReu && $codigo === PrioridadesProcessuaisCatalog::codigoAutomaticaReuPreso()) {
            throw new InvalidArgumentException('Esta prioridade é mantida automaticamente enquanto houver réu preso no cadastro do processo.');
        }
        $codMenorRem = PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor();
        $codVdRem = PrioridadesProcessuaisCatalog::codigoViolenciaDomestica();
        $codSigRem = PrioridadesProcessuaisCatalog::codigoSigiloso();
        $rowsRem = $this->repository->findByProcessoId($processoId);
        $temMenorOuVdNaLista = false;
        foreach ($rowsRem as $rw) {
            $cr = trim((string) ($rw['codigo'] ?? ''));
            if ($cr === $codMenorRem || $cr === $codVdRem) {
                $temMenorOuVdNaLista = true;
                break;
            }
        }
        if ($codigo === $codSigRem && $temMenorOuVdNaLista) {
            throw new InvalidArgumentException('A prioridade Sigiloso é obrigatória enquanto existir prioridade de menor ou de violência doméstica.');
        }
        $procRem = $this->processosRepository->findById($processoId);
        $sigProc = $procRem !== null && !empty($procRem['sigiloso']);
        if ($sigProc && $codigo === $codSigRem) {
            throw new InvalidArgumentException('Esta prioridade é mantida automaticamente enquanto o processo estiver marcado como sigiloso.');
        }

        if ($this->repository->findOneByProcessoAndCodigo($processoId, $codigo) === null) {
            return;
        }

        $this->repository->deleteByProcessoIdAndCodigo($processoId, $codigo);
    }

    /**
     * Substitui prioridades vindas do cadastro de guias (não apaga regras automáticas).
     *
     * @param list<string> $tokens códigos, rótulos ou tokens legados (ex.: reu-preso)
     */
    public function substituirPrioridadesCadastroGuias(string $numeroProcesso, array $tokens): bool
    {
        $numeroProcesso = trim($numeroProcesso);
        if ($numeroProcesso === '') {
            return false;
        }
        $proc = $this->processosRepository->findByNumero($numeroProcesso);
        if ($proc === null) {
            return false;
        }
        $processoId = trim((string) ($proc['id'] ?? ''));
        if ($processoId === '') {
            return false;
        }

        $this->repository->deleteByProcessoIdAndOrigensCadastroUsuario($processoId);

        foreach ($tokens as $t) {
            $codigo = PrioridadesProcessuaisCatalog::normalizeToCodigo((string) $t);
            if ($codigo === null || !PrioridadesProcessuaisCatalog::isValidCodigo($codigo)) {
                continue;
            }
            $this->repository->insert($processoId, $codigo, ProcessoPrioridadeOrigem::CADASTRO_GUIAS, null);
        }

        $this->reconciliarAutomaticas($processoId, trim((string) ($proc['numero_processo'] ?? '')), 0);

        return true;
    }

    /** Na criação da medida protetiva (mesma transação externa). */
    public function registrarViolenciaDomesticaMedidaProtetiva(string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }
        $codigo = PrioridadesProcessuaisCatalog::codigoViolenciaDomestica();
        $this->repository->upsert(
            $processoId,
            $codigo,
            ProcessoPrioridadeOrigem::REGRA_VIOLENCIA_DOMESTICA_MP,
            ['fonte' => 'medida_protetiva']
        );
    }

    /**
     * Insere prioridades a partir das flags da vítima no formulário da MP (idoso, criança, deficiência).
     *
     * @param array<string, int|bool> $flags
     */
    public function registrarPrioridadesFlagsVitimaMedida(string $processoId, array $flags): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }

        $map = [
            'prioridade_mulher' => null,
            'prioridade_crianca_adolescente' => 'crianca_adolescente',
            'prioridade_idoso' => 'idoso',
            'prioridade_pessoa_deficiencia' => 'pessoa_deficiencia',
        ];

        foreach ($map as $flag => $codigo) {
            if ($codigo === null) {
                continue;
            }
            if (empty($flags[$flag])) {
                continue;
            }
            if (!PrioridadesProcessuaisCatalog::isValidCodigo($codigo)) {
                continue;
            }
            $this->repository->upsert($processoId, $codigo, ProcessoPrioridadeOrigem::REGRA_FLAGS_VITIMA_MP, ['flag' => $flag]);
        }
    }

    public function reconciliarAutomaticas(string $processoId, string $numeroProcesso, int $partesMenoresNaPauta = 0): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }
        $this->syncMenor($processoId, $partesMenoresNaPauta);
        $this->syncReuPreso($processoId, $numeroProcesso);
        $this->syncSigiloso($processoId);
    }

    /**
     * @param int $partesMenoresNaPauta quantidade de partes com maioridade "menor" em audiências ativas do processo (complemento ao cadastro).
     */
    public function syncMenor(string $processoId, int $partesMenoresNaPauta = 0): void
    {
        $codigo = PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor();
        $nCad = $this->partesRepository->countMenoresPorIdadeOuFlagNoProcesso($processoId);
        if ($nCad > 0 || $partesMenoresNaPauta > 0) {
            $this->repository->upsert($processoId, $codigo, ProcessoPrioridadeOrigem::REGRA_MENOR, null);
        } else {
            $this->repository->deleteByProcessoIdCodigoAndOrigens(
                $processoId,
                $codigo,
                [ProcessoPrioridadeOrigem::REGRA_MENOR]
            );
        }
    }

    public function syncReuPreso(string $processoId, string $numeroProcesso): void
    {
        $codigo = PrioridadesProcessuaisCatalog::codigoAutomaticaReuPreso();
        $tem = SalaAudienciasReuPresoDetector::processoTemReuPreso($this->connection, $processoId, trim($numeroProcesso));
        if ($tem) {
            $this->repository->upsert($processoId, $codigo, ProcessoPrioridadeOrigem::REGRA_REU_PRESO, null);
        } else {
            $this->repository->deleteByProcessoIdCodigoAndOrigens(
                $processoId,
                $codigo,
                [ProcessoPrioridadeOrigem::REGRA_REU_PRESO]
            );
        }
    }

    public function syncSigiloso(string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }
        $codigoSig = PrioridadesProcessuaisCatalog::codigoSigiloso();
        $codMenor = PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor();
        $codVd = PrioridadesProcessuaisCatalog::codigoViolenciaDomestica();

        $proc = $this->processosRepository->findById($processoId);
        $procSigFlag = $proc !== null && !empty($proc['sigiloso']);

        $rows = $this->repository->findByProcessoId($processoId);
        $codigos = [];
        foreach ($rows as $row) {
            $c = trim((string) ($row['codigo'] ?? ''));
            if ($c !== '') {
                $codigos[$c] = true;
            }
        }
        $temMenorOuVD = isset($codigos[$codMenor]) || isset($codigos[$codVd]);

        if ($procSigFlag) {
            $this->repository->upsert($processoId, $codigoSig, ProcessoPrioridadeOrigem::REGRA_SIGILOSO, null);

            return;
        }
        if ($temMenorOuVD) {
            $this->repository->upsert($processoId, $codigoSig, ProcessoPrioridadeOrigem::REGRA_DERIVADA_MENOR_OU_VD, null);

            return;
        }
        $this->repository->deleteByProcessoIdCodigoAndOrigens(
            $processoId,
            $codigoSig,
            [
                ProcessoPrioridadeOrigem::REGRA_SIGILOSO,
                ProcessoPrioridadeOrigem::REGRA_DERIVADA_MENOR_OU_VD,
            ]
        );
    }

    /** Job semanal / manutenção: reavalia regras que não são VD (VD não é removida automaticamente). */
    public function reconciliarTodosProcessosComPrioridade(): int
    {
        $ids = $this->repository->listDistinctProcessoIds();
        $n = 0;
        foreach ($ids as $pid) {
            $numero = $this->numeroDoProcesso($pid);
            $this->syncMenor($pid);
            $this->syncReuPreso($pid, $numero);
            $this->syncSigiloso($pid);
            $n++;
        }

        return $n;
    }

    private function numeroDoProcesso(string $processoId): string
    {
        $proc = $this->processosRepository->findById($processoId);

        return $proc !== null ? trim((string) ($proc['numero_processo'] ?? '')) : '';
    }
}
