<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Se já logado, vai direto ao dashboard
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$expired  = !empty($_GET['expired']);
$denied   = !empty($_GET['denied']);
$logout   = !empty($_GET['logout']);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <link rel="SHORTCUT ICON" href="imagens/Logo.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-wrapper">
  <div class="login-brand">
    <div class="brand-icon">🔐</div>
    <h1 class="brand-name"><?= APP_NAME ?></h1>
    <p class="brand-sub">Herculano Mineração &middot; Acesso Seguro</p>
  </div>

  <div class="login-card">
    <?php if ($expired): ?>
      <div class="alert alert-warning">⏱ Sessão expirada por inatividade. Faça login novamente.</div>
    <?php elseif ($denied): ?>
      <div class="alert alert-danger">🚫 Acesso negado. Sem permissão para este recurso.</div>
    <?php elseif ($logout): ?>
      <div class="alert alert-info">✓ Logout realizado com sucesso.</div>
    <?php endif; ?>

    <div id="loginAlert" class="alert alert-danger" style="display:none"></div>

    <form id="loginForm" novalidate>
      <div class="form-group">
        <label class="form-label" for="username">Usuário</label>
        <input type="text" id="username" name="username" class="form-input"
               placeholder="seu.usuario" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Senha</label>
        <div class="input-icon-wrap">
          <input type="password" id="password" name="password" class="form-input"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="eye-btn" onclick="togglePass()" title="Mostrar/Ocultar">
            <span id="eyeIcon">👁</span>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full" id="btnLogin">
        <span id="btnText">Entrar no Cofre</span>
        <span id="btnSpinner" class="spinner" style="display:none"></span>
      </button>
    </form>

    <div class="login-footer">
      <span class="version-badge">v<?= APP_VERSION ?></span>
      <span class="security-badge">🔒 AES-256 · bcrypt</span>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  const alert    = document.getElementById('loginAlert');
  const btnText  = document.getElementById('btnText');
  const spinner  = document.getElementById('btnSpinner');
  const btn      = document.getElementById('btnLogin');

  if (!username || !password) {
    showAlert('Preencha usuário e senha.'); return;
  }

  btn.disabled = true;
  btnText.style.display = 'none';
  spinner.style.display = 'inline-block';
  alert.style.display   = 'none';

  try {
    const res = await fetch(BASE_URL + '/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (data.ok) {
      sessionStorage.setItem('csrf_token', data.csrf_token);
      window.location.href = data.redirect || BASE_URL + '/dashboard.php';
    } else {
      showAlert(data.error || 'Erro ao autenticar.');
      btn.disabled = false;
      btnText.style.display = '';
      spinner.style.display = 'none';
    }
  } catch (err) {
    showAlert('Erro de conexão com o servidor.');
    btn.disabled = false;
    btnText.style.display = '';
    spinner.style.display = 'none';
  }
});

function showAlert(msg) {
  const el = document.getElementById('loginAlert');
  el.textContent = msg;
  el.style.display = 'block';
}

function togglePass() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type = 'text';     ico.textContent = '🙈'; }
  else                         { inp.type = 'password'; ico.textContent = '👁'; }
}
</script>
</body>
</html>
