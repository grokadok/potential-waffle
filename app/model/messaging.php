<?php

namespace bopdev;

require __DIR__ . '/../../vendor/autoload.php';

use Error;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class Messaging
{
    private $factory;
    private $messaging;


    public function __construct()
    {
        $serviceAccount = getenv('FIREBASE_SERVICE_ACCOUNT');
        // if (empty($serviceAccount)) {
        //     throw new Error('FIREBASE_SERVICE_ACCOUNT is not set');
        // }
        // print('### FIREBASE_SERVICE_ACCOUNT' . PHP_EOL);
        // var_dump($serviceAccount);
        // print('### END FIREBASE_SERVICE_ACCOUNT' . PHP_EOL);
        // $serviceAccount = json_decode($serviceAccount, true);
        // print('### DECODED FIREBASE_SERVICE_ACCOUNT' . PHP_EOL);
        // var_dump($serviceAccount);
        // print('### END DECODED FIREBASE_SERVICE_ACCOUNT' . PHP_EOL);

        $this->factory = (new Factory)->withServiceAccount($serviceAccount);
        // $this->factory = (new Factory)->withServiceAccount(__DIR__ . '/../../config/firebase.json');
        $this->messaging = $this->factory->createMessaging();
    }

    public function sendNotification(array $tokens, string $title, string $body, array $data)
    {
        try {
            // $this->messaging = $this->factory->createMessaging();
            if (empty($tokens)) {
                throw new Error('Empty tokens');
            }

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::fromArray([
                        'token' => $token,
                        'notification' => Notification::create($title, $body), // optional
                        'data' => $data ?? null, // optional
                    ]);
                    $response = $this->messaging->send($message);
                    // $this->messaging->send($message);
                    print('### MESSAGE RESPONSE' . PHP_EOL);
                    var_dump($response);
                } catch (MessagingException $e) {
                    print('### MESSAGING EXCEPTION' . PHP_EOL);
                    // var_dump($e);
                } catch (Error $error) {
                    print('### SEND ERROR' . PHP_EOL);
                    // var_dump($error);
                }
            }
        } catch (Error $error) {
            print('### TOKEN ERROR' . PHP_EOL);
            var_dump($error);
        }
    }

    public function sendData(array $tokens, array $data)
    {
        try {
            // $this->messaging = $this->factory->createMessaging();
            if (empty($tokens)) {
                throw new Error('Empty tokens');
            }

            foreach ($tokens as $token) {
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'data' => $data ?? null, // optional
                ]);
                $response = $this->messaging->send($message);
                // print('### MESSAGE RESPONSE' . PHP_EOL);
                // var_dump($response);
            }
        } catch (Error $error) {
            print('### ERROR' . PHP_EOL);
            // var_dump($error);
        }
    }

    public function testMessage($tokens)
    {
        try {
            // $this->messaging = $this->factory->createMessaging();
            if (empty($tokens)) {
                throw new Error('Empty tokens');
            }

            foreach ($tokens as $token) {
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'notification' => Notification::create('Title', 'Body'), // optional
                    // 'data' => [/* data array */], // optional
                ]);
                $response = $this->messaging->send($message);
                print('### MESSAGE RESPONSE' . PHP_EOL);
                var_dump($response);
            }

            // $testDeviceToken = getenv('DEVICE_TOKEN');
            // print('### DEVICE_TOKEN' . PHP_EOL);
            // var_dump($testDeviceToken);
            // print('### END DEVICE_TOKEN' . PHP_EOL);

            // $notification = Notification::create('Title', 'Body');
            // print('### NOTIFICATION' . PHP_EOL);
            // var_dump($notification);
            // print('### END NOTIFICATION' . PHP_EOL);


            // $message = CloudMessage::withTarget('token', $testDeviceToken)
            //     ->withNotification($notification) // optional
            // ->withData($data) // optional
            // ;

            // $message = CloudMessage::fromArray([
            //     'token' => $testDeviceToken,
            //     'notification' => Notification::create('Title', 'Body'), // optional
            //     // 'data' => [/* data array */], // optional
            // ]);

            // $messaging->send($message);
            // $response = $this->messaging->send($message);
            // print('### MESSAGE RESPONSE' . PHP_EOL);
            // var_dump($response);

            // Check if the message was sent successfully

            // if ($response["isSuccess"]) {
            //     echo 'Message sent successfully';
            // } else {
            //     // echo 'Error sending message: ' . $response->error()->getMessage();
            // }
        } catch (Error $error) {
            print('### ERROR' . PHP_EOL);
            // var_dump($error);
        }
    }
}
