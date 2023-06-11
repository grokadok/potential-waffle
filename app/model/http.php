<?php

namespace bopdev;

require_once __DIR__ . "/auth.php";
require_once "gazet.php";

trait Http
{
    use Auth;
    use Gazet;
    private function task($post)
    {
        // var_dump($post);
        // var_dump($post['f']);
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

                // if user doesn't exists in db
                if (!$iduser) {
                    // if gmail address corresponds to existing user, link firebase user to it
                    $emailFinal = gmailNoPeriods($post['email']);
                    $iduser = $this->getUserIdFromEmail($emailFinal);
                    if ($iduser) {
                        $this->addUser([
                            'iduser' => $iduser,
                            'email' => $post['email'],
                            'firebase_uid' => $post['sub'],
                            'firebase_name' => $post['name'] ?? '',
                            'avatar' => $post['picture'] ?? '',
                        ]);
                    } else {
                        // else create user
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
                        $iduser = $this->addUser([
                            'email' => $emailFinal,
                            'firstname' => $firstname ?? '',
                            'lastname' => $lastname ?? '',
                            'firebase_uid' => $post['sub'],
                            'firebase_name' => $post['name'] ?? '',
                            'avatar' => $post['picture'] ?? '',
                        ]);
                        $this->updateUserEmailInvitation($iduser, $emailFinal);
                        // DEV: TEST PROTOCOL FOR NEW USERS //
                        $this->testerProcess($iduser);
                    }
                }

                $userData = $this->getUserData($iduser);

                $responseContent = [
                    'admin' => $this->userIsAdmin($iduser),
                    'f' => 1, // login approved
                    'families' => $this->getUserFamiliesData($iduser),
                    'gazetteTypes' => $this->getGazetteTypes(),
                    'invitations' => $this->getUserInvitations($iduser),
                    'member' => $this->userIsMember($iduser),
                    'recipient' => $this->userIsRecipient($iduser),
                    'requests' => $this->getUserRequests($iduser),
                    'subscriptionTypes' => $this->getSubscriptionTypes(),
                    'user' => ['id' => $iduser, 'new' => $this->userIsNew($iduser), ...$userData],
                ];
            }

            /////////////////////////////////////////////////////
            // CREATE FAMILY  (2)
            /////////////////////////////////////////////////////

            if ($f === 2) {
                $responseContent = ['f' => 2, 'family_created' => $this->createFamily($iduser, $post['n'])];
            }

            /////////////////////////////////////////////////////
            // RETRIEVE FAMILIES  (3)
            /////////////////////////////////////////////////////

            if ($f === 3) {
                $responseContent = ['f' => 3, 'families' => $this->getUserFamiliesData($iduser)];
            }

            /////////////////////////////////////////////////////
            // DELETE FAMILY  (4)
            /////////////////////////////////////////////////////

            if ($f === 4) {
                $responseContent = ['f' => 4, 'deleted' => $this->deleteFamily($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // REQUEST FAMILY  (5)
            /////////////////////////////////////////////////////

            if ($f === 5) {
                $responseContent = ['f' => 5, 'sent' => $this->requestAddToFamily($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // ADD USER TO FAMILY  (6)
            /////////////////////////////////////////////////////

            if ($f === 6) {
                $responseContent = ['f' => 6, 'accepted' => $this->addUserToFamily($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // REMOVE MEMBER FROM FAMILY  (7)
            /////////////////////////////////////////////////////

            if ($f === 7) {
                $responseContent = ['f' => 7, 'removed' => $this->removeMemberFromFamily($iduser, $post['i'], $post['m'] ?? null)];
            }

            /////////////////////////////////////////////////////
            // GET FAMILY RECIPIENTS  (8)
            /////////////////////////////////////////////////////

            if ($f === 8) {
                $responseContent = ['f' => 8, 'recipients' => $this->getFamilyRecipients($post['i'], $post['u'] ?? null)];
            }

            /////////////////////////////////////////////////////
            // SET DEFAULT FAMILY  (9)
            /////////////////////////////////////////////////////

            if ($f === 9) {
                $responseContent = ['f' => 9, 'default' => $this->setDefaultFamily($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // CREATE RECIPIENT  (10)
            /////////////////////////////////////////////////////

            if ($f === 10) {
                $responseContent = ['f' => 10, 'created' => $this->userCreateRecipient($iduser, $post['i'], $post['r'])];
            }

            /////////////////////////////////////////////////////
            // DELETE RECIPIENT (11)
            /////////////////////////////////////////////////////

            if ($f === 11) {
                $responseContent = ['f' => 11, 'deleted' => $this->removeRecipient($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // GET SUBSCRIPTION'S CURRENT MONTH MEMBERS' SHARE (12)
            /////////////////////////////////////////////////////

            if ($f === 12) {
                $responseContent = ['f' => 12, 'amount' => $this->getSubscriptionMembersShare($post['i'])];
            }

            /////////////////////////////////////////////////////
            // GET MEMBER DATA (13)
            /////////////////////////////////////////////////////

            if ($f === 13) {
                $responseContent = ['f' => 13, 'member' => $this->getFamilyMemberData($post['i'], $post['m'])];
            }

            /////////////////////////////////////////////////////
            // REQUEST PRESIGNED IMAGE PUT LINK (14)
            /////////////////////////////////////////////////////

            if ($f === 14) {
                $responseContent = ['f' => 14, 'presigned' => $this->s3->presignedUriPut($post['o'])];
            }

            /////////////////////////////////////////////////////
            // HANDLE USER AVATAR UPLOAD CONFIRMATION (15)
            /////////////////////////////////////////////////////

            if ($f === 15) {
                $responseContent = ['f' => 15, 'uploaded' => $this->userUpdateUserAvatar($iduser, $post['u'], $post['k'])];
            }

            /////////////////////////////////////////////////////
            // HANDLE PUBLICATION JPEG UPLOAD CONFIRMATION (16)
            /////////////////////////////////////////////////////

            if ($f === 16) {
                $responseContent = ['f' => 16, 'uploaded' => $this->storeS3Object($iduser, $post['k'])];
            }

            /////////////////////////////////////////////////////
            // UPDATE RECIPIENT (17)
            /////////////////////////////////////////////////////

            if ($f === 17) {
                $responseContent = ['f' => 17, 'updated' => $this->updateRecipient($iduser, $post['i'], $post['r'])];
            }

            /////////////////////////////////////////////////////
            // INVITE MEMBER INTO FAMILY (18)
            /////////////////////////////////////////////////////

            if ($f === 18) {
                $responseContent = ['f' => 18, 'invited' => $this->familyEmailInvite($iduser, $post['i'], $post['e'])];
            }

            /////////////////////////////////////////////////////
            // ADMIN APPROVES INVITATION (19)
            /////////////////////////////////////////////////////

            if ($f === 19) {
                $responseContent = ['f' => 19, 'approved' => $this->familyInvitationApprove($iduser, $post['u'], $post['i'])];
            }

            /////////////////////////////////////////////////////
            // USER ACCEPTS INVITATION (20)
            /////////////////////////////////////////////////////

            if ($f === 20) {
                $responseContent = ['f' => 20, 'accepted' => $this->familyInvitationAccept($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // USER APPLIES TO FAMILY WITH CODE (21)
            /////////////////////////////////////////////////////

            if ($f === 21) {
                $responseContent = ['f' => 21, 'applied' => $this->userUseFamilyCode($iduser, hex2bin($post['c']))];
            }

            /////////////////////////////////////////////////////
            // ADMIN APPROVES REQUEST (22)
            /////////////////////////////////////////////////////

            if ($f === 22) {
                $responseContent = ['f' => 22, 'approved' => $this->familyRequestApprove($iduser, $post['r'], $post['i'])];
            }

            /////////////////////////////////////////////////////
            // USER REFUSES INVITATION (23)
            /////////////////////////////////////////////////////

            if ($f === 23) {
                $responseContent = ['f' => 23, 'refused' => $this->familyInvitationRefuse($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // REQUEST PRESIGNED GET LINK (24)
            /////////////////////////////////////////////////////

            if ($f === 24) {
                $responseContent = ['f' => 24, 'presigned' => $this->userGetFile($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // REMOVE USER AVATAR (25)
            /////////////////////////////////////////////////////

            if ($f === 25) {
                $responseContent = ['f' => 25, 'removed' => $this->userRemoveUserAvatar($iduser, $post['i'], $post['u'])];
            }

            /////////////////////////////////////////////////////
            // UPDATE USER DATA (26)
            /////////////////////////////////////////////////////

            if ($f === 26) {
                $responseContent = ['f' => 26, 'updated' => $this->updateMember($iduser, $post['p'])];
            }

            /////////////////////////////////////////////////////
            // UPDATE RECIPIENT AVATAR (27)
            /////////////////////////////////////////////////////

            if ($f === 27) {
                $responseContent = ['f' => 27, 'uploaded' => $this->updateRecipientAvatar($iduser, $post['i'], $post['k'])];
            }

            /////////////////////////////////////////////////////
            // ADMIN REFUSES REQUEST (28)
            /////////////////////////////////////////////////////

            if ($f === 28) {
                $responseContent = ['f' => 28, 'denied' => $this->familyRequestRefuse($iduser, $post['r'], $post['i'])];
            }


            /////////////////////////////////////////////////////
            // GET FAMILY DATA (31)
            /////////////////////////////////////////////////////

            if ($f === 31) {
                $responseContent = ['f' => 31, 'family' => $this->getUserFamilyData($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // GET PUBLICATIONS (32)
            /////////////////////////////////////////////////////

            if ($f === 32) {
                $responseContent = ['f' => 32, 'publications' => $this->getFamilyPublications($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // ADD PUBLICATION (33)
            /////////////////////////////////////////////////////

            if ($f === 33) {
                $responseContent = ['f' => 33, 'added' => $this->userSetPublication($iduser, $post['i'], $post['p'])];
            }

            /////////////////////////////////////////////////////
            // GET PUBLICATION COMMENTS (34)
            /////////////////////////////////////////////////////

            if ($f === 34) {
                $responseContent = ['f' => 34, 'comments' => $this->getPublicationCommentsData($iduser, $post['i'], $post['p'])];
            }

            /////////////////////////////////////////////////////
            // ADD COMMENT (35)
            /////////////////////////////////////////////////////

            if ($f === 35) {
                $responseContent = ['f' => 35, 'added' => $this->userSetComment($iduser, $post['i'], $post['p'], $post['c'])];
            }

            /////////////////////////////////////////////////////
            // USER TOGGLE LIKE ON PUBLICATION (36)
            /////////////////////////////////////////////////////

            if ($f === 36) {
                $responseContent = ['f' => 36, 'like' => $this->userSetPublicationLike($iduser, $post['i'], $post['p'])];
            }

            /////////////////////////////////////////////////////
            // USER TOGGLE LIKE ON COMMENT (37)
            /////////////////////////////////////////////////////

            if ($f === 37) {
                $responseContent = ['f' => 37, 'like' => $this->userSetCommentLike($iduser, $post['i'], $post['c'])];
            }

            /////////////////////////////////////////////////////
            // DELETE PUBLICATION (38)
            /////////////////////////////////////////////////////

            if ($f === 38) {
                $responseContent = ['f' => 38, 'deleted' => $this->userRemovesPublication($iduser, $post['i'], $post['p'])];
            }

            /////////////////////////////////////////////////////
            // DELETE COMMENT (39)
            /////////////////////////////////////////////////////

            if ($f === 39) {
                $responseContent = ['f' => 39, 'deleted' => $this->userRemovesComment($iduser, $post['i'], $post['p'], $post['c'])];
            }

            /////////////////////////////////////////////////////
            // AUTOCORRECT TOGGLE (40)
            /////////////////////////////////////////////////////

            if ($f === 40) {
                $responseContent = ['f' => 40, 'autocorrect' => $this->userToggleAutocorrect($iduser)];
            }

            /////////////////////////////////////////////////////
            // CAPITALIZE TOGGLE (41)
            /////////////////////////////////////////////////////

            if ($f === 41) {
                $responseContent = ['f' => 41, 'capitalize' => $this->userToggleCapitalize($iduser)];
            }

            /////////////////////////////////////////////////////
            // UPDATE PUBLICATION (42)
            /////////////////////////////////////////////////////

            if ($f === 42) {
                $responseContent = ['f' => 42, 'updated' => $this->userUpdatePublication($iduser, $post['i'], $post['p'])];
            }

            /////////////////////////////////////////////////////
            // UPDATE COMMENT (43)
            /////////////////////////////////////////////////////

            if ($f === 43) {
                $responseContent = ['f' => 43, 'updated' => $this->userUpdateComment($iduser, $post['i'], $post['c'])];
            }

            /////////////////////////////////////////////////////
            // FILL GAZETTE WITH GAMES (44)
            /////////////////////////////////////////////////////

            if ($f === 44) {
                $responseContent = ['f' => 44, 'filled' => $this->userFillGazetteWithGames($iduser, $post['i'], $post['r'], $post['g'])];
            }

            /////////////////////////////////////////////////////
            // GET GAZETTE PAGES (45)
            /////////////////////////////////////////////////////

            if ($f === 45) {
                $responseContent = ['f' => 45, 'gazette' => $this->userGetGazettePages($iduser, $post['i'], $post['g'])];
            }

            /////////////////////////////////////////////////////
            // GET FAMILY GAZETTES (46)
            /////////////////////////////////////////////////////

            if ($f === 46) {
                $responseContent = ['f' => 46, 'gazettes' => $this->userGetGazettes($iduser, $post['i'])];
            }

            /////////////////////////////////////////////////////
            // REMOVE RECIPIENT AVATAR (47)
            /////////////////////////////////////////////////////

            if ($f === 47) {
                $responseContent = ['f' => 47, 'removed' => $this->userRemovesRecipientAvatar($iduser, $post['i'], $post['r'])];
            }





            /////////////////////////////////////////////////////
            // CLEAN S3 FROM UNUSED ITEMS (998)
            /////////////////////////////////////////////////////

            if ($f === 997) {
                $objects = $this->s3->listObjects()['Contents'];
                foreach ($objects as &$object) $object = $object['Key'];
                var_dump($objects);
                $responseContent = ['f' => 997];
            }

            /////////////////////////////////////////////////////
            // CLEAN S3 FROM UNUSED ITEMS (998)
            /////////////////////////////////////////////////////

            if ($f === 998) {
                $responseContent = [
                    'f' => 998,
                    'cleaned' => $this->cleanS3Bucket(),
                ];
            }

            /////////////////////////////////////////////////////
            // EMPTY S3 (999)
            /////////////////////////////////////////////////////

            if ($f === 999) {
                $responseContent = [
                    'f' => 999,
                    // 'emptied' => $this->s3->emptyBucket()
                ];
            }



            // var_dump($responseContent);
            return [
                "type" => $responseType,
                "content" => $responseContent,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
    private function initDb()
    {
        print('#### Initializing database content... ####' . PHP_EOL);

        // Create 10 users :
        $i = 1;
        while ($i < 11) {
            $this->db->request([
                'query' => 'INSERT INTO user (email,first_name,last_name) VALUES (?,?,?);',
                'type' => 'sss',
                'content' => ['test' . $i . '@buddy.com', 'Test' . $i++, 'Buddy'],
            ]);
        }

        $users = $this->db->request([
            'query' => 'SELECT iduser FROM user WHERE last_name = ?;',
            'type' => 's',
            'content' => ['Buddy'],
            'array' => true,
        ]);
        foreach ($users as &$user) $user = $user[0];
        $skynet = $users[0];
        $i = 1;
        while ($i < 5) $this->createFamily($skynet, 'Test ' . $i++); // first user creates 4 families
        foreach ($this->db->request([ // foreach family
            'query' => 'SELECT idfamily FROM family;',
            'array' => true,
        ]) as $family) {
            $i = 2;
            while ($i < 7) $this->addUserToFamily($users[$i++ - 1], $family[0]); // add 5 more users to family
            $this->familyEmailInvite($skynet, $family[0], 'test' . $i++ . '@buddy.com'); // admin invite the next user
            $this->familyEmailInvite($users[1], $family[0], 'test' . $i++ . '@buddy.com'); // member invite the next user
            while ($i < 11) $this->userUseFamilyCode($users[$i++ - 1], $this->getFamilyCode($family[0])); // the 2 last users request to join family
            while ($i < 13) $this->createRecipient($users[$i - 11], $family[0], [ // create a recipient by the admin and by one member
                'display_name' => 'Recipient ' . $i - 10,
                'birthdate' => '2023-01-06',
                'address' => [
                    'name' => 'Lord Recipient ' . $i - 10,
                    'phone' => '+336123456' . $i++,
                    'field1' => '97 Gazet\' Street',
                    'postal' => '98002',
                    'city' => 'Gazet City',
                    'state' => 'Gazet Highlands',
                    'country' => 'Gazet Republic',
                ],
                'self' => false,
            ]);
        }
        // next add shared subscription

        print('#### Database content initialized ####' . PHP_EOL);
    }
}
