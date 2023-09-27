<?php

namespace bopdev;

class Browserless
{
    private $blserver;
    private $path;

    public function __construct()
    {
        $this->blserver = getenv('BROWSERLESS_HOST');
        $this->path = getenv('SERVER_URL');
    }

    public function pdfFromHtml(string $html)
    {
        try {
            // $html = json_encode(str_replace('__DIR__', $this->path, $html));
            $html = json_encode($html);
            // print('### pdfFromHtml: html START' . PHP_EOL);
            // var_dump($html);
            // print('### pdfFromHtml: html END' . PHP_EOL);
            $ch = curl_init();
            print('### pdfFromHtml 1' . PHP_EOL);
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->blserver . '/pdf',
                CURLOPT_PORT => 8083,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => "{
                    \"html\": $html,
                    \n\t\"options\": {
                        \n\t\t\"landscape\": false,
                        \n\t\t\"format\": \"A4\",
                        \n\t\t\"preferCSSPageSize\": true,
                        \n\t\t\"printBackground\": true,
                        \n\t\t\"omitBackground\": false,
                        \n\t\t\"scale\": 0.45,
                        \n\t\t\"displayHeaderFooter\": false,
                        \n\t\t\"headerTemplate\": \"<div style='font-size: 8px; width: 100%; text-align: center;'><span class='date'></span></div>\",
                        \n\t\t\"footerTemplate\": \"<div style='font-size: 8px; width: 100%; text-align: center;'><span class='pageNumber'></span>/<span class='totalPages'></span></div>\"
                    \n\t}
                }",
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            print('### pdfFromHtml 2' . PHP_EOL);
            $result = curl_exec($ch);
            print('### pdfFromHtml 3' . PHP_EOL);
            curl_close($ch);
            return $result;
        } catch (\Throwable $th) {
            print('### pdfFromHtml error: ' . $th->getMessage() . PHP_EOL);
            throw $th;
        }
    }

    public function pdfFromUrl(string $url)
    {
        try {
            print('### pdfFromUrl: ' . $this->path . $url . PHP_EOL);
            $path = json_encode($this->path . '/' . $url);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->blserver . '/pdf',
                CURLOPT_PORT => 8083,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => "{
                    \"url\": $path,
                    \n\t\"options\": {
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
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
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
