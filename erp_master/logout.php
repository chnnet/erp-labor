<?php

// Alle Session-Variablen löschen
$_SESSION = [];

// Session-Cookie löschen (optional, aber empfohlen)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Session beenden
session_destroy();

// Zur Login-Seite weiterleiten
header("Location: login.php");
exit;
