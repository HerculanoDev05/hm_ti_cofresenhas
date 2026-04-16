<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match(true) {
    $method==='GET'  && $action==='list'          => listLogs(),
    $method==='GET'  && $action==='list_policies' => listPolicies(),
    $method==='POST' && $action==='add_policy'    => addPolicy(),
    $method==='POST' && $action==='toggle_policy' => togglePolicy(),
    default => jsonResponse(['ok'=>false,'error'=>'Ação inválida.'],404),
};

function listLogs(): never {
    $nivel  = Auth::nivel();
    $limit  = min((int)($_GET['limit']??100),500);
    $offset = (int)($_GET['offset']??0);
    if ($nivel>=4) {
        $rows = Database::query(
            'SELECT l.* FROM log_acesso l ORDER BY l.criado_em DESC LIMIT ? OFFSET ?',
            [$limit,$offset]
        );
    } else {
        $rows = Database::query(
            'SELECT * FROM log_acesso WHERE usuario_id=? ORDER BY criado_em DESC LIMIT ? OFFSET ?',
            [Auth::userId(),$limit,$offset]
        );
    }
    jsonResponse(['ok'=>true,'data'=>$rows]);
}

function listPolicies(): never {
    Auth::requireLevel(4);
    jsonResponse(['ok'=>true,'data'=>Database::query('SELECT * FROM politicas ORDER BY tipo,nome')]);
}

function addPolicy(): never {
    Auth::requireLevel(4);
    verifyCsrf();
    $body  = json_decode(file_get_contents('php://input'),true)??[];
    $nome  = trim($body['nome']??'');
    $tipo  = trim($body['tipo']??'');
    $valor = $body['valor']??[];
    $tipos = ['ip_whitelist','ip_blacklist','horario','expiracao_senha','max_tentativas','sessao_timeout'];
    if (!$nome||!in_array($tipo,$tipos,true)) { jsonResponse(['ok'=>false,'error'=>'Nome ou tipo inválido.'],400); }
    Database::execute(
        'INSERT INTO politicas (nome,tipo,valor,aplica_nivel,aplica_user) VALUES (?,?,?,?,?)',
        [$nome,$tipo,json_encode($valor),$body['aplica_nivel']??null,$body['aplica_user']??null]
    );
    jsonResponse(['ok'=>true,'id'=>Database::lastId()]);
}

function togglePolicy(): never {
    Auth::requireLevel(4);
    verifyCsrf();
    $body = json_decode(file_get_contents('php://input'),true)??[];
    Database::execute('UPDATE politicas SET ativo=? WHERE id=?',[(int)($body['ativo']??0),(int)($body['id']??0)]);
    jsonResponse(['ok'=>true]);
}
