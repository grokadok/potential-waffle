<?php
$response->header("Content-Type", "text/html; charset=utf-8");
$jsfiles = getenv('ISLOCAL') ? [
    'js/quicksort.js',
    'js/navigation.js',
    'js/basic_functions.js',
    'js/fscreen.js',
    'js/fetch.js',
    'js/fields.js',
    'vendor/quill/quill.js',
    'vendor/fullCalendar/a_main.js',
    'vendor/fullCalendar/locales-all.js',
    'vendor/intlTelInput/js/intlTelInput.js',
    'vendor/intlTelInput/js/utils.js',
    'js/login.js',
    'js/websocket.js',
    'js/themes.js',
    'js/tabs.js',
    'js/btn.js',
    'js/boptable.js',
    'js/bopchat.js',
    'js/bopcal.js',
    'js/bopalert.js',
    'js/modal.js',
    'js/pages.js',
    'js/main.js',
] : ['js/custom.min.js', 'js/vendor.min.js'];
$jsstring = '';
foreach ($jsfiles as $jsfile) {
    $jsstring .= '<script defer src="./assets/' . $jsfile . '"></script>';
}
$main =
    '<!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="author" content="Leneveu Romain, romain@seadesk.fr">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="application-name" content="Seadesk">
        <meta name="apple-mobile-web-app-title" content="Seadesk">
        <meta name="msapplication-starturl" content="index.php">
        <title>SeaDesk</title>
        <link rel="stylesheet" href="./assets/css/style.css">
        <link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
        ' . $jsstring . '
    </head>
    <body class="loading ' .
    $theme .
    '">
        <div class="login hidden loading">
        </div>
        <nav class="navbar left hidden loading">
        </nav>
        <nav class="topbar hidden loading" ' .
    $session .
    '>
        </nav>
        <div id="chat" class="loading hidden"></div>
        <div id="calendar" class="loading hidden"></div>
        <main class="hidden loading">
        </main>
        <div class="modal-container"></div>
        <dialog class="msg fadeout">
            <div>
                <span>Rogntudju !</span>
                <div>
                    <button class="danger">annuler</button>
                    <button class="success">ok</button>
                    <button class="info">more ok</button>
                </div>
            </div>
        </dialog>
    </body>
    </html>';

$response->end($main);
