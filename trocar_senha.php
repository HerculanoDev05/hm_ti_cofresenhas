<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Precisa estar logado
Auth::requireLogin();

// Se não precisa trocar senha, redireciona pro dashboard
$user = Database::queryOne(
    'SELECT trocar_senha FROM usuarios WHERE id = ?',
    [Auth::userId()]
);
if (!$user || !$user['trocar_senha']) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trocar Senha — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    .change-body { display:flex; align-items:center; justify-content:center; min-height:100vh;
      background: radial-gradient(ellipse at 30% 20%, rgba(245,158,11,0.07) 0%, transparent 60%),
                  radial-gradient(ellipse at 70% 80%, rgba(79,142,247,0.06) 0%, transparent 60%), var(--bg); }
    .change-card { width:100%; max-width:420px; background:var(--bg2); border:1px solid var(--border);
      border-radius:var(--radius-xl); padding:2rem; box-shadow:var(--shadow); }
    .warn-banner { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3);
      border-radius:var(--radius); padding:.75rem 1rem; margin-bottom:1.5rem;
      font-size:.82rem; color:#fcd34d; display:flex; gap:.5rem; align-items:flex-start; }
    .req-list { margin-top:.75rem; padding:.75rem; background:var(--bg3);
      border-radius:var(--radius); font-size:.78rem; color:var(--text3); }
    .req-item { padding:2px 0; transition:color .2s; }
    .req-item.ok  { color:var(--green); }
    .req-item.err { color:var(--red); }
  </style>
</head>
<body class="change-body">
<div class="change-card">
  <div style="text-align:center; margin-bottom:1.5rem">
    <div style="font-size:2.5rem; margin-bottom:.5rem">🔑</div>
    <h1 style="font-size:1.1rem; font-weight:600">Troca de Senha Obrigatória</h1>
    <p style="font-size:.8rem; color:var(--text3); margin-top:.25rem">
      Olá, <strong><?= htmlspecialchars($_SESSION['nome']) ?></strong>. Por segurança, defina uma nova senha antes de continuar.
    </p>
  </div>

  <div class="warn-banner">
    ⚠️ Esta é sua primeira entrada no sistema. Você deve definir uma senha pessoal antes de prosseguir.
  </div>

  <div id="alertBox" class="alert alert-danger" style="display:none"></div>

  <form id="changeForm" novalidate>
    <div class="form-group">
      <label class="form-label">Senha Atual</label>
      <div class="input-icon-wrap">
        <input type="password" id="senhaAtual" class="form-input" placeholder="Senha fornecida pelo administrador">
        <button type="button" class="eye-btn" onclick="toggleVis('senhaAtual',this)">👁</button>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Nova Senha</label>
      <div class="input-icon-wrap">
        <input type="password" id="novaSenha" class="form-input" placeholder="Mínimo 8 caracteres"
               oninput="validarRequisitos()">
        <button type="button" class="eye-btn" onclick="toggleVis('novaSenha',this)">👁</button>
      </div>
      <div class="req-list">
        <div class="req-item" id="req-len">   ○ Mínimo 8 caracteres</div>
        <div class="req-item" id="req-upper"> ○ Letra maiúscula (A-Z)</div>
        <div class="req-item" id="req-lower"> ○ Letra minúscula (a-z)</div>
        <div class="req-item" id="req-num">   ○ Número (0-9)</div>
        <div class="req-item" id="req-spec">  ○ Caractere especial (!@#$...)</div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Confirmar Nova Senha</label>
      <div class="input-icon-wrap">
        <input type="password" id="confirmarSenha" class="form-input" placeholder="Repita a nova senha"
               oninput="validarConfirmacao()">
        <button type="button" class="eye-btn" onclick="toggleVis('confirmarSenha',this)">👁</button>
      </div>
      <div id="matchMsg" style="font-size:.75rem; margin-top:.3rem; min-height:16px"></div>
    </div>

    <button type="submit" class="btn btn-primary btn-full" id="btnTrocar" disabled>
      <span id="btnText">Definir Nova Senha</span>
      <span id="btnSpinner" class="spinner" style="display:none"></span>
    </button>
  </form>

  <div style="text-align:center; margin-top:1rem">
    <a href="<?= BASE_URL ?>/api/logout.php" onclick="fetch(this.href,{method:'POST'}).then(()=>window.location='<?= BASE_URL ?>/index.php?logout=1'); return false;"
       style="font-size:.78rem; color:var(--text3)">Cancelar e sair</a>
  </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= csrfToken() ?>';

let requisitosOk = false;

function validarRequisitos() {
  const v = document.getElementById('novaSenha').value;
  const reqs = {
    'req-len':   v.length >= 8,
    'req-upper': /[A-Z]/.test(v),
    'req-lower': /[a-z]/.test(v),
    'req-num':   /[0-9]/.test(v),
    'req-spec':  /[^A-Za-z0-9]/.test(v),
  };
  let todos = true;
  for (const [id, ok] of Object.entries(reqs)) {
    const el = document.getElementById(id);
    el.className = 'req-item ' + (ok ? 'ok' : 'err');
    el.textContent = (ok ? '✓' : '✗') + el.textContent.slice(1);
    if (!ok) todos = false;
  }
  requisitosOk = todos;
  validarConfirmacao();
}

function validarConfirmacao() {
  const nova      = document.getElementById('novaSenha').value;
  const confirmar = document.getElementById('confirmarSenha').value;
  const msg       = document.getElementById('matchMsg');
  const btn       = document.getElementById('btnTrocar');

  if (!confirmar) { msg.textContent = ''; btn.disabled = true; return; }

  if (nova === confirmar && requisitosOk) {
    msg.style.color = 'var(--green)';
    msg.textContent = '✓ Senhas conferem';
    btn.disabled = false;
  } else if (nova !== confirmar) {
    msg.style.color = 'var(--red)';
    msg.textContent = '✗ As senhas não conferem';
    btn.disabled = true;
  } else {
    msg.style.color = 'var(--amber)';
    msg.textContent = '⚠ Atenda todos os requisitos acima';
    btn.disabled = true;
  }
}

document.getElementById('changeForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const senhaAtual    = document.getElementById('senhaAtual').value;
  const novaSenha     = document.getElementById('novaSenha').value;
  const confirmarSenha= document.getElementById('confirmarSenha').value;
  const alert         = document.getElementById('alertBox');
  const btn           = document.getElementById('btnTrocar');
  const btnText       = document.getElementById('btnText');
  const spinner       = document.getElementById('btnSpinner');

  if (!senhaAtual) { showAlert('Informe a senha atual.'); return; }
  if (novaSenha !== confirmarSenha) { showAlert('As senhas não conferem.'); return; }
  if (!requisitosOk) { showAlert('A nova senha não atende todos os requisitos.'); return; }

  btn.disabled = true;
  btnText.style.display = 'none';
  spinner.style.display = 'inline-block';
  alert.style.display   = 'none';

  try {
    const res = await fetch(BASE_URL + '/api/trocar_senha.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      credentials: 'same-origin',
      body: JSON.stringify({ senha_atual: senhaAtual, nova_senha: novaSenha })
    });
    const data = await res.json();

    if (data.ok) {
      window.location.href = BASE_URL + '/dashboard.php';
    } else {
      showAlert(data.error || 'Erro ao trocar senha.');
      btn.disabled = false;
      btnText.style.display = '';
      spinner.style.display = 'none';
    }
  } catch {
    showAlert('Erro de conexão com o servidor.');
    btn.disabled = false;
    btnText.style.display = '';
    spinner.style.display = 'none';
  }
});

function showAlert(msg) {
  const el = document.getElementById('alertBox');
  el.textContent = msg;
  el.style.display = 'block';
}

function toggleVis(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
