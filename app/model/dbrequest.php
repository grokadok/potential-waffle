<?php

namespace bopdev;

use Swoole\Coroutine;

use Swoole\Database\MysqliConfig;
use Swoole\Database\MysqliPool;

class DBRequest
{
    // public $result;
    private $pool;

    /**
     * Performs a mysqli request with prepared statement.
     * @param array $request Associative array regrouping request parameters.
     * @param boolean $request[array] Forces array as result.
     * @param string $request[query] Bla bla.
     * @param string $request[param_type] Blip bloup.
     * @param array $request[param_content] Pouet tagada.
     */
    public function __construct()
    {
        $this->pool = new MysqliPool((new MysqliConfig)
                ->withHost(getenv('MYSQL_ADDON_HOST'))
                ->withPort(getenv('MYSQL_ADDON_PORT') ?? '')
                // ->withUnixSocket('/tmp/mysql.sock')
                ->withDbName(getenv('MYSQL_ADDON_DB'))
                ->withCharset('utf8mb4')
                ->withUsername(getenv('MYSQL_ADDON_USER'))
                ->withPassword(getenv('MYSQL_ADDON_PASSWORD'))
                ->withOptions([mysqli_report(MYSQLI_REPORT_INDEX)]),
            4
        );
    }
    public function request($request)
    {
        $result = [];
        Coroutine::create(
            function () use ($request, &$result) {
                $mysqli = $this->pool->get();
                $stmt = $mysqli->prepare($request["query"]);
                if (
                    isset($request["type"]) &&
                    isset($request["content"])
                ) {
                    $stmt->bind_param(
                        $request["type"],
                        ...$request["content"]
                    );
                }
                $stmt->execute();
                $result = $stmt->get_result();
                if (str_starts_with($request["query"], "SELECT")) {
                    $mode = empty($request['array']) ? MYSQLI_ASSOC : MYSQLI_NUM;
                    $result = $result->fetch_all($mode);
                }
                $this->pool->put($mysqli);
            }
        );
        return $result;
    }
    public function test()
    {
        $response = false;
        $tries = 0;
        while ($response === false && $tries < 10) {
            try {
                $tries++;
                $this->pool->get();
                $response = true;
            } catch (\Exception) {
                sleep(1);
            }
        }
        return $response;
    }
}
