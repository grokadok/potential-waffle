<?php

namespace bopdev;

require_once __DIR__ . "/auth.php";

trait Http
{
    use Auth;
    private function task($post)
    {
        var_dump($post['f']);
        try {
            $f = intval($post["f"]);
            $responseType = 'application/json';
            $iduser = $this->getUserIdFromFirebase($post['sub']);

            /////////////////////////////////////////////////////
            // LOGIN FROM FIREBASE (1)
            /////////////////////////////////////////////////////

            if ($f === 1) {

                // $responseType = 'application/json';
                // $iduser = $this->getUserIdFromFirebase($post['sub']);

                // if user doesn't exists in db, create it
                if (!$iduser) {
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

                $responseContent = [
                    'f' => 1, // login approved
                    'defaultFamily' => $this->getDefaultFamily($iduser),
                    'lastname' => $userData['last_name'],
                    'firstname' => $userData['first_name'],
                    'admin' => $this->isAdmin($iduser),
                    'member' => $this->isMember($iduser),
                    'recipient' => $this->isRecipient($iduser),
                    'theme' => $userData['theme'],
                ];
            }

            /////////////////////////////////////////////////////
            // CREATE FAMILY  (2)
            /////////////////////////////////////////////////////

            if ($f === 2) {
                // $iduser = $this->getUserIdFromFirebase($post['sub']);
                if ($this->familyExistsForUser($iduser, $post['n'])) {
                    // if family name already exist for user, return error
                    $responseContent = ['f' => 0];
                } else {
                    // else create family, send confirmation
                    $this->db->request([
                        'query' => 'INSERT INTO family (name,admin) VALUES (?,?);',
                        'type' => 'si',
                        'content' => [$post['n'], $iduser],
                    ]);
                    $responseContent = ['f' => 1];
                }
            }

            /////////////////////////////////////////////////////
            // RETRIEVE FAMILIES  (3)
            /////////////////////////////////////////////////////

            if ($f === 3) {
                $responseContent = ['f' => 3, 'families' => $this->getUserFamilies($iduser)];
            }

            /////////////////////////////////////////////////////
            // DELETE FAMILY  (4)
            /////////////////////////////////////////////////////

            if ($f === 4) {
                $responseContent = ['deleted' => $this->deleteFamily($iduser, $post['i'])];
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
     * Returns whether a family name is available for a user to create or not.
     */
    private function familyExistsForUser(int $iduser, string $name)
    {
        $families = $this->getUserFamilies($iduser);
        $used = false;
        foreach ($families as $family) if ($family['name'] === $name) $used = true;
        return empty($families) ? false : $used;
    }

    /**
     * Removes permanently family data.
     */
    private function removeFamilyData(int $idfamily)
    {
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE idfamily = ? AND end IS NOT NULL;',
            'type' => 'i',
            'content' => [$idfamily],
        ]))) return false;

        // if family has gazettes, remove them
        $recipients = $this->getFamilyRecipients($idfamily);

        if (!empty($recipients))
            foreach ($recipients as $recipient) $gazettes = $this->getRecipientGazettes($recipient['idrecipient']);

        if (!empty($gazettes)) foreach ($gazettes as $gazette) $this->removeGazette($gazette['idgazette']);
        unset($recipients, $gazettes);

        // if family has publications, remove them
        $publications = $this->getAllFamilyPublications($idfamily);

        if (!empty($publications))
            foreach ($publications as $publication) $this->removePublication($publication);

        $this->db->request([
            'query' => 'DELETE FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);

        return true;
    }

    /**
     * Returns first and last names of user in associative array.
     */
    private function getUserName(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT first_name,last_name FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ])[0];
    }

    /**
     * Returns all publications id for given family.
     */
    private function getAllFamilyPublications(int $idfamily)
    {
        $publications = $this->db->request([
            'query' => 'SELECT idpublication FROM publication WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        $publicationsid = [];
        foreach ($publications as $publication) $publicationsid[] = $publication['idpubilcation'];
        unset($publications);
        return $publicationsid;
    }

    /**
     * Returns publications for given family, between optional dates.
     */
    private function getFamilyPublications(int $idfamily, array $range = null)
    {
        // if range
        $rangeString = '';
        if ($range) $rangeString = ' AND created > TIMESTAMP(FROM_UNIXTIME(' . $range['from'] . ')) AND created < TIMESTAMP(FROM_UNIXTIME(' . $range['to'] . '))';

        $publications = $this->db->request([
            'query' => "SELECT idpublication, idpublication_type,author, description, created WHERE idfamily = ?$rangeString;",
            'type' => 'i',
            'content' => [$idfamily],
        ]);
        unset($rangeString);
        foreach ($publications as &$publication) $publication['author'] = $this->getUserName($publication['author']);
        return $publications;
    }

    /**
     * Returns id, uri and date of gazettes for a given recipient.
     */
    private function getRecipientGazettes(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idgazette,uri,date FROM gazettes WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
    }

    /**
     * Removes publication and all data linked to it.
     */
    private function removePublication(int $idpublication)
    {
        // comments
        $commentsid = $this->getPublicationComments($idpublication);
        if (!empty($commentsid)) {
            $commentsid = implode(',', $commentsid);
            $this->db->request(['query' => "DELETE FROM comment WHERE idcomment IN ($commentsid);"]);
        }
        // pictures
        $pictures = $this->getPublicationPictures($idpublication);
        if (!empty($pictures)) foreach ($pictures as $picture) $this->removePicture($picture);
        // texts
        $this->db->request([
            'query' => 'DELETE FROM text WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
        // movies
        $movies = $this->getPublicationMovies($idpublication);
        if (!empty($movies)) foreach ($movies as $movie) $this->removeMovie($movie);

        $this->db->request([
            'query' => 'DELETE FROM publication WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
        return true;
    }

    /**
     * Returns an array of picture's id for given publication.
     */
    private function getPublicationPictures(int $idpublication)
    {
        $picturesid = [];
        foreach ($this->db->request([
            'query' => 'SELECT idpicture FROM publication_has_picture WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ]) as $picture) $picturesid[] = $picture[0];
        return $picturesid;
    }

    /**
     * Returns an array of comment's id for given publication.
     */
    private function getPublicationComments(int $idpublication)
    {
        $comments = $this->db->request([
            'query' => 'SELECT idcomment FROM publication_has_comment WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ]);
        $commentsid = [];
        foreach ($comments as $comment) $commentsid[] = $comment[0];
        return $commentsid;
    }

    /**
     * Removes a given comment.
     */
    private function removeComment(int $idcomment)
    {
        return $this->db->request([
            'query' => 'DELETE FROM comment WHERE idcomment = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomment],
        ]);
    }

    private function removePicture(int $idpicture)
    {
        // TODO: complete function depending on storage
    }

    /**
     * Removes a given text component.
     */
    private function removeText(int $idtext)
    {
        return $this->db->request([
            'query' => 'DELETE FROM text WHERE idtext = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idtext],
        ]);
    }

    private function removeMovie(int $idmovie)
    {
        // TODO: complete function depending on storage
    }

    private function removeMemberFromFamily(int $iduser, int $idfamily)
    {
        // TODO: handle member removal (recurring payment, etc.)
    }

    private function removeGazette(int $idgazette)
    {
        // TODO: complete function depending on storage
    }

    /**
     * Delete a family and all it's data.
     */
    private function deleteFamily(int $iduser, int $idfamily)
    {
        // if user is admin of family
        if (!$this->isAdminOfFamily($iduser, $idfamily)) return false;
        // if no subscription is running for family recipients
        if ($this->familyHasSubscriptions($idfamily)) return false;
        // mark family for removal if gazettes (a month) or members (a week), else remove immediatly
        if ($this->familyHasGazettes($idfamily)) {
            $this->db->request([
                'query' => 'UPDATE family SET end = DATE_ADD(NOW(),INTERVAL 31 DAY) WHERE idfamily = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idfamily],
            ]);
            return 31;
        }
        if ($this->getFamilyMembers($idfamily)) {
            $this->db->request([
                'query' => 'UPDATE family SET end = DATE_ADD(NOW(),INTERVAL 7 DAY) WHERE idfamily = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idfamily],
            ]);
            return 7;
        }
        $this->db->request([
            'query' => 'UPDATE family SET end = NOW() WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);

        $this->removeFamilyData($idfamily);
        return true;
    }

    /**
     * Returns true if recipient has at least one gazette.
     */
    private function recipientHasGazettes($idrecipient)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM gazettes WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]));
    }

    /**
     * Returns true if family has at least one gazette.
     */
    private function familyHasGazettes(int $idfamily)
    {
        // get family recipients
        $recipients = $this->getFamilyRecipients($idfamily);
        $idrecipients = [];
        foreach ($recipients as $recipient) $idrecipients[] = $recipient['idrecipient'];
        $idrecipients = implode(',', $idrecipients);
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM gazettes WHERE idrecipient IN ($idrecipients) LIMIT 1;",
        ]));
    }

    /**
     * Returns true if family has running subscription.
     */
    private function familyHasSubscriptions(int $idfamily)
    {
        // get family recipients
        $recipients = $this->getFamilyRecipients($idfamily);
        // get recipient subscription
        if (empty($recipients)) return false;
        $idrecipients = [];
        foreach ($recipients as $recipient) $idrecipients[] = $recipient['idrecipient'];
        $idrecipients = implode(',', $idrecipients);
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM subscription WHERE idrecipient IN ($idrecipients) AND end IS NULL LIMIT 1;",
        ]));
    }

    private function getFamilyMembers(int $idfamily)
    {
        $members = $this->db->request([
            'query' => 'SELECT iduser FROM family_has_member WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        $idmembers = [];
        foreach ($members as $member) $idmembers[] = $member[0];
        $idmembers = implode(',', $idmembers);
        return $this->db->request([
            'query' => "SELECT iduser,first_name,last_name FROM user WHERE iduser IN ($idmembers);",
        ]);
    }

    /**
     * Returns id and user id of recipients from a family.
     */
    private function getFamilyRecipients(int $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT idrecipient,iduser FROM recipient WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);
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

    /**
     * Returns user id from Firebase uid or false.
     * @return int|false User id
     */
    private function getUserIdFromFirebase(String $uid)
    {
        return $this->db->request([
            'query' => 'SELECT iduser FROM firebase_has_user WHERE uidfirebase = ? LIMIT 1;',
            'type' => 's',
            'content' => [$uid],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns last name, first name, email, phone and theme preference for given user id.
     */
    private function getUserData(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT last_name,first_name,theme,email,phone FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ])[0];
    }

    /**
     * Returns family name from id.
     * @return String Family name.
     */
    private function getFamilyName(int $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT name FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ])[0][0];
    }

    /**
     * Returns all families ID user is in, sorted by name.
     */
    private function getUserFamilies(int $iduser)
    {
        $families = [];
        foreach ($this->db->request([
            'query' => 'SELECT idfamily FROM family_has_member WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $family) $families[$family[0]]['admin'] = true;

        foreach ($this->db->request([
            'query' => 'SELECT idfamily FROM recipient WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $family) $families[$family[0]]['recipient'] = true;

        foreach ($this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE admin = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $family) $families[$family[0]]['admin'] = true;
        $response = [];
        foreach (array_keys($families) as $key) {
            $families[$key]['name'] = $this->getFamilyName($key);
            $families[$key]['id'] = $key;
            $response[] = $families[$key];
        }
        $names = array_column($response, 'name');
        array_multisort($names, SORT_ASC, $response);
        unset($families, $names);
        return $response;
    }

    private function isMember(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isRecipient(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isAdmin(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE admin = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isAdminOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE admin = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }
}
