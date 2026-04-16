-- ============================================================
--  Cofre de Senhas — hm_cofre
--  Script de instalação completo
--  Execute: mysql -u root -p < install.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS hm_cofre
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE hm_cofre;

-- ── Níveis de acesso ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS niveis_acesso (
  id        TINYINT UNSIGNED PRIMARY KEY,
  nome      VARCHAR(30)  NOT NULL,
  descricao VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO niveis_acesso VALUES
(1,'Visualizador','Le apenas credenciais de nivel 1'),
(2,'Operador',    'Le N1/N2, cria credenciais N1/N2'),
(3,'Gerente',     'Le N1-N3, cria/edita N1-N3'),
(4,'Administrador','Acesso total, politicas, auditoria, usuarios');

-- ── Usuários ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username         VARCHAR(50)  NOT NULL UNIQUE,
  email            VARCHAR(120) NOT NULL UNIQUE,
  nome_completo    VARCHAR(120) NOT NULL,
  senha_hash       VARCHAR(255) NOT NULL,
  nivel_id         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo            TINYINT(1)   NOT NULL DEFAULT 1,
  bloqueado        TINYINT(1)   NOT NULL DEFAULT 0,
  motivo_bloqueio  VARCHAR(255) NULL,
  tentativas_falha TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_acesso    DATETIME NULL,
  senha_expira_em  DATE NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  criado_por       INT UNSIGNED NULL,
  FOREIGN KEY (nivel_id) REFERENCES niveis_acesso(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Senhas provisórias — execute setup.php para definir bcrypt real
INSERT IGNORE INTO usuarios (username, email, nome_completo, senha_hash, nivel_id, senha_expira_em) VALUES
('admin',    'admin@herculano.com.br',    'Administrador Sistema', '$2y$12$PLACEHOLDER', 4, DATE_ADD(NOW(), INTERVAL 90 DAY)),
('gerente',  'gerente@herculano.com.br',  'Gerente TI',            '$2y$12$PLACEHOLDER', 3, DATE_ADD(NOW(), INTERVAL 90 DAY)),
('operador', 'operador@herculano.com.br', 'Operador TI',           '$2y$12$PLACEHOLDER', 2, DATE_ADD(NOW(), INTERVAL 90 DAY)),
('viewer',   'viewer@herculano.com.br',   'Visualizador',          '$2y$12$PLACEHOLDER', 1, DATE_ADD(NOW(), INTERVAL 90 DAY));

-- ── Categorias ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categorias (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(60) NOT NULL,
  icone VARCHAR(10) NOT NULL DEFAULT '🔑',
  cor   VARCHAR(7)  NOT NULL DEFAULT '#888888',
  ativo TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categorias (nome, icone, cor) VALUES
('Sistemas ERP',         '🖥️', '#1d9e75'),
('Banco de Dados',       '🗄️', '#185fa5'),
('Cloud / Infra',        '☁️', '#854f0b'),
('Redes / VPN',          '🌐', '#5f5e5a'),
('E-mail / Comunicacao', '📧', '#993556'),
('Certificados',         '🔏', '#534ab7'),
('Outros',               '📁', '#888888');

-- ── Senhas (AES-256-CBC) ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS senhas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(150) NOT NULL,
  usuario_login   VARCHAR(150) NOT NULL,
  senha_enc       TEXT         NOT NULL COMMENT 'AES-256-CBC base64',
  senha_iv        VARCHAR(64)  NOT NULL COMMENT 'IV base64',
  url             VARCHAR(500) NULL,
  observacao      TEXT         NULL,
  categoria_id    INT UNSIGNED NOT NULL DEFAULT 7,
  nivel_acesso    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  expira_em       DATE NULL,
  adicionado_por  INT UNSIGNED NOT NULL,
  atualizado_por  INT UNSIGNED NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (categoria_id)   REFERENCES categorias(id),
  FOREIGN KEY (adicionado_por) REFERENCES usuarios(id),
  INDEX idx_nivel     (nivel_acesso),
  INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Políticas de acesso ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS politicas (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(80)  NOT NULL,
  tipo         ENUM('ip_whitelist','ip_blacklist','horario','expiracao_senha','max_tentativas','sessao_timeout') NOT NULL,
  valor        TEXT         NOT NULL COMMENT 'JSON com parâmetros',
  aplica_nivel TINYINT UNSIGNED NULL COMMENT 'NULL = todos os níveis',
  aplica_user  INT UNSIGNED NULL     COMMENT 'NULL = todos os usuários',
  ativo        TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO politicas (nome, tipo, valor) VALUES
('Bloqueio apos 5 falhas',   'max_tentativas',  '{"max":5,"bloqueio_min":30}'),
('Timeout sessao 8h',        'sessao_timeout',  '{"minutos":480}'),
('Senha expira em 90 dias',  'expiracao_senha', '{"dias":90}'),
('Acesso horario comercial', 'horario',         '{"inicio":"07:00","fim":"20:00","dias":[1,2,3,4,5]}');

-- ── Log de auditoria ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_acesso (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  username    VARCHAR(50)  NOT NULL,
  acao        VARCHAR(80)  NOT NULL,
  recurso     VARCHAR(150) NULL,
  ip          VARCHAR(45)  NOT NULL,
  user_agent  VARCHAR(255) NULL,
  sucesso     TINYINT(1)   NOT NULL DEFAULT 1,
  detalhe     TEXT         NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario  (usuario_id),
  INDEX idx_criado   (criado_em),
  INDEX idx_acao     (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tentativas de login (brute-force) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS tentativas_login (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL,
  ip            VARCHAR(45) NOT NULL,
  tentativas    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bloqueado_ate DATETIME NULL,
  ultimo_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_ip       (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Sessões ───────────────────────────────────────────────────────────────
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

-- ── Fim do script ─────────────────────────────────────────────────────────
-- Após importar, acesse http://localhost:8081/hm_cofre/setup.php
-- para definir as senhas bcrypt dos usuários iniciais.
