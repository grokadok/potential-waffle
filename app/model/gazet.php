<?php

namespace bopdev;

use DateTime;
use Throwable;
use IntlDateFormatter;

trait Gazet
{
    /**
     * Joins family and sets as default for user if not set.
     */
    private function addUserToFamily(int $iduser, int $idfamily)
    {
        $name = $this->getAvailableFamilyName($iduser, $idfamily, $this->getFamilyName($idfamily)); // sets family display name according to user's family display names availability
        $this->db->request([ // insert into family members
            'query' => 'INSERT INTO family_has_member (idfamily, iduser, display_name) VALUES (?,?,?);',
            'type' => 'iis',
            'content' => [$idfamily, $iduser, $name],
        ]);
        $this->db->request([
            'query' => 'INSERT INTO unseen_family (iduser, idfamily) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]);
        if (!$this->userHasDefaultFamily($iduser)) {
            $this->db->request([
                'query' => 'INSERT INTO default_family (iduser, idfamily) VALUES (?,?);',
                'type' => 'ii',
                'content' => [$iduser, $idfamily],
            ]);
        }

        $familyName = $this->getFamilyName($idfamily);
        $memberName = $this->getUserName($iduser);
        $data = [
            'member' => $iduser,
            'family' => $idfamily,
            'type' => 7,
        ];
        $this->sendNotification(
            [$iduser],
            'Nouvelle famille !',
            'Vous avez rejoint la famille ' . $familyName . '.',
            $data
        );
        $members = $this->getFamilyMembers($idfamily, [$iduser]);
        if (!empty($members))
            $this->sendNotification(
                $members,
                'Nouveau membre !',
                $memberName['first_name'] . ' ' . $memberName['last_name'] . ' a rejoint la famille ' . $familyName . '.',
                $data
            );
        return true;
    }

    /**
     * Check if provided avatar is linked to any user.
     * @return bool
     */
    private function checkAvatarUserLink(int $idobject)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM user WHERE avatar = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ]));
    }

    /**
     * Check if provided avatar is linked to any recipient.
     * @return bool
     */
    private function checkAvatarRecipientLink(int $idobject)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE avatar = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ]));
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
     * Checks and clears given gazette from referent modifications duplicates.
     * @param int $idgazette
     */
    private function checkGazetteModifications(int $idgazette)
    {
        $modifications =  $this->getGazetteModifications($idgazette);
        if (empty($modifications)) return;

        // $idrecipient = $this->db->request([
        // 'query'=>'SELECT idrecipient FROM gazette WHERE idgazette = ? LIMIT 1;',
        // 'type'=>'i',
        // 'content'=>[$idgazette],
        // 'array'=>true,
        // ])[0][0];
        // $recipients = [];
        // foreach ($modifications as $modification)
        //     $recipients[$modification['idrecipient']][$modification['page_num']][$modification['place']] = [
        //         'idpublication' => $modification['idpublication'],
        //         'idgame' => $modification['idgame'],
        //         'idsong' => $modification['idsong']
        //     ];
        $gazette = $this->getGazettePages($idgazette);

        // TODO: check gazette mods should prioritize new publications to game modifications

        // for each modification, check if it's a duplicate
        foreach ($modifications as $modification) { // TODO: to refact according to getGazettePages refact
            if (!empty($modification['idpublication'])) {
                // check if publication is already in gazette
                $duplicate = array_filter($gazette, function ($page) use ($modification) {
                    return ($page['idpublication'] !== null && $page['idpublication'] === $modification['idpublication']) || ($page['idgame'] !== null && $page['idgame'] === $modification['idgame']) || ($page['idsong'] !== null && $page['idsong'] === $modification['idsong']);
                });
                // if so, check it recipient has a modification removing the duplicate
                if (!empty($duplicate) && ($duplicate['idpublication'] !== null && !empty(array_filter($modifications, function ($mod) use ($duplicate) {
                    return $mod['page_num'] === $duplicate['page_num'] && ($this->getPublicationSize($mod['idpublication']) || ($mod['place'] === $duplicate['place']));
                })) || ($duplicate['idgame'] !== null && !empty(array_filter($modifications, function ($mod) use ($duplicate) {
                    return $mod['page_num'] === $duplicate['page_num'] && ($this->getGameSize($mod['idgame']) || ($mod['place'] === $duplicate['place']));
                }))
                ) || ($duplicate['idsong'] !== null && !empty(array_filter($modifications, function ($mod) use ($duplicate) {
                    return $mod['page_num'] === $duplicate['page_num'];
                }))
                ))) {
                    // if not, remove the modification
                    $this->removeGazetteModification($idgazette, $modification['page_num'], $modification['place']);
                }
            }
        }
    }

    private function checkPictureGazetteLink(int $idobject)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM gazette WHERE cover_picture = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
            'array' => true,
        ]));
    }

    private function checkPicturePublicationLinks(int $idobject)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM publication_has_picture WHERE idobject = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ]));
    }

    private function checkRecipientNameAvailiability(int $idfamily, string $name)
    {
        return empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idfamily = ? AND display_name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idfamily, $name],
        ]));
    }

    private function cleanS3Bucket()
    {
        $s3Keys = $this->s3->listObjects()['Contents'];
        foreach ($s3Keys as &$key) $key = $key['Key'];
        $dbKeys = $this->db->request([
            'query' => 'SELECT name,ext FROM s3;',
            'array' => true,
        ]);
        foreach ($dbKeys as &$key) $key = bin2hex($key[0]) . '.' . $key[1];

        $s3Diff = array_diff($s3Keys, $dbKeys);
        if (!empty($s3Diff)) {
            $s3Only = [];
            foreach ($s3Diff as $key) {
                $s3Only[] = [
                    'Key' => $key,
                ];
            }
            $this->s3->deleteObjects($s3Only);
            print('#### Removed ' . count($s3Only) . ' orphan objects from s3.' . PHP_EOL);
            unset($s3Only);
        }

        $dbOnly = array_diff($dbKeys, $s3Keys);
        if (!empty($dbOnly)) {
            $dbOnlyStr = implode(',', $dbOnly);
            $this->db->request([
                'query' => 'DELETE FROM s3 WHERE idobject IN ' . $dbOnlyStr . ';',
            ]);
            print('#### Removed ' . count($dbOnly) . ' orphan objects from db.' . PHP_EOL);
            unset($dbOnlyStr);
        }
        unset($s3Diff, $dbOnly);
        return true;
    }

    /**
     * Creates family if name available, sets it as default for user if default not set and returns family data.
     */
    private function createFamily(int $iduser, string $name)
    {
        $randomCode = random_bytes(5);
        while (!$this->checkFamilyCodeAvailability($randomCode)) $randomCode = random_bytes(5);
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
            'query' => 'INSERT INTO family_has_member (idfamily, iduser, display_name) VALUES (?,?,?);',
            'type' => 'iis',
            'content' => [$idfamily, $iduser, $this->getAvailableFamilyName($iduser, $idfamily, $name)],
        ]);

        // if user's first family, set default family for user
        if (!$this->userHasDefaultFamily($iduser)) {
            $this->db->request([
                'query' => 'INSERT INTO default_family (iduser, idfamily) VALUES (?,?);',
                'type' => 'ii',
                'content' => [$iduser, $idfamily],
            ]);
        }
        return $this->userGetFamilyData($iduser, $idfamily);
    }

    /**
     * Creates recipient.
     */
    private function createRecipient(int $iduser, int $idfamily, array $recipient)
    {
        $displayName = empty($recipient['display_name']) ? $recipient['last_name'] . ' ' . $recipient['first_name'] : $recipient['display_name'];
        // check if recipient with same display name exists
        $displayName = $this->getAvailableRecipientName($idfamily, $displayName);

        $into = '';
        $values = '';
        $type = '';
        $content = [];
        if (!empty($recipient['iduser'])) {
            $into = ',iduser';
            $values = ',?';
            $type = 'i';
            $content = [$recipient['iduser']];
            $userData = $this->db->request([
                'query' => 'SELECT birthdate,avatar FROM user WHERE iduser = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$recipient['iduser']],
            ])[0];
            $recipient['birthdate'] = $userData['birthdate'];
            $recipient['avatar'] = $userData['avatar'];
        }
        if (!empty($recipient['avatar'])) {
            $into .= ',avatar';
            $values .= ',?';
            $type .= 'i';
            $content[] = $recipient['avatar'];
        }

        // create recipient
        $this->db->request([
            'query' => 'INSERT INTO recipient (idfamily,display_name,birthdate,referent' . $into . ') VALUES (?,?,?,?' . $values . ');',
            'type' => 'issi' . $type,
            'content' => [$idfamily, $displayName, $recipient['birthdate'], $iduser, ...$content],
        ]);
        $recipientData = $this->db->request([
            'query' => 'SELECT idrecipient,created FROM recipient WHERE idfamily = ? AND display_name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idfamily, $displayName],
        ])[0];

        // create address
        $this->db->request([
            'query' => 'INSERT INTO address (idrecipient,name,phone,field1,field2,field3,postal,city,state,country) VALUES (?,?,?,?,?,?,?,?,?,?);',
            'type' => 'isssssssss',
            'content' => [
                $recipientData['idrecipient'],
                $recipient['address']['name'],
                $recipient['address']['phone'] ?? '',
                $recipient['address']['field1'],
                $recipient['address']['field2'] ?? '',
                $recipient['address']['field3'] ?? '',
                $recipient['address']['postal'],
                $recipient['address']['city'],
                $recipient['address']['state'],
                $recipient['address']['country'],
            ],
        ]);

        // update gazette
        $this->setGazettes($idfamily, $recipientData['created']);

        $data = [
            'recipient' => $recipientData['idrecipient'],
            'user' => $recipient['iduser'],
            'family' => $idfamily,
            'type' => 15,
        ];
        $familyName = $this->getFamilyName($idfamily);
        $members = $this->getFamilyMembers($idfamily, !empty($recipient['iduser']) ? [$recipient['iduser']] : []);
        if (!empty($recipient['iduser'])) {
            if ($recipient['iduser'] !== $iduser)
                $this->sendNotification(
                    [$recipient['iduser']],
                    'Nouveau destinataire !',
                    'Vous avez été ajouté aux destinataires de la famille ' . $familyName . '.',
                    $data
                );
            else $this->sendData([$recipient['iduser']], $data);
        }
        $this->sendNotification(
            $members,
            'Nouveau destinataire !',
            $displayName . ' a été ajouté aux destinataires de la famille ' . $familyName . '.',
            $data
        );

        return $this->getRecipientData($recipientData['idrecipient']);
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
                'query' => 'UPDATE family SET end = DATE_ADD(NOW(),INTERVAL 31 DAY) WHERE idfamily = ? LIMIT 1;', // TODO: set end date to a year later
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

    /**
     * Sets familyInvitation into family for email.
     * @return bool False if inviter is not from family (should not happen but hey, shit happens)
     */
    private function familyEmailInvite(int $iduser, int $idfamily, string $email)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false; // check if inviter is member
        $email = gmailNoPeriods($email);
        $invitee = $this->getUserByEmail($email); // get iduser for email if exists
        $into = '';
        $value = '';
        $type = '';
        $content = [];
        if ($invitee) { // check if invitee is already member
            if ($this->userIsMemberOfFamily($invitee, $idfamily)) return 1;
            $into = ',invitee';
            $value = ',?';
            $type = 'i';
            $content[] = $invitee;
        }
        if (!empty($this->db->request([ //  check if invitation already exists
            'query' => 'SELECT NULL FROM family_invitation WHERE email = ? AND idfamily = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$email, $idfamily],
        ]))) return 2;
        $approved = $this->userIsAdminOfFamily($iduser, $idfamily) ? 1 : 0;
        $this->db->request([
            'query' => 'INSERT INTO family_invitation (idfamily,email,inviter,approved' . $into . ') VALUES (?,?,?,?' . $value . ');',
            'type' => 'isii' . $type,
            'content' => [$idfamily, $email, $iduser, $approved, ...$content],
        ]);

        $data = [
            'family' => $idfamily,
            'type' => 11,
            'user' => $invitee,
            'email' => $email,
        ];
        $familyName = $this->getFamilyName($idfamily);
        if ($invitee) { // send notification to invitee if exists
            $this->sendNotification(
                [$invitee],
                'Invitation reçue',
                'Vous avez été invité à rejoindre la famille ' . $familyName . '.',
                $data
            );
        }
        $this->sendData([$iduser], $data); // send data to inviter
        if ($approved === 0) { // if admin not inviter, send notification
            $this->sendNotification(
                [$this->getFamilyAdmin($idfamily)],
                'Nouvelle invitation',
                'Une nouvelle invitation dans la famille ' . $familyName . ' requiert votre attention.',
                $data
            );
        }

        return true;
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

    private function familyHasInvitation(int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_invitation WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
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

    private function familyHasRequest(int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_request WHERE idfamily = ? LIMIT 1;',
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
            'query' => "SELECT NULL FROM subscription WHERE idrecipient IN ($idrecipients) LIMIT 1;",
        ]));
    }

    private function familyInvitationDeny($iduser, $invitee, $idfamily)
    {
    }

    private function familyInvitationExist($iduser, $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_invitation WHERE idfamily = ? AND invitee = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]));
    }

    /**
     * Adds user to family and removes familyInvitation. Returns false if user already member.
     */
    private function familyInvitationFinalize($iduser, $idfamily)
    {
        $this->familyInvitationRemove($iduser, $idfamily); // remove familyInvitation
        $this->familyRequestRemove($iduser, $idfamily); // remove family request
        if ($this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $this->addUserToFamily($iduser, $idfamily);
        return true;
    }

    /**
     * Returns acceptation status of familyInvitation for given user and family, false if no familyInvitation.
     * @return int|false
     */
    private function familyInvitationIsAccepted($iduser, $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT accepted FROM family_invitation WHERE idfamily = ? AND invitee = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns approval status of familyInvitation for given user and family, false if no familyInvitation.
     * @return int|false
     */
    private function familyInvitationIsApproved($iduser, $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT approved FROM family_invitation WHERE idfamily = ? AND invitee = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Removes familyInvitation for given user and family.
     */
    private function familyInvitationRemove($iduser, $idfamily)
    {
        $this->db->request([
            'query' => 'DELETE FROM family_invitation WHERE invitee = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]);
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
     * Returns whether family display name is available for a given user to use for given family or not.
     */
    private function familyNameAvailable(int $iduser, string $name, int $idfamily)
    {
        return empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser = ? AND idfamily != ? AND display_name = ? LIMIT 1;',
            'type' => 'iis',
            'content' => [$iduser, $idfamily, $name],
        ]));
    }

    private function familyRequestApprove(int $iduser, int $requester, int $idfamily)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return false; // false if user is not admin of family
        if (!$this->userIsMemberOfFamily($requester, $idfamily)) {
            $this->addUserToFamily($requester, $idfamily); // add requester to family
            // $familyName = $this->getFamilyName($idfamily);
            // $data = [
            //     'family' => $idfamily,
            //     'type' => 10,
            //     'user'=>$requester,
            // ];
            // $this->sendNotification(
            //     [$iduser],
            //     'La Gazet',
            //     'Vous avez rejoint la famille ' . $familyName . '.',
            //     $data
            // );
            // $this->sendData([$iduser], $data);
            // TODO: send push to family members

        }
        $this->familyRequestRemove($requester, $idfamily);
        return $this->userGetFamilyData($iduser, $idfamily);
    }

    private function familyRequestDeny(int $iduser, int $idrequester, int $idfamily)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return false;
        $this->familyRequestRemove($idrequester, $idfamily);
        $data = [
            'user' => $idrequester,
            'family' => $idfamily,
            'type' => 10,
        ];
        // send notification to requester
        $this->sendNotification(
            [$idrequester],
            'Adhésion refusée',
            'Votre demande d\'adhésion à la famille ' . $this->getFamilyName($idfamily) . ' a été refusée.',
            $data
        );
        // send data to admin
        $this->sendData([$iduser], $data);
        return $this->userGetFamilyData($iduser, $idfamily);
    }

    private function familyRequestRemove(int $iduser, int $idfamily)
    {
        $this->db->request([
            'query' => 'DELETE FROM family_request WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]);
        return true;
    }

    /**
     * Fills given gazette with games for a given recipient.
     * @param int $idgazette
     * @param int $idrecipient
     */
    private function fillGazetteWithGames(int $idgazette, int $idrecipient)
    {
        // get gazette pages according to type
        $pageCount = $this->getGazetteTypeData($this->getGazetteType($idgazette));
        // get last page publication
        $lastPage = $this->db->request([
            'query' => 'SELECT MAX(page_num) as page,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND (idrecipient IS NULL OR idrecipient = ?) LIMIT 1;',
            'type' => 'ii',
            'content' => [$idgazette, $idrecipient],
        ]);
        $lastPageFull = $lastPage['place'] === 2 || (
            ($lastPage['idpublication'] !== null && $this->getPublicationSize($lastPage['idpublication']) ||
                ($lastPage['idgame'] !== null && $this->getGameSize($lastPage['idgame'])) ||
                $lastPage['idsong'] !== null
            ));

        if (empty($lastPage) || $lastPage['page'] === $pageCount && $lastPageFull) return false;
        // count empty half pages
        $emptyHalfPages = ($pageCount - $lastPage['page']) * 2 + ($lastPageFull ? 0 : 1);

        // fill with games according to recipient's excluded game types and already printed games (common games)
        $games = $this->getRecipientFillGames($idrecipient, $emptyHalfPages);
        // prepare query to insert games
        $nextPage = $lastPage['page'] + $lastPageFull ? 1 : 0;
        $nextPlace = $lastPageFull ? 1 : 2;
        $gamesQuery = [];
        while ($nextPage <= $pageCount) {
            if (empty($games)) break;
            if ($nextPlace === 2) {
                // insert next half page game
                $gameIndex = 0;
                while ($games[$gameIndex]['full_size'] === 1) $gameIndex++;
                $gamesQuery[] = '(' . $nextPage . ',' . $nextPlace . ',' . $games[$gameIndex]['idgame'] . ')';
                // pop game from game array
                array_splice($games, $gameIndex, 1);
                $nextPlace = 1;
                $nextPage++;
            } else {
                // insert next game
                $game = array_shift($games);
                $gamesQuery[] = '(' . $nextPage . ',' . $nextPlace . ',' . $game['idgame'] . ')';
                $nextPlace = $game['full_page'] === 1 ? 1 : 2;
                if ($game['full_page'] === 1) $nextPage++;
            }
        }
        $gamesQuery = implode(',', $gamesQuery);
        // insert games
        $this->db->request([
            'query' => 'INSERT INTO gazette_page (page_num,place,idgame) VALUES ' . $gamesQuery . ';',
        ]);

        // TODO: if remaining empty page, fill it with a placeholder


        // TODO: set fill limit ? (else gazette could be full of games and it'll be harder to provide enough games to avoid duplicates)
        // TODO: get games size, all half-page ? all full-page ? if so, empty space if last publication page not full.
        // TODO: get song sizes, assuming all will be full-page.
        // TODO: discuss game types, will there be some in db or not.

        return true;
    }

    private function generateCover(array $data, array $recipient)
    {
        // date
        // $date = new DateTime($data['print_date']);
        // $date->setLocale('fr_FR');
        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $formatter->setPattern('d MMMM');
        $dateString = strtoupper($formatter->format(strtotime($data['print_date'])));
        $year = substr($data['print_date'], 0, 4);
        // day-month = first day of print date + 1 month
        // year = if month = 12, year + 1, else year
        // address
        $address = [strtoupper($recipient['address']['name']), strtoupper($recipient['address']['field1'])];
        if (!empty($recipient['address']['field2'])) $address[] = strtoupper($recipient['address']['field2']);
        if (!empty($recipient['address']['field3'])) $address[] = strtoupper($recipient['address']['field3']);
        $address[] = $recipient['address']['postal'] . ' ' . strtoupper($recipient['address']['city']);
        // TODO: country ? depending on how app handles other countries than France
        $address = implode('<br>', $address);

        // get cover img
        $coverImg = !empty($data['cover_picture']) ?
            $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($data['cover_picture'])) : '';

        // get bottom page writers
        $writers = $this->getCoverWriters($data['idgazette']);
        $coverWriters = '';
        $message = '';
        if (!empty($writers)) {
            $message = 'Nous avons des messages pour toi !';
            $i = 0;
            foreach ($writers as $writer) {
                if ($i < 4) {
                    // get user data
                    $user = $this->getUserData($writer);
                    $avatar = $user['avatar'] != null ? $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($user['avatar'])) : 'img/user-solid.svg';
                    $coverWriters .= <<<HTML
                        <div class="people">
                            <div class="people__pic-container">
                                <div class="people__pic">
                                    <img src="$avatar" alt="" />
                                </div>
                            </div>
                            <div class="people__firstname">{$user['first_name']}</div>
                            <div class="people__lastname">{$user['last_name']}</div>
                        </div>
                    HTML;
                    $i++;
                }
            }
        }
        $cover = <<<HTML
            <div class="cover">
                <div class="content">
                    <div class="top">
                        <img
                            class="logo"
                            src="img/logo-cover.svg"
                            alt="La Gazet\', La famille se lit et se relie !"
                        />
                        <div class="date-url">
                            <div class="date-url__date">
                                <div class="date-url__day-month">$dateString</div>
                                <div class="date-url__year">$year</div>
                            </div>
                            <div class="date-url__url">www.la-gazet.com</div>
                        </div>
                    </div>
                    <div class="middle">
                        <!-- <div class="pic-ambient-text">
                            <img height="208" src="assets/ambient-text.png" alt="" />
                        </div> -->
                        <div class="address">
                            $address
                        </div>
                        <div class="pic-main">
                            <img src="$coverImg" alt="" />
                        </div>
                        <div class="pic-text-bottom">
                            $message
                        </div>
                        <div class="pic-logo-bottom">
                            <img height="124" src="img/feather.svg" alt="" />
                        </div>
                    </div>
                    <div class="bottom">
                        $coverWriters
                    </div>
                </div>
            </div>
        HTML;
        return $cover;
    }

    private function generateSinglePage(array $parameters)
    {
        $page = <<<HTML
        <div class="page">
            <div class="content">
                <div class="publication landscape">
                    <div class="pics l-3a">
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <div class="desc">
                        <div class="author">
                            <div class="avatar"></div>
                            <div class="fullname-date">
                                <div>
                                    <span class="firstname">Jean</span>
                                    <span class="lastname">Guth</span>
                                </div>
                                <div>19 décembre 2022</div>
                            </div>
                        </div>
                        <div class="content">
                            <h1 class="title">Titre : Lorem Ipsum</h1>
                            <span class="text">
                                Lorem ipsum dolor sit amet consectetur
                                adipisicing elit. Aliquam, animi molestias
                                repudiandae laboriosam vel porro aliquid minima
                                qui, facere voluptate.
                            </span>
                            <hr class="separator" />
                            <div class="comment">
                                <span class="comment-author">Amélie :</span>
                                <span class="comment-text"
                                    >Quaerat dolor magnam quiquia labore.</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="publication portrait">
                    <div class="pics p-3a">
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <div class="desc">
                        <div class="author">
                            <div class="avatar"></div>
                            <div class="fullname-date">
                                <div class="fullname">
                                    <span class="firstname">Amelie</span>
                                    <span class="lastname">Niffer</span>
                                </div>
                                <div class="date">19 décembre 2022</div>
                            </div>
                        </div>
                        <div class="content">
                            <h1 class="title">Titre : Lorem Ipsum</h1>
                            <span class="text">
                                Lorem ipsum dolor sit amet consectetur
                                adipisicing elit. Aliquam, animi molestias
                                repudiandae laboriosam vel porro aliquid minima
                                qui, facere voluptate.
                            </span>
                            <hr class="separator" />
                            <div class="comment">
                                <div class="comment-author">Jean</div>
                                <span class="comment-text">
                                    Ut consectetur quaerat ut dolor aliquam. Ut
                                    est amet modi adipisci.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <footer>
                <img
                    height="110"
                    src="img/logo.svg"
                    alt="logo de l'application"
                />
                <span>Pour Astrid - Décembre 2022</span>
            </footer>
        </div>
        HTML;
        return $page;
    }

    private function generatePage(array $parameters, array $recipient, string $gazetDate)
    {
        $page = <<<HTML
        <div class="page">
            <div class="content">
        HTML;
        if (!empty($parameters)) {
            foreach ($parameters as $place) {
                // get content (publication,game,song...)
                if ($place['idpublication'] !== null) {
                    // get publication data
                    $publication = $this->getPublicationData($place['idpublication']);
                    print('@@@ generate page publication start' . PHP_EOL);
                    var_dump($publication);
                    print('@@@ generate page publication end' . PHP_EOL);

                    // parse publication date
                    $date = new DateTime($publication['created']);
                    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                    $dateString = $formatter->format($date);
                    // get author data
                    $author = $this->getUserData($publication['author']);
                    $avatar = $author['avatar'] !== null ? $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($author['avatar'])) : 'img/user-solid.svg';
                    // parse text
                    $text = str_replace("\n", '<br>', $publication['text']);

                    // parse publication layout
                    // full / half page
                    $size = $publication['layout']['identifier'][0] === 'f' ? 'full' : 'half';
                    // publication type
                    switch ($publication['layout']['identifier'][1]) {
                        case 'j':
                            // if journal
                            $type = 'notebook';
                            $picture = '';
                            if (!empty($publication['images'])) {
                                $url = $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($publication['images'][0]['idobject']));
                                $picture = <<<HTML
                                <div class="pic__frame">
                                    <div class="pic__pic">
                                        <img
                                            src="$url"
                                            height="470"
                                            width="470"
                                            alt="photo"
                                        />
                                    </div>
                                    <img
                                        width="630"
                                        height="650"
                                        src="img/picture-frame.svg"
                                        alt="cadre de la photo"
                                    />
                                </div>
                                HTML;
                            }
                            // replace \n by <br>
                            $page .= <<<HTML
                            <div class="$type $size">
                                <div class="container">
                                    <div class="text">
                                        <span>Le $dateString</span>
                                        $text
                                    </div>
                                </div>
                                $picture
                            </div>
                            HTML;
                            break;
                        case 'p':
                            // if publication
                            $type = 'publication';
                            $orientation = $publication['layout']['orientation'] === 1 ? 'landscape' : 'portrait';
                            $nameNewLine = $orientation === 'portrait' ? '<br>' : '';
                            $pictureLayout = '_' . $publication['layout']['identifier'][3] . $publication['layout']['identifier'][4];
                            // pictures
                            $pictures = '';
                            foreach ($publication['images'] as $image) {
                                $url = $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($image['idobject']));
                                $pictures .= <<<HTML
                                <div>
                                    <img src="$url" alt="">
                                </div>
                                HTML;
                            }

                            $page .= <<<HTML
                            <div class="$type $size $orientation">
                                <div class="pics $pictureLayout">
                                    $pictures
                                </div>
                                <div class="desc">
                                    <div class="author">
                                        <div class="avatar">
                                            <img src="$avatar" alt="" />
                                        </div>
                                        <div class="fullname-date">
                                            <div>
                                                <span class="firstname">{$author['first_name']}</span>
                                                $nameNewLine
                                                <span class="lastname">{$author['last_name']}</span>
                                            </div>
                                            <div>$dateString</div>
                                        </div>
                                    </div>
                                    <div class="content">
                                        <h1 class="title">{$publication['title']}</h1>
                                        <span class="text">
                                            $text
                                        </span>
                                        <!-- <hr class="separator" />
                                        <div class="comment">
                                            <span class="comment-author">Amélie :</span>
                                            <span class="comment-text"
                                                >Quaerat dolor magnam quiquia labore.</span
                                            >
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                            HTML;
                            break;
                    }
                } else if ($place['idgame'] !== null) {
                    // get game data
                    // game is an image, get it
                } else if ($place['idsong'] !== null) {
                    // get song data
                }
            }
        }
        $page .= <<<HTML
            </div>
            <footer>
                <img
                    height="110"
                    src="img/logo.svg"
                    alt="logo de l\'application"
                />
                <span>Pour {$recipient['display_name']} - $gazetDate</span>
            </footer>
        </div>
        HTML;
        return $page;
    }

    /**
     * Generates PDF from gazette and returns file
     */
    private function generatePDF(int $idgazette)
    {
        try {
            print('@@@ Init generate pdf' . PHP_EOL);
            $serverPath = getenv('SERVER_URL');
            // get gazette data (cover, recipient, type)
            $data = $this->getGazetteData($idgazette);
            $data['idgazette'] = $idgazette;
            print('@@@ generate pdf 1' . PHP_EOL);
            // get gazette recipient data (address, display name)
            $recipient = $this->getRecipientData($data['idrecipient']);
            print('@@@ generate pdf 2' . PHP_EOL);
            $gazette = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="UTF-8" />
                    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                    <link rel="stylesheet" type="text/css" href="css/style.css" />
                    <title>La Gazet</title>
                </head>
                <body>
            HTML;

            // cover page
            $cover = $this->generateCover($data, $recipient);
            print('@@@ generate pdf 3' . PHP_EOL);
            $gazette .= $cover;

            // get all gazette pages
            $pageCount = $this->getGazetteTypeData($data['type']);
            $pages = $this->getGazettePages($idgazette);
            print('@@@ generate pdf 4' . PHP_EOL);
            // for each gazette page
            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $formatter->setPattern('MMMM yyyy');
            $dateString = ucfirst($formatter->format(strtotime($data['print_date'])));
            for ($i = 1; $i < $pageCount - 1; $i++) {
                $gazette .= $this->generatePage($pages[$i] ?? [], $recipient, $dateString);
            }

            // foreach ($pages as $page) $gazette .= $this->generatePage($page, $recipient, $dateString);
            print('@@@ generate pdf 5' . PHP_EOL);

            $gazette .= <<<HTML
                </body>
            </html>
            HTML;


            // generate random file name
            $filename = bin2hex(random_bytes(16)) . '.html';
            // save $gazette string into html file
            file_put_contents('./public/' . $filename, $gazette);

            // get assets (images, fonts, css...) to temp folder

            // generate pdf from HTML file
            // $pdf = $this->chrome->generatePDF($gazette);
            // $pdf = $this->browserless->pdfFromHtml($gazette);
            $pdf = $this->browserless->pdfFromUrl($filename);
            if (empty($pdf)) throw new Throwable('PDF is empty');
            print('@@@ generate pdf 6' . PHP_EOL);
            // store in local storage
            $file = './file.pdf';
            file_put_contents($file, $pdf);

            // remove local html file
            unlink('./public/' . $filename);
            // store pdf in s3
            $keys = $this->s3->put(['body' => $pdf, 'extension' => 'pdf']);
            // $keys = $this->s3->put(['body' => base64_decode($pdf), 'extension' => 'pdf']);
            print('@@@ generate pdf 7' . PHP_EOL);
            // store pdf in db
            $idObject = $this->setS3Object(['key' => $keys['key'], 'binKey' => $keys['binKey'], 'ext' => 'pdf', 'family' => $recipient['idfamily']]);
            print('@@@ generate pdf 8' . PHP_EOL);
            if (!empty($data['pdf'])) $this->removeS3Object($data['pdf']);
            // update gazette with pdf
            $this->db->request([
                'query' => 'UPDATE gazette SET pdf = ? WHERE idgazette = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$idObject, $idgazette],
            ]);
            print('@@@ generate pdf 9' . PHP_EOL);
            return true;
        } catch (Throwable $e) {
            print('@@@ generate pdf error' . PHP_EOL);
            print($e->getMessage() . PHP_EOL);
            print($e->getTraceAsString() . PHP_EOL);
            return false;
        }
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
        foreach ($publications as &$publication) $publication = $publication[0];
        return $publications;
    }

    /**
     * Checks if given name is available for given user and family, returns name or modified one if not.
     */
    private function getAvailableFamilyName(int $iduser, int $idfamily, string $name)
    {
        if ($this->familyNameAvailable($iduser, $name, $idfamily)) return $name;
        $tempName = "$name";
        $suffix = 1;
        while (!$this->familyNameAvailable($iduser, $tempName, $idfamily)) $tempName = $name . '_' . $suffix++;
        return $tempName;
    }

    private function getAvailableRecipientName(int $idfamily, string $name)
    {
        if ($this->checkRecipientNameAvailiability($idfamily, $name)) return $name;
        $i = 1;
        $tempName = "$name";
        while (!$this->checkRecipientNameAvailiability($idfamily, $tempName)) $tempName = $name . ' ' . $i++;
        return $tempName;
    }

    private function getCommentData(int $iduser, int $idcomment)
    {
        $comment = $this->db->request([
            'query' => 'SELECT iduser,content,created FROM comment WHERE idcomment = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomment],
        ])[0];
        $comment['likes'] = $this->getCommentLikesCount($idcomment);
        if ($iduser !== $comment['iduser']) $comment['like'] = $this->userLikesComment($iduser, $idcomment);
        return $comment;
    }

    private function getCommentLikesCount(int $idcomment)
    {
        return $this->db->request([
            'query' => 'SELECT COUNT(*) FROM comment_has_like WHERE idcomment = ?;',
            'type' => 'i',
            'content' => [$idcomment],
            'array' => true,
        ])[0][0];
    }

    private function getCommentsAuthor(int $idcomment)
    {
        return $this->db->request([
            'query' => 'SELECT iduser FROM comment WHERE idcomment = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomment],
            'array' => true,
        ])[0][0];
    }

    private function getCoverPictureModifications(int $idgazette)
    {
        $data = [];
        foreach ($this->db->request([
            'query' => 'SELECT idrecipient, cover_picture FROM gazette_cover WHERE idgazette = ?;',
            'type' => 'i',
            'content' => [$idgazette],
        ]) as $modification) {
            $data[$modification['idrecipient']] = $modification['cover_picture'];
        }
        return $data;
    }

    private function getCoverWriters($idgazette)
    {
        $writers = $this->db->request([
            'query' => 'SELECT iduser FROM gazette_writer WHERE idgazette = ?;',
            'type' => 'i',
            'content' => [$idgazette],
            'array' => true,
        ]);
        if (empty($writers)) return [];
        foreach ($writers as &$writer) $writer = $writer[0];
        return $writers;
    }

    /**
     * Returns family admin's id.
     * @return int
     */
    private function getFamilyAdmin($idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT admin FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ])[0][0];
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

    /**
     * Returns given user given family display name.
     * @return string|false
     */
    private function getFamilyDisplayName(int $iduser, int $idfamily)
    {
        return $this->userIsMemberOfFamily($iduser, $idfamily) ? ($this->db->request([
            'query' => 'SELECT display_name FROM family_has_member WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
            'array' => true,
        ])[0][0] ?? false) : $this->db->request([
            'query' => 'SELECT name FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true
        ])[0][0];
    }

    private function getFamilyGazettes(int $idfamily)
    {
        $recipients = $this->getFamilyRecipients($idfamily);
        if (empty($recipients)) return [];
        $recipients = implode(',', $recipients);
        return $this->db->request([
            'query' => 'SELECT idgazette,print_date,printed,cover_mini,pdf,type FROM gazette WHERE idrecipient IN (' . $recipients . ');',
        ]);
    }

    private function getFamilyInvitations(int $idfamily)
    {
        $invitations = $this->db->request([
            'query' => 'SELECT email,invitee,inviter,approved,accepted,created FROM family_invitation WHERE idfamily = ? AND invitee IS NOT NULL;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);
        foreach ($invitations as &$invitation) {
            $invitation = [...$invitation, ...$this->getUserData($invitation['invitee'])];
        }
        return $invitations;
    }


    private function getFamilyMemberData(int $idfamily, int $idmember)
    {
        if (!$this->userIsMemberOfFamily($idmember, $idfamily)) return false;
        $memberData = $this->getUserData($idmember);
        $idrecipient = $this->userIsRecipientOfFamily($idmember, $idfamily);
        $memberData['idrecipient'] = $idrecipient ? $idrecipient : null;
        return $memberData;
    }

    private function getFamilyMembers(int $idfamily, array $exclude = [])
    {
        $where = '';
        if (!empty($exclude)) {
            $exclude = implode(',', $exclude);
            $where = ' AND iduser NOT IN (' . $exclude . ')';
        }
        $members = $this->db->request([
            'query' => 'SELECT iduser FROM family_has_member WHERE idfamily = ?' . $where . ';',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        if (empty($members)) return [];
        foreach ($members as &$member) $member = $member[0];
        return $members;
    }

    private function getFamilyMembersData(int $idfamily)
    {
        $membersid = $this->getFamilyMembers($idfamily);
        if (empty($membersid)) return [];
        $members = [];
        foreach ($membersid as $memberid) $members[$memberid] = $this->getFamilyMemberData($idfamily, $memberid);
        return $members;
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
    private function getFamilyPublications(int $iduser, int $idfamily, array $range = null)
    {
        // if user from family
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;

        // if range
        // $rangeString = '';
        // if ($range) $rangeString = ' AND created > TIMESTAMP(FROM_UNIXTIME(' . $range['from'] . ')) AND created < TIMESTAMP(FROM_UNIXTIME(' . $range['to'] . '))';

        $publications = $this->db->request([
            'query' => "SELECT idpublication,
            title,
            type,
            author,
            description as text,
            idlayout,
            idbackground,
            private,
            created
            FROM publication
            WHERE idfamily = ? AND (author = ? OR private = 0) ORDER BY created DESC;",
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);
        // unset($rangeString);

        foreach ($publications as &$publication) {
            // get layout
            $publication['layout'] = $this->getLayout($publication['idlayout']);
            // get like
            if ($iduser !== $publication['author'])
                $publication['like'] = $this->userLikesPublication($iduser, $publication['idpublication']);
            // get likes count
            $publication['likes'] = $this->getPublicationLikesCount($publication['idpublication']);
            // get comments count
            $publication['comments'] = $this->getPublicationCommentsCount($publication['idpublication']);
            // get images
            $publication['images'] = $this->getPublicationPictures($publication['idpublication']);
            // get text
            if (empty($publication['text']))
                $publication['text'] = $this->getPublicationText($publication['idpublication']);
        }
        return $publications;
    }

    /**
     * Returns recipients' ids from a family.
     * @return int[]
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
     * Returns family recipients' data.
     */
    private function getFamilyRecipientsData(int $idfamily)
    {
        if (!$this->familyHasRecipients($idfamily)) return [];
        $recipients = [];
        foreach ($this->getFamilyRecipients($idfamily) as $recipient) $recipients[$recipient] = $this->getRecipientData($recipient);
        return $recipients;
    }

    private function getFamilyRequests(int $idfamily)
    {
        $requests = [];
        foreach ($this->db->request([
            'query' => 'SELECT iduser FROM family_request WHERE idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]) as $user) $requests[] = $this->getUserData($user[0]);
        return $requests;
    }

    /**
     * Returns subscriptions data for a given family.
     */
    private function getFamilySubscriptions(int $idfamily)
    {
        // get family recipients
        $recipients = $this->getFamilyRecipients($idfamily);
        if (empty($recipients)) return false;
        $recipients = implode(',', $recipients);
        // get recipients subscriptions
        return $this->db->request([
            'query' => 'SELECT idsubscription,idrecipient,idsubscription_type FROM subscription WHERE idrecipient IN (' . $recipients . ');',
        ]);
    }

    private function getFamilyWithCode(string $code)
    {
        return $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE code = ? LIMIT 1;',
            'type' => 's',
            'content' => [$code],
            'array' => true,
        ])[0][0] ?? false;
    }

    private function getGameData(int $idgame)
    {
        return $this->db->request([
            'query' => 'SELECT idobject,type,difficulty,full_page FROM game WHERE idgame = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgame],
        ])[0];
    }

    /**
     * Returns true if full page.
     * @param int $idgame
     */
    private function getGameSize(int $idgame)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM game WHERE idgame = ? AND full_page = 1;',
            'type' => 'i',
            'content' => [$idgame],
        ]));
    }

    private function getGazettesByCoverPicture(int $idobject)
    {
        $gazettes = $this->db->request([
            'query' => 'SELECT idgazette FROM gazette WHERE cover_picture = ?;',
            'type' => 'i',
            'content' => [$idobject],
            'array' => true,
        ]);
        foreach ($gazettes as &$gazette) $gazette = $gazette[0];
        return $gazettes;
    }

    private function getGazettesByPublication(int $idpublication)
    {
        $gazettes = $this->db->request([
            'query' => 'SELECT idgazette FROM gazette_page WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ]);
        if (empty($gazettes)) return false;
        foreach ($gazettes as &$gazette) $gazette = $gazette[0];
        return $gazettes;
    }

    private function getGazetteData(int $idgazette)
    {
        return $this->db->request([
            'query' => 'SELECT idrecipient,type,print_date,cover_picture,cover_mini,pdf FROM gazette WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ])[0];
    }

    /**
     * Returns gazette's games' ids excluding modifications.
     * @return int[]
     */
    private function getGazetteGames(int $idgazette)
    {
        $games = $this->db->request([
            'query' => 'SELECT idgame FROM gazette_page WHERE idgazette = ? AND idrecipient IS NULL;',
            'type' => 'i',
            'content' => [$idgazette],
            'array' => true,
        ]);
        if (empty($games)) return false;
        foreach ($games as &$game) $game = $game[0];
        return $games;
    }

    /**
     * Returns all gazette's modifications.
     */
    private function getGazetteModifications(int $idgazette)
    {
        return $this->db->request([
            'query' => 'SELECT page_num,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND manual = 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ]);
    }

    /**
     * Returns gazette's modifications' data for a given recipient.
     */
    private function getGazetteModificationsData(int $idgazette, int $idrecipient)
    {
        $pages = $this->db->request([
            'query' => 'SELECT page_num,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND idrecipient = ? ORDER BY page_num,place,idrecipient ASC;',
            'type' => 'ii',
            'content' => [$idgazette, $idrecipient],
        ]);
        // get place content
        foreach ($pages as &$place) {
            if ($place['idpublication'] !== null) $place['publication'] = $this->getPublicationData($place['idpublication']);
            else if ($place['idgame'] !== null) $place['game'] = $this->getGameData($place['idgame']);
            else if ($place['idsong'] !== null) $place['song'] = $this->getSongData($place['idsong']);
        }
        return $pages;
    }

    private function getGazettePages(int $idgazette)
    {
        $pages = [];
        foreach ($this->db->request([
            'query' => 'SELECT page_num,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND manual = 0;',
            'type' => 'i',
            'content' => [$idgazette],
        ]) as $place) {
            // if ($place['idrecipient'] === null)
            $pages[$place['page_num']][$place['place']] = [
                'idpublication' => $place['idpublication'],
                'idgame' => $place['idgame'],
                'idsong' => $place['idsong']
            ];
            // else $pages[$place['page_num']][$place['place']]['modification'][$place['idrecipient']] = [
            //     'idpublication' => $place['idpublication'],
            //     'idgame' => $place['idgame'],
            //     'idsong' => $place['idsong']
            // ];
        }
        return $pages;
    }

    private function getGazettePagesData(int $idgazette)
    {
        $pages = $this->getGazettePages($idgazette);
        foreach ($pages as &$page) {
            foreach ($page as &$place) {
                if (!empty($place['modification'])) {
                    foreach ($place['modification'] as &$modification) {
                        if ($modification['idpublication'] !== null) $modification['publication'] = $this->getPublicationData($modification['idpublication']);
                        else if ($modification['idgame'] !== null) $modification['game'] = $this->getGameData($modification['idgame']);
                        else if ($modification['idsong'] !== null) $modification['song'] = $this->getSongData($modification['idsong']);
                    }
                }
                if ($place['idpublication'] !== null) $place['publication'] = $this->getPublicationData($place['idpublication']);
                else if ($place['idgame'] !== null) $place['game'] = $this->getGameData($place['idgame']);
                else if ($place['idsong'] !== null) $place['song'] = $this->getSongData($place['idsong']);
            }
        }
        return $pages;
    }

    /**
     * Returns gazette's publications' ids.
     * @return int[]
     */
    private function getGazettePublications(int $idgazette)
    {
        $publications = $this->db->request([
            'query' => 'SELECT idpublication FROM gazette_page WHERE idgazette = ?;',
            'type' => 'i',
            'content' => [$idgazette],
            'array' => true,
        ]);
        if (empty($publications)) return false;
        foreach ($publications as &$publication) $publication = $publication[0];
        return $publications;
    }

    /**
     * Returns gazette's object id.
     * @return int|bool
     */
    private function getGazetteObjectsIds(int $idgazette)
    {
        return $this->db->request([
            'query' => 'SELECT pdf,cover_mini,cover_picture FROM gazette WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ])[0];
    }

    /**
     * Returns true if gazette is printed.
     * @return bool
     */
    private function getGazettePrintStatus(int $idgazette)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM gazette WHERE idgazette = ? AND printed = 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ]));
    }

    /**
     * Returns gazette's songs' ids.
     * @return int[]
     */
    private function getGazetteSongs(int $idgazette)
    {
        $songs = $this->db->request([
            'query' => 'SELECT idsong FROM gazette_page WHERE idgazette = ? AND idrecipient IS NULL;',
            'type' => 'i',
            'content' => [$idgazette],
            'array' => true,
        ]);
        if (empty($songs)) return false;
        foreach ($songs as &$song) $song = $song[0];
        return $songs;
    }

    private function getGazetteType($idgazette)
    {
        return $this->db->request([
            'query' => 'SELECT type FROM gazette WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
            'array' => true,
        ])[0][0];
    }

    private function getGazetteTypeData(int $idtype)
    {
        return $this->db->request([
            'query' => 'SELECT pages FROM gazette_type WHERE idgazette_type = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idtype],
            'array' => true,
        ])[0][0];
    }

    private function getGazetteTypes()
    {
        return $this->db->request([
            'query' => 'SELECT idgazette_type,pages FROM gazette_type;',
        ]);
    }

    private function getLayout(int $idlayout)
    {
        return $this->db->request([
            'query' => 'SELECT identifier,description,quantity,orientation,full_page FROM layout WHERE idlayout = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idlayout],
        ])[0];
    }

    private function getLayoutFromString(string $identifier)
    {
        return $this->db->request([
            'query' => 'SELECT idlayout FROM layout WHERE identifier = ? LIMIT 1;',
            'type' => 's',
            'content' => [$identifier],
            'array' => true,
        ])[0][0];
    }

    // private function getLayoutFullPage(int $idlayout)
    // {
    //     return $this->db->request([
    //         'query' => 'SELECT full_page FROM layout WHERE idlayout = ? LIMIT 1;',
    //         'type' => 'i',
    //         'content' => [$idlayout],
    //         'array' => true,
    //     ])[0][0] === 1;
    // }

    /**
     * Returns print date for a given date.
     * @param string $date
     * @return string Print date in Y-m-d format.
     */
    private function getPrintDate($date)
    {
        $day = (int) date('d', strtotime($date));
        $date = new DateTime($date);
        if ($day < 28) {
            $date->modify('first day of this month')->modify('+27 days');
        } else $date->modify('first day of next month')->modify('+27 days');
        return $date->format('Y-m-d');
    }

    private function getPublicationAuthor(int $idpublication)
    {
        return $this->db->request([
            'query' => 'SELECT author FROM publication WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ])[0][0];
    }

    private function getPublicationCommentsCount(int $idpublication)
    {
        // TODO: check if it returns the right count of comments
        return $this->db->request([
            'query' => 'SELECT COUNT(*) FROM comment WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ])[0][0];
    }

    private function getPublicationCommentsData(int $iduser, int $idfamily, int $idpublication)
    {
        // TODO: separate user commands with security checks and data return from general commands without checks and data, e.g. userGetPublicationCommentsData vs getPublicationCommentsData
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $commentsId = $this->getPublicationCommentsId($idpublication);
        if (empty($commentsId)) return [];
        $commentsId = implode(',', $commentsId);
        $comments = $this->db->request([
            'query' => 'SELECT idcomment,iduser,content,created FROM comment WHERE idcomment IN (' . $commentsId . ') ORDER BY created DESC;',
        ]);
        foreach ($comments as &$comment) {
            if ($iduser != $comment['iduser'])
                $comment['like'] = $this->userLikesComment($iduser, $comment['idcomment']);
            $comment['likes'] = $this->getCommentLikesCount($comment['idcomment']);
        }
        return $comments;
    }

    /**
     * Returns an array of comment's id for given publication.
     */
    private function getPublicationCommentsId(int $idpublication)
    {
        $comments = $this->db->request([
            'query' => 'SELECT idcomment FROM comment WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ]);
        foreach ($comments as &$comment) $comment = $comment[0];
        return $comments;
    }

    private function getPublicationData(int $idpublication, int $iduser = null)
    {
        $publication = $this->db->request([
            'query' => "SELECT title,
            type,
            author,
            description as text,
            idlayout,
            idbackground,
            private,
            created
            FROM publication
            WHERE idpublication = ?;",
            'type' => 'i',
            'content' => [$idpublication],
        ])[0];

        // get layout
        $publication['layout'] = $this->getLayout($publication['idlayout']);
        // // get like
        if (!empty($iduser)) {
            $publication['idpublication'] = $idpublication;
            if ($iduser !== $publication['author'])
                $publication['like'] = $this->userLikesPublication($iduser, $idpublication);
        }
        // get likes count
        $publication['likes'] = $this->getPublicationLikesCount($idpublication);
        // get comments count
        $publication['comments'] = $this->getPublicationCommentsCount($idpublication);
        // get images
        $publication['images'] = $this->getPublicationPictures($idpublication);
        // get text
        if (empty($publication['text']))
            $publication['text'] = $this->getPublicationText($idpublication);
        return $publication;
    }

    private function getPublicationDate(int $idpublication)
    {
        return $this->db->request([
            'query' => 'SELECT created FROM publication WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ])[0][0];
    }

    private function getPublicationLikesCount(int $idpublication)
    {
        // TODO: check if it returns the right count of likes
        return $this->db->request([
            'query' => 'SELECT COUNT(*) FROM publication_has_like WHERE idpublication = ?;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ])[0][0];
    }

    /**
     * Returns an array of given publication's pictures ordered by place.
     */
    private function getPublicationPictures(int $idpublication)
    {
        return $this->db->request([
            'query' => 'SELECT idobject,place,title FROM publication_has_picture WHERE idpublication = ? ORDER BY place ASC;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
    }

    /**
     * Returns true if fullpage.
     * @param int $idlayout
     * @return bool
     */
    private function getPublicationSize(int $idpublication)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM publication WHERE idpublication = ? AND full_page = 1;',
            'type' => 'i',
            'content' => [$idpublication],
        ]));
    }

    private function getPublicationText(int $idpublication)
    {
        return $this->db->request([
            'query' => 'SELECT content FROM text WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns recipient address data
     * @return array Address data
     */
    private function getRecipientAddress(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT name,phone,field1,field2,field3,postal,city,state,country FROM address WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ])[0];
    }

    /**
     * Returns recipient's address id.
     * @return int Address id
     */
    // private function getRecipientAddressId(int $idrecipient)
    // {
    //     return $this->db->request([
    //         'query' => 'SELECT idaddress FROM recipient WHERE idrecipient = ? LIMIT 1;',
    //         'type' => 'i',
    //         'content' => [$idrecipient],
    //         'array' => true,
    //     ])[0][0];
    // }

    /**
     * Returns recipient's avatar's idobject
     */
    private function getRecipientAvatar(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0] ?? false;
    }

    private function getRecipientData(int $idrecipient)
    {
        return [
            ...$this->db->request([
                'query' => 'SELECT idrecipient,idfamily,display_name,iduser,referent,birthdate,avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idrecipient],
            ])[0],
            'address' => $this->getRecipientAddress($idrecipient),
            'subscription' => $this->getRecipientSubscription($idrecipient)
        ];
    }

    /**
     * Returns recipient's display name.
     */
    private function getRecipientDisplayName(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT display_name FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
    }

    /**
     * Returns games to fill a recipient's gazette, according to selected game types difficulties and already printed games.
     * @param int $idrecipient
     * @param int $halfPages - Count of half pages to fill
     * 
     * @return array Array of ['idgame','full_page'] games.
     */
    private function getRecipientFillGames(int $idrecipient, int $halfPages)
    {
        // get recipient used games
        $usedGames = $this->getRecipientUsedGames($idrecipient);
        // get recipient game types data
        $gameTypes = $this->getRecipientGameTypes($idrecipient);
        $games = [];
        // for each game type, get a predefined quantity of games ordered by difficulty DESC from selected difficulty
        foreach ($gameTypes as $type) {
            // get games for this type
            $games[$type['idgame_type']] = $this->db->request([
                'query' => 'SELECT idgame,full_page FROM game WHERE type = ? AND difficulty <= ? AND idgame NOT IN (' . implode(',', $usedGames) . ') ORDER BY difficulty DESC, created LIMIT ?;',
                'type' => 'iii',
                'content' => [$type['idgame_type'], $type['idgame_difficulty'], $halfPages],
            ]);
        }
        $selectedGames = [];
        $filledHalfPages = 0;
        // get a random type for the first game
        $type = random_int(0, count($gameTypes) - 1);
        while ($filledHalfPages < $halfPages) {
            if ($filledHalfPages === $halfPages - 1) {
                // try to find a half page game in remaining games
                for ($i = 0; $i < count($gameTypes) - 1; $i++) {
                    while ($games[$type][0]['full_page'] === 1) array_shift($games[$type]);
                    if (!empty($games[$type])) {
                        $usedGames[] = $games[$type][0]['idgame'];
                        $selectedGames[] = array_shift($games[$type]);
                        $filledHalfPages++;
                        break;
                    }
                    $type < count($gameTypes) - 1 ? $type++ : $type = 0;
                }
                break;
            } else {
                $usedGames[] = $games[$type][0]['idgame'];
                // if full page, $filledHalfPages += 2 else $filledHalfPages++
                $filledHalfPages += $games[$type][0]['full_page'] === 1 ? 2 : 1;
                // get next game
                $selectedGames[] = array_shift($games[$type]);
                // set next game type
                $type < count($gameTypes) - 1 ? $type++ : $type = 0;
            }
        }
        // if not enough games, get more half page games
        if ($filledHalfPages < $halfPages) {
            $types = [];
            foreach ($gameTypes as $type) {
                $types[] = $type['idgame_type'];
            }
            $lastGames = $this->db->request([
                'query' => 'SELECT idgame,full_page FROM game WHERE idgame NOT IN (' . implode(',', $usedGames) . ') AND type IN (' . implode(',', $types) . ') AND full_page = 0 ORDER BY created ASC, difficulty ASC LIMIT ?;',
                'type' => 'i',
                'content' => [$halfPages - $filledHalfPages],
            ]);
            if (!empty($lastGames)) foreach ($lastGames as $game) {
                $selectedGames[] = $game;
                $filledHalfPages++;
            }
            // if not enough games, get last ones ignoring used ones except those in current gazette
            if ($filledHalfPages < $halfPages) {
                $gazetteGames = [];
                foreach ($selectedGames as $game) {
                    $gazetteGames[] = $game[0];
                }
                $lastGames = $this->db->request([
                    'query' => 'SELECT idgame,full_page FROM game WHERE idgame NOT IN (' . implode(',', $gazetteGames) . ') AND type IN (' . implode(',', $types) . ') AND full_page = 0 ORDER BY created ASC, difficulty ASC LIMIT ?;',
                    'type' => 'i',
                    'content' => [$halfPages - $filledHalfPages],
                ]);
                if (!empty($lastGames)) foreach ($lastGames as $game) {
                    $selectedGames[] = $game;
                    $filledHalfPages++;
                }
                // if still not enough games, get last games also ignoring game type
                if ($filledHalfPages < $halfPages) {
                    $lastGames = $this->db->request([
                        'query' => 'SELECT idgame,full_page FROM game WHERE full_page = 0 ORDER BY created ASC, difficulty ASC LIMIT ?;',
                        'type' => 'i',
                        'content' => [$halfPages - $filledHalfPages],
                    ]);
                    if (!empty($lastGames)) foreach ($lastGames as $game) {
                        $selectedGames[] = $game;
                        $filledHalfPages++;
                    }
                }
            }
        }
        return $selectedGames;
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

    private function getRecipientGameTypes(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idgame_type,idgame_difficulty FROM recipient_game_difficulty WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
    }

    /**
     * Returns recipient gazette pages for a given gazette.
     * @param int $idgazette
     * @param int $idrecipient
     * 
     * @return array gazette pages including modifications if any
     */
    private function getRecipientGazettePages(int $idgazette, int $idrecipient)
    {
        if (!$this->recipientHasGazetteMods($idgazette, $idrecipient)) return $this->getGazettePages($idgazette);
        // get recipient modified gazette pages
        $recipientMods = $this->db->request([
            'query' => 'SELECT page_num,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND idrecipient = ?;',
            'type' => 'ii',
            'content' => [$idgazette, $idrecipient],
        ]);
        $pages = [];
        $modifications = '';
        foreach ($recipientMods as $modification) {
            $pages[$modification['page_num']][$modification['place']] = ['idpublication' => $modification['idpublication'], 'idgame' => $modification['idgame'], 'idsong' => $modification['idsong']];
            $modifications .= ' AND NOT (page_num = ' . $modification['page_num'] . ' AND place = ' . $modification['place'] . ')';
        }
        foreach ($this->db->request([
            'query' => 'SELECT page_num,place,idpublication,idgame,idsong FROM gazette_page WHERE idgazette = ? AND idrecipient IS NULL' . $modifications . ';',
            'type' => 'i',
            'content' => [$idgazette],
        ]) as $page) {
            $pages[$page['page_num']][$page['place']] = ['idpublication' => $page['idpublication'], 'idgame' => $page['idgame'], 'idsong' => $page['idsong']];
        }
        return $pages;
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

    /**
     * Returns given recipient's referent.
     * @param int $idrecipient
     * @return int
     */
    private function getRecipientReferent(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT referent FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ])[0][0];
    }

    /**
     * Returns active subscription id and type for given recipient, false if none.
     * @return array|false
     */
    private function getRecipientSubscription(int $idrecipient)
    {
        return $this->db->request([
            'query' => 'SELECT idsubscription,idsubscription_type FROM subscription WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ])[0] ?? false;
    }

    /**
     * Returns ids of games already in family gazettes for a given recipient.
     * @param int $idrecipient
     * @return array|false
     */
    private function getRecipientUsedGames(int $idrecipient)
    {
        $games =
            $this->db->request([
                'query' => 'SELECT idgame FROM gazette_page WHERE idrecipient = ?;',
                'type' => 'i',
                'content' => [$idrecipient],
                'array' => true,
            ]);
        if (empty($games)) return false;
        foreach ($games as &$game) $game = $game[0];
        return $games;
    }

    /**
     * Returns array of given family recipients' ids from which user is referent.
     * @return int[]|false
     */
    private function getReferentRecipients(int $iduser, int $idfamily)
    {
        $recipients = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE iduser = ? AND idfamily = ?;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ]);
        if (empty($recipients)) return false;
        foreach ($recipients as &$recipient) $recipient = $recipient[0];
        return $recipients;
    }

    private function getS3ObjectData(int $idobject)
    {
        return $this->db->request([
            'query' => 'SELECT owner,family FROM s3 WHERE idobject = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ])[0] ?? false;
    }

    private function getS3ObjectIdFromKey(string $key)
    {
        return $this->db->request([
            'query' => 'SELECT idobject FROM s3 WHERE name = ? LIMIT 1;',
            'type' => 's',
            'content' => [$key],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns key from object, false if object not set.
     */
    private function getS3ObjectKeyFromId(int $idobject)
    {
        $object = $this->db->request([
            'query' => 'SELECT name,ext FROM s3 WHERE idobject = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
            'array' => true,
        ])[0];
        return empty($object) ? false : bin2hex($object[0]) . '.' . $object[1];
    }

    private function getSongData(int $idsong)
    {
        return $this->db->request([
            'query' => 'SELECT title,content,link FROM song WHERE idsong = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idsong],
        ])[0];
    }

    /**
     * Returns array of user's id of members participating in given subscription.
     * @return int[]|false
     */
    private function getSubscriptionMembers(int $idsubscription)
    {
        $members = $this->db->request([
            'query' => 'SELECT iduser FROM recurring_payment WHERE idsubscription = ?;',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ]);
        if (empty($members)) return false;
        foreach ($members as &$member) $member = $member[0];
        return $members;
    }

    private function getSubscriptionMembersShare(int $idsubscription)
    {
        // get current month => use last_day(now()) in mysql
        $memberPayments = [];
        // get members having recurring payment with end = null or > current month
        $payments = $this->db->request([
            'query' => 'SELECT amount FROM user_has_payment WHERE idsubscription = ? AND date > DATE_ADD(DATE(LAST_DAY(NOW() - INTERVAL 1 MONTH)), INTERVAL 1 DAY) AND date < DATE_ADD(LAST_DAY(NOW()), INTERVAL 1 DAY);',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ]);
        if (!empty($payments))
            foreach ($payments as $payment) $memberPayments[] = $payment[0];
        unset($payment);
        // get members having payment with date in current month
        $payments = $this->db->request([
            'query' => 'SELECT amount FROM recurring_payment WHERE idsubscription = ?;',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ]);
        if (!empty($payments)) foreach ($payments as $payment) $memberPayments[] = $payment[0];
        if (empty($memberPayments)) return false;
        return array_sum($memberPayments);
    }

    /**
     * Returns recipient's id linked to given subscription.
     */
    private function getSubscriptionRecipient(int $idsubscription)
    {
        return $this->db->request([
            'query' => 'SELECT idrecipient FROM subscription WHERE idsubscription = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idsubscription],
            'array' => true,
        ])[0][0];
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

    private function getUnseen(int $iduser)
    {
        $response = [];
        $setFamily = fn (int $idfamily) => [
            $idfamily => [
                'publications' => [],
                'comments' => [],
                'members' => [],
            ]
        ];
        $publications = $this->db->request([
            'query' => 'SELECT idpublication,idfamily,created FROM unseen_publication LEFT JOIN publication USING (idpublication) WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        // regroup publications by family
        if (!empty($publications))
            foreach ($publications as $publication) {
                if (!isset($response[$publication['idfamily']])) $setFamily($publication['idfamily']);
                $response[$publication['idfamily']]['publications'][] = $publication;
            }

        $comments = $this->db->request([
            'query' => 'SELECT comment.idcomment, comment.idpublication, publication.idfamily, comment.created
                FROM unseen_comment
                LEFT JOIN comment USING (idcomment)
                LEFT JOIN publication ON comment.idpublication = publication.idpublication
                WHERE unseen_comment.iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        // regroup comments by family
        if (!empty($comments))
            foreach ($comments as $comment) {
                if (!isset($response[$comment['idfamily']])) $setFamily($comment['idfamily']);
                $response[$comment['idfamily']]['comments'][] = $comment;
            }

        $members = $this->db->request([
            'query' => 'SELECT member,idfamily,created FROM unseen_member WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        // regroup members by family
        if (!empty($members))
            foreach ($members as $member) {
                if (!isset($response[$member['idfamily']])) $setFamily($member['idfamily']);
                $response[$member['idfamily']]['members'][] = $member;
            }

        return $response;
    }

    /**
     * Returns idobject of user's avatar
     * @return int|false Avatar's idobject or false
     */
    private function getUserAvatar(int $iduser)
    {
        return $this->db->request([
            'query' => 'SELECT avatar FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] ?? false;
    }

    /**
     * Returns iduser if exists, else false.
     * @return int|false
     */
    private function getUserByEmail(string $email)
    {
        return $this->db->request([
            'query' => 'SELECT iduser FROM user WHERE email = ? LIMIT 1;',
            'type' => 's',
            'content' => [$email],
            'array' => true,
        ])[0][0] ?? false;
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
            'query' => 'SELECT iduser as id,last_name,first_name,birthdate,email,phone,avatar,theme,autocorrect,capitalize FROM user WHERE iduser = ? LIMIT 1;',
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

    private function getUserEmail($iduser)
    {
        return $this->db->request([
            'query' => 'SELECT email FROM user WHERE iduser = ? LIMIT 1;',
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
            'query' => 'SELECT idfamily,display_name FROM family_has_member WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $family) {
            $families[$family[0]] = [
                'invitation' => false,
                'member' => true,
                'name' => $family[1],
                'request' => false,
            ];
        }

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
        $invitations = $this->getUserInvitations($iduser);
        if ($invitations)
            foreach ($invitations as $invitation) {
                $families[$invitation['idfamily']]['invitation'] = true;
                $families[$invitation['idfamily']]['accepted'] = $invitation['accepted'];
                $families[$invitation['idfamily']]['approved'] = $invitation['approved'];
            }
        $requests = $this->getUserRequests($iduser);
        if ($requests) foreach ($requests as $request) $families[$request]['request'] = true;

        $response = [];
        foreach ($families as $idfamily => $family) {
            $family['id'] = $idfamily;
            $family['invitation'] = $family['invitation'] ?? false;
            $family['code'] = bin2hex($this->getFamilyCode($idfamily));
            $family['admin'] = $family['admin'] ?? false;
            $family['member'] = $family['member'] ?? false;
            $family['name'] = $family['name'] ?? $this->getAvailableFamilyName($iduser, $idfamily, $this->getFamilyName($idfamily));
            $family['recipient'] = $family['recipient'] ?? false;
            $family['request'] = $family['request'] ?? false;
            $family['default'] = $idfamily === $default ? true : false;
            $response[] = $family;
        }
        $names = array_column($response, 'name');
        array_multisort($names, SORT_ASC, $response);
        unset($families, $names);
        return $response;
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
     * Returns pending familyInvitations for given user, false if none.
     * @return array|false
     */
    private function getUserInvitations($iduser)
    {
        $invitations = $this->db->request([
            'query' => 'SELECT idfamily,approved,accepted FROM family_invitation WHERE invitee = ?;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        return $invitations;
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
     * Returns families' ids where given user has pending request.
     * @return int[]
     */
    private function getUserRequests(int $iduser)
    {
        $requests = [];
        foreach ($this->db->request([
            'query' => 'SELECT idfamily FROM family_request WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $request) $requests[] = $request[0];
        return $requests;
    }

    private function getUsersTokens(array $users)
    {
        $users = implode(',', $users);
        $tokens = $this->db->request([
            'query' => 'SELECT token FROM user_has_fcm_token WHERE iduser IN (' . $users . ') GROUP BY token;',
            'array' => true,
        ]);
        if (empty($tokens)) return false;
        foreach ($tokens as &$token) $token = $token[0];
        return $tokens;
    }

    /**
     * Returns an array of subscriptions ids corresponding to given user & family.
     * @return int[]|false
     */
    private function getUserSubscriptionsForFamily(int $iduser, int $idfamily)
    {
        $subscriptions = $this->getUserSubscriptions($iduser);
        if (empty($subscriptions)) return false;
        $values = '(' . implode(',', $subscriptions) . ')';
        $familySubs = $this->db->request([
            'query' => 'SELECT idsubscription
            FROM subscription
            LEFT JOIN recipient USING (idrecipient)
            WHERE idsubscription IN ' . $values . ' AND idfamily = ?;',
            'type' => 'i',
            'content' => [$idfamily],
            'array' => true,
        ]);
        foreach ($familySubs as &$sub) $sub = $sub[0]; // icy.
        return $familySubs;
    }

    /**
     * Returns array of user's active subscriptions' ids, false if none.
     */
    private function getUserSubscriptions(int $iduser)
    {
        $subscriptions = $this->db->request([
            'query' => 'SELECT idsubscription FROM recurring_payment WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);
        if (empty($subscriptions)) return false;
        foreach ($subscriptions as &$subscription) $subscription = $subscription[0];
        return $subscriptions;
    }

    /**
     * Check if recipient has modifications for gazette
     * @param int $idgazette
     * @param int $idrecipient
     * 
     * @return bool
     */
    private function recipientHasGazetteMods(int $idgazette, int $idrecipient)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM gazette_page WHERE idgazette = ? AND idrecipient = ?;',
            'type' => 'ii',
            'content' => [$idgazette, $idrecipient],
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
     * Removes address if not linked to any recipient.
     */
    // private function removeAddress(int $idaddress)
    // {
    //     if (!empty($this->db->request([
    //         'query' => 'SELECT NULL FROM recipient WHERE idaddress = ? LIMIT 1;',
    //         'type' => 'i',
    //         'content' => [$idaddress],
    //     ]))) return false;
    //     $this->db->request([
    //         'query' => 'DELETE FROM address WHERE idaddress = ? LIMIT 1;',
    //         'type' => 'i',
    //         'content' => [$idaddress],
    //     ]);
    //     return true;
    // }

    /**
     * Removes a given comment.
     */
    private function removeComment(int $idfamily, int $idpublication, int $idcomment)
    {
        $this->db->request([
            'query' => 'DELETE FROM comment WHERE idcomment = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomment],
        ]);
        $members = $this->getFamilyMembers($idfamily);
        $data = [
            'comment' => $idcomment,
            'family' => $idfamily,
            'publication' => $idpublication,
            'type' => 5,
        ];
        $this->sendData($members, $data);
        return true;
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
            'query' => "SELECT idcomment,idpublication FROM comment WHERE idpublication IN ($publications) AND idcomment IN ($comments);",
        ]);
        if (empty($targetComments)) return;
        foreach ($targetComments as $comment) $this->removeComment($idfamily, $comment['idpublication'], $comment['idcomment']);
        return;
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
            foreach ($publications as $publication) $this->removePublication($idfamily, $publication);

        $this->db->request([
            'query' => 'DELETE FROM family WHERE idfamily = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idfamily],
        ]);

        return true;
    }

    private function removeGazette(int $idgazette)
    {
        // delete gazette linked objects if any
        $objects = $this->getGazetteObjectsIds($idgazette);
        if (!empty($objects)) {
            if (!empty($objects['cover_mini'])) $this->removeS3Object($objects['cover_mini']);
            if (!empty($objects['pdf'])) $this->removeS3Object($objects['pdf']);
            if (!empty($objects['cover_picture']) && !$this->checkPicturePublicationLinks($objects['cover_picture'])) $this->removeS3Object($objects['cover_picture']);
        }
        // delete gazette
        $this->db->request([
            'query' => 'DELETE FROM gazette WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ]);
        return true;
    }

    /**
     * Remove specific gazette modification
     */
    private function removeGazetteModification(int $idgazette, int $page_num, int $place)
    {
        $this->db->request([
            'query' => 'DELETE FROM gazette_page WHERE idgazette = ? AND manual = 1 AND page_num = ? AND place = ? LIMIT 1;',
            'type' => 'iii',
            'content' => [$idgazette, $page_num, $place],
        ]);
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
            'query' => "SELECT idcomment FROM comment WHERE idpublication IN ($publications);",
            'array' => true,
        ]);
        if (empty($comments)) return;
        foreach ($comments as &$comment) $comment = $comment[0];
        $comments = implode(',', $comments);
        $this->db->request([
            'query' => "DELETE FROM comment_has_like WHERE iduser = ? AND idcomment IN ($comments);",
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]);
        return;
    }

    private function removeMember(int $iduser, int $idfamily)
    {
        // TODO: if leaver is referent for recipient(s) AND not admin, set admin referent with notification
        $recipients = $this->getReferentRecipients($iduser, $idfamily);
        if ($recipients) {
            $admin = $this->getFamilyAdmin($idfamily);
            foreach ($recipients as $recipient) $this->setReferent($idfamily, $recipient, $admin);
        }

        // TODO: keep/archive member's family data for some time in case user joins again in the future ?
        $this->removePublicationsByFamilyMember($iduser, $idfamily);
        $this->removeCommentsByFamilyMember($iduser, $idfamily);
        $this->removeLikesByFamilyMember($iduser, $idfamily);

        // if family is default for user, set another family as default
        $default = $this->familyIsDefaultForUser($iduser, $idfamily) ? $this->setOtherFamilyDefault($iduser, $idfamily) : false;

        // remove user from family
        $this->db->request([
            'query' => 'DELETE FROM family_has_member WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);

        // send data to members
        $members = $this->getFamilyMembers($idfamily);
        $data = [
            'family' => $idfamily,
            'member' => $iduser,
            'type' => 8,
        ];
        $this->sendData([$iduser, ...$members], $data);

        // return true;
        return [
            'default' => $default,
            'family' => $this->userGetFamilyData($iduser, $idfamily),
        ];
    }

    /**
     * Removes publication and all data linked to it.
     */
    private function removePublication(int $idfamily, int $idpublication)
    {
        // get gazettes where publication is
        $gazettes = $this->getGazettesByPublication($idpublication);

        // comments
        $commentsid = $this->getPublicationCommentsId($idpublication);
        if (!empty($commentsid)) {
            $commentsid = implode(',', $commentsid);
            $this->db->request(['query' => "DELETE FROM comment WHERE idcomment IN ($commentsid);"]);
        }
        // pictures
        $pictures = $this->getPublicationPictures($idpublication);
        if (!empty($pictures)) foreach ($pictures as $picture) $this->removePublicationPicture($picture['idobject'], $idpublication);
        // text
        $this->removePublicationText($idpublication);
        // movies
        // $movies = $this->getPublicationMovies($idpublication);
        // if (!empty($movies)) foreach ($movies as $movie) $this->removePublicationMovie($movie);


        $this->db->request([
            'query' => 'DELETE FROM publication WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
        // update gazettes
        if ($gazettes) foreach ($gazettes as $idgazette) $this->updateGazette($idgazette);

        // send notifications
        $members = $this->getFamilyMembers($idfamily);
        $data = [
            'family' => $idfamily,
            'publication' => $idpublication,
            'type' => 2,
        ];
        $this->sendData($members, $data);

        return true;
    }

    private function removePublicationMovie(int $idmovie)
    {
        // TODO: should be tested if this usage is approved
        $idobject = $this->db->request([
            'query' => 'SELECT idobject FROM movie WHERE idmovie = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idmovie],
            'array' => true,
        ])[0][0];
        $this->s3->deleteObject($this->getS3ObjectKeyFromId($idobject));
        $this->db->request([
            'query' => 'DELETE FROM movie WHERE idmovie = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idmovie],
        ]);
        return true;
    }

    private function replaceGazetteCover(int $idgazette, int $idobject)
    {
        $publications = $this->getGazettePublications($idgazette);
        if (empty($publications)) return $this->db->request([
            'query' => 'UPDATE gazette SET cover_picture = NULL WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ]);
        $newPicture = null;
        for ($i = 0; $i < count($publications); $i++) {
            $pictures = $this->getPublicationPictures($publications[$i]);
            if (!empty($pictures)) {
                for ($p = 0; $p < count($pictures); $p++) {
                    if ($pictures[$p]['idobject'] !== $idobject) {
                        $newPicture = $pictures[$p]['idobject'];
                        break;
                    }
                }
            }
            if (!empty($newPicture)) break;
        }
        return empty($newPicture) ? $this->db->request([
            'query' => 'UPDATE gazette SET cover_picture = NULL WHERE idgazette = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idgazette],
        ]) : $this->db->request([
            'query' => 'UPDATE gazette SET cover_picture = ? WHERE idgazette = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$newPicture, $idgazette],
        ]);
    }

    private function removePublicationPicture(int $idobject, int $idpublication)
    {
        if ($this->checkPictureGazetteLink($idobject)) {
            $gazettes = $this->getGazettesByCoverPicture($idobject);
            foreach ($gazettes as $gazette) $this->replaceGazetteCover($gazette, $idobject);
        }
        $this->s3->deleteObject($this->getS3ObjectKeyFromId($idobject));
        $this->db->request([
            'query' => 'DELETE FROM s3 WHERE idobject = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ]);
        $this->reorderPublicationPictures($idpublication);
        return true;
    }

    private function removePublicationText(int $idpublication)
    {
        $this->db->request([
            'query' => 'DELETE FROM text WHERE idpublication = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idpublication],
        ]);
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
        foreach ($publications as $publication) $this->removePublication($idfamily, $publication[0]);
        return true;
    }

    private function removeRecipient(int $idfamily, int $idrecipient)
    {
        // TODO: remove gazettes from storage if recipient is not a user.

        $data = $this->db->request([
            'query' => 'SELECT display_name,avatar FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ])[0];
        $this->db->request([
            'query' => 'DELETE FROM recipient WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
        // $this->removeAddress($data['idaddress']); // automatic with foreign key
        if (!empty($data['avatar']) && !$this->checkAvatarUserLink($data['avatar'])) $this->removeS3Object($data['avatar']);
        // if (empty($data['iduser'])) $this->removeRecipientGazettes($idrecipient); // automatic with foreign key
        // $data = [
        //     'date' => date('Y-m-d H:i:s'),
        //     'family' => $idfamily,
        //     'recipient' => $idrecipient,
        //     'type' => 16,
        // ];
        // $members = $this->getFamilyMembers($idfamily,[$iduser]);
        // $this->sendNotification(
        //     $members,
        //     'Destinataire supprimé',
        //     'Le destinataire ' . $data['display_name'] . ' a été supprimé de la famille ' . $this->getFamilyName($idfamily) . '.',
        //     $data,
        // );
        return true;
    }

    private function removeRecipientAvatar($idrecipient)
    {
        $idobject = $this->getRecipientAvatar($idrecipient);
        if (!$idobject) return true;
        if (!$this->checkAvatarUserLink($idobject)) $this->removeS3Object($idobject);
        $this->db->request([
            'query' => 'UPDATE recipient SET avatar = NULL WHERE idrecipient = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idrecipient],
        ]);
        return $idobject;
    }

    private function removeRecipientGazettes($idrecipient)
    {
        foreach ($this->db->request([
            'query' => 'SELECT idgazette FROM gazette WHERE idrecipient = ?;',
            'type' => 'i',
            'content' => [$idrecipient],
            'array' => true,
        ]) as $gazette) $this->removeGazette($gazette[0]);

        // TODO: replace with multi object single request

        // create objects array [['key'=>string $key]]
        // s3->deleteObjects($objects)

        // then remove gazettes from db
        // $this->db->request([
        //     'query' => 'DELETE FROM gazette WHERE idrecipient = ?;',
        //     'type' => 'i',
        //     'content' => [$idrecipient],
        // ]);
        return true;
    }

    private function removeRecipientSubscription(int $iduser, int $idrecipient)
    {
        if (!$this->userIsAdminOfFamily($iduser, $this->getRecipientFamily($idrecipient)) && !$this->userIsReferent($iduser, $idrecipient)) return false;
        $subscription = $this->getRecipientSubscription($idrecipient);
        if (!$subscription) return false;
        $members = $this->getSubscriptionMembers($subscription['idsubscription']);
        if ($members) {
            foreach ($members as &$member) $member = $member[0];
            $members = implode(',', $members);
            $this->db->request([
                'query' => 'DELETE FROM recurring_payment WHERE idsubscription = ? AND iduser IN (' . $members . ');',
                'type' => 'i',
                'content' => [$subscription['idsubscription']],
            ]);
        }
        $this->db->request([
            'query' => 'DELETE FROM subscription WHERE idsubscription = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$subscription['idsubscription']],
        ]);
        return true;
    }

    /**
     * Removes object from s3 and db.
     * @return bool
     */
    private function removeS3Object(int $idobject)
    {
        $this->removeS3ObjectFromKey($this->getS3ObjectKeyFromId($idobject));
        $this->db->request([
            'query' => 'DELETE FROM s3 WHERE idobject = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idobject],
        ]);
        return true;
    }

    /**
     * Removes object from s3, leaves object in db.
     * @return bool
     */
    private function removeS3ObjectFromKey(string $key)
    {
        $this->s3->deleteObject($key);
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

    // private function removeFCMToken(string $token)
    // {
    //     return $this->db->request([
    //         'query' => 'DELETE FROM user_has_fcm_token WHERE token = ? LIMIT 1;',
    //         'type' => 's',
    //         'content' => [$token],
    //     ]);
    // }

    private function removeUnseenPublication(int $iduser, int $idpublication)
    {
        return $this->db->request([
            'query' => 'DELETE FROM unseen_publication WHERE iduser = ? AND idpublication = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idpublication],
        ]);
    }

    private function removeUserAvatar($iduser)
    {
        $idobject = $this->getUserAvatar($iduser);
        if (!$idobject) return false;
        $this->removeS3Object($idobject);
        $this->db->request([
            'query' => 'UPDATE user SET avatar = NULL WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        $this->db->request([
            'query' => 'UPDATE recipient SET avatar = NULL WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        return $idobject;
    }

    private function removeUserFamilySubscriptions(int $iduser, int $idfamily)
    {
        $subscriptions = $this->getUserSubscriptionsForFamily($iduser, $idfamily);
        if (empty($subscriptions)) return false;
        foreach ($subscriptions as $subscription)
            $this->db->request([
                'query' => 'DELETE FROM recurring_payment WHERE iduser = ? AND idsubscription = ?;',
                'type' => 'ii',
                'content' => [$iduser, $subscription],
            ]);
        return true;
    }

    private function removeUserSubscription(int $iduser, int $idsubscription)
    {
        return $this->db->request([
            'query' => 'DELETE FROM recurring_payment WHERE iduser = ? AND idsubsciption = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idsubscription],
        ]);
    }

    private function reorderPublicationPictures(int $idpublication)
    {
        $pictures = $this->db->request([
            'query' => 'SELECT idobject FROM publication_has_picture WHERE idpublication = ? ORDER BY place ASC;',
            'type' => 'i',
            'content' => [$idpublication],
            'array' => true,
        ]);
        if (!empty($pictures)) return false;
        // for each picture, update place
        $place = 1;
        foreach ($pictures as $picture) {
            $this->db->request([
                'query' => 'UPDATE publication_has_picture SET place = ? WHERE idobject = ? AND idpublication = ?;',
                'type' => 'iii',
                'content' => [$place++, $picture[0], $idpublication],
            ]);
        }
        return true;
    }

    private function sendData(array $users, array $data = [])
    {
        if (empty($users)) return;
        $tokens = $this->getUsersTokens($users);
        if (empty($tokens)) return;
        $data['notification'] = false;
        $this->serv->task([
            'tokens' => $tokens,
            'data' => $data,
        ]);
        return;
    }

    private function sendNotification(array $users, string $title, string $message, array $data = [])
    {
        if (empty($users)) return;
        $tokens = $this->getUsersTokens($users);
        if (empty($tokens)) return;
        $data['notification'] = true;
        $this->serv->task([
            'tokens' => $tokens,
            'title' => $title,
            'body' => $message,
            'data' => $data,
        ]);
        return;
    }

    /**
     * Add comment to publication, returns updated publication comments.
     * @return array
     */
    private function setComment(int $iduser, int $idfamily, int $idpublication, string $comment)
    {
        $this->db->request([
            'query' => 'INSERT INTO comment (iduser,idpublication,content) VALUES (?,?,?);',
            'type' => 'iis',
            'content' => [$iduser, $idpublication, $comment],
        ]);
        $comment = $this->db->request([
            'query' => 'SELECT idcomment,created FROM comment WHERE iduser = ? AND content = ? ORDER BY created DESC LIMIT 1;',
            'type' => 'is',
            'content' => [$iduser, $comment],
        ])[0];
        $author = $this->getPublicationAuthor($idpublication);
        $members = $this->getFamilyMembers($idfamily, [$iduser, $author]);
        $title = 'Nouveau commentaire';
        $data = [
            'author' => $iduser,
            'date' => $comment['created'],
            'family' => $idfamily,
            'comment' => $comment['idcomment'],
            'publication' => $idpublication,
            'type' => 4,
        ];
        $this->setUnseenComment([$author, ...$members], $comment['idcomment']);
        $this->sendData([$iduser], $data);
        if (!empty($members))
            $this->sendNotification(
                $members,
                $title,
                $this->getUserName($iduser)['first_name'] . ' a commenté une publication',
                $data,
            );
        if ($author !== $iduser)
            $this->sendNotification(
                [$author],
                $title,
                $this->getUserName($iduser)['first_name'] . ' a commenté votre publication',
                $data,
            );
        return $comment['idcomment'];
    }

    private function getCommentsPublication(int $idcomment)
    {
        return $this->db->request([
            'query' => 'SELECT idpublication FROM comment WHERE idcomment = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomment],
            'array' => true,
        ])[0][0];
    }

    private function setCommentLike(int $iduser, int $idfamily, int $idcomment)
    {
        $like = $this->userLikesComment($iduser, $idcomment);
        $exclude = [];
        $idpublication = $this->getCommentsPublication($idcomment);
        $data = [
            'comment' => $idcomment,
            'family' => $idfamily,
            'likes' => $this->getCommentLikesCount($idcomment) + ($like ? -1 : 1),
            'publication' => $idpublication,
            'type' => 6,
            'user' => $iduser,
            'value' => !$like,
        ];
        if ($like)
            $this->db->request([
                'query' => 'DELETE FROM comment_has_like WHERE iduser = ? AND idcomment = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$iduser, $idcomment],
            ]);
        else {
            $this->db->request([
                'query' => 'INSERT INTO comment_has_like (idcomment,iduser) VALUES (?,?);',
                'type' => 'ii',
                'content' => [$idcomment, $iduser],
            ]);
            $author = $this->getCommentsAuthor($idcomment);
            if ($author !== $iduser) {
                $exclude[] = $author;
                $this->sendNotification(
                    [$author],
                    'Nouveau like',
                    $this->getUserName($iduser)['first_name'] . ' a aimé votre commentaire',
                    $data,
                );
            }
        }
        $members = $this->getFamilyMembers($idfamily, $exclude);
        $this->sendData($members, $data);
        return ['liked' => !$like, 'likes' => $this->getCommentLikesCount($idcomment)];
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

    /**
     * Create and update gazette(s) for family according to subscription modifications.
     */
    private function setGazettes(int $idfamily, string $date)
    {
        // get print date
        $printDate = $this->getPrintDate($date);
        print("@@@ print date: " . $printDate . PHP_EOL);
        // for each recipient, create or update gazette
        foreach ($this->getFamilyRecipients($idfamily) as $recipient) {
            // get gazette id
            $idgazette = $this->db->request([
                'query' => 'SELECT idgazette FROM gazette WHERE idrecipient = ? AND print_date = ? LIMIT 1;',
                'type' => 'is',
                'content' => [$recipient, $printDate],
                'array' => true,
            ])[0][0] ?? null;
            // if gazette doesn't exist, create it
            if (empty($idgazette)) {
                // get recipient's gazette type
                $gazetteType = 1;
                $subscription = $this->getRecipientSubscription($recipient);
                if (!empty($subscription)) {
                    $gazetteType =  $this->db->request([
                        'query' => 'SELECT idgazette_type FROM subscription_type WHERE idsubscription_type = ? LIMIT 1;',
                        'type' => 'i',
                        'content' => [$subscription['idsubscription_type']],
                        'array' => true,
                    ])[0][0];
                }
                $this->db->request([
                    'query' => 'INSERT INTO gazette (idrecipient,print_date,type) VALUES (?,?,?);',
                    'type' => 'isi',
                    'content' => [$recipient, $printDate, $gazetteType],
                ]);
                $idgazette = $this->db->request([
                    'query' => 'SELECT idgazette FROM gazette WHERE idrecipient = ? AND print_date = ? LIMIT 1;',
                    'type' => 'is',
                    'content' => [$recipient, $printDate],
                    'array' => true,
                ])[0][0];
            }
            // fill or update gazette
            $this->updateGazette($idgazette);
        }
    }

    /**
     * Sets another family as default for user and returns its id.
     * @return int|null
     */
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
        // if null, select next family as recipient (shouldn't be possible, as user can't be recipient only for now).
        // if (empty($nextFamily)) $nextFamily = $this->db->request([
        //     'query' => 'SELECT idfamily FROM recipient WHERE iduser = ? AND idfamily != ? LIMIT 1;',
        //     'type' => 'ii',
        //     'content' => [$iduser, $idfamily],
        //     'array' => true,
        // ])[0][0] ?? null;
        // if user has other family, set as default family.
        if (!empty($nextFamily)) $this->db->request([
            'query' => 'UPDATE default_family SET idfamily = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$nextFamily, $iduser],
        ]);
        else $this->db->request([
            'query' => 'DELETE FROM default_family WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        return $nextFamily ?? false;
    }

    private function setPublication(int $iduser, int $idfamily, array $parameters)
    {
        $into = '';
        $values = '';
        $type = '';
        $content = [];
        $text = true;

        if (strlen($parameters['text']) < 510) {
            $text = false;
            $into .= ',description';
            $values .= ',?';
            $type .= 's';
            $content[] = $parameters['text'];
        }
        if ($parameters['layout'][0] === 'f') {
            $into .= ',full_page';
            $values .= ',?';
            $type .= 'i';
            $content[] = 1;
        }
        if (!empty($parameters['recipients'])) {
            $into .= ',global';
            $values .= ',?';
            $type .= 'i';
            $content[] = 0;
        }
        $into .= ',idlayout';
        $values .= ',?';
        $type .= 'i';
        $content[] = $this->getLayoutFromString($parameters['layout']);

        $this->db->request([
            'query' => 'INSERT INTO publication (author,idfamily,private,title' . $into . ') VALUES (?,?,?,?' . $values . ');',
            'type' => 'iiis' . $type,
            'content' => [$iduser, $idfamily, $parameters['private'] ? 1 : 0, $parameters['title'], ...$content],
        ]);

        $publication = $this->db->request([
            'query' => 'SELECT idpublication,created FROM publication WHERE author = ? AND idfamily = ? ORDER BY created DESC LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ])[0];

        if ($text) $this->setPublicationText($publication['idpublication'], $parameters['text']);

        if (!empty($parameters['images'])) {
            foreach ($parameters['images'] as $image) {
                $this->setPublicationPicture($publication['idpublication'], $image);
            }
        }
        // update gazette
        $this->setGazettes($idfamily, $publication['created']);
        // set recipients' publication
        if (!empty($parameters['recipients'])) {
            $insert = [];
            foreach ($parameters['recipients'] as $recipient) $insert[] = '(' . $recipient . ',' . $publication['idpublication'] . ')';
            $insert = implode(',', $insert);
            $this->db->request([
                'query' => 'INSERT INTO recipient_has_publication (idrecipient,idpublication) VALUES ' . $insert . ';',
            ]);
        }
        // send notifications
        $data = [
            'date' => $publication['created'],
            'family' => $idfamily,
            'publication' => $publication['idpublication'],
            'type' => 1,
            'author' => $iduser,
        ];
        $this->sendData([$iduser], $data);
        $members = $this->getFamilyMembers($idfamily, [$iduser]);
        if (!empty($members)) {
            $this->setUnseenPublication($members, $publication['idpublication']);
            $this->sendNotification(
                $members,
                'Nouvelle publication',
                $this->getUserName($iduser)['first_name'] . ' a ajouté une publication',
                $data,
            );
        }
        return $publication['idpublication'];
    }

    private function setPublicationLike(int $iduser, int $idfamily, int $idpublication)
    {
        $like = $this->userLikesPublication($iduser, $idpublication);
        $exclude = [];
        $data = [
            'date' => date('Y-m-d H:i:s'),
            'family' => $idfamily,
            'likes' => $this->getPublicationLikesCount($idpublication) + ($like ? -1 : 1),
            'publication' => $idpublication,
            'type' => 3,
            'user' => $iduser,
            'value' => !$like,
        ];
        if ($like)
            $this->db->request([
                'query' => 'DELETE FROM publication_has_like WHERE iduser = ? AND idpublication = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$iduser, $idpublication],
            ]);
        else {
            $this->db->request([
                'query' => 'INSERT INTO publication_has_like (idpublication,iduser) VALUES (?,?);',
                'type' => 'ii',
                'content' => [$idpublication, $iduser],
            ]);
            $author = $this->getPublicationAuthor($idpublication);
            if ($author !== $iduser) {
                $exclude[] = $author;
                $this->sendNotification(
                    [$author],
                    'Nouveau like',
                    $this->getUserName($iduser)['first_name'] . ' a aimé votre publication',
                    $data,
                );
            }
        }
        $members = $this->getFamilyMembers($idfamily, $exclude);
        if (!empty($members))
            $this->sendData($members, $data);
        return ['liked' => !$like, 'likes' => $this->getPublicationLikesCount($idpublication)];
    }

    /**
     * Sets or updates publication picture.
     */
    private function setPublicationPicture(int $idpublication, array $picture)
    {
        empty($this->db->request([
            'query' => 'SELECT NULL FROM publication_has_picture WHERE idpublication = ? AND idobject = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idpublication, $picture['id']],
        ])) ?
            $this->db->request([
                'query' => 'INSERT INTO publication_has_picture (idpublication,idobject,place,title) VALUES (?,?,?,?);',
                'type' => 'iiis',
                'content' => [$idpublication, $picture['id'], $picture['place'], $picture['title'] ?? ''],
            ]) : $this->db->request([
                'query' => 'UPDATE publication_has_picture SET place = ?, title = ? WHERE idpublication = ? AND idobject = ? LIMIT 1;',
                'type' => 'isii',
                'content' => [$picture['place'], $picture['title'] ?? '', $idpublication, $picture['id']],
            ]);
        return true;
    }

    /**
     * Sets or updates publication text.
     * @return bool
     */
    private function setPublicationText(int $idpublication, string $content)
    {
        $text = $this->getPublicationText($idpublication);
        $text ?
            $this->db->request([
                'query' => 'UPDATE text SET content = ? WHERE idpublication = ? LIMIT 1;',
                'type' => 'si',
                'content' => [$content, $idpublication],
            ]) :
            $this->db->request([
                'query' => 'INSERT INTO text (idpublication,content) VALUES (?,?);',
                'type' => 'is',
                'content' => [$idpublication, $content],
            ]);
        return true;
    }

    private function setReferent(int $idfamily, int $idrecipient, int $idreferent)
    {
        $oldReferent = $this->getRecipientReferent($idrecipient);
        $this->db->request([
            'query' => 'UPDATE recipient SET referent = ? WHERE idrecipient = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idreferent, $idrecipient],
        ]);
        $data = [
            'date' => date('Y-m-d H:i:s'),
            'family' => $idfamily,
            'recipients' => [$idrecipient],
            'referent' => $idreferent,
            'type' => 17,
        ];
        // send notification to new referent
        $recipientName = $this->getRecipientDisplayName($idrecipient);
        $de = preg_match('/^[aeiouy]/i', $recipientName) ? 'd\'' : 'de ';
        $this->sendNotification(
            [$idreferent],
            'Nouveau référent',
            'Vous êtes désormais référent ' . $de . $recipientName . '.',
            $data,
        );
        $this->sendNotification(
            [$oldReferent],
            'Nouveau référent',
            'Vous n\'êtes désormais plus le référent ' . $de . $recipientName . '.',
            $data,
        );
        // send data to members
        $members = $this->getFamilyMembers($idfamily, [$idreferent, $oldReferent]);
        if (!empty($members))
            $this->sendData(
                $members,
                $data,
            );

        return true;
    }

    /**
     * Set object in db and returns its ID.
     * @return int Object's ID.
     */
    private function setS3Object(array $object)
    {
        $idobject = $this->getS3ObjectIdFromKey($object['key']);
        if ($idobject) return $idobject;
        $into = '';
        $values = '';
        $type = '';
        $content = [];
        if (!empty($object['family'])) {
            $into .= ',family';
            $values .= ',?';
            $type .= 'i';
            $content[] = $object['family'];
        }
        if (!empty($object['owner'])) {
            $into .= ',owner';
            $values .= ',?';
            $type .= 'i';
            $content[] = $object['owner'];
        }
        $this->db->request([
            'query' => 'INSERT INTO s3 (name,ext' . $into . ') VALUES (?,?' . $values . ');',
            'type' => 'ss' . $type,
            'content' => [$object['binKey'], $object['ext'], ...$content],
        ]);
        return $idobject = $this->getS3ObjectIdFromKey($object['binKey']);
    }

    private function setUnseenComment(array $users, int $idcomment)
    {
        $values = [];
        foreach ($users as $user) $values[] = '(' . $user . ',' . $idcomment . ')';
        $values = implode(',', $values);
        return $this->db->request([
            'query' => 'INSERT INTO unseen_comment (iduser,idcomment) VALUES ' . $values . ';',
        ]);
    }

    private function setUnseenPublication(array $users, int $idpublication)
    {
        $values = [];
        foreach ($users as $user) $values[] = '(' . $user . ',' . $idpublication . ')';
        $values = implode(',', $values);
        return $this->db->request([
            'query' => 'INSERT INTO unseen_publication (iduser,idpublication) VALUES ' . $values . ';',
        ]);
    }

    private function storeS3Object(int $iduser, string $key)
    {
        $newObject = $this->s3->move($key);
        $newObject['owner'] = $iduser;
        return $this->setS3Object($newObject);
    }

    private function testerProcess(int $iduser)
    {
        print('#### Tester process for user ' . $iduser . ' ####' . PHP_EOL);
        $bots = $this->db->request([ // get bots
            'query' => 'SELECT iduser FROM user WHERE last_name = ? LIMIT 10;',
            'type' => 's',
            'content' => ['buddy'],
            'array' => true,
        ]);
        foreach ($bots as &$bot) $bot = $bot[0];
        $families = $this->db->request([ // add user to the 4 test families
            'query' => 'SELECT idfamily FROM family WHERE name LIKE ? LIMIT 4;',
            'type' => 's',
            'content' => ["%test%"],
            'array' => true,
        ]);
        foreach ($families as &$family) $family = $family[0];
        $this->addUserToFamily($iduser, $families[0]); // add user as member of first family 
        $this->addUserToFamily($iduser, $families[1]); // add user as member and recipient of second family
        $this->createRecipient($iduser, $families[1], [
            'display_name' => $this->getAvailableRecipientName($families[1], 'user ' . $iduser),
            'birthdate' => '2023-01-06',
            'address' => [
                'name' => 'User Tester',
                'phone' => '+33612345678',
                'field1' => '97 Gazet\' Street',
                'postal' => '98002',
                'city' => 'Gazet City',
                'state' => 'Gazet Highlands',
                'country' => 'Gazet Republic',
            ],
            'self' => true,
        ]);
        $this->familyEmailInvite($bots[0], $families[2], $this->getUserEmail($iduser)); // invite user in third family
        $this->userUseFamilyCode($iduser, $this->getFamilyCode($families[3])); // user request into last family


        $this->createFamily($iduser, 'Test'); // create a family
        $idfamily = $this->db->request([
            'query' => 'SELECT idfamily FROM family WHERE admin = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0];

        $i = 0;
        while ($i < 6) $this->addUserToFamily($bots[$i++], $idfamily); // add 6 more users to family
        $this->familyEmailInvite($iduser, $idfamily, 'test' . $i++ + 1 . '@buddy.com'); // admin invite the next user
        $this->familyEmailInvite($bots[0], $idfamily, 'test' . $i++ + 1 . '@buddy.com'); // member invite the next user
        while ($i < 10) $this->userUseFamilyCode($bots[$i++], $this->getFamilyCode($idfamily)); // the 2 last users request to join family
        $this->createRecipient($iduser, $idfamily, [ // create a recipient by the admin
            'display_name' => 'Recipient ' . $i - 9,
            'birthdate' => '2023-01-06',
            'address' => [
                'name' => 'Lord Recipient ' . $i - 9,
                'phone' => '+336123456' . $i++ + 1,
                'field1' => '97 Gazet\' Street',
                'postal' => '98002',
                'city' => 'Gazet City',
                'state' => 'Gazet Highlands',
                'country' => 'Gazet Republic',
            ],
        ]);
        $this->createRecipient($bots[$i - 11], $idfamily, [ // create a recipient by a member
            'display_name' => 'Recipient ' . $i - 9,
            'birthdate' => '2023-01-06',
            'address' => [
                'name' => 'Lord Recipient ' . $i - 9,
                'phone' => '+336123456' . $i++ + 1,
                'field1' => '97 Gazet\' Street',
                'postal' => '98002',
                'city' => 'Gazet City',
                'state' => 'Gazet Highlands',
                'country' => 'Gazet Republic',
            ],
        ]);
    }

    private function updateComment(int $idcomment, string $content)
    {
        $this->db->request([
            'query' => 'UPDATE comment SET content = ? WHERE idcomment = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$content, $idcomment],
        ]);
    }

    /**
     * Update gazette with publications
     */
    private function updateGazette($idgazette)
    {
        // check if gazette is already printed
        if ($this->getGazettePrintStatus($idgazette)) return false;
        // get gazette data
        $gazette = $this->getGazetteData($idgazette);
        // get gazette family
        $idfamily = $this->getRecipientFamily($gazette['idrecipient']);
        // get gazette_type data
        $type = $this->getGazetteTypeData($gazette['type']);

        // remove gazettes pages // TODO: maybe keep modifications ?
        $this->db->request([
            'query' => 'DELETE FROM gazette_page WHERE idgazette = ?;',
            'type' => 'i',
            'content' => [$idgazette],
        ]);
        // remove gazette cover writers
        $this->db->request([
            'query' => 'DELETE FROM gazette_writer WHERE idgazette = ?;',
            'type' => 'i',
            'content' => [$idgazette],
        ]);

        // get publications
        $publications = $this->db->request([
            'query' => 'SELECT idpublication,author,idlayout,created,full_page,global
                FROM publication
                WHERE idfamily = ?
                AND created >= DATE_SUB(?, INTERVAL 1 MONTH)
                AND created < ?
                ORDER BY created ASC;',
            'type' => 'iss',
            'content' => [$idfamily, $gazette['print_date'], $gazette['print_date']],
        ]);
        // if no publications at all, delete gazette
        if (empty($publications)) return $this->removeGazette($idgazette);
        $halfpages = 0;
        // remove publications not for this recipient, get likes and count half pages
        $pub = 0;
        while ($pub < count($publications)) {
            if ($publications[$pub]['global'] === 0 && empty($this->db->request([
                'query' => 'SELECT NULL FROM recipient_has_publication WHERE idrecipient = ? AND idpublication = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$gazette['idrecipient'], $publications[$pub]['idpublication']],
            ]))) array_splice($publications, $pub, 1);
            else {
                $publications[$pub]['likes'] = $this->getPublicationLikesCount($publications[$pub]['idpublication']);
                $halfpages += $publications[$pub]['full_page'] === 1 ? 2 : 1;
                $pub++;
            }
        }
        unset($pub);
        // get total publications half pages count and publications likes
        $gazetteHalfpages = ($type - 2) * 2;
        $overflow = false;
        // sort by likes
        usort($publications, function ($a, $b) {
            return $b['likes'] - $a['likes'];
        });
        // sort by specific
        usort($publications, function ($a, $b) {
            return $b['global'] - $a['global'];
        });
        // if $halfpages > $gazetteHalfpages, remove extra publications
        if ($halfpages > $gazetteHalfpages) {
            $overflow = true;
            // extract the right amount of publications
            $hp = 0;
            for ($pub = 0; $pub < count($publications); $pub++) {
                $size = $publications[$pub]['full_page'] === 1 ? 2 : 1;
                if ($hp + $size < $gazetteHalfpages) {
                    // if (count($insert) < 4 && !in_array($publications[$pub]['author'], $insert)) $insert[] = $publications[$pub]['author'];
                    $hp += $size;
                    if ($hp === $gazetteHalfpages) {
                        array_splice($publications, $pub + 1);
                        break;
                    }
                } else if ($hp + $size > $gazetteHalfpages) {
                    array_splice($publications, $pub--, 1);
                }
            }
        }
        // set writers & cover picture
        $writers = [];
        $cover_picture = $gazette['cover_picture'];
        for ($pub = 0; $pub < count($publications); $pub++) {
            // if cover picture is null and publication has image, set it as cover picture
            if (empty($cover_picture)) {
                $pictures = $this->getPublicationPictures($publications[$pub]['idpublication']);
                if (!empty($pictures)) {
                    $cover_picture = $pictures[0]['idobject'];
                    $this->db->request([
                        'query' => 'UPDATE gazette SET cover_picture = ? WHERE idgazette = ?;',
                        'type' => 'ii',
                        'content' => [$cover_picture, $idgazette],
                    ]);
                }
            }
            if (!in_array($publications[$pub]['author'], $writers)) $writers[] = $publications[$pub]['author'];
            if (count($writers) === 4 && !empty($cover_picture)) break;
        }
        // set cover writers while sorted
        foreach ($writers as &$writer) $writer = '(' . $idgazette . ',' . $writer . ')';
        $writers = implode(',', $writers);
        $this->db->request([
            'query' => 'INSERT INTO gazette_writer (idgazette,iduser) VALUES ' . $writers . ';',
        ]);
        unset($writers);
        // reorder by date
        usort($publications, function ($a, $b) {
            return strtotime($a['created']) - strtotime($b['created']);
        });
        // fill gaps induced by publications sizes
        $half = 0;
        for ($pub = 0; $pub < count($publications); $pub++) {
            if ($publications[$pub]['full_page'] === 1) {
                if ($half === 1) {
                    // retrieve next size 1 publication and put it before current size 2 publication
                    for ($nextPub = $pub + 1; $nextPub < count($publications); $nextPub++) {
                        if ($publications[$nextPub]['full_page'] === 0) {
                            // Insert the item at the new index
                            array_splice($publications, $pub, 0, [$publications[$nextPub]]);
                            // Remove the item from the original index
                            array_splice($publications, $nextPub + 1, 1);
                            break;
                        }
                    }
                    $half = 0;
                }
            } else $half = $half === 1 ? 0 : 1;
        }
        // fill gazette with publications
        $page = 1;
        $place = 1;
        foreach ($publications as $publication) {
            if ($page < $type) {
                if ($publication['full_page'] === 1 && $place === 2) {
                    $place = 1;
                    $page++;
                }
                $this->db->request([
                    'query' => 'INSERT INTO gazette_page (idgazette,page_num,place,idpublication) VALUES (?,?,?,?);',
                    'type' => 'iiii',
                    'content' => [$idgazette, $page, $place, $publication['idpublication']],
                ]);
                if ($publication['full_page'] === 0) {
                    if ($place === 1) {
                        $place = 2;
                    } else {
                        $place = 1;
                        $page++;
                    }
                } else {
                    $place = 1;
                    $page++;
                }
            }
        }

        // check for conflicting recipient modifications after update (useless if mods are removed on update)
        // $this->checkGazetteModifications($idgazette);

        // TODO: let admin/referent know of gazette update & overflow
        if ($overflow) {
            // TODO: notification to admin/referent if gazette overflows, and suggest to take a bigger gazette type
        }

        // generate pdf
        // TODO: $this->serv->task() to avoid blocking
        $this->generatePdf($idgazette);
    }

    /**
     * Update member's data and returns uppdated data.
     * @return array
     */
    private function userUpdateMember(int $iduser, array $parameters)
    {
        print('@@@ updateMember: ' . $iduser . PHP_EOL);
        var_dump($parameters);
        $response = [];
        // if member's data modifed and is user
        if (!empty($parameters['user']['id']) && $iduser === $parameters['user']['id']) {
            $response['user'] = $this->updateUser($iduser, $parameters['user']);
            $response['user']['id'] = $parameters['user']['id'];
        }
        // if recipient's data modified
        if (!empty($parameters['recipient'])) {
            // create or update recipient
            if ($parameters['recipient']['id'] == null)
                $response['recipient'] = $this->createRecipient($iduser, $parameters['idfamily'], $parameters['recipient']);
            else {
                $response['recipient'] = $this->updateRecipient($iduser, $parameters['recipient']['id'], $parameters['recipient']);
                if (!$response['recipient']) {
                    print('updateMember: FALSE' . PHP_EOL);
                    return false;
                }
            }
        } else if (!empty($parameters['idfamily'])) {
            $recipientId = $this->userIsRecipientOfFamily($iduser, $parameters['idfamily']);
            if ($recipientId) {
                $response['recipient'] = $this->getRecipientData($recipientId);
            }
        }
        print('@@@ updateMember response: ' . $iduser . PHP_EOL);
        var_dump($response);
        return $response;
    }

    private function updatePublication(int $idpublication, array $parameters)
    {
        if ($parameters['text'] !== null || $parameters['title'] !== null || !empty($parameters['layout']) || $parameters['private'] !== null) {
            $set = [];
            $type = '';
            $content = [];
            if ($parameters['text'] !== null) {
                $set[] = 'description = ?';
                $type .= 's';
                if (strlen($parameters['text'] < 500)) {
                    $this->removePublicationText($idpublication);
                    $content[] = $parameters['text'];
                } else {
                    $content[] = '';
                    $this->setPublicationText($idpublication, $parameters['text']);
                }
            }
            if ($parameters['title'] !== null) {
                $set[] = 'title = ?';
                $type .= 's';
                $content[] = $parameters['title'];
            }
            if (!empty($parameters['layout'])) {
                $set[] = 'idlayout = ?, full_page = ?';
                $type .= 'si';
                $content[] = $this->getLayoutFromString($parameters['layout']);
                $content[] = $parameters['layout'][0] === 'f' ? 1 : 0;
            }
            if ($parameters['private'] !== null) {
                $set[] = 'private = ?';
                $type .= 'i';
                $content[] = $parameters['private'] ? 1 : 0;
            }
            $set = implode(', ', $set);
            $this->db->request([ // update publication
                'query' => 'UPDATE publication SET ' . $set . ' WHERE idpublication = ? LIMIT 1;',
                'type' => $type . 'i',
                'content' => [...$content, $idpublication],
            ]);
        }
        // TODO: if journal, remove all images but first place
        if ($parameters['layout'][1] === 'j') {
            if ($parameters['images'] !== null) {
                // remove from $parameters['images'] all images but first place
                $images = [];
                foreach ($parameters['images'] as $index => $image) {
                    if ($image['place'] !== 1) {
                        $this->removeS3Object($image['id']);
                        $images[] = $index;
                    }
                }
                foreach ($images as $index) unset($parameters['images'][$index]);
            } else {
                // remove all images but first place in db
                // get images from db
                $pictures = $this->getPublicationPictures($idpublication);
                foreach ($pictures as $picture) {
                    if ($picture['place'] !== 1) $this->removePublicationPicture($picture['idobject'], $idpublication);
                }
            }
        }
        // images update
        if ($parameters['images'] !== null) {
            $pictures = $this->getPublicationPictures($idpublication); // get publication images
            // if no images in parameters, remove all images
            if (empty($parameters['images'])) foreach ($pictures as $picture) $this->removePublicationPicture($picture['idobject'], $idpublication);
            // else apply modifications
            else {
                $ids = [];
                foreach ($parameters['images'] as $image) {
                    $ids[] = $image['id'];
                    $this->setPublicationPicture($idpublication, $image);
                }
                foreach ($pictures as $picture) // remove images not in parameters
                {
                    if (!in_array($picture['idobject'], $ids, false)) {
                        $this->removePublicationPicture($picture['idobject'], $idpublication);
                    }
                }
            }
        }
        // gazettes update
        $gazettes = $this->getGazettesByPublication($idpublication);
        if (!empty($gazettes)) foreach ($gazettes as $gazette) $this->updateGazette($gazette);
    }

    private function updateRecipient(int $iduser, int $idrecipient, array $parameters)
    {
        if (!$this->userIsAdminOfFamily($iduser, $this->getRecipientFamily($idrecipient)) && !$this->userIsReferent($iduser, $idrecipient) && !$this->userIsRecipient($iduser, $idrecipient)) {
            print('updateRecipient: FALSE' . PHP_EOL);
            return false;
        }
        if (count($parameters) > 2) {
            $set = [];
            $type = '';
            $content = [];
            if (!empty($parameters['display_name'])) {
                $set[] = 'display_name = ?';
                $type .= 's';
                $content[] = $parameters['display_name'];
            }
            if (!empty($parameters['birthdate'])) {
                $set[] = 'birthdate = ?';
                $type .= 's';
                $content[] = $parameters['birthdate'];
            }
            $set = implode(',', $set);
            $this->db->request([
                'query' => 'UPDATE recipient SET ' . $set . ' WHERE idrecipient = ? LIMIT 1;',
                'type' => $type . 'i',
                'content' => [...$content, $idrecipient],
            ]);
        }
        if (!empty($parameters['address'])) $this->updateRecipientAddress($iduser, $idrecipient, $parameters['address']);

        return $this->getRecipientData($idrecipient);
    }

    private function updateRecipientAddress(int $iduser, int $idrecipient, array $address)
    {
        if (!$this->userIsReferent($iduser, $idrecipient)) return false;
        // $idaddress = $this->getRecipientAddressId($idrecipient);
        $set = [];
        $type = '';
        $content = [];
        if (!empty($address['name'])) {
            $set[] = 'name = ?';
            $type .= 's';
            $content[] = $address['name'];
        }
        if (!empty($address['phone'])) {
            $set[] = 'phone = ?';
            $type .= 's';
            $content[] = $address['phone'];
        }
        if (!empty($address['field1'])) {
            $set[] = 'field1 = ?';
            $type .= 's';
            $content[] = $address['field1'];
        }
        if (!empty($address['field2'])) {
            $set[] = 'field2 = ?';
            $type .= 's';
            $content[] = $address['field2'];
        }
        if (!empty($address['field3'])) {
            $set[] = 'field3 = ?';
            $type .= 's';
            $content[] = $address['field3'];
        }
        if (!empty($address['postal'])) {
            $set[] = 'postal = ?';
            $type .= 's';
            $content[] = $address['postal'];
        }
        if (!empty($address['city'])) {
            $set[] = 'city = ?';
            $type .= 's';
            $content[] = $address['city'];
        }
        if (!empty($address['state'])) {
            $set[] = 'state = ?';
            $type .= 's';
            $content[] = $address['state'];
        }
        if (!empty($address['country'])) {
            $set[] = 'country = ?';
            $type .= 's';
            $content[] = $address['country'];
        }
        $set = implode(',', $set);
        $this->db->request([
            'query' => 'UPDATE address SET ' . $set . ' WHERE idrecipient = ? LIMIT 1;',
            'type' => $type . 'i',
            'content' => [...$content, $idrecipient],
        ]);
        return true;
    }

    private function updateRecipientAvatar(int $iduser, int $idrecipient, string $key)
    {
        if (!$this->userIsReferent($iduser, $idrecipient)) return false;
        $newObject = $this->s3->move($key);
        $newObject['owner'] = $iduser;
        $idobject = $this->getRecipientAvatar($idrecipient);
        if ($idobject) $this->removeS3Object($idobject);
        $idobject = $this->setS3Object($newObject);
        $this->db->request([
            'query' => 'UPDATE recipient SET avatar = ? WHERE idrecipient = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idobject, $idrecipient],
        ]);
        return $idobject;
    }

    private function updateS3Object(int $idobject, array $object)
    {
        $this->removeS3ObjectFromKey($this->getS3ObjectKeyFromId($idobject));
        $this->db->request([
            'query' => 'UPDATE s3 SET name = ?,ext = ? WHERE idobject = ? LIMIT 1;',
            'type' => 'ssi',
            'content' => [$object['binKey'], $object['ext'], $idobject],
        ]);
        return $idobject;
    }

    private function updateUser(int $iduser, array $parameters)
    {
        // TODO: change email or phone with verification process
        $set = [];
        $type = '';
        $content = [];
        if (!empty($parameters['first_name'])) {
            $set[] = 'first_name = ?';
            $type .= 's';
            $content[] = $parameters['first_name'];
        }
        if (!empty($parameters['last_name'])) {
            $set[] = 'last_name = ?';
            $type .= 's';
            $content[] = $parameters['last_name'];
        }
        if (!empty($parameters['birthdate'])) {
            $set[] = 'birthdate = ?';
            $type .= 's';
            $content[] = $parameters['birthdate'];
        }
        $set = implode(',', $set);
        $this->db->request([
            'query' => 'UPDATE user SET ' . $set . ' WHERE iduser = ? LIMIT 1;',
            'type' => $type . 'i',
            'content' => [...$content, $iduser],
        ]);
        if ($parameters['new']) $this->db->request([
            'query' => 'DELETE FROM new_user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]);
        return $this->getUserData($iduser);
    }

    private function updateUserAvatar(int $iduser, string $key)
    {
        $newObject = $this->s3->move($key);
        $newObject['owner'] = $iduser;
        $oldObject = $this->getUserAvatar($iduser);
        $idobject = $this->setS3Object($newObject);
        $this->db->request([
            'query' => 'UPDATE user SET avatar = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idobject, $iduser],
        ]);
        $this->db->request([
            'query' => 'UPDATE recipient SET avatar = ? WHERE iduser = ?;',
            'type' => 'ii',
            'content' => [$idobject, $iduser],
            'array' => true,
        ]);
        if ($oldObject) $this->removeS3Object($oldObject);
        return $idobject;
    }

    private function updateUserEmail(int $iduser, string $email)
    {
        // TODO: code update user email
    }

    private function updateUserPhone(int $iduser, string $phone)
    {
        // TODO: code update user phone
    }

    /**
     * Sets new iduser in email familyInvitations where email corresponds.
     */
    private function updateUserEmailInvitation(int $iduser, string $email)
    {
        $this->db->request([
            'query' => 'UPDATE family_invitation SET invitee = ? WHERE email = ?;',
            'type' => 'is',
            'content' => [$iduser, $email],
        ]);
    }

    /**
     * If familyInvitation approved, finalizes it, else sets it accepted.
     */
    private function userAcceptsInvitation($iduser, $idfamily)
    {
        if (!$this->familyInvitationExist($iduser, $idfamily)) return false; // if invitation doesn't exist, false
        if ($this->userIsMemberOfFamily($iduser, $idfamily)) { // if already member, remove invitation
            $this->familyInvitationRemove($iduser, $idfamily);
            return false;
        }
        if (!$this->familyInvitationIsApproved($iduser, $idfamily)) {
            $this->db->request([ // else set accepted
                'query' => 'UPDATE family_invitation SET accepted = 1 WHERE idfamily = ? AND invitee = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$idfamily, $iduser],
            ]);
            $data = [
                'family' => $idfamily,
                'type' => 12,
                'user' => $iduser,
            ];
            $admin = $this->getFamilyAdmin($idfamily);
            $this->sendData(
                [$admin, $iduser],
                $data
            );
            return ['state' => '0'];
        }
        $this->familyInvitationFinalize($iduser, $idfamily); // finalize familyInvitation process
        return ['data' => $this->userGetFamilyData($iduser, $idfamily), 'state' => '1'];
    }

    /**
     * If familyInvitation accepted, finalizes it, else sets it approved.
     */
    private function userApprovesInvitation($iduser, $invitee, $idfamily)
    {
        if (!$this->familyInvitationExist($invitee, $idfamily)) return false; // if invitation doesn't exist, false
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return false; // if admin of family, false
        $this->familyInvitationIsAccepted($invitee, $idfamily) // if accepted
            ? $this->familyInvitationFinalize($iduser, $idfamily) // finalize familyInvitation process
            : $this->db->request([ // else set approved
                'query' => 'UPDATE family_invitation SET approved = 1 WHERE idfamily = ? AND invitee = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$idfamily, $invitee],
            ]);
        return true;
    }

    private function userCanReadObject(int $iduser, int $idobject)
    {
        $object = $this->getS3ObjectData($idobject);
        if (!$object) return false; // idobject doesn't exist
        // if user is from set family
        if (!empty($object['family'])) return $this->userIsMemberOfFamily($iduser, $object['family']) ? true : false;
        // if user is owner
        // if user and owner share a family
        return $iduser === $object['owner'] || $this->usersHaveCommonFamily($iduser, $object['owner']);
    }

    private function userCreateRecipient(int $iduser, int $idfamily, array $recipient)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        return $this->createRecipient($iduser, $idfamily, $recipient);
    }

    private function userDeniesInvitation(int $iduser, int $idfamily, int $invitee)
    {
        if (!$this->familyInvitationExist($invitee, $idfamily)) return false; // if invitation doesn't exist, false
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return false; // if not admin of family, false
        // remove invitation
        $this->db->request([
            'query' => 'DELETE FROM invitation WHERE idfamily = ? AND invitee = ?;',
            'type' => '',
            'content' => [],
            'array' => true,
        ]);
        $data = [
            'family' => $idfamily,
            'invitee' => $invitee,
            'type' => 13,
        ];
        // send notification to invitee
        $this->sendNotification(
            [$invitee],
            'Invitation annulée',
            'L\'administrateur de la famille ' . $this->getFamilyName($idfamily) . ' a annulé votre invitation.',
            $data
        );
        // send data to admin
        $this->sendData(
            [$iduser],
            $data
        );
        // return true
        return true;
    }

    private function userFillGazetteWithGames(int $iduser, int $idfamily, int $idrecipient, int $idgazette)
    {
        // if user is referent
        if (!$this->userIsReferent($iduser, $idrecipient) || !$this->userIsAdminOfFamily($iduser, $idfamily)) return false;
        $this->fillGazetteWithGames($idgazette, $idrecipient);
        return $this->getGazettePagesData($idgazette);
    }

    private function userGetFamiliesData(int $iduser)
    {
        $families = $this->getUserFamilies($iduser);
        if (empty($families)) return false;
        foreach ($families as &$family) {
            if (empty($family['invitation']) && empty($family['request'])) {
                $family['recipients'] = $this->getFamilyRecipientsData($family['id']); // get recipients + active subscription
                $family['members'] = $this->getFamilyMembersData($family['id']); // get members
                $family['gazettes'] = $this->getFamilyGazettes($family['id']); // get gazettes
                if ($this->userIsAdminOfFamily($iduser, $family['id'])) {
                    $family['invitations'] = $this->getFamilyInvitations($family['id']);
                    $family['requests'] = $this->getFamilyRequests($family['id']);
                }
            }
        }
        return $families;
    }

    private function userGetFamilyData(int $iduser, int $idfamily)
    {
        // TODO: getUserFamilyData: fix != family name if member or requestee/invitee (if duplicate family name)
        if (
            !$this->familyExists($idfamily)
            || (!$this->userIsMemberOfFamily($iduser, $idfamily) && !$this->userHasFamilyInvitation($iduser, $idfamily) && !$this->userHasFamilyRequest($iduser, $idfamily))
        ) return false;
        $family = [
            'admin' => $this->userIsAdminOfFamily($iduser, $idfamily),
            'code' => bin2hex($this->getFamilyCode($idfamily)),
            'default' => $this->familyIsDefaultForUser($iduser, $idfamily),
            'id' => $idfamily,
            'member' => $this->userIsMemberOfFamily($iduser, $idfamily),
            'name' => $this->getFamilyDisplayName($iduser, $idfamily),
            'recipient' => $this->userIsRecipientOfFamily($iduser, $idfamily),
            'request' => $this->userHasFamilyRequest($iduser, $idfamily),
            'invitation' => $this->userHasFamilyInvitation($iduser, $idfamily),
        ];
        if (!$family['request'] && !$family['invitation']) {
            $family['recipients'] = $this->getFamilyRecipientsData($idfamily);
            $family['members'] = $this->getFamilyMembersData($idfamily);
        }
        if ($family['admin']) {
            $family['invitations'] = $this->getFamilyInvitations($idfamily);
            $family['requests'] = $this->getFamilyRequests($idfamily);
        }
        return $family;
    }

    private function userGetFile(int $iduser, int $idobject)
    {
        if (!$this->userCanReadObject($iduser, $idobject)) return false; // if file doesn't exist in s3
        $key = $this->getS3ObjectKeyFromId($idobject);
        return empty($key) ? false : $this->s3->presignedUriGet($this->getS3ObjectKeyFromId($idobject));
    }

    /**
     * Returns user's gazette data with recipients' modifications if any.
     * @param int $iduser
     * @param int $idfamily
     * @param int $idgazette
     * @return array
     */
    private function userGetGazettePages($iduser, $idfamily, $idgazette)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $gazette = $this->getGazetteData($idgazette);
        // get gazette writers
        $gazette['writers'] = $this->getCoverWriters($idgazette);
        // get gazette pages data
        $gazette['pages'] = $this->getGazettePagesData($idgazette);
        return $gazette;
    }

    private function userGetGazettes(int $iduser, int $idfamily)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        return $this->getFamilyGazettes($idfamily);
    }

    private function userGetPublicationData(int $iduser, int $idfamily, int $idpublication)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        return $this->getPublicationData($idpublication, $iduser);
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

    private function userHasFamilyInvitation(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_invitation WHERE invitee = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    /**
     * Returns whether or not a user has applied to a family.
     */
    private function userHasFamilyRequest(int $iduser, int $idfamily)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_request WHERE iduser = ? AND idfamily = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
        ]));
    }

    private function userHasFamilySubscription(int $iduser, int $idfamily)
    {
        $recipients = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE iduser = ? AND idfamily = ?;',
            'type' => 'ii',
            'content' => [$iduser, $idfamily],
            'array' => true,
        ]);
        if (empty($recipients)) return false;
        foreach ($recipients as $recipient) {
            if ($this->getRecipientSubscription($recipient['idrecipient'])) return true;
        }
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

    private function userIsCommentsAuthor(int $iduser, int $idcomment)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM comment WHERE iduser = ? AND idcomment = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idcomment],
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

    private function userIsNew(int $iduser)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM new_user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
        ]));
    }

    private function userIsPublicationsAuthor(int $iduser, int $idpublication)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM publication WHERE author = ? AND idpublication = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $idpublication],
        ]));
    }

    /**
     * Returns true if user is given recipient.
     */
    private function userIsRecipient(int $iduser, int $idrecipient)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM recipient WHERE idrecipient = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idrecipient, $iduser],
        ]));
    }

    /**
     * Returns idrecipient if given user is recipient of given family else false
     * @return int|false
     */
    private function userIsRecipientOfFamily(int $iduser, int $idfamily)
    {
        return $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE idfamily = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
            'array' => true
        ])[0][0] ?? false;
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

    private function userIsReferentInFamily(int $iduser, int $idfamily)
    {
        $recipients = $this->db->request([
            'query' => 'SELECT idrecipient FROM recipient WHERE idfamily = ? AND referent = ?;',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
            'array' => true,
        ]);
        return empty($recipients) ? false : array_column($recipients, 0);
    }

    private function userLikesComment(int $iduser, int $idcomment)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM comment_has_like WHERE idcomment = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idcomment, $iduser],
        ]));
    }

    private function userLikesPublication(int $iduser, int $idpublication)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM publication_has_like WHERE idpublication = ? AND iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idpublication, $iduser],
        ]));
    }

    private function userRefusesInvitation($iduser, $idfamily)
    {
        if (!$this->familyInvitationExist($iduser, $idfamily)) return false;
        // TODO: invitation refusal: notify inviter && admin
        $this->familyInvitationRemove($iduser, $idfamily);
        $admin = $this->getFamilyAdmin($idfamily);
        $userName = $this->getUserName($iduser);
        $data = [
            'family' => $idfamily,
            'invitee' => $iduser,
            'type' => 14,
        ];
        $this->sendNotification(
            [$admin],
            'Invitation refusée',
            'L\'invitation de ' . $userName['first_name'] . ' ' . $userName['last_name'] . ' à rejoindre la famille ' . $this->getFamilyName($idfamily) . ' a été refusée.',
            $data
        );
        $this->sendData([$iduser], $data);
        return true;
    }

    private function userRemovesComment(int $iduser, int $idfamily, int $idpublication, int $idcomment)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsCommentsAuthor($iduser, $idcomment)) return false;
        $this->removeComment($idfamily, $idpublication, $idcomment);
        return $this->getPublicationCommentsData($iduser, $idfamily, $idpublication);
    }

    private function userRemovesMember(int $iduser, int $idfamily, ?int $idmember)
    {
        $idmember = $idmember ?? $iduser;

        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && $iduser !== $idmember) return false;
        if ($this->userIsAdminOfFamily($idmember, $idfamily)) return 1;
        $recipient = $this->userIsRecipientOfFamily($idmember, $idfamily);
        if ($recipient) {
            if ($this->getRecipientSubscription($recipient)) return 2;
            else $this->removeRecipient($idfamily, $recipient);
        }
        // if user is referent of family
        $recipients = $this->userIsReferentInFamily($idmember, $idfamily);
        if ($recipients) {
            // set admin as referent before user removal
            $admin = $this->getFamilyAdmin($idfamily);
            $date = date('Y-m-d H:i:s');
            $this->db->request([
                'query' => 'UPDATE recipient SET referent = ? WHERE idrecipient IN (' . implode(',', $recipients) . ');',
                'type' => 'i',
                'content' => [$admin],
            ]);
            foreach ($recipients as $recipient) {
                $data = [
                    'date' => $date,
                    'family' => $idfamily,
                    'referent' => $admin,
                    'recipients' => [$recipient],
                    'type' => 17,
                ];
                $recipientName = $this->getRecipientDisplayName($recipient);
                $de = preg_match('/^[aeiouy]/i', $recipientName) ? 'd\'' : 'de ';
                $this->sendNotification(
                    [$admin],
                    'Nouveau référent',
                    'Vous êtes à présent le référent ' . $de . $recipientName . '.',
                    $data
                );
            }
            $members = $this->getFamilyMembers($idfamily, [$idmember, $admin]);
            if (!empty($members))
                $this->sendData($members, [
                    'date' => $date,
                    'family' => $idfamily,
                    'referent' => $admin,
                    'recipients' => $recipients,
                    'type' => 17,
                ]);
        }

        // remove member from family
        return $this->removeMember($idmember, $idfamily);

        // return [
        //     'state' => 0,
        //     // 'default' => $iduser === $idmember ? $default : false,
        //     'family' => $this->userGetFamilyData($iduser, $idfamily)
        // ];
    }

    private function userRemovesPublication(int $iduser, int $idfamily, int $idpublication)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsPublicationsAuthor($iduser, $idpublication)) return false;
        $this->removePublication($idfamily, $idpublication);
        return true;
    }

    private function userRemovesRecipient(int $iduser, int $idfamily, int $idrecipient)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsReferent($iduser, $idrecipient)) return false;
        if ($this->getRecipientSubscription($idrecipient)) return 1;
        $displayname = $this->getRecipientDisplayName($idrecipient);
        $this->removeRecipient($idfamily, $idrecipient);
        $data = [
            'date' => date('Y-m-d H:i:s'),
            'family' => $idfamily,
            'recipient' => $idrecipient,
            'type' => 16,
        ];
        $members = $this->getFamilyMembers($idfamily, [$iduser]);
        $this->sendData([$iduser], $data);
        if (!empty($members))
            $this->sendNotification(
                $members,
                'Destinataire supprimé',
                'Le destinataire ' . $displayname . ' a été supprimé de la famille ' . $this->getFamilyName($idfamily) . '.',
                $data,
            );
        return true;
    }

    private function userRemovesRecipientAvatar(int $iduser, int $idfamily, int $idrecipient)
    {
        if (!$this->userIsReferent($iduser, $idrecipient) && !$this->userIsAdminOfFamily($iduser, $idfamily)) return false;
        $idobject = $this->removeRecipientAvatar($idrecipient);
        return $idobject;
    }

    private function userRemovesUserAvatar(int $iduser, int $idfamily, int $idAvatarUser)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && $iduser !== $idAvatarUser) return false;
        $idobject = $this->removeUserAvatar($idAvatarUser);
        return $idobject;
    }

    private function userSetComment(int $iduser, int $idfamily, int $idpublication, string $comment)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $this->setComment($iduser, $idfamily, $idpublication, $comment);
        return $this->getPublicationCommentsData($iduser, $idfamily, $idpublication);
    }

    private function userSetCommentLike(int $iduser, int $idfamily, int $idcomment)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily) || $this->userIsCommentsAuthor($iduser, $idcomment)) return false;
        return $this->setCommentLike($iduser, $idfamily, $idcomment);
    }

    private function userSetPublication(int $iduser, int  $idfamily, array $parameters)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily)) return false;
        $this->setPublication($iduser, $idfamily, $parameters);
        return true;
    }

    private function userSetPublicationLike(int $iduser, int $idfamily, int $idpublication)
    {
        if (!$this->userIsMemberOfFamily($iduser, $idfamily) || $this->userIsPublicationsAuthor($iduser, $idpublication)) return false;
        return $this->setPublicationLike($iduser, $idfamily, $idpublication);
    }

    private function userSetRecipientReferent(int $iduser, int $idfamily, int $idrecipient, int $idreferent)
    {
        if (!$this->userIsAdminOfFamily($iduser, $this->getRecipientFamily($idrecipient)) && !$this->userIsReferent($iduser, $idrecipient)) return false;
        return $this->setReferent($idfamily, $idrecipient, $idreferent);
    }

    private function userToggleAutocorrect(int $iduser)
    {
        $autocorrect = $this->db->request([
            'query' => 'SELECT autocorrect FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] === 0 ? 1 : 0;
        $this->db->request([
            'query' => 'UPDATE user SET autocorrect = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$autocorrect, $iduser],
        ]);
        return $autocorrect;
    }

    private function userToggleCapitalize(int $iduser)
    {
        $capitalize = $this->db->request([
            'query' => 'SELECT capitalize FROM user WHERE iduser = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ])[0][0] === 0 ? 1 : 0;
        $this->db->request([
            'query' => 'UPDATE user SET capitalize = ? WHERE iduser = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$capitalize, $iduser],
        ]);
        return $capitalize;
    }

    private function userUpdateComment(int $iduser, int $idfamily, array $parameters)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsCommentsAuthor($iduser, $parameters['idcomment'])) return false;
        $this->updateComment($parameters['idcomment'], $parameters['content']);
        $members = $this->getFamilyMembers($idfamily, [$iduser]);
        if (!empty($members))
            $this->sendData($members, [
                'family' => $idfamily,
                'publication' => $parameters['idpublication'],
                'type' => 19,
            ]);
        return true;
    }

    /**
     * Create or update fcm token in database and removes duplicates.
     */
    private function userUpdateFCMToken(int $iduser, string $token)
    {
        $user = $this->db->request([
            'query' => 'SELECT iduser FROM user_has_fcm_token WHERE token = ?;',
            'type' => 's',
            'content' => [$token],
            'array' => true,
        ]);
        if (empty($user) || count($user) > 1) {
            if (count($user) > 1) $this->db->request([
                'query' => 'DELETE FROM user_has_fcm_token WHERE token = ?;',
                'type' => 's',
                'content' => [$token],
            ]);
            $this->db->request([
                'query' => 'INSERT INTO user_has_fcm_token (iduser,token) VALUES (?,?);',
                'type' => 'is',
                'content' => [$iduser, $token],
            ]);
        } else {
            $this->db->request([
                'query' => 'UPDATE user_has_fcm_token SET iduser = ?,modified = CURRENT_TIMESTAMP() WHERE token = ? LIMIT 1;',
                'type' => 'is',
                'content' => [$iduser, $token],
            ]);
        }
        return true;
    }

    private function userUpdatePublication(int $iduser, int $idfamily, array $parameters)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily) && !$this->userIsPublicationsAuthor($iduser, $parameters['idpublication'])) return false;
        $this->updatePublication($parameters['idpublication'], $parameters);
        $members = $this->getFamilyMembers($idfamily, [$iduser]);
        if (!empty($parameters['layout'])) $parameters['layout'] = ['identifier' => $parameters['layout']];
        if (!empty($parameters['images'])) foreach ($parameters['images'] as &$image) {
            $image['idobject'] = $image['id'];
            unset($image['id']);
        }
        // TODO: if publication private, don't send to other members the updates
        if (!empty($members))
            $this->sendData($members, [
                'family' => $idfamily,
                'publication' => json_encode($parameters),
                'type' => 18,
            ]);
        return $this->getPublicationData($parameters['idpublication'], $iduser);
    }

    private function userUpdateReferent(int $iduser, int $idfamily, int $recipient, int $referent)
    {
        if (!$this->userIsAdminOfFamily($iduser, $idfamily)) return false;
        return $this->setReferent($idfamily, $recipient, $referent);
    }

    private function userUpdateUserAvatar(int $iduser, int $idUserAvatar, string $key)
    {
        if ($iduser !== $idUserAvatar) return false;
        $idobject = $this->updateUserAvatar($iduser, $key);
        return $idobject;
    }

    private function userUseFamilyCode(int $iduser, string $code)
    {
        $idfamily = $this->getFamilyWithCode($code); // get family with code
        if (!$idfamily) return 1; // no family
        if ($this->userHasFamilyRequest($iduser, $idfamily)) return 2; // user already applied
        if ($this->userIsMemberOfFamily($iduser, $idfamily)) return 3; // user already member of family
        $this->db->request([
            'query' => 'INSERT INTO family_request (idfamily,iduser) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$idfamily, $iduser],
        ]);
        $data = [
            'user' => $iduser,
            'family' => $idfamily,
            'type' => 9,
        ];
        // send notification to family admin
        $admin = $this->getFamilyAdmin($idfamily);
        $name = $this->getUserName($iduser);
        $this->sendNotification(
            [$admin],
            'Nouvelle demande d\'adhésion',
            $name['first_name'] . ' ' . $name['last_name'] . ' souhaite rejoindre la famille ' . $this->getFamilyName($idfamily) . '.',
            $data,
        );
        // send data to user
        $this->sendData(
            [$iduser],
            $data
        );
        return $this->userGetFamilyData($iduser, $idfamily);
    }

    private function usersHaveCommonFamily(int $user1, int $user2)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM family_has_member WHERE iduser IN (?,?) GROUP BY idfamily HAVING COUNT(*) = 2 LIMIT 1;',
            'type' => 'ii',
            'content' => [$user1, $user2],
        ]));
    }
}
