-- Aplicar antes de publicar a proteção de classe, após backup privado
-- e restauração isolada verificada. NULL indica origem legada desconhecida.
-- Não reclassificar legado na migração. Apenas consulta DataJud verificada
-- registra origem 'datajud'; novas gravações manuais registram 'manual'.
ALTER TABLE processos
    ADD COLUMN IF NOT EXISTS classe_origem ENUM('manual','datajud') NULL DEFAULT NULL;
