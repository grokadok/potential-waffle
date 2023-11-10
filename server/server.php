<?php

namespace bopdev;

foreach ([
    __DIR__ . '/vendor/autoload.php',
    // __DIR__ . '/vendor/easytransac/easytransac-sdk-php/sdk/EasyTransac/autoload.php',
    __DIR__ . '/app/model/functions.php',
    __DIR__ . '/app/model/dbrequest.php',
    __DIR__ . '/app/model/gazet.php',
    __DIR__ . '/app/model/http.php',
    __DIR__ . '/app/model/websocket.php',
    __DIR__ . '/app/model/auth.php',
    __DIR__ . '/app/model/messaging.php',
    __DIR__ . '/app/model/s3.php',
    __DIR__ . '/app/model/browserless.php',
    // __DIR__ . '/app/model/dompdf.php',
    // __DIR__ . '/app/model/chrome.php',
] as $value) require_once $value;
if (getenv('ISLOCAL')) require_once __DIR__ . '/config/env.php';

use Swoole\Coroutine;
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use EasyTransac;
use bopdev\DBRequest;
use bopdev\Browserless;
use bopdev\Messaging;
use bopdev\S3Client;
use Throwable;

class FWServer
{
    use Http;
    use Gazet;
    // use Websocket;
    // use EasyTransac;
    use Auth;

    private $appname;
    private $messaging;

    public function __construct(
        private $db = new DBRequest(),
        private $browserless = new Browserless(),
        private $s3 = new S3Client(),
        private $serv = new Server("0.0.0.0", 8080),
    ) {
        $this->appname = getenv('APP_NAME');
        $this->messaging = new Messaging($this->serv);
        $this->serv->set([
            "dispatch_mode" => 1, // not compatible with onClose, for stateless server
            // 'dispatch_mode' => 7, // not compatible with onClose, for stateless server
            'worker_num' => 2, // Open 4 Worker Process
            'task_enable_coroutine' => true,
            'task_worker_num' => 2, // Open 2 Task Worker Process
            // 'open_cpu_affinity' => true,
            // "open_http2_protocol" => true // not compatible with stateless, only dispatch_modes 2 & 4
            // 'max_request' => 4, // Each worker process max_request is set to 4 times
            // 'document_root'   => '',
            // 'enable_static_handler' => true,
            // 'daemonize' => false, // daems (TRUE / FALSE)
        ]);
        $this->serv->on("Close", [$this, "onClose"]);
        $this->serv->on("Finish", [$this, "onFinish"]);
        $this->serv->on("ManagerStart", [$this, "onManagerStart"]);
        $this->serv->on("Message", [$this, "onMessage"]); // on websocket message
        $this->serv->on("Open", [$this, "onOpen"]);
        $this->serv->on('PipeMessage', [$this, 'onPipeMessage']); // on internal message
        $this->serv->on("Request", [$this, "onRequest"]);
        $this->serv->on("Start", [$this, "onStart"]);
        $this->serv->on("Task", [$this, "onTask"]);
        $this->serv->on("WorkerStart", [$this, "onWorkStart"]);

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
    public function onFinish($serv, $task_id, $data)
    {
        echo "AsyncTask[{$task_id}] finished.\n";
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
    public function onPipeMessage(Server $serv, int $srcWorkerId, array $message)
    {
        try {
            Coroutine\go(function () use ($message, $srcWorkerId) {
                // print('### PIPE MESSAGE TO ' . $this->serv->getWorkerId() . ' FROM ' . $srcWorkerId . PHP_EOL);
                // var_dump($message);
                switch ($message['code']) {
                    case 1:
                        $this->removeInvalidTokens($message['data']);
                        break;
                    default:
                        break;
                }
                // $serv->sendMessage($result, $srcWorkerId);
            });
        } catch (Throwable $e) {
            print('### PIPE MESSAGE ERROR' . PHP_EOL);
            print($e->getMessage());
        }
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
            "html" => "text/html",
            "jpg" => "image/jpg",
            "jpeg" => "image/jpg",
            "mp4" => "video/mp4",
            "woff" => "font/woff",
            "woff2" => "font/woff2",
            "ttf" => "font/ttf",
            "svg" => "image/svg+xml",
            "eot" => "application/vnd.ms-fontobject",
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
                $jwt = $this->JWTVerify($request->header['authorization'], $this->appname);
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
            }
            // elseif ($request_uri === "/" || $request_uri === "/index.php") {
            //     $theme = "light";
            //     $session = "";
            //     // require __DIR__ . "/public/index.php";
            // }
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

        // Easytransac
        EasyTransac\Core\Services::getInstance()->provideAPIKey(getenv('EASYTRANSAC_API_KEY'));
        EasyTransac\Core\Services::getInstance()->setRequestTimeout(30);
        // EasyTransac\Core\Services::getInstance()->setDebug(true);

        // Using operation methods creates a command implicitly
        if ($this->db->test() === true) {
            print('#### Db connected. ####' . PHP_EOL);
            // if ($this->db->request(['query' => 'SELECT COUNT(iduser) FROM user;', 'array' => true])[0][0] === 0) $this->initDb();
        } else print('!!!! No db connection. !!!!' . PHP_EOL);
    }

    public function onTask($serv, $task)
    // public function onTask($serv, $task_id, $srcWorkerId, $data)
    {
        try {
            // echo "New AsyncTask[id={$task_id}] to worker[id={$srcWorkerId}]\n";
            print("New AsyncTask[id={$task->id}] to worker[id={$task->worker_id}]\n");
            // var_dump($task);
            switch ($task->data['task']) {
                case 'messaging':
                    $response = isset($task->data['body']) ?
                        $this->messaging->sendNotification(
                            $task->data['tokens'],
                            $task->data["title"],
                            $task->data["body"],
                            $task->data["data"]
                        ) :
                        $this->messaging->sendData(
                            $task->data['tokens'],
                            $task->data["data"]
                        );
                    if (!empty($response)) {
                        $this->serv->sendMessage([
                            'code' => 1,
                            'data' => $response,
                        ], $task->worker_id);
                    }
                    break;
                case 'pdf':
                    $this->generatePdf($task->data['idgazette']);
                    break;
            }
            // $serv->finish("AsyncTask[id={$task_id}] -> OK");
            $task->finish("AsyncTask -> OK");
        } catch (Throwable $e) {
            print('### TASK ERROR' . PHP_EOL);
            print($e->getMessage());
            // $serv->finish("AsyncTask[id={$task_id}] -> ERROR");
            $task->finish("AsyncTask -> ERROR");
        }
    }

    private function removeInvalidTokens($invalid)
    {
        foreach ($invalid as $token) {
            $this->db->request([
                'query' => 'DELETE FROM user_has_fcm_token WHERE token = ?;',
                'type' => 's',
                'content' => [$token],
            ]);
        }
    }

    public function onWorkStart($serv, $worker_id)
    {
        echo "#### Worker#$worker_id started ####" . PHP_EOL;
        swoole_set_process_name("swoole_process_server_worker");
    }
}

$server = new FWServer();
