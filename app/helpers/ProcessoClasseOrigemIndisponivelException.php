<?php
declare(strict_types=1);

/** Gravar classe sem sua origem deixaria a sincronização sem proteção. */
final class ProcessoClasseOrigemIndisponivelException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A atualização de classe requer a migração de origem da classe processual.');
    }
}
