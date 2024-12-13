<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
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
	$buchdatum = date($_POST['tag_datum'], $_POST['monat_datum'], $_POST['tag_datum']);
	

    session_start();
    $_SESSION['con'] = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8";
    $_SESSION['benutzer'] = $benutzer;
    $_SESSION['passwort'] = $passwort;
    $_SESSION['buchdatum'] = $buchdatum;
?>
<html>
	<header>Login<header>
	<body>
		<table>
<?php
	try {
		$conn = new PDO($con, $benutzer, $passwort);

	} catch (PDOException $ex) {
		echo $ex;
		die('Die Datenbank ist momentan nicht erreichbar!');
	}
	$result = $conn->prepare("SELECT journal_id FROM journal WHERE status = 'O'");
	$result->execute()
	    or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()));

    $rownum=0;
    while ($row = $result->fetch()) {

		$rownum++;
		echo "<tr><td><a href=\"journal_hauptbuch.php?journal_id=" . $row[0] . "\">" . $row[0] . "</a><tr><td>";

    }
    
?>
</table>    

    
</body>
