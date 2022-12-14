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
        var_dump($post);
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
                    'families' => $this->getUserFamiliesData($iduser),
                    'id' => $iduser,
                    'lastname' => $userData['last_name'],
                    'firstname' => $userData['first_name'],
                    'admin' => $this->isAdmin($iduser),
                    'member' => $this->isMember($iduser),
                    'recipient' => $this->isRecipient($iduser),
                    'subscriptionTypes' => $this->getSubscriptionTypes(),
                    'theme' => $userData['theme'],
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
                $responseContent = ['f' => 3, 'families' => $this->getUserFamilies($iduser)];
            }

            /////////////////////////////////////////////////////
            // GET FAMILY DATA  (31)
            /////////////////////////////////////////////////////

            if ($f === 31) {
                $responseContent = ['f' => 31, 'family' => $this->getUserFamilyData($iduser, $post['i'])];
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
                $responseContent = ['f' => 7, 'removed' => $this->removeMemberFromFamily($iduser, $post['i'], $post['m'])];
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
                $responseContent = ['f' => 10, 'created' => $this->createRecipient($iduser, $post['i'], $post['r'])];
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
            // REQUEST SEND FILE URL TEST (14)
            /////////////////////////////////////////////////////

            if ($f === 14) {
            }

            return [
                "type" => $responseType,
                "content" => $responseContent,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
