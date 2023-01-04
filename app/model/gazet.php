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

    private function checkFamilyCodeAvailability(string $code)
    {
        return empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE code = ? LIMIT 1;',
            'type' => 's',
            'content' => [$code],
            'array' => true,
        ])[0][0]);
    }

    /**
     * Creates family if name available, sets it as default for user if default not set and returns family data.
     */
    private function createFamily(int $iduser, string $name)
    {
        // if family name is available for user
        if ($this->familyExistsForUser($iduser, $name)) return false;

        $randomCode = bin2hex(random_bytes(7));
        while (!$this->checkFamilyCodeAvailability($randomCode)) $randomCode = bin2hex(random_bytes(7));
        // create family
        $this->db->request([
            'query' => 'INSERT INTO family (name,admin,code) VALUES (?,?,?);',
            'type' => 'sis',
            'content' => [$name, $iduser, $randomCode],
        ]);
        $idfamily = $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE code = ? LIMIT 1;',
            'type' => 's',
            'content' => [$randomCode],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO family_has_member (idfamily, iduser) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);

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
            'query' => 'INSERT INTO recipient (idfamily,display_name,birth_date,idaddress,referent) VALUES (?,?,?,?,?);',
            'type' => 'issii',
            'content' => [$idfamily, $displayName, $recipient['birth_date'], $idaddress, $iduser],
        ]);
        $idrecipient = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE idfamily = ? AND display_name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idfamily, $displayName],
            'array' => true,
        ])[0][0];
        return $this->getRecipientData($idrecipient);
    }

    /**
     * Mark family for deletion, immediate if no gazettes nor members.
     * @return int Value > 0 = days before deletion, 0 = deleted, -1 = user not admin, -2 = running subscription
     */
    private function deleteFamily(int $iduser, int $idfamily)
    {
        // if user is admin of family
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return ['state' => -1];
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
        if ($this->familyHasOtherMembers($iduser, $idfamily)) {
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

        $this->removeFamilyData($iduser, $idfamily);
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
        if (!$this->familyHasRecipients($idfamily)) return false;
        $recipients = implode(',', $this->getFamilyRecipients($idfamily));
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM gazette WHERE idrecipient IN ($recipients) LIMIT 1;",
        ]));
    }

    private function familyHasOtherMembers(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE idfamily = ? AND iduser != ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]));
    }

    private function familyHasRecipients(int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]));
    }

    /**
     * Returns true if family has running subscription.
     */
    private function familyHasSubscriptions(int $idfamily)
    {
        if (!$this->familyHasRecipients($idfamily)) return false;
        $idrecipients = implode(',', $this->getFamilyRecipients($idfamily));
        return !empty($this->db->request([
            'query' => "SELECT NULL FROM subscription WHERE idrecipient IN ($idrecipients) AND end IS NULL LIMIT 1;",
        ]));
    }

    /**
     * Returns true if given family is default for given user.
     */
    private function familyIsDefaultForUser(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM default_family WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    private function familyIsDefaultForAnyUser(int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM default_family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
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

    private function getFamilyCode(int $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT code FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ])[0][0];
    }

    private function getFamilyMemberData(int $idfamily, int $idmember)
    {
        if (!$this->userIsMemberOfFamily($idmember, $idfamily)) return false;
        // get member data 
        $memberData = $this->getUserData($idmember);
        // if recipient
        if (!$this->userIsRecipientOfFamily($idmember, $idfamily)) return $memberData;
        // get recipient data as well
        $idrecipient = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idmember],
            'array' => true,
        ])[0][0];
        $memberData['recipient'] = $this->getRecipientData($idrecipient);
        return $memberData;
    }

    private function getFamilyMembers(int $idfamily)
    {
        $members = $this->db->request([
            'query' => 'SELECT iduser FROM family_has_member WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        if (empty($members)) return [];
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
     * Returns recipients' ids from a family.
     */
    private function getFamilyRecipients(int $idfamily, int $iduser = null)
    {
        if (!$this->familyHasRecipients($idfamily)) return [];
        $recipients = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE idfamily = ? ORDER BY display_name;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        foreach ($recipients as &$recipient) $recipient = $recipient[0];
        return $recipients;
    }

    /**
     * Returns recipient address data
     * @return array Address data
     */
    private function getRecipientAddress(int $idrecipient)
    {
        $idaddress = $this->getRecipientAddressId($idrecipient);
        return $this->db->request([
            'query' => 'SELECT first_name,last_name,phone,field1,postal,city,state,country FROM address WHERE idaddress = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idaddress],
        ])[0];
    }

    /**
     * Returns recipient's address id.
     * @return int Address id
     */
    private function getRecipientAddressId(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idaddress FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
    }

    private function getRecipientData(int $idrecipient)
    {
        return [
            ...$this->db->request([
                'query' => 'SELECT idrecipient,display_name,referent,birth_date FROM recipient WHERE idrecipient = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idrecipient],
            ])[0],
            'address' => $this->getRecipientAddress($idrecipient),
            'subscription' => $this->getRecipientActiveSubscription($idrecipient)
        ];
    }

    /**
     * Returns family recipients' data.
     */
    private function getFamilyRecipientsData(int $idfamily)
    {
        if (!$this->familyHasRecipients($idfamily)) return [];
        $recipients = [];
        foreach ($this->getFamilyRecipients($idfamily) as $recipient) $recipients[] = $this->getRecipientData($recipient);
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
     * Returns active subscription id and type for given recipient, false if none.
     * @return array|false
     */
    private function getRecipientActiveSubscription(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idsubscription,idsubscription_type FROM subscription WHERE idrecipient = ? AND end IS NULL LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ])[0] ?? false;
    }

    /**
     * Returns recipient's family id.
     * @return int Family id
     */
    private function getRecipientFamily(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idfamily FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
    }

    /**
     * Returns id, uri and date of gazettes for a given recipient.
     */
    private function getRecipientGazettes(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idgazette,object_key,date FROM gazette WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
    }

    private function getSubscriptionMembersShare(int $idrecipient)
    {
        // TODO: code getSubscriptionMembersShare
        $idsubscription = $this->getRecipientActiveSubscription($idrecipient);
        if (!$idsubscription) return false;
        // get current month => use last_day(now()) in mysql
        $memberPayments = [];
        // get members having recurring payment with end = null or > current month
        $payments = $this->db->request([
            'query' => 'SELECT amount FROM user_has_payment WHERE idsubcription = ? AND date > DATE_ADD(DATE(LAST_DAY(NOW() - INTERVAL 1 MONTH)), INTERVAL 1 DAY) AND date < DATE_ADD(LAST_DAY(NOW()), INTERVAL 1 DAY);',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ]);
        if (!empty($payments))
            foreach ($payments as $payment) $memberPayments[] = $payment[0];
        unset($payment);
        // get members having payment with date in current month
        $payments = $this->db->request([
            'query' => 'SELECT amount FROM recurring_payment WHERE idsubscription = ? AND (end IS NULL OR end > LAST_DAY(NOW()));',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ]);
        if (!empty($payments)) foreach ($payments as $payment) $memberPayments[] = $payment[0];
        if (empty($memberPayments)) return false;
        return array_sum($memberPayments);
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

        $response = [];
        foreach (array_keys($families) as $key) {
            $families[$key]['id'] = $key;
            $families[$key]['code'] = $this->getFamilyCode($key);
            $families[$key]['name'] = $this->getFamilyName($key);
            $families[$key]['admin'] = $families[$key]['admin'] ?? false;
            $families[$key]['member'] = $families[$key]['member'] ?? false;
            $families[$key]['recipient'] = $families[$key]['recipient'] ?? false;
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
            $family['recipients'] = $this->getFamilyRecipientsData($family['id']);
            // get members
            $family['members'] = $this->getFamilyMembers($family['id']);
        }
        return $families;
    }

    private function getUserFamilyData(int $iduser, int $idfamily)
    {
        return $this->familyExists($idfamily) ? [
            'id' => $idfamily,
            'code' => $this->getFamilyCode($idfamily),
            'name' => $this->getFamilyName($idfamily),
            'admin' => $this->userIsAdminOfFamily($iduser, $idfamily),
            'member' => $this->userIsMemberOfFamily($iduser, $idfamily),
            'recipient' => $this->userIsRecipientOfFamily($iduser, $idfamily),
            'default' => $this->familyIsDefaultForUser($iduser, $idfamily),
            'recipients' => $this->getFamilyRecipientsData($idfamily),
            'members' => $this->getFamilyMembers($idfamily),
        ] : false;
    }

    private function getUserIdFromEmail(String $email)
    {
        return $this->db->request([
            'query' => 'SELECT iduser FROM user WHERE email = ? LIMIT 1;',
            'type' => 's',
            'content' => [$email],
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
        return [...$this->db->request([
            'query' => 'SELECT idsubscription,amount FROM recurring_payment WHERE iduser = ? AND (end IS NULL OR end > DATE(NOW()));',
            'type' => 'i',
            'content' => [$iduser],
        ]), ...$this->db->request([
            'query' => 'SELECT idsubscription,amount FROM user_has_payment WHERE iduser = ? AND date > DATE(NOW());',
            'type' => 'i',
            'content' => [$iduser],
        ])];
    }

    /**
     * Sets invitation into family for email.
     * @return bool False if inviter is not from family (should not happen but hey, shit happens)
     */
    private function familyEmailInvite(int $iduser, int $idfamily, string $email)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $approved = $this->userIsAdminOfFamily($iduser, $idfamily) ? 1 : 0;
        $this->db->request([
            'query' => 'INSERT INTO family_invitation (idfamily,email,inviter,approved) VALUES (?,?,?,?);',
            'type' => 'isii',
            'content' => [$idfamily, gmailNoPeriods($email), $iduser, $approved],
        ]);
        return true;
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
     * Removes address if not linked to any recipient.
     */
    private function removeAddress(int $idaddress)
    {
        print("remove id: " . $idaddress . PHP_EOL);
        if (!empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idaddress = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idaddress],
        ]))) return false;
        $this->db->request([
            'query' => 'DELETE FROM address WHERE idaddress = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idaddress],
        ]);
        return true;
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

    private function setOtherFamilyDefault($iduser, $idfamily)
    {
        // select next family as member
        $nextFamily = $this->db->request([
            'query' => 'SELECT idfamily FROM family_has_member WHERE iduser = ? AND idfamily != ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ])[0][0] ?? null;
        // if null, select next family as admin
        if (empty($nextFamily)) $nextFamily = $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE admin = ? AND idfamily != ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ])[0][0] ?? null;
        // if null, select next family as recipient
        if (empty($nextFamily)) $nextFamily = $this->db->request([
            'query' => 'SELECT idfamily FROM recipient WHERE iduser = ? AND idfamily != ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ])[0][0] ?? null;
        // if user has other family, set as default family.
        if (!empty($nextFamily)) $this->db->request([
            'query' => 'UPDATE default_family SET idfamily = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$nextFamily, $iduser],
        ]);
        return $nextFamily;
    }

    /**
     * Removes permanently family data.
     */
    private function removeFamilyData(int $iduser, int $idfamily)
    {
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE idfamily = ? AND end IS NOT NULL;',
            'type' => 'i',
            'content' => [$idfamily],
        ]))) return false;

        // if family is default for any user, set new default family
        if ($this->familyIsDefaultForAnyUser($idfamily)) {
            foreach ($this->db->request([
                'query' => 'SELECT iduser FROM default_family WHERE idfamily = ?;',
                'type' => 'i',
                'content' => [$idfamily],
                'array' => true,
            ]) as $user) $this->setOtherFamilyDefault($user[0], $idfamily);
        }

        // if family has recipients, remove them
        if ($this->familyHasRecipients($idfamily))
            foreach ($this->getFamilyRecipients($idfamily) as $recipient) $this->removeRecipient($iduser, $recipient);

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

    private function removeMemberFromFamily(int $iduser, int $idfamily, int $idmember)
    {
        // TODO: handle member removal (recurring payment, etc.)

        // if user != member or not admin of family, return false
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && $iduser !== $idmember) return false;

        // if member has running subscription for family, return false
        $subscriptions = $this->getUserRunningPayments($idmember);
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
            // TODO: show warning message to user, so that he can decide whether to proceed with removal or halt.
            // if removal proceeds, cancel any recurring payment, then remove member from family.
        }

        // TODO: keep/archive member's family data for some time in case user joins again in the future ?
        $this->removePublicationsByFamilyMember($idmember, $idfamily);
        $this->removeCommentsByFamilyMember($idmember, $idfamily);
        $this->removeLikesByFamilyMember($idmember, $idfamily);

        // TODO: if admin is leaving family, let him/she designate the next admin

        // TODO: if leaver is referent for recipient(s) AND not admin, set admin referent with notification, 


        // remove user from family
        $this->db->request([
            'query' => 'DELETE FROM family_has_member WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $idmember],
        ]);

        // TODO: send push notification to removed member

        // TODO: if family empty, remove family.


        return true;
    }

    private function removePublicationMovie(int $idmovie)
    {
        // TODO: test function removePublicationMovie
        $this->s3->deleteObject($this->db->request([
            'query' => 'SELECT object_key FROM movie WHERE idmovie = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idmovie],
            'array' => true,
        ])[0][0]);
        $this->db->request([
            'query' => 'DELETE FROM movie WHERE idmovie = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idmovie],
        ]);
        return true;
    }

    private function removePublicationPicture(int $idpicture)
    {
        // TODO: test function removePublicationPicture
        $this->s3->deleteObject($this->db->request([
            'query' => 'SELECT object_key FROM picture WHERE idpicture = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpicture],
            'array' => true,
        ])[0][0]);
        $this->db->request([
            'query' => 'DELETE FROM picture WHERE idpicture = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpicture],
        ]);
        return true;
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
        if (!empty($pictures)) foreach ($pictures as $picture) $this->removePublicationPicture($picture);
        // texts
        $this->db->request([
            'query' => 'DELETE FROM text WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
        // movies
        $movies = $this->getPublicationMovies($idpublication);
        if (!empty($movies)) foreach ($movies as $movie) $this->removePublicationMovie($movie);

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
        // TODO: check if not too late to remove recipient before next gazette.
        $idfamily = $this->getRecipientFamily($idrecipient);
        // if user admin or referent
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsReferent($iduser, $idrecipient)) return false;

        // if recipient has running subscription
        $subscription = $this->getRecipientActiveSubscription($idrecipient);
        if ($subscription) {
            // TODO: cancel future payments for subscription.
        }

        // remove recipient and its data
        // TODO: remove gazettes from storage if recipient is not a user.

        $data = $this->db->request([
            'query' => 'SELECT iduser,idaddress,avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ])[0];
        print($data['idaddress']);
        $this->db->request([
            'query' => 'DELETE FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
        $this->removeAddress($data['idaddress']);
        if (!empty($data['avatar'])) $this->s3->deleteObject($data['avatar']);
        if (empty($data['iduser'])) $this->removeRecipientGazettes($idrecipient);
        return true;
    }

    private function removeRecipientAvatar($idrecipient)
    {
        $avatar = $this->db->request([
            'query' => 'SELECT avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
        if (empty($avatar)) return false;
        $this->s3->deleteObject($avatar);
        return true;
    }

    private function removeRecipientGazettes($idrecipient)
    {
        foreach ($this->db->request([
            'query' => 'SELECT object_key FROM gazette WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ]) as $gazette) $this->s3->deleteObject($gazette[0]); // TODO: replace with multi object single request
        $this->db->request([
            'query' => 'DELETE FROM gazette WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
        return true;
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

    private function setPublicationPicture(int $idpublication, string $key, string $title = '')
    {
        $this->db->request([
            'query' => 'INSERT INTO picture (key, title) VALUES (?,?);',
            'type' => 'ss',
            'content' => [$key, $title],
        ]);
        $idpicture = $this->db->request([
            'query' => 'SELECT idpicture FROM picture WHERE object_key = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$key],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO publication_has_picture (idpublication, idpicture) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$idpublication, $idpicture],
        ]);
        return true;
    }

    private function setRecipientAvatar(int $idrecipient, string $key)
    {
        $oldAvatar = $this->db->request([
            'query' => 'SELECT avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
        if (!empty($oldAvatar) && $oldAvatar !== $key) $this->s3->deleteObject($oldAvatar);
        $this->db->request([
            'query' => 'UPDATE recipient SET avatar = ? WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$key, $idrecipient],
        ]);
        return true;
    }

    private function setUserAvatar(int $iduser, string $key)
    {
        $oldAvatar = $this->db->request([
            'query' => 'SELECT avatar FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0];
        if (!empty($oldAvatar) && $oldAvatar !== $key) $this->s3->deleteObject($oldAvatar);
        $this->db->request([
            'query' => 'UPDATE user SET avatar = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$key, $iduser],
        ]);
        return true;
    }

    private function updateRecipient(int $iduser, int $idrecipient, array $parameters)
    {
        if (!$this->userIsAdminOfFamily($iduser, $this->getRecipientFamily($idrecipient))) return false;
        $this->db->request([
            'query' => 'UPDATE recipient SET display_name = ?, birth_date = ? WHERE idrecipient = ? LIMIT 1;',
            'type' => 'ssi',
            'content' => [$parameters['display_name'], $parameters['birth_date'], $idrecipient],
        ]);
        $idaddress = $this->getRecipientAddressId($idrecipient);
        $this->db->request([
            'query' => 'UPDATE address SET 
                first_name = ?,
                last_name = ?,
                phone = ?,
                field1 = ?,
                postal = ?,
                city = ?,
                state = ?,
                country = ?
                WHERE idaddress = ? LIMIT 1;',
            'type' => 'ssssssssi',
            'content' => [
                $parameters['first_name'],
                $parameters['last_name'],
                $parameters['phone'],
                $parameters['field1'],
                $parameters['postal'],
                $parameters['city'],
                $parameters['state'],
                $parameters['country'],
                $idaddress
            ],
        ]);
        return $this->getRecipientData($idrecipient);
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

    /**
     * Returns true if user is admin of any family.
     */
    private function userIsAdmin(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE admin = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    /**
     * Returns true if user is admin of given family.
     */
    private function userIsAdminOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family WHERE admin = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    /**
     * Returns true if user is member of any family.
     */
    private function userIsMember(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    /**
     * Returns true if user is member of given family;
     */
    private function userIsMemberOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    /**
     * Returns true if user is recipient of any family.
     */
    private function userIsRecipient(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    /**
     * Returns true if given user is recipient of given family.
     */
    private function userIsRecipientOfFamily(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]));
    }

    /**
     * Returns true is given user is referent for given recipient.
     */
    private function userIsReferent(int $iduser, int $idrecipient)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idrecipient = ? AND referent = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idrecipient, $iduser],
            'array' => true,
        ]));
    }
}
