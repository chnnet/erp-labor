<?php

// var_dump($_POST);
// User pwd holen aus Form index.php
// Anspassungen an Hosting, da nur ein DB-User

	require_once('./classes/database.php');
	require_once('./classes/journal.php');

	$app_benutzer = $_POST['loginname'];
	$app_passwort = $_POST['password'];
	$buchdatum = $_POST['jahr_datum'] . "-" . $_POST['monat_datum'] . "-" . $_POST['tag_datum'];
	

    if(session_status() !== PHP_SESSION_ACTIVE) 
    {
	    session_start();
    	$_SESSION['benutzer'] = $app_benutzer;
	    $_SESSION['passwort'] = $app_passwort;
    	$_SESSION['buchdatum'] = $buchdatum;
	}
?>
<!DOCTYPE html>
<html>
	<head><meta charset="utf-8"></head>
	<title>Journal Auswahl</title>
<body>
<table>
<?php
	Journal::GetOpenJournals();
    
?>
</table>    
<form name="journal_auswahl" action="journal_hauptbuch.php" target = "main" method= "post">
<input name="neu" type=submit value="Neues Hauptbuch-Journal" />
</form>
</table>    
<form name="journal_ER" action="journal_er.php" target = "main" method= "post">
<input name="neu" type=submit value="Neues ER-Journal" />
</form>
</table>    
<form name="journal_anlagen" action="journal_anlagen.php" target = "main" method= "post">
<input name="neu" type=submit value="Neues Anlagen-Journal" />
</form>
</table>    
<form name="journal_auswahl" action="journal_zahlungen.php" target = "main" method= "post">
<input name="neu" type=submit value="Neues Zahlungen-Journal" />
</form>
</body>
