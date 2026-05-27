-- Migração: adiciona contatos específicos da parte na pauta de audiência.
-- A coluna é usada por SalaAudienciasPautaParteRepository em insert/update.

ALTER TABLE `sala_audiencias_pauta_parte`
    ADD COLUMN `contatos` VARCHAR(200) NULL DEFAULT NULL
        COMMENT 'Telefones/e-mails específicos da parte nesta audiência'
        AFTER `id_documento_cumprido`;
