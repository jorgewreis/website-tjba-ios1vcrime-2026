<?php
declare(strict_types=1);

require_once __DIR__ . '/CartorioTarefasCondicoes.php';

/** Operações puras de validação e precedência das regras de tarefas. */
final class CartorioTarefasRegras
{
    public const EVENTOS = ['criacao', 'finalizacao', 'status_vinculada', 'alocacao', 'prazo'];
    public const ACOES = ['alocar', 'criar_tarefa', 'alterar_status', 'definir_prazo'];
    public const INICIOS_PRAZO = ['criacao', 'andamento', 'alocacao', 'finalizacao_anterior'];
    public const STATUS = ['a_fazer', 'em_andamento', 'com_pendencias', 'finalizada'];

    /**
     * Uma ação aponta para "original" ou para o identificador de uma ação de
     * criação anterior. Os identificadores são estáveis ao editar a regra.
     */
    public static function validar(array $regra): void
    {
        $nome = $regra['nome'] ?? null;
        if (!is_string($nome) || trim($nome) === '' || strlen($nome) > 200) {
            throw new InvalidArgumentException('Informe um nome de regra com até 200 bytes.');
        }
        if (!in_array($regra['evento'] ?? null, self::EVENTOS, true)) {
            throw new InvalidArgumentException('Selecione um evento válido.');
        }
        if (($regra['evento'] ?? '') === 'status_vinculada' && !in_array($regra['evento_status'] ?? null, self::STATUS, true)) {
            throw new InvalidArgumentException('Selecione o status da tarefa vinculada.');
        }
        if (!is_int($regra['prioridade'] ?? null) || $regra['prioridade'] < -100000 || $regra['prioridade'] > 100000) {
            throw new InvalidArgumentException('Informe uma prioridade inteira válida.');
        }
        if (!is_array($regra['condicoes'] ?? null)) throw new InvalidArgumentException('Informe as condições da regra.');
        CartorioTarefasCondicoes::validar($regra['condicoes']);
        $acoes = $regra['acoes'] ?? null;
        if (!is_array($acoes) || $acoes === [] || count($acoes) > 50 || !array_is_list($acoes)) {
            throw new InvalidArgumentException('Informe entre 1 e 50 ações ordenadas.');
        }
        $ids = [];
        $criadas = [];
        foreach ($acoes as $acao) {
            if (!is_array($acao)) throw new InvalidArgumentException('Ação inválida.');
            $id = $acao['id'] ?? null;
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $id) || isset($ids[$id])) {
                throw new InvalidArgumentException('Cada ação precisa de um identificador único.');
            }
            $ids[$id] = true;
            $tipo = $acao['tipo'] ?? null;
            if (!in_array($tipo, self::ACOES, true)) throw new InvalidArgumentException('Tipo de ação inválido.');
            $alvo = $acao['alvo'] ?? 'original';
            if (!is_string($alvo) || ($alvo !== 'original' && !isset($criadas[$alvo]))) {
                throw new InvalidArgumentException('Uma ação só pode usar uma tarefa criada por ação anterior.');
            }
            if ($tipo === 'criar_tarefa') {
                if ($regra['evento'] !== 'finalizacao' || $alvo !== 'original') {
                    throw new InvalidArgumentException('A criação em sequência exige a finalização da tarefa original.');
                }
                foreach (['tipo_tarefa', 'tipo_documento', 'local_tarefa'] as $campo) {
                    if (!is_string($acao[$campo] ?? null) || trim($acao[$campo]) === '') throw new InvalidArgumentException('Preencha os campos da tarefa criada.');
                }
                if (isset($acao['especificacao_documento']) && !is_string($acao['especificacao_documento'])) throw new InvalidArgumentException('Especificação inválida.');
                $criadas[$id] = true;
            } elseif ($tipo === 'alocar') {
                if (!is_string($acao['usuario_id'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $acao['usuario_id'])) throw new InvalidArgumentException('Selecione um usuário válido.');
            } elseif ($tipo === 'alterar_status') {
                if (!in_array($acao['status'] ?? null, self::STATUS, true)) throw new InvalidArgumentException('Selecione um status válido.');
                if ($regra['evento'] === 'status_vinculada' && $alvo === 'original' && $acao['status'] === ($regra['evento_status'] ?? null)) {
                    throw new InvalidArgumentException('A regra pode formar um ciclo de status entre tarefas vinculadas.');
                }
            } else {
                if (!in_array($acao['inicio'] ?? null, self::INICIOS_PRAZO, true) || !is_int($acao['dias'] ?? null) || $acao['dias'] < 1 || $acao['dias'] > 36500 || !is_int($acao['alerta_dias'] ?? null) || $acao['alerta_dias'] < 0 || $acao['alerta_dias'] > 36500) {
                    throw new InvalidArgumentException('Informe o início, o prazo e a antecedência do alerta.');
                }
            }
        }
    }

    /** Verdadeiro quando uma regra ativa concorrente deve impedir o salvamento. */
    public static function conflitantes(array $a, array $b): bool
    {
        if (($a['evento'] ?? null) !== ($b['evento'] ?? null) || ($a['prioridade'] ?? null) !== ($b['prioridade'] ?? null)) return false;
        if (($a['evento'] ?? null) === 'status_vinculada' && ($a['evento_status'] ?? null) !== ($b['evento_status'] ?? null)) return false;
        $alvos = static function (array $regra): array {
            $tipos = [];
            foreach ($regra['acoes'] as $acao) {
                if (($acao['alvo'] ?? 'original') === 'original' && in_array($acao['tipo'], ['alocar', 'alterar_status', 'definir_prazo'], true)) $tipos[] = $acao['tipo'];
            }
            return array_unique($tipos);
        };
        if (!array_intersect($alvos($a), $alvos($b))) return false;
        $comparacao = CartorioTarefasCondicoes::comparar($a['condicoes'], $b['condicoes']);
        return $comparacao['sobrepoem'] && !$comparacao['primeiro_mais_especifico'] && !$comparacao['segundo_mais_especifico'];
    }

    /** Impede ciclos identificáveis entre regras ativas antes do salvamento. */
    public static function validarCiclos(array $regras): void
    {
        $statusGraph = [];
        foreach ($regras as $regra) {
            if ($regra['evento'] !== 'status_vinculada') continue;
            foreach ($regra['acoes'] as $acao) {
                if ($acao['tipo'] === 'alterar_status' && ($acao['alvo'] ?? 'original') === 'original') {
                    $statusGraph[$regra['evento_status']][] = $acao['status'];
                }
            }
        }
        self::verificarGrafo($statusGraph, 'As regras de status formam um ciclo entre tarefas vinculadas.');

        $sequencias = array_values(array_filter($regras, static fn(array $regra): bool => $regra['evento'] === 'finalizacao'));
        $criacao = array_values(array_filter($regras, static fn(array $regra): bool => $regra['evento'] === 'criacao'));
        $sequenciaGraph = [];
        foreach ($sequencias as $i => $regra) {
            foreach ($regra['acoes'] as $acao) {
                if ($acao['tipo'] !== 'criar_tarefa') continue;
                $filha = ['tipo' => $acao['tipo_tarefa'], 'tipo_documento' => $acao['tipo_documento'], 'especificacao_documento' => $acao['especificacao_documento'] ?? '', 'local_tarefa' => $acao['local_tarefa']];
                $finalizada = false;
                foreach ($regra['acoes'] as $subacao) {
                    if ($subacao['tipo'] === 'alterar_status' && ($subacao['alvo'] ?? '') === $acao['id'] && $subacao['status'] === 'finalizada') $finalizada = true;
                }
                foreach ($criacao as $regraCriacao) {
                    if (!CartorioTarefasCondicoes::corresponde($regraCriacao['condicoes'], $filha)) continue;
                    foreach ($regraCriacao['acoes'] as $subacao) {
                        if ($subacao['tipo'] === 'alterar_status' && ($subacao['alvo'] ?? 'original') === 'original' && $subacao['status'] === 'finalizada') $finalizada = true;
                    }
                }
                if (!$finalizada) continue;
                foreach ($sequencias as $j => $proxima) {
                    if (CartorioTarefasCondicoes::corresponde($proxima['condicoes'], $filha)) $sequenciaGraph[(string) $i][] = (string) $j;
                }
            }
        }
        self::verificarGrafo($sequenciaGraph, 'As regras podem criar e finalizar tarefas em um ciclo.');
    }

    private static function verificarGrafo(array $grafo, string $mensagem): void
    {
        $estado = [];
        $visitar = static function ($no) use (&$visitar, &$estado, $grafo, $mensagem): void {
            if (($estado[$no] ?? 0) === 1) throw new InvalidArgumentException($mensagem);
            if (($estado[$no] ?? 0) === 2) return;
            $estado[$no] = 1;
            foreach ($grafo[$no] ?? [] as $proximo) $visitar($proximo);
            $estado[$no] = 2;
        };
        foreach (array_keys($grafo) as $no) $visitar($no);
    }

    /** Seleciona o vencedor de um tipo de ação aplicável à tarefa. */
    public static function vencedora(array $regras, array $tarefa, string $tipo): ?array
    {
        $candidatas = array_values(array_filter($regras, static function (array $regra) use ($tarefa, $tipo): bool {
            foreach ($regra['acoes'] as $acao) {
                if (($acao['tipo'] ?? null) === $tipo && ($acao['alvo'] ?? 'original') === 'original') {
                    return CartorioTarefasCondicoes::corresponde($regra['condicoes'], $tarefa);
                }
            }
            return false;
        }));
        $maximas = [];
        foreach ($candidatas as $i => $candidata) {
            foreach ($candidatas as $j => $outra) {
                if ($i !== $j && CartorioTarefasCondicoes::comparar($outra['condicoes'], $candidata['condicoes'])['primeiro_mais_especifico']) continue 2;
            }
            $maximas[] = $candidata;
        }
        usort($maximas, static fn(array $a, array $b): int => $b['prioridade'] <=> $a['prioridade']);
        if (count($maximas) > 1 && $maximas[0]['prioridade'] === $maximas[1]['prioridade']) throw new LogicException('Regras concorrentes empatadas.');
        return $maximas[0] ?? null;
    }
}
