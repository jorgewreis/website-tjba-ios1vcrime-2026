ALTER TABLE cartas_expedidas
    ADD COLUMN idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN idempotency_payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD UNIQUE KEY uq_cartas_expedidas_idempotency (criado_por, idempotency_key);
