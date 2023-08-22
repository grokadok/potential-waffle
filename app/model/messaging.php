<?php

namespace bopdev;

require __DIR__ . '/../../vendor/autoload.php';

use Error;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class Messaging
{
    private $factory;
    private $messaging;

    public function __construct()
    {
        $serviceAccount = getenv('FIREBASE_SERVICE_ACCOUNT');
        $this->factory = (new Factory)->withServiceAccount($serviceAccount);
        $this->messaging = $this->factory->createMessaging();
    }

    private function handleMessagingError($e, $token)
    {
        if ($e instanceof MessagingException) {
            print('### MESSAGING EXCEPTION' . PHP_EOL);
            $errors = $e->errors();
            if (!empty($errors) && $errors['error']['code'] === 404) {
                echo 'Token not found: ' . $token . PHP_EOL . 'Removing token from database NOW (and get to da choppaaa!)' . PHP_EOL;
                return $token;
            } else {
                echo 'Other error: ' . $e->getMessage();
                return;
            }
        } else {
            echo '### SEND ERROR: ' . $e->getMessage();
            return;
        }
    }

    public function sendNotification(array $tokens, string $title, string $body, array $data)
    {
        if (empty($tokens)) {
            throw new Error('Empty tokens');
        }
        $invalidTokens = [];
        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'notification' => Notification::create($title, $body), // optional
                    'data' => $data ?? null, // optional
                ]);
                $this->messaging->send($message);
            } catch (throwable $e) {
                $response = $this->handleMessagingError($e, $token);
                if (!empty($response)) $invalidTokens[] = $response;
            }
        }
        return $invalidTokens;
    }

    public function sendData(array $tokens, array $data)
    {
        if (empty($tokens)) {
            throw new Error('Empty tokens');
        }
        $invalidTokens = [];
        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'data' => $data ?? null, // optional
                ]);
                $this->messaging->send($message);
                // $response = $this->messaging->send($message);
                // print('### MESSAGE RESPONSE' . PHP_EOL);
                // var_dump($response);
            } catch (throwable $e) {
                $response = $this->handleMessagingError($e, $token);
                if (!empty($response)) $invalidTokens[] = $response;
            }
        }
        return $invalidTokens;
    }

    public function testMessage($tokens)
    {
        if (empty($tokens)) {
            throw new Error('Empty tokens');
        }
        $invalidTokens = [];
        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'notification' => Notification::create('Title', 'Body'), // optional
                    // 'data' => [/* data array */], // optional
                ]);
                $response = $this->messaging->send($message);
                print('### MESSAGE RESPONSE' . PHP_EOL);
                var_dump($response);
            } catch (throwable $e) {
                $response = $this->handleMessagingError($e, $token);
                if (!empty($response)) $invalidTokens[] = $response;
            }
        }
        return $invalidTokens;
    }
}
