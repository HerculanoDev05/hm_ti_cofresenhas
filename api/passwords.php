<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match(true) {
    $method==='GET'    && $action==='list'     => listPasswords(),
    $method==='GET'    && $action==='reveal'   => revealPassword(),
    $method==='POST'   && $action==='add'      => addPassword(),
    $method==='POST'   && $action==='edit'     => editPassword(),
    $method==='DELETE' && $action==='delete'   => deletePassword(),
    $method==='GET'    && $action==='cats'     => listCategories(),
    $method==='GET'    && $action==='generate' => generatePassword(),
    default => jsonResponse(['ok'=>false,'error'=>'Ação inválida.'],404),
};

function listPasswords(): never {
    $nivel = Auth::nivel();
    $rows  = Database::query(
        "SELECT s.id, s.titulo, s.usuario_login, s.url, s.observacao,
                s.nivel_acesso, s.expira_em, s.criado_em, s.atualizado_em,
                c.nome AS categoria, c.icone AS cat_icone, c.cor AS cat_cor, c.id AS categoria_id,
                u.nome_completo AS adicionado_por
           FROM senhas s
           JOIN categorias c ON c.id = s.categoria_id
           JOIN usuarios   u ON u.id = s.adicionado_por
          WHERE s.ativo=1 AND s.nivel_acesso<=?
          ORDER BY c.nome, s.titulo",
        [$nivel]
    );
    jsonResponse(['ok'=>true,'data'=>$rows]);
}

function revealPassword(): never {
    $id    = (int)($_GET['id']??0);
    $nivel = Auth::nivel();
    $row   = Database::queryOne(
        'SELECT * FROM senhas WHERE id=? AND ativo=1 AND nivel_acesso<=?',
        [$id,$nivel]
    );
    if (!$row) {
        Logger::log(Logger::ACCESS_DENIED,"senha_id=$id",false,'Nível insuficiente');
        jsonResponse(['ok'=>false,'error'=>'Credencial não encontrada ou acesso negado.'],403);
    }
    try { $plain = Crypto::decrypt($row['senha_enc'],$row['senha_iv']); }
    catch (Throwable $e) { jsonResponse(['ok'=>false,'error'=>'Erro ao descriptografar.'],500); }
    Logger::log(Logger::SENHA_VER, $row['titulo']);
    jsonResponse(['ok'=>true,'senha'=>$plain]);
}

function addPassword(): never {
    Auth::requireLevel(2);
    verifyCsrf();
    $body        = json_decode(file_get_contents('php://input'),true)??[];
    $titulo      = trim($body['titulo']??'');
    $userLogin   = trim($body['usuario_login']??'');
    $senhaPlain  = $body['senha']??'';
    $categoriaId = (int)($body['categoria_id']??7);
    $nivelAcesso = min((int)($body['nivel_acesso']??1), Auth::nivel());
    $url         = trim($body['url']??'');
    $obs         = trim($body['observacao']??'');
    $expiraEm    = $body['expira_em']??null;
    if (!$titulo||!$userLogin||!$senhaPlain) {
        jsonResponse(['ok'=>false,'error'=>'Título, usuário e senha são obrigatórios.'],400);
    }
    $cripto = Crypto::encrypt($senhaPlain);
    Database::execute(
        'INSERT INTO senhas (titulo,usuario_login,senha_enc,senha_iv,url,observacao,categoria_id,nivel_acesso,expira_em,adicionado_por)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$titulo,$userLogin,$cripto['enc'],$cripto['iv'],$url?:null,$obs?:null,$categoriaId,$nivelAcesso,$expiraEm?:null,Auth::userId()]
    );
    Logger::log(Logger::SENHA_ADD,$titulo);
    jsonResponse(['ok'=>true,'id'=>Database::lastId()]);
}

function editPassword(): never {
    Auth::requireLevel(2);
    verifyCsrf();
    $body      = json_decode(file_get_contents('php://input'),true)??[];
    $id        = (int)($body['id']??0);
    $nivel     = Auth::nivel();
    $existing  = Database::queryOne('SELECT * FROM senhas WHERE id=? AND ativo=1 AND nivel_acesso<=?',[$id,$nivel]);
    if (!$existing) { jsonResponse(['ok'=>false,'error'=>'Credencial não encontrada ou acesso negado.'],403); }
    $titulo      = trim($body['titulo']??$existing['titulo']);
    $userLogin   = trim($body['usuario_login']??$existing['usuario_login']);
    $categoriaId = (int)($body['categoria_id']??$existing['categoria_id']);
    $nivelAcesso = min((int)($body['nivel_acesso']??$existing['nivel_acesso']),$nivel);
    $url         = trim($body['url']??$existing['url']??'');
    $obs         = trim($body['observacao']??$existing['observacao']??'');
    $expiraEm    = $body['expira_em']??$existing['expira_em'];
    $enc = $existing['senha_enc']; $iv = $existing['senha_iv'];
    if (!empty($body['senha'])) { $c=$cripto=Crypto::encrypt($body['senha']); $enc=$c['enc']; $iv=$c['iv']; }
    Database::execute(
        'UPDATE senhas SET titulo=?,usuario_login=?,senha_enc=?,senha_iv=?,url=?,observacao=?,categoria_id=?,nivel_acesso=?,expira_em=?,atualizado_por=? WHERE id=?',
        [$titulo,$userLogin,$enc,$iv,$url?:null,$obs?:null,$categoriaId,$nivelAcesso,$expiraEm?:null,Auth::userId(),$id]
    );
    Logger::log(Logger::SENHA_EDIT,$titulo);
    jsonResponse(['ok'=>true]);
}

function deletePassword(): never {
    Auth::requireLevel(3);
    verifyCsrf();
    $id    = (int)($_GET['id']??0);
    $nivel = Auth::nivel();
    $row   = Database::queryOne('SELECT id,titulo FROM senhas WHERE id=? AND ativo=1 AND nivel_acesso<=?',[$id,$nivel]);
    if (!$row) { jsonResponse(['ok'=>false,'error'=>'Credencial não encontrada ou acesso negado.'],403); }
    Database::execute('UPDATE senhas SET ativo=0,atualizado_por=? WHERE id=?',[Auth::userId(),$id]);
    Logger::log(Logger::SENHA_DEL,$row['titulo']);
    jsonResponse(['ok'=>true]);
}

function listCategories(): never {
    jsonResponse(['ok'=>true,'data'=>Database::query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')]);
}

function generatePassword(): never {
    $len = min((int)($_GET['len']??16),64);
    jsonResponse(['ok'=>true,'senha'=>Crypto::generatePassword($len)]);
}
