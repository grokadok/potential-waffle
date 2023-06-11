<?php

namespace bopdev;

$functions = __DIR__ . '/app/model/functions.php';
$dbrequest = __DIR__ . '/app/model/dbrequest.php';
$http = __DIR__ . '/app/model/http.php';
$websocket = __DIR__ . '/app/model/websocket.php';
// $login = __DIR__ . '/app/model/login.php';
$auth = __DIR__ . '/app/model/auth.php';
// $jwt_jwt = __DIR__ . '/app/jwt/JWT.php';
// $jwt_jwk = __DIR__ . '/app/jwt/JWK.php';
// $jwt_key = __DIR__ . '/app/jwt/Key.php';
// $jwt_before = __DIR__ . '/app/jwt/BeforeValidException.php';
// $jwt_cached = __DIR__ . '/app/jwt/CachedKeySet.php';
// $jwt_expired = __DIR__ . '/app/jwt/ExpiredException.php';
// $jwt_signature = __DIR__ . '/app/jwt/SignatureInvalidException.php';
// $chat = __DIR__ . '/app/chat/chat.php';
// $calendar = __DIR__ . '/app/calendar/calendar.php';
// $caldav = __DIR__ . '/app/simplecaldav/SimpleCalDAVClient.php';
$s3 = __DIR__ . '/app/model/s3.php';
$localenv = __DIR__ . '/config/env.php'; // not used anymore

foreach ([
    $dbrequest,
    $functions,
    $http,
    $websocket,
    // $login,
    $auth,
    // $chat,
    // $calendar,
    // $caldav,
    // $jwt_jwt,
    // $jwt_jwk,
    // $jwt_key,
    // $jwt_before,
    // $jwt_cached,
    // $jwt_expired,
    // $jwt_signature,
    $s3,
] as $value) {
    require_once $value;
    unset($value);
};
// require 'vendor/autoload.php';
unset($dbrequest, $functions, $http, $websocket, $auth, $s3);
if (getenv('ISLOCAL')) {
    require_once $localenv;
    unset($localenv);
}


use Swoole\Coroutine as Co;
use Swoole\Table;
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;
use bopdev\DBRequest;
use bopdev\S3Client;
// use Aws\S3\S3Client as S3;
// use Aws\Exception\AwsException;

class FWServer
{
    use Http;
    use Websocket;
    // use Login;
    use Auth;
    // use Tools;

    private $appname;

    public function __construct(
        private $db = new DBRequest(),
        private $s3 = new S3Client(),
        private $serv = new Server("0.0.0.0", 8080),
        // private $table = new Table(1024),
    ) {
        $this->appname = getenv('APP_NAME');
        // $this->table->column("user", Table::TYPE_INT);
        // $this->table->column("session", Table::TYPE_INT);
        // $this->table->create();
        $this->serv->set([
            "dispatch_mode" => 1, // not compatible with onClose, for stateless server
            // 'dispatch_mode' => 7, // not compatible with onClose, for stateless server
            'worker_num' => 4, // Open 4 Worker Process
            'open_cpu_affinity' => true,
            // "open_http2_protocol" => true // not compatible with stateless, only dispatch_modes 2 & 4
            // 'max_request' => 4, // Each worker process max_request is set to 4 times
            // 'document_root'   => '',
            // 'enable_static_handler' => true,
            // 'daemonize' => false, // daems (TRUE / FALSE)
        ]);
        $this->serv->on("Start", [$this, "onStart"]);
        $this->serv->on("WorkerStart", [$this, "onWorkStart"]);
        $this->serv->on("ManagerStart", [$this, "onManagerStart"]);
        $this->serv->on("Request", [$this, "onRequest"]);
        $this->serv->on("Open", [$this, "onOpen"]);
        $this->serv->on("Message", [$this, "onMessage"]);
        $this->serv->on("Close", [$this, "onClose"]);
        // $this->serv->table = $this->table;
        $this->serv->start();
    }
    private function getUserInfo(int $user)
    {
        return $this->db->request([
            "query" => 'SELECT CONCAT_WS(" ",first_name,last_name) "name", role.name "role" 
                FROM user
                LEFT JOIN user_has_role USING (iduser)
                LEFT JOIN role USING (idrole)
                WHERE iduser = ?;',
            "type" => "i",
            "content" => [$user],
        ])[0] ?? false;
    }
    private function getUserFd(int $user)
    {
        $res = $this->db->request([
            "query" => "SELECT fd FROM session WHERE iduser = ?;",
            "type" => "i",
            "content" => [$user],
            "array" => true,
        ]);
        $fds = [];
        if ($res) {
            foreach ($res as $fd) {
                $fds[] = $fd[0];
            }
        }
        return $fds ?? false;
    }
    public function onClose(
        Server $server,
        int $fd,
        int $reactorId
    ) {
        // remove session from db then $fd from $this->table
        $user = $server->getClientInfo($fd);
        // $session = $server->table->get($fd, "session");
        // $iduser = $server->table->get($fd, "user");
        // if ($session) {
        //     echo "delete session: " . $session . PHP_EOL;
        //     $this->db->request([
        //         "query" => "DELETE FROM session WHERE idsession = ?",
        //         "type" => "i",
        //         "content" => [$session],
        //     ]);
        //     $this->serv->table->del($fd);

        //     // CHAT related
        //     // foreach ($this->chatUserLogout($iduser) as $chat) {
        //     //     foreach ($chat["users"] as $chatUser) {
        //     //         if ($chatUser["inchat"] === 1) {
        //     //             $this->serv->push(
        //     //                 $chatUser["fd"],
        //     //                 json_encode([
        //     //                     "f" => 19,
        //     //                     "chat" => [
        //     //                         "id" => $chat["id"],
        //     //                         "participants" => $chat["list"],
        //     //                     ],
        //     //                 ])
        //     //             );
        //     //         }
        //     //     }
        //     // }
        // }

        $closer = $reactorId < 0 ? "server" : "client";
        echo "{$closer} closed connection {$fd} from {$user["remote_ip"]}:{$user["remote_port"]}" .
            PHP_EOL;
    }
    public function onManagerStart($serv)
    {
        echo "#### Manager started ####" . PHP_EOL;
        swoole_set_process_name("swoole_process_server_manager");
    }
    public function onMessage(
        Server $server,
        Frame $frame
    ) {
        // if (!$this->serv->table->exist($frame->fd)) {
        //     echo "login request from socket {$frame->fd} on worker {$server->worker_id}" . PHP_EOL;
        //     $data = json_decode(urldecode($frame->data), true);
        //     $data["fd"] = $frame->fd;
        //     $res = $this->login($data);
        //     if ($res["status"] === 1) {
        //         echo "login succeded for user {$res["user"]} at socket {$frame->fd} on session {$res["session"]}" .
        //             PHP_EOL;
        //         $this->serv->table->set($frame->fd, [
        //             "session" => $res["session"],
        //             "user" => $res["user"],
        //         ]);
        //         $server->push($frame->fd, json_encode($res["data"]));
        //     } else {
        //         echo "login failed for {$frame->fd}" . PHP_EOL;
        //         $server->push($frame->fd, json_encode($res["data"]));
        //         $server->disconnect(
        //             $frame->fd,
        //             1000,
        //             "Login failed, sorry bro."
        //         );
        //     }
        // } else {
        //     $session = $server->table->get($frame->fd, "session");
        //     $user = $server->table->get($frame->fd, "user");
        //     echo "Request from u" .
        //         $user .
        //         ":s" .
        //         $session .
        //         " : " .
        //         $frame->data .
        //         PHP_EOL;
        //     try {
        //         $task = [
        //             "fd" => $frame->fd,
        //             "session" => $session,
        //             "user" => $user,
        //             ...json_decode($frame->data, true),
        //         ];
        //         $response = $this->wsTask($task);
        //         unset($task['content'], $task['session'], $task['user'], $task['fd']);
        //         if (!empty($response)) {
        //             $message = json_encode([
        //                 "response" => $response,
        //                 ...$task,
        //             ]);
        //             $server->push($frame->fd, $message);
        //         }
        //     } catch (\Exception $e) {
        //         echo "Exception reçue : " . $e->getMessage() . PHP_EOL;
        //         $response = json_encode([
        //             "response" => [
        //                 "fail" => "Ta mère en string.",
        //                 "error" => $e->getMessage(),
        //             ],
        //             ...$task,
        //         ]);
        //         $server->push($frame->fd, $response);
        //     }
        // }
    }
    public function onOpen(
        Server $server,
        Request $request
    ) {
        $user = $server->getClientInfo($request->fd);
        echo "connection {$request->fd} open for {$user["remote_ip"]}:{$user["remote_port"]}" .
            PHP_EOL;
    }
    public function onRequest(
        Request $request,
        Response $response
    ) {
        $response->header("Server", "SeaServer");
        $open_basedir = __DIR__ . "/public";
        $server = $request->server;
        $path_info = $server["path_info"];
        $request_uri = $server["request_uri"];
        $type = pathinfo($path_info, PATHINFO_EXTENSION);
        $file = $open_basedir . $request_uri;
        $static = [
            "css" => "text/css",
            "js" => "text/javascript",
            "map" => "application/json",
            "ico" => "image/x-icon",
            "png" => "image/png",
            "gif" => "image/gif",
            "jpg" => "image/jpg",
            "jpeg" => "image/jpg",
            "mp4" => "video/mp4",
            "woff" => "font/woff",
            "woff2" => "font/woff2",
        ];

        if (isset($static[$type])) {
            if (file_exists($file)) {
                $response->header("Content-Type", $static[$type]);
                $response->sendfile($file);
            } else {
                $response->status(404);
                $response->end();
            }
        } else {
            if ($server["request_method"] === "POST") {
                // $begin = microtime();
                $jwt = $this->JWTVerify($request->header['authorization'], $this->appname);
                // print(PHP_EOL . '### CHECK TIME' . PHP_EOL . (microtime() - $begin) . PHP_EOL . '###' . PHP_EOL);
                if (!$jwt) {
                    $response->status(401);
                    return $response->end();
                }
                $res = $this->task(
                    [...json_decode($request->getContent(), true), ...(array)$jwt,]
                );
                // var_dump($res);
                // $res = $this->task($request->post);
                $response->header("Content-Type", $res["type"] ?? "");
                $response->end(json_encode($res["content"]) ?? "");
                // $response->end(json_encode($res["content"], JSON_NUMERIC_CHECK) ?? "");
            } elseif ($request_uri === "/" || $request_uri === "/index.php") {
                $theme = "light";
                $session = "";
                // require __DIR__ . "/public/index.php";
            }
        }
    }
    public function onStart($serv)
    {
        echo "#### onStart ####" . PHP_EOL;
        swoole_set_process_name("swoole_process_server_master");
        echo "Swoole Service has started" . PHP_EOL;
        echo "master_pid: {$serv->master_pid}" . PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}" . PHP_EOL;
        echo "########" . PHP_EOL . PHP_EOL;

        // DB query BENCHMARK
        // $this->dbQueryBenchmark(10000);

        // s3 test
        // $test = $this->s3->listObjects('gazet');
        // print('s3 test result: ');
        // var_dump($test);
        // print(PHP_EOL);


        // Using operation methods creates a command implicitly
        if ($this->db->test() === true) {
            // $this->db->request([
            //     "query" => "DELETE FROM session;", // where worker no longer exist or equal to this worker
            // ]);
            // $this->db->request([
            //     "query" => "ALTER TABLE session AUTO_INCREMENT=1; ;",
            // ]);
            print('#### Db connected. ####' . PHP_EOL);
            if ($this->db->request(['query' => 'SELECT COUNT(iduser) FROM user;', 'array' => true])[0][0] === 0) $this->initDb();
            // $test = $this->db->request([
            //     'query' => 'SELECT NULL FROM user WHERE first_name = ? LIMIT 1;',
            //     'type' => 's',
            //     'content' => ['Dugenou'],
            // ]);
            // var_dump(empty($test));
        } else print('!!!! No db connection. !!!!' . PHP_EOL);
    }

    public function onWorkStart($serv, $worker_id)
    {
        echo "#### Worker#$worker_id started ####" . PHP_EOL;
        swoole_set_process_name("swoole_process_server_worker");
    }
}

$server = new FWServer();
