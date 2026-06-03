ALTER TABLE `sala_audiencias_pauta_parte`
    ADD COLUMN `contatos` VARCHAR(200) NULL DEFAULT NULL
    AFTER `id_documento_cumprido`;
