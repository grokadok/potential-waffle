<?php

namespace bopdev;

trait BopChat
{
    private function addMessage(int $user, int $chat, string $message)
    {
        // get last message data
        $last = $this->getLastMessage($chat);
        // insert new message
        $new = $this->insertMessage($user, $chat, $message);
        return [
            "last" => $last,
            "new" => [
                "content" => $message,
                "idchat" => $chat,
                "iduser" => $user,
                "created" => $new["created"],
            ],
            "users" => $this->getChatUsersFd($chat),
        ];
    }
    private function chatRequest(int $from, int $to)
    {
        // select $to fd,name,role
        $recipient = $this->getUserInfo($to);
        $sender = $this->getUserInfo($from);
        if ($recipient) {
            return [
                "recipient" => $recipient,
                "sender" => $sender,
            ];
        }
        return false;
    }
    private function chatRequestOK(
        int $sender,
        int $recipient,
        ?int $chat
    ) {
        // if chat
        if (isset($chat)) {
            // insert recipient in chat
            $this->userJoins($recipient, $chat);
            // refresh chat for users
        } else {
            // else create chat
            $senderInfo = $this->getUserInfo($sender);
            $idchat = $this->createChat($senderInfo["name"]);
            // insert both recipient and sender to chat
        }
    }
    private function chatUserLogout(int $user)
    {
        // check every chat he's in to warn other users he's logout
        // get chat list
        $batch = [];
        foreach ($this->getChatList($user) as $chat) {
            // for each chat
            // get userlist
            $batch[$chat["idchat"]]["id"] = $chat["idchat"];
            $batch[$chat["idchat"]]["list"] = $this->getUsersList(
                $chat["idchat"]
            );
            // get online users
            $batch[$chat["idchat"]]["users"] = $this->getChatUsersFd(
                $chat["idchat"],
                $user
            );
        }
        return $batch;
    }
    private function createChat(string $name)
    {
        $this->db->request([
            "query" => "INSERT INTO chat (name) VALUES (?);",
            "type" => "s",
            "content" => [$name],
        ]);
        // $fetch = $this->db->request([
        //     "query" => 'SELECT MAX(idchat) "idchat" FROM chat WHERE name = ?;',
        //     "type" => "s",
        //     "content" => [$name],
        // ]);
        return $this->db->request([
            "query" => 'SELECT MAX(idchat) "idchat" FROM chat WHERE name = ?;',
            "type" => "s",
            "content" => [$name],
        ])[0]["idchat"];
    }
    private function createChatForUsers(int $host, array $guests)
    {
        $hostInfo = $this->getUserInfo($host);
        $idchat = $this->createChat($hostInfo["name"]);
        $hostJoin = $this->userJoins($host, $idchat);
        $guestJoin = [];
        foreach ($guests as $guest) {
            $guestJoin[] = $this->userJoins($guest, $idchat);
        }
        return [
            "host" => $hostJoin,
            "guests" => $guestJoin,
        ];
    }
    private function getChatList(int $user)
    {
        // $fetch = $this->db->request([
        //     "query" => "SELECT idchat FROM user_in_chat WHERE iduser = ?;",
        //     "type" => "i",
        //     "content" => [$user],
        // ]);
        return $this->db->request([
            "query" => "SELECT idchat FROM user_in_chat WHERE iduser = ?;",
            "type" => "i",
            "content" => [$user],
        ]);
    }
    private function getChatName(int $chat)
    {
        // $fetch = $this->db->request([
        //     "query" => "SELECT name FROM chat WHERE idchat = ?;",
        //     "type" => "i",
        //     "content" => [$chat],
        // ]);
        return $this->db->request([
            "query" => "SELECT name FROM chat WHERE idchat = ? LIMIT 1;",
            "type" => "i",
            "content" => [$chat],
        ])[0]["name"];
    }
    private function getChatUsersFd(int $chat, ?int $user = null)
    {
        $userCheck = "";
        $paramType = "iii";
        $paramContent = [$chat, $chat, $chat];
        if ($user !== null) {
            $userCheck = " AND session.iduser != ?";
            $paramType = "iiiii";
            $paramContent = [$chat, $chat, $user, $chat, $user];
        }
        $res = $this->db->request([
            "query" => "SELECT fd, ticket.iduser,1 as 'assignee',
                IF((SELECT COUNT(*)
                    FROM user_in_chat
                    WHERE idchat = ? AND iduser = ticket.iduser
                    ) > 0,1,0) 'inchat'
                FROM ticket
                LEFT JOIN session USING (iduser)
                WHERE idchat = ? AND fd IS NOT NULL{$userCheck}
                UNION
                SELECT fd, user_in_chat.iduser, 0 AS 'assignee',1 AS 'inchat'
                FROM user_in_chat
                LEFT JOIN session USING (iduser)
                LEFT JOIN ticket USING (idchat)
                WHERE idchat = ?
                AND fd IS NOT NULL
                AND (session.iduser != ticket.iduser OR ticket.iduser IS NULL){$userCheck}",
            "type" => $paramType,
            "content" => $paramContent,
        ]);
        var_dump($res);
        return $res ?? false;
    }
    private function getLastMessage(int $chat)
    {
        $res = $this->db->request([
            "query" =>
            "SELECT created,iduser FROM message WHERE idchat = ? AND created = (SELECT MAX(created) FROM message WHERE idchat = ?) GROUP BY idmessage LIMIT 1;",
            "type" => "ii",
            "content" => [$chat, $chat],
        ]);
        return $res[0] ?? false;
    }
    private function getMessages(int $chat)
    {
        // $fetch = $this->db->request([
        //     "query" => "SELECT idchat,content,created,message.iduser,GROUP_CONCAT(DISTINCT message_read_by.iduser) 'readby'
        //                 FROM message
        //                 LEFT JOIN message_read_by USING (idmessage)
        //                 WHERE idchat = ?
        //                 GROUP BY idmessage;",
        //     "type" => "i",
        //     "content" => [$chat],
        // ]);
        return $this->db->request([
            "query" => "SELECT idchat,content,created,message.iduser,GROUP_CONCAT(DISTINCT message_read_by.iduser) 'readby'
                        FROM message
                        LEFT JOIN message_read_by USING (idmessage)
                        WHERE idchat = ?
                        GROUP BY idmessage;",
            "type" => "i",
            "content" => [$chat],
        ]);
    }
    private function getUsersList(int $chat)
    {
        $res = $this->db->request([
            "query" => "SELECT user_in_chat.iduser,image,CONCAT_WS(' ',last_name,first_name) 'name',ISNULL(session.fd) 'status',
                CASE
                    WHEN user_in_chat.iduser = ticket.iduser THEN 'assignee'
                    WHEN user_in_chat.iduser = about THEN 'client'
                    ELSE 'other'
                END 'position'
                FROM user_in_chat
                LEFT JOIN user USING (iduser)
                LEFT JOIN session USING (iduser)
                LEFT JOIN ticket USING (idchat)
                WHERE user_in_chat.idchat = ?
                GROUP BY iduser
                UNION
                SELECT iduser,image,CONCAT_WS(' ',last_name,first_name) 'name',
                2 AS 'status',
                'assignee' AS 'position'
                FROM ticket
                LEFT JOIN user USING (iduser)
                LEFT JOIN session USING (iduser)
                WHERE idchat = ? AND (SELECT COUNT(*) FROM user_in_chat WHERE idchat = ticket.idchat AND iduser = ticket.iduser) = 0
                GROUP BY iduser
                UNION DISTINCT
                SELECT message.iduser,image,CONCAT_WS(' ',last_name,first_name) 'name',2 AS 'status',
                CASE
                    WHEN message.iduser = ticket.iduser THEN 'assignee'
                    WHEN message.iduser = about THEN 'client'
                    ELSE 'other'
                END 'position'
                FROM message
                LEFT JOIN user USING (iduser)
                LEFT JOIN user_in_chat USING (iduser)
                LEFT JOIN session USING (iduser)
                LEFT JOIN ticket ON message.idchat = ticket.idchat
                WHERE message.idchat = ? AND (SELECT COUNT(*) FROM user_in_chat WHERE idchat = message.idchat AND iduser = message.iduser) = 0
                GROUP BY iduser;",
            "type" => "iii",
            "content" => [$chat, $chat, $chat],
        ]);
        return $res;
    }
    private function insertMessage(int $user, int $chat, string $message)
    {
        $this->db->request([
            "query" =>
            "INSERT INTO message (iduser,idchat,content,created) VALUES (?,?,?,UNIX_TIMESTAMP());",
            "type" => "iis",
            "content" => [$user, $chat, $message],
        ]);
        // $fetch = $this->db->request([
        //     "query" => "SELECT idmessage,created,GROUP_CONCAT(DISTINCT message_read_by.iduser) 'readby'
        //         FROM message
        //         LEFT JOIN message_read_by USING (idmessage)
        //         WHERE idchat = ? AND message.iduser = ? AND content = ? AND created = (SELECT MAX(created) FROM message) GROUP BY idmessage;",
        //     "type" => "iis",
        //     "content" => [$chat, $user, $message],
        // ]);
        return $this->db->request([
            "query" => "SELECT idmessage,created,GROUP_CONCAT(DISTINCT message_read_by.iduser) 'readby'
                FROM message
                LEFT JOIN message_read_by USING (idmessage)
                WHERE idchat = ? AND message.iduser = ? AND content = ? AND created = (SELECT MAX(created) FROM message) GROUP BY idmessage;",
            "type" => "iis",
            "content" => [$chat, $user, $message],
        ])[0];
    }
    private function insertUser(int $user, int $chat)
    {
        $this->db->request([
            "query" => "INSERT INTO user_in_chat (iduser,idchat) VALUES (?,?);",
            "type" => "ii",
            "content" => [$user, $chat],
        ]);
        return $this->getUsersList($chat);
    }
    private function removeUser(int $user, int $chat)
    {
        $this->db->request([
            "query" =>
            "DELETE FROM user_in_chat WHERE iduser = ? AND idchat = ?;",
            "type" => "ii",
            "content" => [$user, $chat],
        ]);
        return $this->getUsersList($chat);
    }
    private function setUsersList(array $list, int $user)
    {
        $corrected = [];
        foreach ($list as $row) {
            if ($row["iduser"] === $user) {
                $row["position"] = "user";
            }
            $corrected[] = $row;
        }
        return $corrected;
    }
    private function userInChat(int $user, int $chat)
    {
        $res = $this->db->request([
            "query" =>
            "SELECT COUNT(*) FROM user_in_chat WHERE iduser = ? AND idchat = ?;",
            "type" => "ii",
            "content" => [$user, $chat],
            "array" => true,
        ]);
        return $res[0][0] > 0 ? true : false;
    }
    private function userJoins(int $user, int $chat)
    {
        $inChat = $this->userInChat($user, $chat);
        // if user in chat, get sessions not connected to it and connect them.

        // else join chat on all sessions and inform other users of it.

        return [
            "content" => $this->getMessages($chat),
            "id" => $chat,
            "list" => $inChat
                ? $this->getUsersList($chat)
                : $this->insertUser($user, $chat),
            "name" => $this->getChatName($chat),
            "other" => $this->getChatUsersFd($chat, $user),
            "user" => $this->getUserFd($user),
        ];
    }
    private function userLeaves(int $user, int $chat)
    {
        $list = $this->removeUser($user, $chat);
        $this->deleteUnused();
        $users = $this->getChatUsersFd($chat, $user);
        return [
            "id" => $chat,
            "deserter" => $this->getUserFd($user),
            "list" => $list,
            "users" => $users,
        ];
    }
    private function deleteUnused()
    {
        $this->db->request([
            "query" => 'DELETE chat FROM chat
            LEFT JOIN ticket USING (idchat)
            LEFT JOIN user_in_chat USING (idchat)
            WHERE ticket.idchat IS NULL AND user_in_chat.idchat IS NULL;',
        ]);
    }
}
