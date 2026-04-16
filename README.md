# 🔐 Cofre de Senhas — hm_cofre

Sistema seguro de gestão de credenciais com níveis de acesso, políticas de restrição e criptografia AES-256.

---

## Estrutura de arquivos

```
hm_cofre/
├── config.php          ← Banco de dados, chave AES, constantes
├── bootstrap.php       ← Autoload das classes, helpers CSRF
├── install.sql         ← Script SQL completo para criação do banco
├── setup.php           ← Instalador (define senhas bcrypt, delete após usar)
├── index.php           ← Página de login
├── dashboard.php       ← SPA principal
├── .htaccess           ← Proteção de arquivos sensíveis
├── assets/
│   └── style.css       ← Estilos completos (tema dark)
├── src/
│   ├── Database.php    ← Singleton PDO
│   ├── Crypto.php      ← AES-256-CBC encrypt/decrypt
│   ├── Logger.php      ← Log de auditoria
│   ├── Policy.php      ← Motor de políticas de acesso
│   └── Auth.php        ← Autenticação, sessão, bloqueio de brute-force
└── api/
    ├── login.php       ← POST /api/login.php
    ├── logout.php      ← POST /api/logout.php
    ├── passwords.php   ← CRUD de credenciais
    ├── users.php       ← Gestão de usuários
    └── logs.php        ← Auditoria e políticas
```

---

## Instalação

### 1. Requisitos
- PHP 8.1+ com extensões: `pdo_mysql`, `openssl`, `mbstring`
- MySQL 5.7+ / MariaDB 10.4+
- Apache com `mod_rewrite` habilitado

### 2. Banco de dados
```bash
mysql -u root -p < install.sql
```

### 3. Configuração
Edite `config.php`:
```php
define('DB_USER',    'root');        // usuário MySQL
define('DB_PASS',    'sua_senha');   // senha MySQL
define('CRYPTO_KEY', 'CHAVE_32B');  // gere com: php -r "echo base64_encode(random_bytes(32));"
define('BASE_URL',   'http://localhost:8081/hm_cofre');
```

> ⚠️ **CRYPTO_KEY** deve ser única, aleatória e **nunca** compartilhada.
> Se perder a chave, todas as senhas armazenadas ficam irrecuperáveis.

### 4. Setup inicial
Acesse `http://localhost:8081/hm_cofre/setup.php` para definir as senhas bcrypt dos usuários.

### 5. Segurança pós-setup
- Delete ou bloqueie o acesso a `setup.php`
- Em produção: habilite HTTPS e ajuste `secure: true` nos cookies (Auth.php)
- Troque a `CRYPTO_KEY` padrão antes de usar

---

## Credenciais iniciais

| Usuário   | Senha          | Nível           |
|-----------|----------------|-----------------|
| admin     | Admin@2024!    | Administrador 4 |
| gerente   | Gerente@2024!  | Gerente 3       |
| operador  | Operador@2024! | Operador 2      |
| viewer    | Viewer@2024!   | Visualizador 1  |

> Troque todas as senhas após o primeiro acesso!

---

## Níveis de Acesso

| Nível | Nome          | Permissões |
|-------|---------------|------------|
| 1     | Visualizador  | Visualiza credenciais N1 |
| 2     | Operador      | N1 + visualiza N2 + cria/edita N1-N2 |
| 3     | Gerente       | N1-N3 + gerencia usuários N1/N2 + deleta credenciais |
| 4     | Administrador | Acesso total + políticas + auditoria completa |

---

## Políticas de Acesso

Configuráveis pelo Administrador em **Políticas de Acesso**:

| Tipo              | Descrição |
|-------------------|-----------|
| `ip_whitelist`    | Permite acesso apenas de IPs/CIDRs específicos |
| `ip_blacklist`    | Bloqueia IPs/CIDRs específicos |
| `horario`         | Restringe acesso a dias/horários |
| `max_tentativas`  | Bloqueia após N tentativas de login falhas |
| `sessao_timeout`  | Encerra sessão após N minutos de inatividade |
| `expiracao_senha` | Define validade das senhas dos usuários |

---

## Segurança Implementada

- **AES-256-CBC** com IV aleatório por credencial — senhas nunca armazenadas em texto plano
- **bcrypt (cost 12)** para senhas de usuários
- **Proteção brute-force** — bloqueio automático após 5 tentativas (configurável)
- **Token CSRF** — todas as requisições POST são validadas
- **Auditoria completa** — todas as ações registradas com IP e timestamp
- **Soft delete** — credenciais nunca são apagadas fisicamente
- **Controle de nível** — cada credencial tem nível mínimo de acesso
- **Sessão segura** — cookies HttpOnly + SameSite=Strict + timeout de inatividade
- **Expiração de senha** — usuários com senha expirada não conseguem logar
