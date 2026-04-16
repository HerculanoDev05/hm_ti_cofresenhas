<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLevel(3);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match(true) {
    $method==='GET'    && $action==='list'   => listUsers(),
    $method==='POST'   && $action==='add'    => addUser(),
    $method==='POST'   && $action==='edit'   => editUser(),
    $method==='POST'   && $action==='toggle' => toggleUser(),
    default => jsonResponse(['ok'=>false,'error'=>'Ação inválida.'],404),
};

function listUsers(): never {
    $rows = Database::query(
        "SELECT u.id,u.username,u.email,u.nome_completo,u.nivel_id,n.nome AS nivel_nome,
                u.ativo,u.bloqueado,u.motivo_bloqueio,u.tentativas_falha,
                u.ultimo_acesso,u.senha_expira_em,u.criado_em
           FROM usuarios u JOIN niveis_acesso n ON n.id=u.nivel_id
          ORDER BY u.nivel_id DESC, u.nome_completo"
    );
    jsonResponse(['ok'=>true,'data'=>$rows]);
}

function addUser(): never {
    Auth::requireLevel(4);
    verifyCsrf();
    $body    = json_decode(file_get_contents('php://input'),true)??[];
    $username= trim($body['username']??'');
    $email   = trim($body['email']??'');
    $nome    = trim($body['nome_completo']??'');
    $senha   = $body['senha']??'';
    $nivelId = (int)($body['nivel_id']??1);
    $diasExp = (int)($body['dias_expira']??90);
    if (!$username||!$email||!$nome||!$senha) { jsonResponse(['ok'=>false,'error'=>'Todos os campos são obrigatórios.'],400); }
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { jsonResponse(['ok'=>false,'error'=>'E-mail inválido.'],400); }
    if (strlen($senha)<8) { jsonResponse(['ok'=>false,'error'=>'Senha mínima: 8 caracteres.'],400); }
    $hash = password_hash($senha,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
    try {
        Database::execute(
            'INSERT INTO usuarios (username,email,nome_completo,senha_hash,nivel_id,senha_expira_em,criado_por) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY),?)',
            [$username,$email,$nome,$hash,$nivelId,$diasExp,Auth::userId()]
        );
        Logger::log(Logger::USER_ADD,$username);
        jsonResponse(['ok'=>true,'id'=>Database::lastId()]);
    } catch (PDOException $e) {
        if ($e->getCode()==='23000') { jsonResponse(['ok'=>false,'error'=>'Username ou e-mail já cadastrado.'],409); }
        throw $e;
    }
}

function editUser(): never {
    verifyCsrf();
    $body    = json_decode(file_get_contents('php://input'),true)??[];
    $id      = (int)($body['id']??0);
    $myLevel = Auth::nivel();
    $target  = Database::queryOne('SELECT * FROM usuarios WHERE id=?',[$id]);
    if (!$target) { jsonResponse(['ok'=>false,'error'=>'Usuário não encontrado.'],404); }
    if ($myLevel<4 && (int)$target['nivel_id']>=$myLevel) { jsonResponse(['ok'=>false,'error'=>'Sem permissão para editar este usuário.'],403); }
    $email   = trim($body['email']??$target['email']);
    $nome    = trim($body['nome_completo']??$target['nome_completo']);
    $nivelId = (int)($body['nivel_id']??$target['nivel_id']);
    $diasExp = isset($body['dias_expira'])?(int)$body['dias_expira']:null;
    $sql     = 'UPDATE usuarios SET email=?,nome_completo=?,nivel_id=?';
    $params  = [$email,$nome,$nivelId];
    if ($diasExp!==null) { $sql.=',senha_expira_em=DATE_ADD(NOW(),INTERVAL ? DAY)'; $params[]=$diasExp; }
    if (!empty($body['senha'])) {
        if (strlen($body['senha'])<8) { jsonResponse(['ok'=>false,'error'=>'Senha mínima: 8 caracteres.'],400); }
        $sql.=',senha_hash=?'; $params[]=password_hash($body['senha'],PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
    }
    $sql.=' WHERE id=?'; $params[]=$id;
    Database::execute($sql,$params);
    Logger::log(Logger::USER_EDIT,$target['username']);
    jsonResponse(['ok'=>true]);
}

function toggleUser(): never {
    Auth::requireLevel(4);
    verifyCsrf();
    $body   = json_decode(file_get_contents('php://input'),true)??[];
    $id     = (int)($body['id']??0);
    $action = $body['action']??'';
    $user   = Database::queryOne('SELECT * FROM usuarios WHERE id=?',[$id]);
    if (!$user) { jsonResponse(['ok'=>false,'error'=>'Usuário não encontrado.'],404); }
    switch ($action) {
        case 'block':
            Database::execute("UPDATE usuarios SET bloqueado=1,motivo_bloqueio=? WHERE id=?",[$body['motivo']??'Bloqueado pelo administrador',$id]);
            Logger::log(Logger::USER_BLOCK,$user['username']); break;
        case 'unblock':
            Database::execute("UPDATE usuarios SET bloqueado=0,motivo_bloqueio=NULL,tentativas_falha=0 WHERE id=?",[$id]);
            Database::execute('DELETE FROM tentativas_login WHERE username=?',[$user['username']]); break;
        case 'activate':
            Database::execute('UPDATE usuarios SET ativo=1 WHERE id=?',[$id]); break;
        case 'deactivate':
            if ($id===Auth::userId()) { jsonResponse(['ok'=>false,'error'=>'Não pode desativar a si mesmo.'],400); }
            Database::execute('UPDATE usuarios SET ativo=0 WHERE id=?',[$id]); break;
        default: jsonResponse(['ok'=>false,'error'=>'Ação inválida.'],400);
    }
    jsonResponse(['ok'=>true]);
}
