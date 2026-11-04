<?php
declare(strict_types=1);

/** Condições em grupos OU; cada grupo contém comparações por igualdade unidas por E. */
final class CartorioTarefasCondicoes
{
    public const CAMPOS = ['tipo', 'tipo_documento', 'especificacao_documento', 'local_tarefa'];

    public static function validar(array $grupos): void
    {
        if ($grupos === [] || count($grupos) > 100) {
            throw new InvalidArgumentException('Informe entre 1 e 100 grupos de condições.');
        }
        foreach ($grupos as $grupo) {
            if (!is_array($grupo) || count($grupo) > count(self::CAMPOS)) {
                throw new InvalidArgumentException('Grupo de condições inválido.');
            }
            foreach ($grupo as $campo => $valor) {
                if (!in_array($campo, self::CAMPOS, true) || !is_string($valor) || strlen($valor) > 720) {
                    throw new InvalidArgumentException('Campo ou valor de condição inválido.');
                }
            }
        }
    }

    public static function corresponde(array $grupos, array $tarefa): bool
    {
        self::validar($grupos);
        return self::correspondeValidado($grupos, $tarefa);
    }

    /** Retorna sobreposição e se cada conjunto é subconjunto próprio do outro. */
    public static function comparar(array $primeiro, array $segundo): array
    {
        self::validar($primeiro);
        self::validar($segundo);
        $valores = array_fill_keys(self::CAMPOS, []);
        foreach ([$primeiro, $segundo] as $grupos) {
            foreach ($grupos as $grupo) {
                foreach ($grupo as $campo => $valor) {
                    if (!in_array($valor, $valores[$campo], true)) $valores[$campo][] = $valor;
                }
            }
        }
        $dominios = [];
        $combinacoes = 1;
        foreach (self::CAMPOS as $campo) {
            // null representa todos os valores não mencionados em qualquer regra.
            $dominios[$campo] = array_merge($valores[$campo], [null]);
            $combinacoes *= count($dominios[$campo]);
        }
        if ($combinacoes > 200000) {
            throw new InvalidArgumentException('Há combinações demais para comparar as regras.');
        }
        $ambos = false;
        $soPrimeiro = false;
        $soSegundo = false;
        $visitar = static function (int $nivel, array $tarefa) use (&$visitar, &$ambos, &$soPrimeiro, &$soSegundo, $dominios, $primeiro, $segundo): void {
            if ($ambos && $soPrimeiro && $soSegundo) return;
            if ($nivel === count(self::CAMPOS)) {
                $a = self::correspondeValidado($primeiro, $tarefa);
                $b = self::correspondeValidado($segundo, $tarefa);
                $ambos = $ambos || ($a && $b);
                $soPrimeiro = $soPrimeiro || ($a && !$b);
                $soSegundo = $soSegundo || (!$a && $b);
                return;
            }
            $campo = self::CAMPOS[$nivel];
            foreach ($dominios[$campo] as $valor) {
                $tarefa[$campo] = $valor;
                $visitar($nivel + 1, $tarefa);
            }
        };
        $visitar(0, []);
        return ['sobrepoem' => $ambos, 'primeiro_mais_especifico' => !$soPrimeiro && $soSegundo, 'segundo_mais_especifico' => !$soSegundo && $soPrimeiro];
    }

    private static function correspondeValidado(array $grupos, array $tarefa): bool
    {
        foreach ($grupos as $grupo) {
            $corresponde = true;
            foreach ($grupo as $campo => $valor) {
                $atual = $tarefa[$campo] ?? null;
                if ($campo === 'especificacao_documento' && is_string($atual)) {
                    $atual = mb_strtolower($atual, 'UTF-8');
                    $valor = mb_strtolower($valor, 'UTF-8');
                }
                if ($atual !== $valor) {
                    $corresponde = false;
                    break;
                }
            }
            if ($corresponde) return true;
        }
        return false;
    }
}
