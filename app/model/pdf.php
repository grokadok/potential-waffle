<?php

namespace bopdev;

use Dompdf\Dompdf;
use Dompdf\Options;

class PDF
{
    private $dompdf;

    public function __construct()
    {
        $options = new Options();
        $options->set([
            'chroot' => __DIR__ . '/../pdf',
            'isFontSubsettingEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'isRemoteEnabled' => true,
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ]);
        $this->dompdf = new Dompdf(options: $options);
    }

    public function generate(String $html)
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->render();
        return $this->dompdf->output();
    }
}
