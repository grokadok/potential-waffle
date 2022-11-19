<?php

namespace bopdev;

$jwt_jwt = __DIR__ . "/../jwt/JWT.php";
$jwt_jwk = __DIR__ . "/../jwt/JWK.php";
$jwt_key = __DIR__ . "/../jwt/Key.php";
$jwt_before = __DIR__ . "/../jwt/BeforeValidException.php";
$jwt_cached = __DIR__ . "/../jwt/CachedKeySet.php";
$jwt_expired = __DIR__ . "/../jwt/ExpiredException.php";
$jwt_signature = __DIR__ . "/../jwt/SignatureInvalidException.php";

foreach ([
    $jwt_jwt,
    $jwt_jwk,
    $jwt_key,
    $jwt_before,
    $jwt_cached,
    $jwt_expired,
    $jwt_signature,
] as $value) {
    require_once $value;
    unset($value);
};

use Error;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

trait JWTAuth
{
    // decode JWT
    private function JWTVerify($jwt, $appName)
    {
        // get keys
        $keys = $this->retrieveGoogleKeys();

        foreach ($keys as $key) {
            try {
                $decoded = JWT::decode($jwt, new Key($key, 'RS256'));
                $currentTime = time();
                // check expiration time (exp) must be in the future
                if ($decoded->exp < $currentTime) return false;
                // check issued at time (iat) must be in the past
                if ($decoded->iat > $currentTime) return false;
                // check auth time (auth) must be in the past
                if ($decoded->auth_time > $currentTime) return false;
                // check app name (aud & iss)
                if ($decoded->aud !== $appName) return false;
                if (str_replace('https://securetoken.google.com/', '', $decoded->iss) !== $appName) return false;
                // check user uid (sub) non empty string
                if (empty($decoded->sub)) return false;

                return $decoded;
            } catch (\LogicException) {
                // errors having to do with environmental setup or malformed JWT Keys
                print('Error: INVALID JWT.' . PHP_EOL);
                return false;
            } catch (\UnexpectedValueException) {
                // print('Error: JWT INVALID SIGNATURE.' . PHP_EOL);
                // errors having to do with JWT signature and claims
            }
        }
        return false;
    }


    private function refreshGoogleKeys()
    {
        try {
            // create curl resource
            $ch = curl_init();
            $headers = [];
            // set url
            curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com");

            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt(
                $ch,
                CURLOPT_HEADERFUNCTION,
                function ($curl, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;
                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                    return $len;
                }
            );
            $output = curl_exec($ch);
            curl_close($ch);

            preg_match('/max-age=(\d{1,6}),/', $headers['cache-control'][0], $matches);
            $expiration = time() + intval($matches[1]);
            unset($matches);

            // store into db
            $keys = [];
            foreach (json_decode($output, true) as $key => $value) {
                $keys[] = $value;
                $this->db->request([
                    'query' => 'INSERT INTO google_key (idgoogle_key, content, expiration) VALUES (?,?,FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE expiration = FROM_UNIXTIME(?);',
                    'type' => 'ssii',
                    'content' => [$key, $value, $expiration, $expiration],
                ]);
            }
            if (empty($keys)) throw new Error('Error: no keys retrieved.');
            return $keys;
        } catch (Error $e) {
            // should wait and retry
            print($e);
        }
    }

    private function retrieveGoogleKeys()
    {
        // remove expired keys from db
        $this->db->request([
            'query' => 'DELETE FROM google_key WHERE expiration < SUBTIME(NOW(),"00:05:00");',
        ]);
        $keys = [];
        foreach ($this->db->request([
            'query' => 'SELECT content FROM google_key;',
            'array' => true,
        ]) as $key) $keys[] = $key[0];

        if (empty($keys)) return $this->refreshGoogleKeys();
        return $keys;
    }
}
