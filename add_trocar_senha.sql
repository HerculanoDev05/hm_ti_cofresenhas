USE hm_cofre;

-- Adiciona flag de troca obrigatória de senha
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS trocar_senha TINYINT(1) NOT NULL DEFAULT 1
  AFTER senha_expira_em;

-- Usuários existentes: admin não precisa trocar (já está configurado)
-- Os demais precisam trocar no primeiro acesso
UPDATE usuarios SET trocar_senha = 0 WHERE username = 'breno.ventura';
UPDATE usuarios SET trocar_senha = 1 WHERE username != 'breno.ventura';

-- Confirma
SELECT username, nivel_id, trocar_senha FROM usuarios;
