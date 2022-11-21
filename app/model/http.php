<?php

namespace bopdev;

require_once __DIR__ . "/auth.php";

trait Http
{
    use Auth;
    private function task($post)
    {
        var_dump($post);
        try {
            $f = intval($post["f"]);

            /////////////////////////////////////////////////////
            // LOGIN FROM FIREBASE (1)
            /////////////////////////////////////////////////////

            if ($f === 1) {

                $responseType = 'application/json';
                $iduser = $this->db->request([
                    'query' => 'SELECT iduser FROM firebase_has_user WHERE uidfirebase = ? LIMIT 1;',
                    'type' => 's',
                    'content' => [$post['sub']],
                    'array' => true,
                ])[0][0] ?? null;
                // if user doesn't exists in db, create it
                if (empty($iduser)) {
                    if ($post['name']) {
                        $name = explode(' ', $post['name']);
                        if (count($name) > 1) {
                            $firstname = array_shift($name);
                            $lastname = implode(' ', $name);
                        } else {
                            $lastname = $name[0];
                        }
                        unset($name);
                    }
                    // TODO: handle picture
                    // download picture
                    // store it & get uri
                    // set avatar in db

                    $iduser = $this->addUser([
                        'email' => $post['email'],
                        'firstname' => $firstname ?? '',
                        'lastname' => $lastname ?? '',
                        'firebase_uid' => $post['sub'],
                        'firebase_name' => $post['name'] ?? '',
                    ]);
                }

                $userData = $this->getUserData($iduser);

                $responseContent = json_encode([
                    'f' => 1, // login approved
                    'defaultFamily' => $this->getDefaultFamily($iduser),
                    'lastname' => $userData['last_name'],
                    'firstname' => $userData['first_name'],
                    'admin' => $this->isAdmin($iduser),
                    'member' => $this->isMember($iduser),
                    'recipient' => $this->isRecipient($iduser),
                    'theme' => $userData['theme'],
                ]);
            }

            /////////////////////////////////////////////////////
            // CHECK MAIL  (3)
            /////////////////////////////////////////////////////

            if ($f === 3 && $post["a"]) {
            }



            return [
                "type" => $responseType,
                "content" => $responseContent,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns default family if set, else false.
     * @return int|false
     */
    private function getDefaultFamily(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT idfamily FROM default_family WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] ?? false;
    }

    private function getUserData($iduser)
    {
        return $this->db->request([
            'query' => 'SELECT last_name,first_name,theme,email,phone FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ])[0];
    }

    private function getUserFamilies($iduser)
    {
        $families = [];

        $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE admin = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);

        $this->db->request([
            'query' => 'SELECT idfamily FROM family_has_member WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);

        $this->db->request([
            'query' => 'SELECT idfamily FROM recipient WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);

        return $this->db->request([
            'query' => 'SELECT ;',
            'type' => '',
            'content' => [],
            'array' => true,
        ]);
    }

    private function isMember($iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isRecipient($iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isAdmin($iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE admin = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }
}
