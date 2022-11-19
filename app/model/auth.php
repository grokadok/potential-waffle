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
    private function JWTVerify($jwt)
    {
        // get keys

        // $keys = [
        //     "-----BEGIN CERTIFICATE-----\nMIIDHTCCAgWgAwIBAgIJANjZF91rvB00MA0GCSqGSIb3DQEBBQUAMDExLzAtBgNV\nBAMMJnNlY3VyZXRva2VuLnN5c3RlbS5nc2VydmljZWFjY291bnQuY29tMB4XDTIy\nMTEwMzA5MzkwNloXDTIyMTExOTIxNTQwNlowMTEvMC0GA1UEAwwmc2VjdXJldG9r\nZW4uc3lzdGVtLmdzZXJ2aWNlYWNjb3VudC5jb20wggEiMA0GCSqGSIb3DQEBAQUA\nA4IBDwAwggEKAoIBAQC3XITAKtD1enEWjWTMHKsMSI/hRCkzJPC4/UkYTReK905M\nHCWRpy+wxTeL8OgJzdqMwUusgElyUOYUrn9HI1VY1hzdqZw64bpN4Qv3HMqp/1EJ\nGdufw0A9ctFaCMOsiivSZjhTa8RFKI2iNfyivXCYa5lygTfG0xlCKYwfyrbdOaU8\n0S7MeB/4C4cXINN0v+XEQU1fXyeYh+mrMlxadhimGtN9sok5gJ8/CrQYnnerlL06\nem+csYIeb6UqS3kL4boABm5yClOUh5Aw+vYYuo0hHjxFgBUmCfg20rg9Rg/rZ72k\njAQXnkP/NH9MKLMScwHhZTwXNNi8KCyLxHsQykjXAgMBAAGjODA2MAwGA1UdEwEB\n/wQCMAAwDgYDVR0PAQH/BAQDAgeAMBYGA1UdJQEB/wQMMAoGCCsGAQUFBwMCMA0G\nCSqGSIb3DQEBBQUAA4IBAQBo3sT7TN191Ao9c6VfvMVWidt5k/cG/QexLOxS2LYu\ns1ze3bhk4/7xWzq8zqnfa9ERuNHq4K+OsreEuxGURDlzW2mJ5Ql8ZexaLvwqsk5j\nmTu91ymkTupiQ4RcpGl+JqSlba21ovFNpilys3+/xs07HuPT6GkI7r9e/rVjTDcA\nNQnyAP7Be/M3k2OczRyza3aaa3rcchYSNCD+qHPeLghe413HZm22XFth7IqIb9L+\nhSMpDwNAiO2IOdPn1ps0so8/hO5vnkS5YvTbVlNvQFtk5Sz00h0Q+SKpP8hMPAhK\n2XxrnGz2EhGhvLsfXlGAiBjnyPDPwsenQJBje8dOG8Qu\n-----END CERTIFICATE-----\n",
        //     "-----BEGIN CERTIFICATE-----\nMIIDHDCCAgSgAwIBAgIISPVnMgyCk88wDQYJKoZIhvcNAQEFBQAwMTEvMC0GA1UE\nAwwmc2VjdXJldG9rZW4uc3lzdGVtLmdzZXJ2aWNlYWNjb3VudC5jb20wHhcNMjIx\nMTExMDkzOTA3WhcNMjIxMTI3MjE1NDA3WjAxMS8wLQYDVQQDDCZzZWN1cmV0b2tl\nbi5zeXN0ZW0uZ3NlcnZpY2VhY2NvdW50LmNvbTCCASIwDQYJKoZIhvcNAQEBBQAD\nggEPADCCAQoCggEBALA3uqgU683ySF4awP8QWPPmf4+/3Ej/HXhauDgRrnTow2br\niXuhMnMMSnsrKXr7flmA33RjKAfdnWRArBcXQtfPuL/YHEiqsNriJYQl9VDx4l8m\nqVBx8q6Rb4WtolXWJwanujeoOXku3+JN8gapk598gadRqyGMdVdaAOa6tUp5Zcku\nb3By0MlJjnxTWgyX//erK3c0WFxktXM7vzss9zShXGeSc/vsgE9VM9N3BwXGhEfD\nuwaPhbLgJPC2PhW9x7a+1i3FgGYib/EeRxH7F3NWc1mwIMExf8FX0NWIyhVBDnuK\noirE5R6fsxkkTIGpk3O6rn5AZvYf20VnxGSBjL0CAwEAAaM4MDYwDAYDVR0TAQH/\nBAIwADAOBgNVHQ8BAf8EBAMCB4AwFgYDVR0lAQH/BAwwCgYIKwYBBQUHAwIwDQYJ\nKoZIhvcNAQEFBQADggEBAHUPj76NIULDRL5C/s19smpO+7a6/Xia3BlUBBmLmk4Z\nRIc/l3N4ipBZ3blhNQVFp8B+Woh8PQP8MTD17I8EV5eA8FlHco7YExUj4yOk5yya\nmvI99PrzjOWgeTobZvIwq6cQi/AcDBMgWACxlyn2+Kc01cO4ZH8W4Ep2AhVwAc57\ndgKfb6r5FJaEoT7n+G+sAyMMznmPXOle7o/EQqwGWKmUhcZEUBdrI0hbBwcsqsKp\nPqe8hBfZmWNMgH6eF+bZp5v51UovztNljZcKmGwieecvzkBoHfKz1hef8m1Ld7H4\no/rnM9pfslNSVXG6WR7oFLf4UPy6w1RzyKVGdBOHAq0=\n-----END CERTIFICATE-----\n",
        // ];
        $appName = 'project-ff955';
        // code logic to get/renew google public keys

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


    // refresh google public keys https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com
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

            // $output contains the output string
            $output = curl_exec($ch);
            preg_match('/max-age=(\d{1,6}),/', $headers['cache-control'][0], $matches);
            // $maxAge = intval($matches[1]);
            $expiration = time() + intval($matches[1]);
            unset($matches);
            // close curl resource to free up system resources
            curl_close($ch);

            // var_dump(json_decode($output, true));

            // store into db
            $keys = [];
            foreach (json_decode($output, true) as $key => $value) {
                $keys[] = [
                    'idgoogle_key' => $key,
                    'content' => $value,
                ];
                $this->db->request([
                    'query' => 'INSERT INTO google_key (idgoogle_key, content, expiration) VALUES (?,?,?) ON DUPLICATE KEY UPDATE expiration = ?;',
                    'type' => 'ssi',
                    'content' => [$key, $value, $expiration, $expiration],
                ]);
            }
            if (empty($keys)) throw ('Error: no keys retrieved.');
            return $keys;
        } catch (Error $e) {
            print($e);
        }
    }

    private function retrieveGoogleKeys()
    {
        // remove expired keys from db
        $this->db->request([
            'query' => 'DELETE FROM google_key WHERE expiration < SUBTIME(NOW(),"00:05:00");',
        ]);
        $keys = $this->db->request([
            'query' => 'SELECT idgoogle_key,content FROM google_key;',
            'array' => true,
        ]);
        if (empty($keys)) return $keys = $this->refreshGoogleKeys();
        return $keys;
    }
}
