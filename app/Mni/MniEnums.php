<?php
declare(strict_types=1);

/**
 * Enumeracoes oficiais do MNI v300 (CNJ), extraidas de:
 *   - api/mni3/xsd_wsdl/intercomunicacao.xsd
 *   - api/mni3/xsd_wsdl/tipos-servico-intercomunicacao.xsd
 *
 * Centralizar aqui evita "magic strings" no parser/mapper/UI e
 * facilita exibir descricoes amigaveis ao usuario.
 *
 * Compatibilidade: as enums sao apenas mapas estaticos; nao depende
 * de enum nativa do PHP 8.1+ (para nao quebrar tests-mni em CLI).
 */
final class MniEnums
{
    /**
     * modalidadePrioridade (cabecalhoProcesso.prioridade).
     * Fonte: intercomunicacao.xsd ~ linha 1094 (e definicoes anteriores).
     */
    public const PRIORIDADE_IDOSO       = 'IDOSO';
    public const PRIORIDADE_REU_PRESO   = 'REU_PRESO';
    public const PRIORIDADE_PERECIMENTO = 'PERECIMENTO';
    public const PRIORIDADE_MENOR       = 'MENOR';

    /** @var array<string, string> codigo -> descricao curta */
    public const PRIORIDADES = [
        self::PRIORIDADE_IDOSO       => 'Idoso',
        self::PRIORIDADE_REU_PRESO   => 'Reu preso',
        self::PRIORIDADE_PERECIMENTO => 'Perecimento de direito',
        self::PRIORIDADE_MENOR       => 'Menor de idade',
    ];

    /**
     * Nivel de sigilo (cabecalhoProcesso.nivelSigilo) - inteiros de 0 a 5.
     * Fonte: intercomunicacao.xsd linhas 1019-1037.
     */
    public const NIVEL_SIGILO = [
        0 => 'Publico (todos)',
        1 => 'Segredo de justica (partes + judiciario)',
        2 => 'Sigilo minimo',
        3 => 'Sigilo medio',
        4 => 'Sigilo intenso (servidores qualificados)',
        5 => 'Sigilo absoluto (magistrado + indicados)',
    ];

    /**
     * modalidadeQualificacaoPessoa (cnj:tipoPessoa).
     * Fonte: intercomunicacao.xsd linhas 137-186.
     */
    public const PESSOA_FISICA      = 'FIS';
    public const PESSOA_JURIDICA    = 'JUR';
    public const PESSOA_AUTORIDADE  = 'AUT';
    public const PESSOA_ORGAO_REPR  = 'ORP';
    public const PESSOA_DESPERS     = 'EDP';

    /** @var array<string, string> */
    public const QUALIFICACAO_PESSOA = [
        self::PESSOA_FISICA     => 'Pessoa fisica',
        self::PESSOA_JURIDICA   => 'Pessoa juridica',
        self::PESSOA_AUTORIDADE => 'Autoridade',
        self::PESSOA_ORGAO_REPR => 'Orgao de representacao',
        self::PESSOA_DESPERS    => 'Ente despersonalizado',
    ];

    /**
     * Polo processual (modalidadePoloProcessual, atributo "polo" de tipoPoloProcessual).
     * Fonte: intercomunicacao.xsd linhas 713-725.
     */
    public const POLO_ATIVO          = 'AT';
    public const POLO_PASSIVO        = 'PA';
    public const POLO_TERCEIRO       = 'TC';
    public const POLO_FISCAL_DA_LEI  = 'FL';
    public const POLO_TESTEMUNHA     = 'TJ';
    public const POLO_AMICUS_CURIAE  = 'AD';
    public const POLO_VITIMA         = 'VI';
    /** Codigo legado nao previsto oficialmente, mantido como fallback de exibicao. */
    public const POLO_OUTROS         = 'OT';

    /** @var array<string, string> */
    public const POLOS = [
        self::POLO_ATIVO         => 'Ativo',
        self::POLO_PASSIVO       => 'Passivo',
        self::POLO_TERCEIRO      => 'Terceiro',
        self::POLO_FISCAL_DA_LEI => 'Fiscal da lei',
        self::POLO_TESTEMUNHA    => 'Testemunha do juizo',
        self::POLO_AMICUS_CURIAE => 'Amicus curiae',
        self::POLO_VITIMA        => 'Vitima',
        self::POLO_OUTROS        => 'Outros',
    ];

    /**
     * modalidadeTipoComunicacao (avisos pendentes).
     * Fonte: intercomunicacao.xsd ~ linhas 1597-1607 (citacao/intimacao/etc.).
     * Inclusos aqui para uso futuro nas fases 9-10.
     */
    public const COMUNICACAO = [
        'CIT' => 'Citacao',
        'INT' => 'Intimacao',
        'NOT' => 'Notificacao',
        'VIS' => 'Vista para manifestacao',
        'URG' => 'Urgente',
        'PTA' => 'Pauta de julgamento/audiencia',
    ];

    /**
     * modalidadePendenciaComunicacao (avisos pendentes).
     */
    public const PENDENCIA = [
        'PC' => 'Pendente de ciencia',
        'PR' => 'Pendente de resposta',
        'AM' => 'Ambos',
    ];

    /**
     * Resolve descricao amigavel para prioridade.
     */
    public static function prioridadeLabel(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }
        $key = strtoupper(trim($codigo));
        return self::PRIORIDADES[$key] ?? null;
    }

    /**
     * Resolve descricao amigavel para nivel de sigilo.
     */
    public static function nivelSigiloLabel(int|string|null $nivel): ?string
    {
        if ($nivel === null || $nivel === '') {
            return null;
        }
        $key = (int) $nivel;
        return self::NIVEL_SIGILO[$key] ?? null;
    }

    /**
     * Resolve descricao amigavel para polo.
     */
    public static function poloLabel(?string $polo): ?string
    {
        if ($polo === null) {
            return null;
        }
        $key = strtoupper(trim($polo));
        return self::POLOS[$key] ?? null;
    }

    /**
     * Resolve descricao amigavel para qualificacao da pessoa.
     */
    public static function qualificacaoPessoaLabel(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }
        $key = strtoupper(trim($codigo));
        return self::QUALIFICACAO_PESSOA[$key] ?? null;
    }
}
