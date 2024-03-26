<?php

namespace bopdev;

class PdfGenerator
{
    private $host;
    private $token;
    private $app;
    private $bucket;


    public function __construct()
    {
        $this->host = getenv('PDF_HOST');
        $this->token = getenv('PDF_API');
        $this->app = getenv('SERVER_URL');
        $this->bucket = getenv('CELLAR_ADDON_BUCKET');
    }

    public function request(int $id)
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->host,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_POSTFIELDS => json_encode([
                    'id' => $id,
                    'server' => $this->app,
                    'bucket' => $this->bucket,
                ]),
                CURLOPT_FAILONERROR => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Api-Authorization: ' . $this->token,
                ]
            ]);
            $result = curl_exec($ch);
            // if response is not 200, throw exception
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                throw new \Exception('PdfGen request failed: ' . $result);
            }
            curl_close($ch);
            return $result;
        } catch (\Throwable $th) {
            print('### PdfGen request error: ' . $th->getMessage() . PHP_EOL);
            throw $th;
        }
    }
}
