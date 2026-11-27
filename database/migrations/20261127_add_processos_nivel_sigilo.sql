-- Aplicação manual após backup privado e restauração isolada verificada.
-- NULL mantém o legado: sem tag se público e Segredo de Justiça se já marcado sigiloso.
-- O nível é informativo. A coluna sigiloso continua controlando o acesso.
ALTER TABLE processos ADD COLUMN IF NOT EXISTS nivel_sigilo TINYINT UNSIGNED NULL DEFAULT NULL
    CHECK (nivel_sigilo BETWEEN 1 AND 5);
