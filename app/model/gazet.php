<?php

namespace bopdev;

trait Gazet
{
    /**
     * Joins family and sets as default for user if not set.
     */
    private function addUserToFamily(int $iduser, int $idfamily)
    {
        //if family name available for user
        if ($this->familyExistsForUser($iduser, $this->getFamilyName($idfamily))) return false;
        // insert into family members
        $this->db->request([
            'query' => 'INSERT INTO family_has_member (idfamily, iduser) VALUES ($idfamily, $iduser);',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);
        // TODO: send push to user added

        return true;
    }

    /**
     * Creates family if name available, sets it as default for user if default not set and returns family data.
     */
    private function createFamily(int $iduser, string $name)
    {
        // if family name is available for user
        if ($this->familyExistsForUser($iduser, $name)) return false;

        // create family
        $this->db->request([
            'query' => 'INSERT INTO family (name,admin) VALUES (?,?);',
            'type' => 'si',
            'content' => [$name, $iduser],
        ]);
        $idfamily = $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE name = ? AND admin = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$name, $iduser],
            'array' => true,
        ])[0][0];

        // if user's first family, set default family for user
        if (!$this->userHasDefaultFamily($iduser)) {
            $this->db->request([
                'query' => 'INSERT INTO default_family (iduser, idfamily) VALUES (?,?);',
                'type' => 'ii',
                'content' => [$iduser, $idfamily],
            ]);
        }
        return $this->getUserFamilyData($iduser, $idfamily);
    }

    /**
     * Creates recipient, returns false if recipient with same display name already exists for family.
     */
    private function createRecipient(int $iduser, int $idfamily, array $recipient)
    {
        // check if recipient with same display name exists
        if (!empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idfamily = ? AND display_name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idfamily, empty($recipient['display_name']) ? $recipient['last_name'] . ' ' . $recipient['first_name'] : $recipient['display_name']],
        ]))) return false;

        // TODO: add recipient to family
        // TODO: handle member adding recipient and only able to edit/delete those recipients, including themselves.
        $displayName = empty($recipient['display_name']) ? $recipient['last_name'] . ' ' . $recipient['first_name'] : $recipient['display_name'];
        // create address
        $this->db->request([
            'query' => 'INSERT INTO address (last_name,first_name,phone,field1,field2,field3,postal,city,state,country) VALUES (?,?,?,?,?,?,?,?,?,?);',
            'type' => 'ssssssssss',
            'content' => [
                $recipient['last_name'],
                $recipient['first_name'],
                $recipient['phone'],
                $recipient['address'],
                '',
                '',
                $recipient['postal'],
                $recipient['city'],
                $recipient['state'],
                $recipient['country'],
            ],
        ]);
        $idaddress = $this->db->request([
            'query' => 'SELECT idaddress FROM address WHERE last_name = ? AND first_name = ? AND phone = ? AND field1 = ? AND postal = ? AND city = ? AND state = ? AND country = ? LIMIT 1;',
            'type' => 'ssssssss',
            'content' => [
                $recipient['last_name'],
                $recipient['first_name'],
                $recipient['phone'],
                $recipient['address'],
                $recipient['postal'],
                $recipient['city'],
                $recipient['state'],
                $recipient['country'],
            ],
            'array' => true,
        ])[0][0];

        // create recipient
        $this->db->request([
            'query' => 'INSERT INTO recipient (idfamily,display_name,birth_date,idaddress,added_by) VALUES (?,?,?,?,?);',
            'type' => 'issii',
            'content' => [$idfamily, $displayName, $recipient['birth_date'], $idaddress, $iduser],
        ]);
        return $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE idfamily = ? AND display_name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idfamily, $displayName],
            'array' => true,
        ])[0][0];
    }

    /**
     * Mark family for deletion, immediate if no gazettes nor members.
     * @return int Value > 0 = days before deletion, 0 = deleted, -1 = user not admin, -2 = running subscription
     */
    private function deleteFamily(int $iduser, int $idfamily)
    {
        // if user is admin of family
        if (!$this->isAdminOfFamily($iduser, $idfamily)) return ['state' => -1];
        // if no subscription is running for family recipients
        if ($this->familyHasSubscriptions($idfamily)) return ['state' => -2];
        // mark family for removal if gazettes (a month) or members (a week), else remove immediatly
        if ($this->familyHasGazettes($idfamily)) {
            $this->db->request([
                'query' => 'UPDATE family SET end = DATE_ADD(NOW(),INTERVAL 31 DAY) WHERE idfamily = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idfamily],
            ]);
            return ['state' => 31];
        }
        if ($this->getFamilyMembers($idfamily)) {
            $this->db->request([
                'query' => 'UPDATE family SET end = DATE_ADD(NOW(),INTERVAL 7 DAY) WHERE idfamily = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idfamily],
            ]);
            return ['state' => 7];
        }
        $this->db->request([
            'query' => 'UPDATE family SET end = NOW() WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);

        $this->removeFamilyData($idfamily);
        return ['state' => 0, 'default' => $this->getUserDefaultFamily($iduser)];
    }

    private function familyExists(int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]));
    }

    /**
     * Returns whether or not a family name is already used for a given user.
     */
    private function familyExistsForUser(int $iduser, string $name)
    {
        $families = $this->getUserFamilies($iduser);
        $used = false;
        foreach ($families as $family) if ($family['name'] === $name) $used = true;
        return empty($families) ? false : $used;
    }

    /**
     * Returns true if family has at least one gazette.
     */
    private function familyHasGazettes(int $idfamily)
    {
        // get family recipients
        $recipients = $this->getFamilyRecipients($idfamily);
        if (!$recipients) return false;
        foreach ($recipients as &$recipient) $recipient = $recipient['idrecipient'];
        $recipients = implode(',', $recipients);
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM gazette WHERE idrecipient IN ($recipients) LIMIT 1;",
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
        if (!$recipients) return false;
        $idrecipients = [];
        foreach ($recipients as $recipient) $idrecipients[] = $recipient['idrecipient'];
        $idrecipients = implode(',', $idrecipients);
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM subscription WHERE idrecipient IN ($idrecipients) AND end IS NULL LIMIT 1;",
        ]));
    }

    /**
     * Returns all publications id for given family, false if none.
     * @return array|false
     */
    private function getAllFamilyPublications(int $idfamily)
    {
        $publications = $this->db->request([
            'query' => 'SELECT idpublication FROM publication WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        if (empty($publications)) return false;
        foreach ($publications as &$publication) $publication = $publication['idpubilcation'];
        return $publications;
    }

    private function getFamilyMembers(int $idfamily)
    {
        $members = $this->db->request([
            'query' => 'SELECT iduser FROM family_has_member WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        if (empty($members)) return false;
        foreach ($members as &$member) $member = $member[0];
        $members = implode(',', $members);
        return $this->db->request([
            'query' => "SELECT iduser,first_name,last_name FROM user WHERE iduser IN ($members);",
        ]);
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
     * Returns id and user id of recipients from a family.
     */
    private function getFamilyRecipients(int $idfamily, int $iduser = null)
    {
        $recipients = $this->db->request([
            'query' => 'SELECT idrecipient,display_name,added_by FROM recipient WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);
        if (empty($recipients)) return false;
        foreach ($recipients as &$recipient) {
            if ($iduser) $recipient['added_by'] = $recipient['added_by'] === $iduser ? true : false;
            $recipient['subscription'] = $this->getRecipientActiveSubscription($recipient['idrecipient']);
        }
        return $recipients;
    }

    /**
     * Returns family recipients' data.
     */
    private function getFamilyRecipientsData(int $idfamily)
    {
        $recipients = $this->getFamilyRecipients($idfamily);
        if (!$recipients) return false;
        foreach ($recipients as &$recipient) {
            $user = $this->getUserData($recipient['iduser']);
            $recipient['first_name'] = $user['first_name'];
            $recipient['last_name'] = $user['last_name'];
        }
        return $recipients;
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
     * Returns active subscriptions for given recipient, false if none.
     */
    private function getRecipientActiveSubscription(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idsubscription FROM subscription WHERE idrecipient = ? AND end IS NULL LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns id, uri and date of gazettes for a given recipient.
     */
    private function getRecipientGazettes(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idgazette,uri,date FROM gazette WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
    }

    private function getSubscriptionScreenDate(int $idrecipient)
    {
    }

    private function getSubscriptionTypes()
    {
        return $this->db->request([
            'query' => 'SELECT idsubscription_type,name,price FROM subscription_type;',
        ]);
    }

    /**
     * Returns comments' id for given user, false if none.
     * @return array|false
     */
    private function getUserComments(int $iduser)
    {
        $comments = $this->db->request([
            'query' => 'SELECT idcomment FROM comment WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);
        if (empty($comments)) return false;
        foreach ($comments as &$comment) $comment = $comment[0];
        return $comments;
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
     * Returns default family if set, else false.
     * @return int|false
     */
    private function getUserDefaultFamily(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT idfamily FROM default_family WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] ?? false;
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
        ]) as $family) $families[$family[0]]['member'] = true;

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

        $default = $this->getUserDefaultFamily($iduser);
        // if ($default) $families[$default]['default'] = true;

        $response = [];
        foreach (array_keys($families) as $key) {
            $families[$key]['name'] = $this->getFamilyName($key);
            $families[$key]['id'] = $key;
            $families[$key]['default'] = $key === $default ? true : false;
            $response[] = $families[$key];
        }
        $names = array_column($response, 'name');
        array_multisort($names, SORT_ASC, $response);
        unset($families, $names);
        return $response;
    }

    private function getUserFamiliesData(int $iduser)
    {
        $families = $this->getUserFamilies($iduser);
        if (empty($families)) return false;
        foreach ($families as &$family) {
            // get recipients + active subscription
            $family['recipients'] = $this->getFamilyRecipients($family['id']);
            // get members
            $family['members'] = $this->getFamilyMembers($family['id']);
        }
        return $families;
    }

    private function getUserFamilyData(int $iduser, int $idfamily)
    {
        return $this->familyExists($idfamily) ? [
            'id' => $idfamily,
            'name' => $this->getFamilyName($idfamily),
            'admin' => $this->isAdminOfFamily($iduser, $idfamily),
            'member' => $this->isMemberOfFamily($iduser, $idfamily),
            'recipient' => $this->isRecipientOfFamily($iduser, $idfamily),
            'default' => $this->isDefaultForUser($iduser, $idfamily),
            'recipients' => $this->getFamilyRecipients($idfamily),
            'members' => $this->getFamilyMembers($idfamily),
        ] : false;
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
     * Returns associative array of user running recurring payments.
     */
    private function getUserRunningPayments(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT idsubscription,amount,start FROM recurring_payment WHERE iduser = ? AND end IS NULL;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
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

    private function isDefaultForUser(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM default_family WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    private function isMember(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function isMemberOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
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

    private function isRecipientOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]));
    }

    /**
     * Returns true if recipient has at least one gazette.
     */
    private function recipientHasGazettes($idrecipient)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM gazette WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]));
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

    /**
     * Removes every comment from user in publications of given family.
     */
    private function removeCommentsByFamilyMember(int $iduser, int $idfamily)
    {
        $comments = $this->getUserComments($iduser);
        if (!$comments) return;
        $comments = implode(',', $comments);
        $publications = $this->getAllFamilyPublications($idfamily);
        if (!$publications) return;
        $publications = implode(',', $publications);
        $targetComments = $this->db->request([
            'query' => "SELECT idcomment FROM publication_has_comment WHERE idpublication IN ($publications) AND idcomment IN ($comments);",
            'array' => true,
        ]);
        if (empty($targetComments)) return;
        foreach ($targetComments as $comment) $this->removeComment($comment);
        return;
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

        // if family is default for any user
        $users = $this->db->request([
            'query' => 'SELECT iduser FROM default_family WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        if (!empty($users)) {
            foreach ($users as $user) {
                // select next family as member
                $nextFamily = $this->db->request([
                    'query' => 'SELECT idfamily FROM family_has_member WHERE iduser = ? AND idfamily != ? LIMIT 1;',
                    'type' => 'ii',
                    'content' => [$user[0], $idfamily],
                    'array' => true,
                ])[0][0] ?? null;
                // if null, select next family as admin
                if (empty($nextFamily)) $nextFamily = $this->db->request([
                    'query' => 'SELECT idfamily FROM family WHERE admin = ? AND idfamily != ? LIMIT 1;',
                    'type' => 'ii',
                    'content' => [$user[0], $idfamily],
                    'array' => true,
                ])[0][0] ?? null;
                // if null, select next family as recipient
                if (empty($nextFamily)) $nextFamily = $this->db->request([
                    'query' => 'SELECT idfamily FROM recipient WHERE iduser = ? AND idfamily != ? LIMIT 1;',
                    'type' => 'ii',
                    'content' => [$user[0], $idfamily],
                    'array' => true,
                ])[0][0] ?? null;
                // if user has other family, set as default family.
                if (!empty($nextFamily)) $this->db->request([
                    'query' => 'UPDATE default_family SET idfamily = ? WHERE iduser = ? LIMIT 1;',
                    'type' => 'ii',
                    'content' => [$nextFamily, $user[0]],
                ]);
            }
        }

        // if family has gazettes, remove them
        $recipients = $this->getFamilyRecipients($idfamily);

        if ($recipients)
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

    private function removeGazette(int $idgazette)
    {
        // TODO: complete function depending on storage
    }

    /**
     * Removes all likes from user for given family.
     */
    private function removeLikesByFamilyMember(int $iduser, int $idfamily)
    {
        $publications = $this->getAllFamilyPublications($idfamily);
        if (empty($publications)) return;
        $publications = implode(',', $publications);
        $this->db->request([
            'query' => "DELETE FROM publication_has_like WHERE iduser = ? AND idpublication IN ($publications);",
            'type' => 'i',
            'content' => [$iduser],
        ]);
        $comments = $this->db->request([
            'query' => "SELECT idcomment FROM publication_has_comment WHERE idpublication IN ($publications);",
            'array' => true,
        ]);
        if (empty($comments)) return;
        $comments = implode(',', $comments);
        $this->db->request([
            'query' => "DELETE FROM comment_has_like WHERE iduser = ? AND idcomment IN ($comments);",
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);
        return;
    }

    private function removeMemberFromFamily(int $iduser, int $idfamily)
    {
        // TODO: handle member removal (recurring payment, etc.)

        // if user has running subscription for family, return false
        $subscriptions = $this->getUserRunningPayments($iduser);
        if (!empty($subscriptions)) {
            foreach ($subscriptions as &$subscription) $subscription = $subscription['idsubscription'];
            $subscriptions = implode(',', $subscriptions);
            $recipients = $this->db->request([
                'query' => "SELECT idrecipient FROM subscription WHERE idsubscription IN ($subscriptions);",
                'array' => true,
            ]);
            foreach ($recipients as &$recipient) $recipient = $recipient[0];
            $recipients = implode(',', $recipients);
            if (!empty($this->db->request([
                'query' => "SELECT NULL FROM recipient WHERE idrecipient IN ($recipients) AND idfamily = ? LIMIT 1;",
                'type' => 'i',
                'content' => [$idfamily],
                'array' => true,
            ]))) return false;
            // TODO: show warning message to oiginal requester, so that he can decide whether to proceed with removal or halt.
            // if removal proceeds, cancel any recurring payment, then remove user from family.
        }

        $this->removePublicationsByFamilyMember($iduser, $idfamily);
        $this->removeCommentsByFamilyMember($iduser, $idfamily);
        $this->removeLikesByFamilyMember($iduser, $idfamily);

        // remove user from family
        $this->db->request([
            'query' => 'DELETE FROM family_has_member WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);

        // TODO: send push notification to removed member
        return true;
    }

    private function removeMovie(int $idmovie)
    {
        // TODO: complete function depending on storage
    }

    private function removePicture(int $idpicture)
    {
        // TODO: complete function depending on storage
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
     * Removes publications from member in given family.
     */
    private function removePublicationsByFamilyMember(int $iduser, int $idfamily)
    {
        $publications = $this->db->request([
            'query' => 'SELECT idpublication FROM publication WHERE author = ? AND idfamily = ?;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ]);
        foreach ($publications as &$publication) $this->removePublication($publication);
        return true;
    }

    private function removeRecipient(int $iduser, int $idrecipient)
    {
        // TODO: 
        // if user admin
        // if no subscription running
        // remove recipient and its data
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

    /**
     * Returns false if family name unavailble for user, else true for successful request.
     */
    private function requestAddToFamily(int $iduser, int $idfamily)
    {
        // if family name available to user
        if ($this->familyExistsForUser($iduser, $this->getFamilyName($idfamily))) return false;
        // insert into family_request
        $this->db->request([
            'query' => 'INSERT INTO family_request (iduser, idfamily) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]);
        // TODO: send push to family admin

        return true;
    }

    /**
     * Sets default family, returns previous default family if set.
     */
    private function setDefaultFamily(int $iduser, int $idfamily)
    {
        $previous = $this->db->request([
            'query' => 'SELECT idfamily FROM default_family WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] ?? true;
        $this->db->request([
            'query' => 'UPDATE default_family SET idfamily = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);
        return $previous;
    }

    private function updateRecipient(int $iduser, int $idrecipient)
    {
        // TODO: if user = admin or added_by, update recipients data
    }

    /**
     * Returns whether or not a user has a default family set.
     */
    private function userHasDefaultFamily(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM default_family WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }
}
