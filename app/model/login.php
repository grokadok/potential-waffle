<?php

namespace bopdev;

trait Login
{
    private function login($request)
    {
        if (
            isset($request["email"]) &&
            filter_var($request["email"], FILTER_VALIDATE_EMAIL) &&
            $request["password"]
        ) {
            $status = 0;
            $res = $this->db->request([
                "query" => 'SELECT iduser,password,phone_number,CONCAT_WS(" ",IFNULL(first_name,""),IFNULL(last_name,"")) "name", value, total_since_last, grand_total, timer
                FROM user
                LEFT JOIN pw_counter USING (iduser)
                WHERE email = ?
                GROUP BY iduser;',
                "type" => "s",
                "content" => [$request["email"]],
            ]);
            $data = [
                "attempts" => $res[0]["value"],
                "attempts_grand" => $res[0]["grand_total"],
                "attempts_total" => $res[0]["total_since_last"],
                "chat" => [],
                "name" => $res[0]["name"] ?? "",
                "role" => [],
                "tabs" => [],
                "timer" => $res[0]["timer"],
            ];
            $iduser = $res[0]["iduser"];
            if (
                isset($data["attempts"]) &&
                $data["attempts"] > 6 &&
                time() < strtotime($data["timer"]) + 300
            ) {
                $data = "-3";
            } elseif (
                !isset($data["attempts"]) ||
                $data["attempts"] < 7 ||
                time() > strtotime($data["timer"]) + 300
            ) {
                if (
                    password_verify($request["password"], $res[0]["password"])
                ) {
                    $this->db->request([
                        "query" => 'INSERT INTO pw_counter (iduser) VALUES (?)
                        ON DUPLICATE KEY UPDATE value = 0, total_since_last = 0;',
                        "type" => "i",
                        "content" => [$iduser],
                    ]);

                    // roles
                    $res = $this->db->request([
                        "query" =>
                        "SELECT idrole FROM user_has_role WHERE iduser = ?;",
                        "type" => "i",
                        "content" => [$iduser],
                        "array" => true,
                    ]);
                    array_map(function ($value) use (&$data) {
                        $data["role"][] = $value[0];
                    }, $res);

                    if (in_array(10, $data["role"])) {
                        $data = "-2"; // compte pas encore validé
                    } elseif (in_array(11, $data["role"])) {
                        $data = "-4"; // compte client
                    } else {
                        $user = $this->serv->getClientInfo($request["fd"]);
                        $ip = $user["remote_ip"] . ":" . $user["remote_port"];
                        $this->db->request([
                            "query" => "INSERT INTO session (iduser,fd,ip,refresh_time,total_time)
                                VALUES (?,?,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());",
                            "type" => "iis",
                            "content" => [$iduser, $request["fd"], $ip],
                        ]);
                        $session = $this->db->request([
                            "query" =>
                            "SELECT MAX(idsession) 'idsession' FROM session WHERE iduser=?;",
                            "type" => "i",
                            "content" => [$iduser],
                        ])[0]["idsession"];
                        $status = 1;
                        $res = $this->db->request([
                            "query" =>
                            "SELECT idchat FROM user_in_chat WHERE iduser = ?;",
                            "type" => "i",
                            "content" => [$iduser],
                            "array" => true,
                        ]);
                        array_map(function ($value) use (&$data) {
                            $data["chat"][] = $value[0];
                        }, $res);
                        $res = $this->db->request([
                            "query" =>
                            "SELECT theme,solar,animations FROM options WHERE iduser = ?;",
                            "type" => "i",
                            "content" => [$iduser],
                        ]);
                        $data["options"] = $res[0] ?? null;

                        // if active tab set, get this tab, else get default tab (id1)
                        $data["active_tab"] = isset($request["active"]) ? $request["active"] : 1;
                        // get tabs (if not admin, admin as all tabs by default, or has he?)
                        $res = $this->db->request([
                            "query" =>
                            "SELECT idtab FROM user_has_role LEFT JOIN role_has_tab USING (idrole) WHERE iduser = ? GROUP BY idtab;",
                            "type" => "i",
                            "content" => [$iduser],
                            "array" => true,
                        ]);
                        if ($res[0][0]) {
                            foreach ($res as $tab) {
                                if ($data["active_tab"] === $tab[0]) {
                                    $data["tabs"][$tab[0]] =
                                        $this->tabs[$tab[0]];
                                } else {
                                    $data["tabs"][$tab[0]] = [
                                        "actions" =>
                                        $this->tabs[$tab[0]]["actions"] ??
                                            null,
                                        "name" => $this->tabs[$tab[0]]["name"],
                                        "icon" => $this->tabs[$tab[0]]["icon"],
                                    ];
                                }
                            }
                        }
                        $data["tabs_map"] = arrayAssocFilterKeys(
                            $this->tabsMap,
                            $data["tabs"]
                        );

                        // unset attempts,total,grand,iduser,timer
                        unset($data["attempts_grand"]);
                        unset($data["attempts"]);
                        unset($data["timer"]);
                    }
                } else {
                    // increment value or reset to 1 if > 6
                    if (
                        $data["attempts"] === null ||
                        time() > strtotime($data["timer"]) + 300
                    ) {
                        $val = 1;
                    } else {
                        $val =
                            intval($data["attempts"]) > 6
                            ? 1
                            : intval($data["attempts"]) + 1;
                    }
                    $tot =
                        $data["attempts_total"] === null
                        ? 1
                        : intval($data["attempts_total"]) + 1;
                    $grd =
                        $data["attempts_grand"] === null
                        ? 1
                        : intval($data["attempts_grand"]) + 1;
                    $this->db->request([
                        "query" => 'INSERT INTO pw_counter (iduser) VALUES (?)
                      ON DUPLICATE KEY UPDATE value = ?, total_since_last = ?, grand_total = ?;',
                        "type" => "iiii",
                        "content" => [$iduser, $val, $tot, $grd],
                    ]);
                    $data = $val;
                }
            }
        } else {
            $data = "-1";
        } // bad email
        return [
            "status" => $status,
            "data" => $data,
            "session" => $session ?? "",
            "user" => $iduser ?? "",
        ];
    }
}
