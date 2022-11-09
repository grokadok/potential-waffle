<?php

namespace bopdev;

use Swoole\Coroutine as Co;

trait Tools
{
    private function dbQueryBenchmark($number)
    {
        // DB query BENCHMARK
        $s = microtime(true);
        Co\run(
            function () use ($number) {
                for ($n = $number; $n--;) {
                    Co::create(
                        function () {
                            $this->db->request([
                                'query' => 'SELECT ? + ?',
                                'type' => 'ii',
                                'content' => [mt_rand(1, 100), mt_rand(1, 100)]
                            ]);
                        }
                    );
                }
            }
        );
        $s = microtime(true) - $s;
        echo PHP_EOL . 'Use ' . $s . 's for 100000 queries' . PHP_EOL . PHP_EOL;
    }
}
