<?php

namespace bopdev;

trait Websocket
{
    private function wsTask(array $task)
    {
        try {
            $f = $task["f"] ?? "";
            $fd = $task["fd"];
            $iduser = $task["user"];
            $data = $task["content"] ?? null;

            /////////////////////////////////////////////////////
            // Action (1)
            /////////////////////////////////////////////////////


        } catch (\Exception $e) {
            throw $e;
        }
    }
}
