<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();
$nivel     = Auth::nivel();
$username  = $_SESSION['username'];
$nomeCurto = explode(' ', $_SESSION['nome'])[0];
$csrf      = csrfToken();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<!-- ── Toast container ─────────────────────────────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<!-- ── App layout ─────────────────────────────────────────────────────── -->
<div class="app-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">
        <span class="sidebar-logo-icon">🔐</span>
        <span class="sidebar-logo-text"><?= APP_NAME ?></span>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar av-<?= $nivel ?>" id="sideAvatar">
        <?= strtoupper(substr($nomeCurto, 0, 2)) ?>
      </div>
      <div class="user-name"><?= htmlspecialchars($nomeCurto) ?></div>
      <div class="user-nivel"><?= htmlspecialchars($_SESSION['nivel_nome']) ?></div>
    </div>

    <nav>
      <div class="nav-section">
        <div class="nav-section-title">Principal</div>
        <div class="nav-item active" data-panel="senhas" onclick="switchPanel(this,'senhas')">
          <span class="nav-icon">🔑</span> Credenciais
        </div>
        <div class="nav-item" data-panel="log" onclick="switchPanel(this,'log')">
          <span class="nav-icon">📋</span> Meu Log
        </div>
      </div>

      <?php if ($nivel >= 3): ?>
      <div class="nav-section">
        <div class="nav-section-title">Gestão</div>
        <?php if ($nivel >= 3): ?>
        <div class="nav-item" data-panel="usuarios" onclick="switchPanel(this,'usuarios')">
          <span class="nav-icon">👥</span> Usuários
        </div>
        <?php endif; ?>
        <?php if ($nivel >= 4): ?>
        <div class="nav-item" data-panel="auditoria" onclick="switchPanel(this,'auditoria')">
          <span class="nav-icon">🕵️</span> Auditoria
        </div>
        <div class="nav-item" data-panel="politicas" onclick="switchPanel(this,'politicas')">
          <span class="nav-icon">🛡️</span> Políticas
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="db-indicator">
        <div class="db-dot"></div>
        <span>MySQL · hm_cofre</span>
      </div>
      <button class="btn btn-ghost btn-sm btn-full" onclick="doLogout()">⬅ Sair</button>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-content">
    <div class="topbar">
      <span class="topbar-title" id="topbarTitle">Credenciais</span>
      <div class="topbar-actions">
        <?php if ($nivel >= 2): ?>
        <button class="btn btn-success btn-sm" onclick="openModalAdd()">＋ Nova Credencial</button>
        <?php endif; ?>
        <button class="btn btn-ghost btn-sm" onclick="doLogout()">Sair</button>
      </div>
    </div>

    <div class="content-area">

      <!-- ── PAINEL SENHAS ────────────────────────────────────────────── -->
      <div id="panel-senhas" class="panel active">
        <div class="stats-grid" id="statsGrid"></div>
        <div class="card">
          <div class="card-header">
            <span class="card-title">🔑 Credenciais</span>
            <span id="credCount" style="font-size:.75rem;color:var(--text3)"></span>
          </div>
          <div class="card-body">
            <div class="filter-bar">
              <input type="text" class="search-input" id="searchInput"
                     placeholder="🔍  Buscar por título, usuário ou categoria..."
                     oninput="filterCreds()">
              <div class="filter-pills" id="catPills"></div>
            </div>
            <div id="credList" class="cred-list"><div class="empty-state"><div class="spinner"></div></div></div>
          </div>
        </div>
      </div>

      <!-- ── PAINEL LOG PESSOAL ───────────────────────────────────────── -->
      <div id="panel-log" class="panel">
        <div class="card">
          <div class="card-header">
            <span class="card-title">📋 Meu Log de Acesso</span>
            <button class="btn btn-ghost btn-sm" onclick="loadLog()">↺ Atualizar</button>
          </div>
          <div class="card-body" style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr><th>Data/Hora</th><th>Ação</th><th>Recurso</th><th>Status</th><th>IP</th></tr>
              </thead>
              <tbody id="logBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <?php if ($nivel >= 3): ?>
      <!-- ── PAINEL USUÁRIOS ──────────────────────────────────────────── -->
      <div id="panel-usuarios" class="panel">
        <div class="card">
          <div class="card-header">
            <span class="card-title">👥 Usuários do Sistema</span>
            <?php if ($nivel >= 4): ?>
            <button class="btn btn-success btn-sm" onclick="openModalAddUser()">＋ Novo Usuário</button>
            <?php endif; ?>
          </div>
          <div class="card-body" style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr><th>Nome</th><th>Username</th><th>E-mail</th><th>Nível</th><th>Status</th><th>Último Acesso</th><th>Ações</th></tr>
              </thead>
              <tbody id="usersBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($nivel >= 4): ?>
      <!-- ── PAINEL AUDITORIA ─────────────────────────────────────────── -->
      <div id="panel-auditoria" class="panel">
        <div class="card">
          <div class="card-header">
            <span class="card-title">🕵️ Auditoria Completa</span>
            <button class="btn btn-ghost btn-sm" onclick="loadAuditoria()">↺ Atualizar</button>
          </div>
          <div class="card-body" style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr><th>Data/Hora</th><th>Usuário</th><th>Ação</th><th>Recurso</th><th>Status</th><th>IP</th></tr>
              </thead>
              <tbody id="auditoriaBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── PAINEL POLÍTICAS ─────────────────────────────────────────── -->
      <div id="panel-politicas" class="panel">
        <div class="card">
          <div class="card-header">
            <span class="card-title">🛡️ Políticas de Acesso</span>
            <button class="btn btn-success btn-sm" onclick="openModalPolicy()">＋ Nova Política</button>
          </div>
          <div class="card-body">
            <div class="policy-list" id="policyList"></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /content-area -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- ══════════════ MODAIS ══════════════════════════════════════════════════ -->

<!-- Modal: Adicionar/Editar Credencial -->
<div class="modal-overlay" id="modalCred">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title" id="modalCredTitle">Nova Credencial</h2>
      <button class="modal-close" onclick="closeModal('modalCred')">✕</button>
    </div>
    <input type="hidden" id="credId">
    <div class="form-group">
      <label class="form-label">Título *</label>
      <input type="text" id="credTitulo" class="form-input" placeholder="Ex: ERP Totvs RM">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="form-group">
        <label class="form-label">Usuário / Login *</label>
        <input type="text" id="credUsuario" class="form-input" placeholder="usuario@empresa.com">
      </div>
      <div class="form-group">
        <label class="form-label">Categoria</label>
        <select id="credCat" class="form-input"></select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Senha *</label>
      <div class="input-icon-wrap">
        <input type="text" id="credSenha" class="form-input" placeholder="Senha" oninput="updateStrength(this.value)">
        <button type="button" class="eye-btn" onclick="generateAndFill()" title="Gerar senha forte">⚡</button>
      </div>
      <div class="strength-bar-wrap str-0" id="strengthBar">
        <div class="strength-seg"></div><div class="strength-seg"></div>
        <div class="strength-seg"></div><div class="strength-seg"></div>
        <div class="strength-seg"></div>
      </div>
      <div class="strength-label" id="strengthLabel">—</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="form-group">
        <label class="form-label">Nível de Acesso</label>
        <select id="credNivel" class="form-input">
          <option value="1">1 — Visualizador</option>
          <option value="2">2 — Operador</option>
          <?php if ($nivel >= 3): ?><option value="3">3 — Gerente</option><?php endif; ?>
          <?php if ($nivel >= 4): ?><option value="4">4 — Administrador</option><?php endif; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Expira em</label>
        <input type="date" id="credExpira" class="form-input">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">URL (opcional)</label>
      <input type="url" id="credUrl" class="form-input" placeholder="https://...">
    </div>
    <div class="form-group">
      <label class="form-label">Observação</label>
      <textarea id="credObs" class="form-input" rows="2" placeholder="Observações adicionais..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modalCred')">Cancelar</button>
      <button class="btn btn-success" onclick="saveCred()">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- Modal: Revelar Senha -->
<div class="modal-overlay" id="modalReveal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <h2 class="modal-title">🔓 Senha Revelada</h2>
      <button class="modal-close" onclick="closeModal('modalReveal')">✕</button>
    </div>
    <p style="font-size:.78rem;color:var(--text3);margin-bottom:.75rem" id="revealCredName"></p>
    <div style="display:flex;align-items:center;gap:.5rem">
      <code id="revealPass" style="flex:1;padding:.6rem .85rem;background:var(--bg3);border-radius:var(--radius);
        font-family:var(--font-mono);font-size:.9rem;color:var(--green);word-break:break-all;
        border:1px solid var(--border2)"></code>
      <button class="btn btn-ghost btn-icon" onclick="copyReveal()" title="Copiar">📋</button>
    </div>
    <p style="font-size:.7rem;color:var(--text3);margin-top:.75rem">
      ⚠️ Esta ação foi registrada no log de auditoria.
    </p>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modalReveal')">Fechar</button>
    </div>
  </div>
</div>

<?php if ($nivel >= 4): ?>
<!-- Modal: Novo Usuário -->
<div class="modal-overlay" id="modalUser">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title" id="modalUserTitle">Novo Usuário</h2>
      <button class="modal-close" onclick="closeModal('modalUser')">✕</button>
    </div>
    <input type="hidden" id="userId">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" id="userUsername" class="form-input" placeholder="usuario.nome">
      </div>
      <div class="form-group">
        <label class="form-label">E-mail *</label>
        <input type="email" id="userEmail" class="form-input" placeholder="email@empresa.com">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Nome Completo *</label>
      <input type="text" id="userNome" class="form-input" placeholder="Nome Completo">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="form-group">
        <label class="form-label">Nível *</label>
        <select id="userNivel" class="form-input">
          <option value="1">1 — Visualizador</option>
          <option value="2">2 — Operador</option>
          <option value="3">3 — Gerente</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Expira senha (dias)</label>
        <input type="number" id="userDiasExp" class="form-input" value="90" min="1" max="365">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Senha Inicial *</label>
      <input type="text" id="userSenha" class="form-input" placeholder="Mínimo 8 caracteres">
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modalUser')">Cancelar</button>
      <button class="btn btn-success" onclick="saveUser()">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- Modal: Nova Política -->
<div class="modal-overlay" id="modalPolicy">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title">Nova Política de Acesso</h2>
      <button class="modal-close" onclick="closeModal('modalPolicy')">✕</button>
    </div>
    <div class="form-group">
      <label class="form-label">Nome da Política *</label>
      <input type="text" id="policyNome" class="form-input" placeholder="Ex: Bloqueio IP Externo">
    </div>
    <div class="form-group">
      <label class="form-label">Tipo *</label>
      <select id="policyTipo" class="form-input" onchange="renderPolicyFields()">
        <option value="ip_whitelist">IP Whitelist</option>
        <option value="ip_blacklist">IP Blacklist</option>
        <option value="horario">Restrição de Horário</option>
        <option value="max_tentativas">Máx. Tentativas de Login</option>
        <option value="sessao_timeout">Timeout de Sessão</option>
        <option value="expiracao_senha">Expiração de Senha</option>
      </select>
    </div>
    <div id="policyFields"></div>
    <div class="form-group">
      <label class="form-label">Aplicar ao Nível (vazio = todos)</label>
      <select id="policyNivel" class="form-input">
        <option value="">Todos os níveis</option>
        <option value="1">1 — Visualizador</option>
        <option value="2">2 — Operador</option>
        <option value="3">3 — Gerente</option>
        <option value="4">4 — Administrador</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modalPolicy')">Cancelar</button>
      <button class="btn btn-success" onclick="savePolicy()">💾 Salvar</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════ JAVASCRIPT ══════════════════════════════════════════════ -->
<script>
const BASE_URL   = '<?= BASE_URL ?>';
const NIVEL      = <?= $nivel ?>;
const CSRF_TOKEN = '<?= $csrf ?>';

// ─── Estado global ────────────────────────────────────────────────────────
let allCreds = [], allCats = [], activeCat = 'all';

// ─── Init ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadCategorias().then(() => loadCreds());
  renderStats();
});

// ─── Navegação ────────────────────────────────────────────────────────────
function switchPanel(el, panel) {
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + panel).classList.add('active');

  const titles = { senhas:'Credenciais', log:'Meu Log', usuarios:'Usuários',
                   auditoria:'Auditoria', politicas:'Políticas de Acesso' };
  document.getElementById('topbarTitle').textContent = titles[panel] || panel;

  if (panel === 'log')        loadLog();
  if (panel === 'usuarios')   loadUsers();
  if (panel === 'auditoria')  loadAuditoria();
  if (panel === 'politicas')  loadPolicies();
}

async function doLogout() {
  await api('api/logout.php', 'POST');
  window.location.href = BASE_URL + '/index.php?logout=1';
}

// ─── API helper ───────────────────────────────────────────────────────────
async function api(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
  };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(BASE_URL + '/' + endpoint, opts);
  const data = await res.json();
  if (!data.ok && data.error) toast(data.error, 'err');
  return data;
}

// ─── CREDENCIAIS ──────────────────────────────────────────────────────────
async function loadCategorias() {
  const d = await api('api/passwords.php?action=cats');
  if (d.ok) {
    allCats = d.data;
    renderCatPills();
    populateCatSelect();
  }
}

function renderCatPills() {
  const wrap = document.getElementById('catPills');
  wrap.innerHTML = `<button class="pill active" onclick="setCat('all',this)">Todas</button>` +
    allCats.map(c => `<button class="pill" onclick="setCat(${c.id},this)">${c.icone} ${c.nome}</button>`).join('');
}

function populateCatSelect() {
  const sel = document.getElementById('credCat');
  sel.innerHTML = allCats.map(c => `<option value="${c.id}">${c.icone} ${c.nome}</option>`).join('');
}

function setCat(catId, btn) {
  activeCat = catId;
  document.querySelectorAll('#catPills .pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  filterCreds();
}

async function loadCreds() {
  document.getElementById('credList').innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';
  const d = await api('api/passwords.php?action=list');
  if (d.ok) {
    allCreds = d.data;
    filterCreds();
    renderStats();
  }
}

function filterCreds() {
  const q = (document.getElementById('searchInput').value || '').toLowerCase();
  const visible = allCreds.filter(c => {
    if (activeCat !== 'all' && c.categoria_id != activeCat && c.categoria != activeCat) {
      const cat = allCats.find(x => String(x.id) === String(activeCat));
      if (cat && c.categoria !== cat.nome) return false;
    }
    if (q) {
      const hay = (c.titulo + c.usuario_login + c.categoria).toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });
  renderCreds(visible);
}

function renderCreds(list) {
  const el = document.getElementById('credList');
  document.getElementById('credCount').textContent = `${list.length} credencial(is)`;

  if (!list.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-state-icon">🔍</div><div class="empty-state-text">Nenhuma credencial encontrada.</div></div>';
    return;
  }

  const today = new Date();
  el.innerHTML = list.map(c => {
    const expDate  = c.expira_em ? new Date(c.expira_em) : null;
    const expiring = expDate && (expDate - today) / 86400000 <= 30;
    const expired  = expDate && expDate < today;
    const nivelCls = `tag-nivel${c.nivel_acesso}`;
    const canEdit  = NIVEL >= 3 || (NIVEL >= 2);
    const canDel   = NIVEL >= 3;
    return `
    <div class="cred-card" id="cred-${c.id}">
      <div class="cred-top">
        <div class="cred-meta">
          <div class="cred-title">${esc(c.titulo)}</div>
          <div class="cred-user">👤 ${esc(c.usuario_login)}</div>
        </div>
        <div class="cred-tags">
          <span class="tag tag-cat">${esc(c.cat_icone)} ${esc(c.categoria)}</span>
          <span class="tag ${nivelCls}">N${c.nivel_acesso}</span>
        </div>
      </div>
      <div class="cred-pass">
        <div class="pass-display" id="pass-${c.id}">●●●●●●●●●●</div>
        <div class="cred-actions">
          <button class="btn btn-ghost btn-sm btn-icon" onclick="revealPass(${c.id},'${esc(c.titulo)}')" title="Ver senha">👁</button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="copyPass(${c.id},'${esc(c.titulo)}')" title="Copiar senha">📋</button>
          ${canEdit ? `<button class="btn btn-ghost btn-sm btn-icon" onclick="openModalEdit(${c.id})" title="Editar">✏️</button>` : ''}
          ${canDel  ? `<button class="btn btn-ghost btn-sm btn-icon" onclick="deleteCred(${c.id},'${esc(c.titulo)}')" title="Remover" style="color:var(--red)">🗑</button>` : ''}
        </div>
      </div>
      ${expired  ? `<div class="expiry-warn">⛔ Senha expirada em ${fmtDate(c.expira_em)}</div>` : ''}
      ${expiring && !expired ? `<div class="expiry-warn">⚠️ Expira em ${fmtDate(c.expira_em)}</div>` : ''}
    </div>`;
  }).join('');
}

async function revealPass(id, titulo) {
  const d = await api(`api/passwords.php?action=reveal&id=${id}`);
  if (!d.ok) return;
  document.getElementById('revealCredName').textContent = titulo;
  document.getElementById('revealPass').textContent = d.senha;
  openModal('modalReveal');
}

function copyReveal() {
  const txt = document.getElementById('revealPass').textContent;
  navigator.clipboard?.writeText(txt);
  toast('Senha copiada!');
}

async function copyPass(id, titulo) {
  const d = await api(`api/passwords.php?action=reveal&id=${id}`);
  if (!d.ok) return;
  navigator.clipboard?.writeText(d.senha);
  toast(`Senha de "${titulo}" copiada!`);
}

// ─── Modal Credencial ──────────────────────────────────────────────────────
function openModalAdd() {
  document.getElementById('credId').value = '';
  document.getElementById('modalCredTitle').textContent = 'Nova Credencial';
  ['credTitulo','credUsuario','credSenha','credUrl','credObs'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('credNivel').value = '1';
  document.getElementById('credExpira').value = '';
  updateStrength('');
  openModal('modalCred');
}

async function openModalEdit(id) {
  const cred = allCreds.find(c => c.id == id);
  if (!cred) return;
  document.getElementById('credId').value = id;
  document.getElementById('modalCredTitle').textContent = 'Editar Credencial';
  document.getElementById('credTitulo').value   = cred.titulo;
  document.getElementById('credUsuario').value  = cred.usuario_login;
  document.getElementById('credSenha').value    = '';
  document.getElementById('credNivel').value    = cred.nivel_acesso;
  document.getElementById('credExpira').value   = cred.expira_em || '';
  document.getElementById('credUrl').value      = cred.url || '';
  document.getElementById('credObs').value      = cred.observacao || '';

  const cat = allCats.find(c => c.nome === cred.categoria);
  if (cat) document.getElementById('credCat').value = cat.id;
  updateStrength('');
  openModal('modalCred');
}

async function saveCred() {
  const id     = document.getElementById('credId').value;
  const titulo = document.getElementById('credTitulo').value.trim();
  const user   = document.getElementById('credUsuario').value.trim();
  const senha  = document.getElementById('credSenha').value;
  const nivel  = document.getElementById('credNivel').value;
  const cat    = document.getElementById('credCat').value;
  const expira = document.getElementById('credExpira').value;
  const url    = document.getElementById('credUrl').value.trim();
  const obs    = document.getElementById('credObs').value.trim();

  if (!titulo || !user) { toast('Título e usuário são obrigatórios.', 'err'); return; }
  if (!id && !senha)    { toast('Senha é obrigatória.', 'err'); return; }

  const body = { titulo, usuario_login: user, senha, nivel_acesso: parseInt(nivel),
                 categoria_id: parseInt(cat), url, observacao: obs };
  if (expira) body.expira_em = expira;

  const endpoint = id
    ? 'api/passwords.php?action=edit'
    : 'api/passwords.php?action=add';

  if (id) body.id = parseInt(id);

  const d = await api(endpoint, 'POST', body);
  if (d.ok) {
    toast(id ? 'Credencial atualizada!' : 'Credencial adicionada e criptografada!');
    closeModal('modalCred');
    loadCreds();
  }
}

async function deleteCred(id, titulo) {
  if (!confirm(`Remover a credencial "${titulo}"?`)) return;
  const d = await api(`api/passwords.php?action=delete&id=${id}`, 'DELETE');
  if (d.ok) { toast('Credencial removida.'); loadCreds(); }
}

// ─── Força da senha ───────────────────────────────────────────────────────
function updateStrength(pwd) {
  let score = 0;
  if (pwd.length >= 8)  score++;
  if (pwd.length >= 12) score++;
  if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  score = Math.min(score, 5);
  const bar    = document.getElementById('strengthBar');
  const labels = ['','Muito fraca','Fraca','Média','Boa','Forte'];
  bar.className = `strength-bar-wrap str-${score}`;
  document.getElementById('strengthLabel').textContent = labels[score] || '';
}

async function generateAndFill() {
  const d = await api('api/passwords.php?action=generate&len=16');
  if (d.ok) {
    document.getElementById('credSenha').value = d.senha;
    updateStrength(d.senha);
    toast('Senha forte gerada!');
  }
}

// ─── Stats ────────────────────────────────────────────────────────────────
function renderStats() {
  const total   = allCreds.length;
  const today   = new Date();
  const expSoon = allCreds.filter(c => {
    if (!c.expira_em) return false;
    const d = (new Date(c.expira_em) - today) / 86400000;
    return d >= 0 && d <= 30;
  }).length;
  const expired = allCreds.filter(c => c.expira_em && new Date(c.expira_em) < today).length;

  document.getElementById('statsGrid').innerHTML = `
    <div class="stat-card">
      <div class="stat-label">Total de Credenciais</div>
      <div class="stat-val">${total}</div>
      <div class="stat-sub">visíveis para seu nível</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Categorias</div>
      <div class="stat-val">${allCats.length}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Expirando em 30 dias</div>
      <div class="stat-val" style="color:var(--amber)">${expSoon}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Expiradas</div>
      <div class="stat-val" style="color:var(--red)">${expired}</div>
    </div>`;
}

// ─── LOG ──────────────────────────────────────────────────────────────────
async function loadLog() {
  const d = await api('api/logs.php?action=list&limit=100');
  if (!d.ok) return;
  const tbody = document.getElementById('logBody');
  if (!d.data.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text3)">Sem registros</td></tr>'; return; }
  tbody.innerHTML = d.data.map(l => `
    <tr>
      <td class="td-mono">${fmtDateTime(l.criado_em)}</td>
      <td>${esc(l.acao)}</td>
      <td style="color:var(--text2)">${esc(l.recurso || '—')}</td>
      <td>${l.sucesso ? '<span class="badge badge-ok">✓ OK</span>' : '<span class="badge badge-fail">✗ Falha</span>'}</td>
      <td class="td-mono" style="color:var(--text3)">${esc(l.ip)}</td>
    </tr>`).join('');
}

// ─── USUÁRIOS ─────────────────────────────────────────────────────────────
async function loadUsers() {
  const d = await api('api/users.php?action=list');
  if (!d.ok) return;
  const tbody = document.getElementById('usersBody');
  const nivelLabels = {1:'Visualizador',2:'Operador',3:'Gerente',4:'Administrador'};
  const nivelCls    = {1:'badge-neutral',2:'badge-info',3:'badge-warn',4:'badge-ok'};
  tbody.innerHTML = d.data.map(u => `
    <tr>
      <td>${esc(u.nome_completo)}</td>
      <td class="td-mono">${esc(u.username)}</td>
      <td style="color:var(--text3)">${esc(u.email)}</td>
      <td><span class="badge ${nivelCls[u.nivel_id]}">${nivelLabels[u.nivel_id]}</span></td>
      <td>
        ${u.bloqueado ? '<span class="badge badge-fail">Bloqueado</span>'
          : u.ativo   ? '<span class="badge badge-ok">Ativo</span>'
                      : '<span class="badge badge-neutral">Inativo</span>'}
      </td>
      <td class="td-mono" style="color:var(--text3)">${u.ultimo_acesso ? fmtDateTime(u.ultimo_acesso) : '—'}</td>
      <td>
        ${NIVEL >= 4 ? `
          ${u.bloqueado
            ? `<button class="btn btn-ghost btn-sm" onclick="toggleUser(${u.id},'unblock')">Desbloquear</button>`
            : `<button class="btn btn-ghost btn-sm" style="color:var(--amber)" onclick="toggleUser(${u.id},'block')">Bloquear</button>`}
          <button class="btn btn-ghost btn-sm" onclick="openModalEditUser(${u.id})">✏️</button>
        ` : '—'}
      </td>
    </tr>`).join('');
}

function openModalAddUser() {
  document.getElementById('userId').value = '';
  document.getElementById('modalUserTitle').textContent = 'Novo Usuário';
  ['userUsername','userEmail','userNome','userSenha'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('userNivel').value   = '1';
  document.getElementById('userDiasExp').value = '90';
  openModal('modalUser');
}

async function openModalEditUser(id) {
  const d = await api('api/users.php?action=list');
  if (!d.ok) return;
  const u = d.data.find(x => x.id == id);
  if (!u) return;
  document.getElementById('userId').value       = id;
  document.getElementById('modalUserTitle').textContent = 'Editar Usuário';
  document.getElementById('userUsername').value = u.username;
  document.getElementById('userEmail').value    = u.email;
  document.getElementById('userNome').value     = u.nome_completo;
  document.getElementById('userNivel').value    = u.nivel_id;
  document.getElementById('userSenha').value    = '';
  openModal('modalUser');
}

async function saveUser() {
  const id    = document.getElementById('userId').value;
  const body  = {
    username:     document.getElementById('userUsername').value.trim(),
    email:        document.getElementById('userEmail').value.trim(),
    nome_completo:document.getElementById('userNome').value.trim(),
    nivel_id:     parseInt(document.getElementById('userNivel').value),
    dias_expira:  parseInt(document.getElementById('userDiasExp').value),
    senha:        document.getElementById('userSenha').value,
  };
  if (id) body.id = parseInt(id);
  const ep = id ? 'api/users.php?action=edit' : 'api/users.php?action=add';
  const d  = await api(ep, 'POST', body);
  if (d.ok) { toast(id ? 'Usuário atualizado!' : 'Usuário criado!'); closeModal('modalUser'); loadUsers(); }
}

async function toggleUser(id, action) {
  if (action === 'block' && !confirm('Bloquear este usuário?')) return;
  const d = await api('api/users.php?action=toggle', 'POST', { id, action });
  if (d.ok) { toast('Usuário atualizado.'); loadUsers(); }
}

// ─── AUDITORIA ────────────────────────────────────────────────────────────
async function loadAuditoria() {
  const d = await api('api/logs.php?action=list&limit=200');
  if (!d.ok) return;
  const tbody = document.getElementById('auditoriaBody');
  if (!d.data.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text3)">Sem registros</td></tr>'; return; }
  tbody.innerHTML = d.data.map(l => `
    <tr>
      <td class="td-mono">${fmtDateTime(l.criado_em)}</td>
      <td class="td-mono">${esc(l.username)}</td>
      <td>${esc(l.acao)}</td>
      <td style="color:var(--text2)">${esc(l.recurso || '—')}</td>
      <td>${l.sucesso ? '<span class="badge badge-ok">✓</span>' : '<span class="badge badge-fail">✗</span>'}</td>
      <td class="td-mono" style="color:var(--text3)">${esc(l.ip)}</td>
    </tr>`).join('');
}

// ─── POLÍTICAS ────────────────────────────────────────────────────────────
async function loadPolicies() {
  const d = await api('api/logs.php?action=list_policies');
  if (!d.ok) return;
  const el = document.getElementById('policyList');
  if (!d.data.length) { el.innerHTML = '<div class="empty-state">Nenhuma política cadastrada.</div>'; return; }
  el.innerHTML = d.data.map(p => `
    <div class="policy-card">
      <div>
        <div class="policy-name">${esc(p.nome)}</div>
        <div class="policy-type">${p.tipo} · ${p.aplica_nivel ? 'Nível '+p.aplica_nivel : 'Todos os níveis'}</div>
        <div style="font-size:.72rem;color:var(--text3);margin-top:3px;font-family:var(--font-mono)">${esc(p.valor)}</div>
      </div>
      <label class="toggle-switch">
        <input type="checkbox" ${p.ativo ? 'checked' : ''} onchange="togglePolicy(${p.id},this.checked)">
        <div class="toggle-track"></div>
        <div class="toggle-thumb"></div>
      </label>
    </div>`).join('');
}

async function togglePolicy(id, ativo) {
  const d = await api('api/logs.php?action=toggle_policy', 'POST', { id, ativo: ativo ? 1 : 0 });
  if (d.ok) toast(ativo ? 'Política ativada.' : 'Política desativada.');
}

function openModalPolicy() {
  document.getElementById('policyNome').value = '';
  document.getElementById('policyTipo').value = 'ip_whitelist';
  renderPolicyFields();
  openModal('modalPolicy');
}

function renderPolicyFields() {
  const tipo = document.getElementById('policyTipo').value;
  const el   = document.getElementById('policyFields');
  const fields = {
    ip_whitelist:   `<div class="form-group"><label class="form-label">IPs permitidos (separados por vírgula)</label><input type="text" id="pf1" class="form-input" placeholder="192.168.1.0/24, 10.0.0.1"></div>`,
    ip_blacklist:   `<div class="form-group"><label class="form-label">IPs bloqueados (separados por vírgula)</label><input type="text" id="pf1" class="form-input" placeholder="0.0.0.0/0"></div>`,
    horario:        `<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem"><div class="form-group"><label class="form-label">Hora início</label><input type="time" id="pf1" class="form-input" value="08:00"></div><div class="form-group"><label class="form-label">Hora fim</label><input type="time" id="pf2" class="form-input" value="18:00"></div></div>`,
    max_tentativas: `<div class="form-group"><label class="form-label">Máximo de tentativas</label><input type="number" id="pf1" class="form-input" value="5" min="1" max="20"></div>`,
    sessao_timeout: `<div class="form-group"><label class="form-label">Timeout em minutos</label><input type="number" id="pf1" class="form-input" value="480" min="5"></div>`,
    expiracao_senha:`<div class="form-group"><label class="form-label">Expiração em dias</label><input type="number" id="pf1" class="form-input" value="90" min="1"></div>`,
  };
  el.innerHTML = fields[tipo] || '';
}

async function savePolicy() {
  const nome  = document.getElementById('policyNome').value.trim();
  const tipo  = document.getElementById('policyTipo').value;
  const nivel = document.getElementById('policyNivel').value || null;
  const pf1   = document.getElementById('pf1')?.value || '';
  const pf2   = document.getElementById('pf2')?.value || '';

  if (!nome) { toast('Nome é obrigatório.', 'err'); return; }

  let valor = {};
  if (tipo === 'ip_whitelist' || tipo === 'ip_blacklist')
    valor = { ips: pf1.split(',').map(s => s.trim()).filter(Boolean) };
  else if (tipo === 'horario')
    valor = { inicio: pf1, fim: pf2, dias: [1,2,3,4,5] };
  else if (tipo === 'max_tentativas')
    valor = { max: parseInt(pf1), bloqueio_min: 30 };
  else if (tipo === 'sessao_timeout')
    valor = { minutos: parseInt(pf1) };
  else if (tipo === 'expiracao_senha')
    valor = { dias: parseInt(pf1) };

  const d = await api('api/logs.php?action=add_policy', 'POST',
    { nome, tipo, valor, aplica_nivel: nivel ? parseInt(nivel) : null });
  if (d.ok) { toast('Política criada!'); closeModal('modalPolicy'); loadPolicies(); }
}

// ─── Utilitários ──────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Fecha modal ao clicar no overlay - apenas para modalReveal
const modalReveal = document.getElementById('modalReveal');
if (modalReveal) {
  modalReveal.addEventListener('click', e => { if (e.target === modalReveal) modalReveal.classList.remove('open'); });
}

function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(d) { return d ? new Date(d).toLocaleDateString('pt-BR') : '—'; }
function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
}

function toast(msg, type = 'ok') {
  const c  = document.getElementById('toastContainer');
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = (type === 'ok' ? '✓ ' : type === 'err' ? '✗ ' : 'ℹ ') + msg;
  c.appendChild(el);
  setTimeout(() => { el.classList.add('toast-out'); setTimeout(() => el.remove(), 300); }, 3000);
}
</script>
</body>
</html>
