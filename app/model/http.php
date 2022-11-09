<?php
// use ContextManager as CM;
namespace bopdev;

trait Http
{
    private function task($post)
    {
        try {
            $f = intval($post["f"]);

            /////////////////////////////////////////////////////
            // CHECK MAIL  (3)
            /////////////////////////////////////////////////////

            if ($f === 3 && $post["a"]) {
                $responseType = "text/html; charset=UTF-8";
                $email = $post["a"];
                $res = $this->db->request([
                    "query" => "SELECT null FROM user WHERE email = ?;",
                    "type" => "s",
                    "content" => [$email],
                ]);
                $responseContent = count($res);
            }

            /////////////////////////////////////////////////////
            // REGISTER  (4)
            /////////////////////////////////////////////////////

            if ($f === 4) {
                $responseType = "text/html; charset=UTF-8";
                $email = $post["email"];
                $password = password_hash($post["password"], PASSWORD_DEFAULT);
                $phone = $post["phone"];

                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->db->request([
                        "query" =>
                        "INSERT INTO user (email,password,phone_number) VALUES (?,?,?);",
                        "type" => "sss",
                        "content" => [$email, $password, $phone],
                    ]);
                    $this->db->request([
                        "query" =>
                        "INSERT INTO user_has_role (iduser) VALUES ((SELECT iduser FROM user WHERE email = ?));",
                        "type" => "s",
                        "content" => [$email],
                    ]);
                    $responseContent = 1;
                }
            }

            /////////////////////////////////////////////////////
            // FORGOTTEN PASSWORD  (5)
            /////////////////////////////////////////////////////

            return [
                "type" => $responseType,
                "content" => $responseContent,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
