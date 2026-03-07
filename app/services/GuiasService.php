<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GuiasRepository.php';
require_once __DIR__ . '/../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/GuiasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class GuiasService
{
    private PDO $connection;
    private GuiasRepository $repository;
    private GuiasSubtarefasRepository $subtarefasRepository;
    private GuiasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;

    private const REGRA_ALOCAR_GUIAS = 'ALLOCATE-GUIAS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new GuiasRepository($connection);
        $this->subtarefasRepository = new GuiasSubtarefasRepository($connection);
        $this->alocadosRepository = new GuiasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
    }

    public function listGuias(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idGuia = (string) ($row['IdGuia'] ?? '');
            if ($idGuia !== '') {
                $this->subtarefasRepository->ensurePrimeiraSubtarefa($idGuia);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByGuiaId($idGuia);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByGuiaId($idGuia);
                $alocadosRaw = $this->alocadosRepository->findByGuiaId($idGuia);
                $rows[$i]['Alocados'] = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $rows[$i]['Alocados'][] = [
                        'iniciais' => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id' => $a['usuario_id'] ?? '',
                        'tipo' => $this->normalizeTipoAlocado($a['tipo'] ?? ''),
                    ];
                }
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['Alocados'] = [];
            }
        }
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function getTipoCounts(array $filters): array
    {
        return $this->repository->countByTipo($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $denunciado = trim((string) ($payload['denunciado'] ?? ''));
        if ($denunciado !== '') {
            $denunciado = TextFormatter::formatProperName($denunciado, 255);
        }

        $tipo = trim((string) ($payload['tipo'] ?? 'Guia - Tipo não informado'));
        if ($tipo === '') {
            $tipo = 'Guia - Tipo não informado';
        }

        $idGuia = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'GUI',
            30
        );

        $row = [
            'id' => $idGuia,
            'numero_processo' => $numeroFormatado,
            'processo_id' => $payload['processo_id'] ?? null,
            'denunciado' => $denunciado !== '' ? $denunciado : null,
            'reu_id' => $payload['reu_id'] ?? null,
            'tipo' => $tipo,
            'etapa' => trim((string) ($payload['etapa'] ?? '')),
            'status' => 'pendente',
            'regime' => trim((string) ($payload['regime'] ?? '')),
            'data_fato' => $this->parseDate($payload['data_fato'] ?? null),
            'dias_multa' => isset($payload['dias_multa']) ? (int) $payload['dias_multa'] : null,
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
            'user_cadastro' => trim((string) ($payload['usuario_cadastro_id'] ?? '')),
        ];
        if ($row['etapa'] === '') {
            $row['etapa'] = null;
        }
        if ($row['regime'] === '') {
            $row['regime'] = null;
        }
        if ($row['observacoes'] === '') {
            $row['observacoes'] = null;
        }
        if ($row['user_cadastro'] === '') {
            $row['user_cadastro'] = null;
        }

        $this->repository->insert($row);

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($usuarioCadastroId !== '') {
            try {
                $this->alocadosRepository->addAlocado($idGuia, $usuarioCadastroId, GuiasAlocadosRepository::TIPO_ALL);
            } catch (RuntimeException $e) {
                // limite já atingido ou duplicata, ignorar
            }
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_GUIAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                try {
                    $this->alocadosRepository->addAlocado($idGuia, $usuarioIdRegra, GuiasAlocadosRepository::TIPO_RULE);
                } catch (RuntimeException $e) {
                    // limite ou duplicata
                }
            }
        }

        $this->subtarefasRepository->create(
            $idGuia,
            GuiasSubtarefasRepository::TIPO_ORDEM_1,
            1,
            'em_andamento',
            null
        );

        $this->repository->update($idGuia, ['status' => 'em_andamento']);

        return $idGuia;
    }

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $usuarioId = trim((string) ($payload['usuario_id'] ?? ''));
        unset($payload['usuario_id']);
        if ($usuarioId !== '') {
            try {
                $this->alocadosRepository->addAlocado($id, $usuarioId);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        $allowed = [
            'numero_processo', 'processo_id', 'denunciado', 'reu_id', 'tipo', 'etapa', 'status',
            'regime', 'data_fato', 'dias_multa', 'observacoes', 'user_cadastro',
        ];
        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $filtered[$key] = $payload[$key];
            }
        }
        if (isset($filtered['denunciado']) && is_string($filtered['denunciado']) && $filtered['denunciado'] !== '') {
            $filtered['denunciado'] = TextFormatter::formatProperName($filtered['denunciado'], 255);
        }
        if (isset($filtered['data_fato'])) {
            $filtered['data_fato'] = $this->parseDate($filtered['data_fato']);
        }

        return $this->repository->update($id, $filtered);
    }

    public function cancel(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }
        return $this->repository->update($id, ['status' => 'cancelada']);
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $this->subtarefasRepository->deleteByGuiaId($id);
        $this->alocadosRepository->deleteByGuiaId($id);
        return $this->repository->delete($id);
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $guiaId = trim((string) ($subtarefa['guia_id'] ?? ''));

        if ($guiaId === '') {
            throw new InvalidArgumentException('Guia da subtarefa não identificada.');
        }

        $usuarioIdMovimentacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '') {
            try {
                $this->alocadosRepository->addAlocado($guiaId, $usuarioIdMovimentacao);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        $proximoTipo = $this->subtarefasRepository->obterProximoTipo($ordem);
        if ($proximoTipo !== null) {
            $proximaOrdem = $ordem + 1;
            $proxima = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, $proximaOrdem);
            if ($proxima === null) {
                $this->subtarefasRepository->create($guiaId, $proximoTipo, $proximaOrdem, 'em_andamento', null);
            } else {
                $statusProx = trim((string) ($proxima['status'] ?? ''));
                if ($statusProx !== 'concluida') {
                    $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
                }
            }
            $this->repository->update($guiaId, ['status' => 'em_andamento']);
        } else {
            $this->repository->update($guiaId, ['status' => 'concluída']);
        }
    }

    public function criarPrimeiraSubtarefa(string $guiaId, ?string $usuarioId = null): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            throw new InvalidArgumentException('Identificador da guia é obrigatório.');
        }
        if ($this->repository->findById($guiaId) === null) {
            throw new InvalidArgumentException('Guia não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByGuiaId($guiaId);
        if (count($existentes) > 0) {
            $this->subtarefasRepository->ensurePrimeiraSubtarefa($guiaId);
            $ordem1 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 1);
            if ($ordem1 !== null && trim((string) ($ordem1['status'] ?? '')) !== 'concluida') {
                $this->subtarefasRepository->atualizarStatus((string) $ordem1['id'], 'em_andamento');
            }
            $this->repository->update($guiaId, ['status' => 'em_andamento']);
            if ($usuarioId !== null && trim($usuarioId) !== '') {
                try {
                    $this->alocadosRepository->addAlocado($guiaId, trim($usuarioId));
                } catch (RuntimeException $e) {
                    // limite ou duplicata
                }
            }
            return;
        }
        $this->subtarefasRepository->create(
            $guiaId,
            GuiasSubtarefasRepository::TIPO_ORDEM_1,
            1,
            'em_andamento',
            null
        );
        $this->repository->update($guiaId, ['status' => 'em_andamento']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            try {
                $this->alocadosRepository->addAlocado($guiaId, trim($usuarioId));
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === GuiasAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === GuiasAlocadosRepository::TIPO_ALL || $t === 'all') {
            return 'all';
        }
        return 'specific';
    }

    private function formatarCNJ(string $digits): string
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

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1e12) {
                $ts = (int) floor($ts / 1000);
            }
            $date = date('Y-m-d', $ts);
            return $date !== false && $this->isValidDateYear($date) ? $date : null;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return null;
        }
        $date = date('Y-m-d', $ts);
        return $date !== false && $this->isValidDateYear($date) ? $date : null;
    }

    private function isValidDateYear(string $ymd): bool
    {
        $y = (int) substr($ymd, 0, 4);
        return $y >= 1900 && $y <= 2100;
    }
}
