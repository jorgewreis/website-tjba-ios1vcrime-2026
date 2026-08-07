-- Armazena a classe processual retornada pelo DataJud no cadastro CEJUSC.
ALTER TABLE `cejusc_registros`
    ADD COLUMN `classe_codigo` VARCHAR(30) DEFAULT NULL AFTER `numero_processo`,
    ADD COLUMN `classe_descricao` VARCHAR(255) DEFAULT NULL AFTER `classe_codigo`;
