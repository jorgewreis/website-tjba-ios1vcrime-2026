<?php
declare(strict_types=1);

final class CartorioTarefasAutomacaoVersao
{
    public static function hash(): string
    {
        $raiz = dirname(__DIR__, 2);
        $arquivos = [
            'scripts/processar-cartorio-tarefas-automacao.php',
            'app/services/CartorioTarefasAutomacaoService.php',
            'app/repositories/CartorioTarefasRegrasRepository.php',
            'app/repositories/CartorioTarefasRepository.php',
            'app/helpers/CartorioTarefasAutomacaoVersao.php',
            'app/helpers/CartorioTarefasRegras.php',
            'app/helpers/CartorioTarefasCondicoes.php',
            'app/helpers/IdGenerator.php',
            'app/helpers/CnjValidator.php',
        ];
        $hashes = [];
        foreach ($arquivos as $arquivo) {
            $hash = hash_file('sha256', $raiz . '/' . $arquivo);
            if ($hash === false) throw new RuntimeException('Não foi possível verificar os arquivos da automação.');
            $hashes[] = $hash;
        }
        return hash('sha256', implode('', $hashes));
    }
}
