<?php
declare(strict_types=1);

class Produtividade
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function registrar(
        string $idProd,
        string $procProd,
        string $descProd,
        float $pontosProd,
        string $dataProd,
        string $userProd,
        int $visible = 1
    ): bool {
        $userProd = trim($userProd);
        if ($userProd === '') {
            return false;
        }

        $sql = "INSERT INTO Produtividade 
                (IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd, Visible) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $idProd,
            $procProd,
            $descProd,
            $pontosProd,
            $dataProd,
            $userProd,
            $visible
        ]);
    }

    public function registrarCadastroGuia(
        string $idGuia,
        string $processo,
        string $userProd,
        string $dataProd,
        bool $temDetalhamento = false
    ): bool
    {
        $desc = 'Cadastro de nova guia para expedição';
        $pontos = $temDetalhamento ? 2.0 : 1.0;

        return $this->registrar($idGuia, $processo, $desc, $pontos, $dataProd, $userProd, 1);
    }

    public function registrarExclusaoGuia(string $idGuia, string $processo, string $userProd, string $dataProd): bool
    {
        $desc = 'Guia - Excluída';
        $pontos = 1.0;

        $sufixo = date('ymdHis') . random_int(100, 999); // 12 + 3 = 15 chars
        $prefixo = 'DEL-';
        $maxLen = 30;
        $baseMax = $maxLen - strlen($prefixo) - strlen($sufixo);
        $base = $baseMax > 0 ? substr($idGuia, 0, $baseMax) : '';
        $idProd = $base . $prefixo . $sufixo;

        return $this->registrar($idProd, $processo, $desc, $pontos, $dataProd, $userProd, 1);
    }

    /**
     * Registra produtividade da conclusão de uma subtarefa
     * 
     * Pontuação:
     * - Calcular pena de multa: 2 pts
     * - Expedir guia: 3 pts
     * - Assinar guia: 1 pt
     * - Gerar PDF: 0.2 pt por documento
     * - Enviar documentos: 2 pts
     * - Juntar termo de envio: 3 pts
     * - Expedir certidão: 3 pts
     */
    public function registrarSubtarefa(
        string $idSubtarefa,
        string $idGuia,
        string $processo,
        string $codigoSubtarefa,
        string $tituloSubtarefa,
        string $userProd,
        string $dataProd,
        int $qtdDocumentos = 0
    ): bool {
        // Definir pontuação baseada no tipo de subtarefa
        $pontos = match($codigoSubtarefa) {
            'calcular-pena-multa' => 2.0,
            'expedir-guia' => 3.0,
            'assinar-guia' => 1.0,
            'gerar-pdf' => max(0.2, $qtdDocumentos * 0.2), // Mínimo 0.2, 0.2 por documento
            'enviar-documentos' => 2.0,
            'juntar-termo-envio' => 3.0,
            'expedir-certidao' => 3.0,
            default => 1.0 // Fallback para subtarefas não mapeadas
        };

        $desc = "Subtarefa: {$tituloSubtarefa}";
        
        // Se for gerar-pdf, incluir quantidade no desc
        if ($codigoSubtarefa === 'gerar-pdf' && $qtdDocumentos > 0) {
            $desc .= " ({$qtdDocumentos} doc" . ($qtdDocumentos > 1 ? 's' : '') . ")";
        }

        // Usar ID da subtarefa como identificador único na tabela de produtividade
        $idProd = "SUB-{$idSubtarefa}";

        return $this->registrar($idProd, $processo, $desc, $pontos, $dataProd, $userProd, 1);
    }
}
