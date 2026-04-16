-- ============================================================
--  fix.sql — Execute no phpMyAdmin ou mysql CLI
--  Corrige estrutura do banco hm_cofre para a versão atual
-- ============================================================

USE hm_cofre;

-- ── 1. Cria tabela tentativas_login se não existir ────────────────────────
CREATE TABLE IF NOT EXISTS tentativas_login (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL,
  ip            VARCHAR(45) NOT NULL,
  tentativas    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bloqueado_ate DATETIME NULL,
  ultimo_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Corrige tabela log_acesso ──────────────────────────────────────────
-- Adiciona coluna 'recurso' se não existir
ALTER TABLE log_acesso
  ADD COLUMN IF NOT EXISTS recurso VARCHAR(150) NULL AFTER acao;

-- Renomeia registrado_em → criado_em se ainda não foi renomeado
ALTER TABLE log_acesso
  RENAME COLUMN registrado_em TO criado_em;

-- ── 3. Garante coluna senha_hash (não password_hash) ─────────────────────
-- (Só executa se ainda estiver com o nome antigo)
ALTER TABLE usuarios
  RENAME COLUMN password_hash TO senha_hash;

-- ── 4. Adiciona colunas faltantes na tabela usuarios ──────────────────────
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS motivo_bloqueio  VARCHAR(255) NULL AFTER bloqueado,
  ADD COLUMN IF NOT EXISTS tentativas_falha TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER motivo_bloqueio,
  ADD COLUMN IF NOT EXISTS senha_expira_em  DATE NULL AFTER tentativas_falha;

-- ── 5. Cria tabela sessoes se não existir ─────────────────────────────────
CREATE TABLE IF NOT EXISTS sessoes (
  id         VARCHAR(128) PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  user_agent VARCHAR(255) NULL,
  criada_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em  DATETIME     NOT NULL,
  INDEX idx_usuario (usuario_id),
  INDEX idx_expira  (expira_em),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. Garante que as categorias existam ─────────────────────────────────
INSERT IGNORE INTO categorias (id, nome, icone, cor) VALUES
(1,'Sistemas ERP',         '🖥️','#1d9e75'),
(2,'Banco de Dados',       '🗄️','#185fa5'),
(3,'Cloud / Infra',        '☁️','#854f0b'),
(4,'Redes / VPN',          '🌐','#5f5e5a'),
(5,'E-mail / Comunicacao', '📧','#993556'),
(6,'Certificados',         '🔏','#534ab7'),
(7,'Outros',               '📁','#888888');

-- ── 7. Verifica resultado final ───────────────────────────────────────────
SELECT 'Tabelas presentes:' AS info;
SHOW TABLES;

SELECT 'Colunas de usuarios:' AS info;
SHOW COLUMNS FROM usuarios;

SELECT 'Colunas de log_acesso:' AS info;
SHOW COLUMNS FROM log_acesso;
