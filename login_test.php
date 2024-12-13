<?php

// var_dump($_POST);
// User pwd holen aus Form index.php
// Anspassungen an Hosting, da nur ein DB-User
$app_benutzer = $_POST['loginname'];
$app_passwort = $_POST['password'];
$host = $_POST['host'];
$dbname = $_POST['dbname'];
$benutzer = "u48930";
$passwort = "yGrG2LiBUPFikNAz9ZoBgqjnvkL8KX";

// DB-Connection
try {
	$con = new PDO('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8', $benutzer, $passwort);
} catch (PDOException $ex) {
	die('Die Datenbank ist momentan nicht erreichbar!');
}
// Anpassung an Hosting
$ben_menu = $con->prepare("SELECT keynr,password,berechtigungsgruppe FROM benutzer WHERE login='" . $app_benutzer . "'");
$ben_menu->execute(array($app_benutzer))
	or die('Fehler bei Abfrage Benutzer');
if ($ben_menu->rowCount() < 1) {
exit("Benutzer " . $_POST["loginname"] . " nicht gefunden.");
}
else {
    // Passwort prüfen
    $row = $ben_menu->fetch();
    //var_dump($row);
    $chkpwd = $row[1];
    $keynr = $row[0];
    $ben_menu->closeCursor(); // check
    // Anpassung an Hosting
    //if ($app_passwort == $chkpwd) {
    if (password_verify($app_passwort, $chkpwd)) {
        
        session_start();
        $_SESSION['con'] = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8, " . $benutzer ", " . $passwort;
        
		echo "<!DOCTYPE html>";
		echo "<html>";
		echo "<head>";
		echo "<title>ERP-Labor</title>";
/*
		echo "<script>";		
		echo "function loadDoc(url) {";
		echo "var xhttp = new XMLHttpRequest();";
		echo "xhttp.open("GET", url, true);";
		echo "xhttp.send();";
		echo "}";
		echo "<script>";		
*/		
		echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"chnnet.css\">";
		echo "</head>";
		echo "<ul class=\"topnav\">";
		include ("menu.php");
		echo "</ul>";
		echo "<iframe name=\"main\" height=\"600\" width=\"1000\" frameborder=\"0\"></iframe>";
		echo "</body>";
		echo "</html>";
    }
    else {
        echo "<html>";
        echo "<head><title>Login-page</title><head>";
        echo "<body>";
        echo "Falsches Passwort/Login!";
        echo "</body>";
        echo "</html>";
    }
}
?>