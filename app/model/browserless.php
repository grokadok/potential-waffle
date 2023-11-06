<?php

namespace bopdev;

class Browserless
{
    private $blserver;
    private $path;
    private $port;

    public function __construct()
    {
        $this->blserver = getenv('BROWSERLESS_HOST');
        $this->path = getenv('SERVER_URL');
        $this->port = intval(getenv('BROWSERLESS_PORT'));
    }

    public function pdfFromUrl(string $url)
    {
        try {
            $path = json_encode($this->path . '/' . $url);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->blserver . '/pdf?token=' . getenv('BROWSERLESS_TOKEN'),
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => "{
                    \"url\": $path,\n\t
                    \"options\": {
                        \n\t\t\"landscape\": false,
                        \n\t\t\"format\": \"A4\",
                        \n\t\t\"preferCSSPageSize\": true,
                        \n\t\t\"printBackground\": true,
                        \n\t\t\"omitBackground\": false,
                        \n\t\t\"scale\": 0.45,
                        \n\t\t\"displayHeaderFooter\": false,
                        \n\t\t\"headerTemplate\": \"<div style='font-size: 8px; width: 100%; text-align: center;'><span class='date'></span></div>\",
                        \n\t\t\"footerTemplate\": \"<div style='font-size: 8px; width: 100%; text-align: center;'><span class='pageNumber'></span>/<span class='totalPages'></span></div>\"
                    \n\t},
                    \n\t\"gotoOptions\": {
                        \"waitUntil\": \"networkidle2\"
                    \n\t}
                }",
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    // 'CF-Access-Client-Id: ' . getenv('CF_ACCESS_CLIENT_ID'),
                    // 'CF-Access-Client-Secret: ' . getenv('CF_ACCESS_CLIENT_SECRET'),
                ]
            ]);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        } catch (\Throwable $th) {
            print('### pdfFromUrl error: ' . $th->getMessage() . PHP_EOL);
            throw $th;
        }
    }
}
