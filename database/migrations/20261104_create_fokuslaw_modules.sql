CREATE TABLE IF NOT EXISTS `fokuslaw_tipos_tarefa` (
  `id` VARCHAR(30) NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `prazo_dias` INT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_por` VARCHAR(30) NULL,
  `atualizado_por` VARCHAR(30) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fokuslaw_tipos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fokuslaw_tarefas` (
  `id` VARCHAR(30) NOT NULL,
  `numero_processo` VARCHAR(25) NOT NULL,
  `processo_id` VARCHAR(30) NULL,
  `tipo_id` VARCHAR(30) NULL,
  `titulo` VARCHAR(180) NOT NULL,
  `descricao` TEXT NULL,
  `status` ENUM('a_fazer','em_andamento','pendente','concluida','cancelada') NOT NULL DEFAULT 'a_fazer',
  `prazo` DATE NULL,
  `sigiloso` TINYINT(1) NOT NULL DEFAULT 0,
  `is_teste` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_por` VARCHAR(30) NOT NULL,
  `atualizado_por` VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fokuslaw_tarefas_status` (`status`), KEY `idx_fokuslaw_tarefas_processo` (`numero_processo`), KEY `idx_fokuslaw_tarefas_prazo` (`prazo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fokuslaw_tarefas_responsaveis` (
  `id` VARCHAR(30) NOT NULL,
  `tarefa_id` VARCHAR(30) NOT NULL,
  `usuario_id` VARCHAR(30) NOT NULL,
  `status` ENUM('a_fazer','em_andamento','concluida') NOT NULL DEFAULT 'a_fazer',
  `concluido_em` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_fokuslaw_tarefa_usuario` (`tarefa_id`,`usuario_id`), KEY `idx_fokuslaw_tarefa_resp_usuario` (`usuario_id`),
  CONSTRAINT `fk_fokuslaw_tarefa_resp_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `fokuslaw_tarefas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fokuslaw_expedicoes` (
  `id` VARCHAR(30) NOT NULL,
  `tarefa_id` VARCHAR(30) NULL,
  `numero_processo` VARCHAR(25) NOT NULL,
  `processo_id` VARCHAR(30) NULL,
  `destinatario` VARCHAR(180) NOT NULL,
  `tipo_documento` VARCHAR(120) NOT NULL,
  `descricao_documento` VARCHAR(255) NULL,
  `status` ENUM('a_fazer','em_andamento','pendente','concluida','cancelada') NOT NULL DEFAULT 'a_fazer',
  `responsavel_id` VARCHAR(30) NOT NULL,
  `data_envio` DATE NULL,
  `sigiloso` TINYINT(1) NOT NULL DEFAULT 0,
  `is_teste` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_por` VARCHAR(30) NOT NULL,
  `atualizado_por` VARCHAR(30) NOT NULL,
  `concluido_por` VARCHAR(30) NULL,
  `concluido_em` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fokuslaw_expedicoes_status` (`status`), KEY `idx_fokuslaw_expedicoes_processo` (`numero_processo`), KEY `idx_fokuslaw_expedicoes_resp` (`responsavel_id`), KEY `idx_fokuslaw_expedicoes_envio` (`data_envio`),
  CONSTRAINT `fk_fokuslaw_expedicoes_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `fokuslaw_tarefas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fokuslaw_historico` (
  `id` VARCHAR(30) NOT NULL,
  `modulo` ENUM('tarefa','expedicao') NOT NULL,
  `registro_id` VARCHAR(30) NOT NULL,
  `acao` VARCHAR(40) NOT NULL,
  `detalhes` JSON NULL,
  `usuario_id` VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fokuslaw_historico_registro` (`modulo`,`registro_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
