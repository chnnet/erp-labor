<?php	

    if(session_status() !== PHP_SESSION_ACTIVE) 
    {
	    session_start();
		$_SESSION['benutzer'] = $benutzer;
		$_SESSION['passwort'] = $passwort;
	}
	    
	include_once "keyvalue.php";

	require_once('./classes/database.php');
	require_once('./classes/journal.php');
	require_once('./classes/journalzeile.php');
    //$buchdatum = $_SESSION['buchdatum'];
    
	
	global $form_status;
	global $con;
	global $benutzer;
	global $passwort;
	global $tsaldo;
	global $betrag;
	global $ggbetrag;
	global $ggkonto;
	global $transaktions_id;
	global $datum;
	global $text;

	//$buchungsdatum = $_SESSION['buchdatum'];
	
	// mit Journal ID als Parameter
	if ($_POST['action'] == 'loeschen')
	{
		//Journal::L
	}
	
	// JournalNr abfragen oder neues Journal
	// !!! Test ob korrekt in allen Fällen
	if (isset($_POST['journal_id']) && !isset($_POST['betrag_int']))
	{
		$journal_id = $_POST['journal_id'];
	}
	else
	{
		$journal_id = Journal::GetJournalNr();
	}		


	
	if (isset($_POST['betrag_int']))
	{
		$oJournalzeile = new Journalzeile();

		
		$oJournalzeile->$journal_id = $_POST['journal_id'];
		$oJournalzeile->$belegnr = $_POST['belegnr'];
		$oJournalzeile->$journalzeile = $_POST['journalzeile'];
		$oJournalzeile->$datum = $_POST['jahr_Datum'] . "-" . $_POST['monat_Datum'] . "-" . $_POST['tag_Datum'];
		$oJournalzeile->$konto = $_POST['soll'];
		$oJournalzeile->$ggkonto = $_POST['haben'];
		$betrag = $_POST['betrag_dec'] * 100 + $_POST['betrag_int'];
		$oJournalzeile->$betrag = $betrag;
		$oJournalzeile->$buchungstext = $_POST['buchungstext'];

		Journalzeile::InsertJournalLines($oJournalzeile, "H");
	}
	

?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Journal - Hauptbuch</title>
	</head>
<body>

<h1>Journal - Hauptbuch</h1>

<?php
	// Vorher unten nach der Form, Test warum journal ID nicht gesetzt
	if (isset($journal_id)) Journalzeile::GetJournalLines($journal_id, "H");
	
?>


<form name="journal_hauptbuch" action="journal_hauptbuch.php" target = "main" method= "post">
<?php 
	echo "<input name=\"journal_id\" type=\"hidden\" size=\"5\" value=\"" . $journal_id . "\">";
?>

<table>
<tr>
	<td><b>Beleg-Nr</b></td>
		<td><input name="belegnr" type="text" size="5" /> </td>
</td></tr>
<tr>
		<td><b>Datum</b></td>
		<td>
		<select name="tag_Datum">
		<?php
			$tag = date("d");
			$monat = date("m");
			$jahr = date("Y");

			$i = 1;
			while ($i < 32)
			{
				if ($i == $tag)
				{
					echo "<option value = \"" . $i . "\" selected>" . $i . "</option>";
				}
				else {
					echo "<option value = \"" . $i . "\" >" . $i . "</option>";
				}
				$i++;
			}
		?>
		</select>
		<select name="monat_Datum">
		<?php
			$i = 1;
			while ($i < 13)
			{
				if ($i == $monat)
				{
					echo "<option value = \"" . $i . "\" selected>" . $i . "</option>";
				}
				else {
					echo "<option value = \"" . $i . "\" >" . $i . "</option>";
				}
				$i++;
			}
		?>
		</select>
		<?php
			echo "<input type=text name=\"jahr_Datum\" size=\"5\" maxlength=\"4\" value=\"" . $jahr . "\" />";
		?>
		</td>
</tr>
<tr>
	<td>Soll</td>
	<td>
		<input list="kontensoll" name="soll">
		<datalist id="kontensoll">
<?php

		$optionen = BuildHTMLOptions("kontenstamm","kontonr","bezeichnung");
		echo $optionen;			
?>
		</datalist>
		</input>
	</td>
</tr>
<tr>
	<td>Haben</td>
	<td>
		<input list="kontenhaben" name="haben">
		<datalist id="kontenhaben">
<?php

		$optionen = BuildHTMLOptions("kontenstamm","kontonr","bezeichnung");
		echo $optionen;			
?>
		</datalist>
		</input>
	</td>
</tr>
<tr>
		<td>Betrag</td>
		<td><input name="betrag_int" type="text" size="7" />,<input name="betrag_dec" type="text" size="2" /></td>
</tr>
<tr>
		<td>Buchungstext</td>
		<td><input name="buchungstext" type="text" size=30 maxlength=50></td>
</tr>
</table>
<br>
</table>
<br>
		<button type=submit>Neue Zeile</button><br>
		<button type=submit value="loeschen">Journal l&ouml;schen</button><br>
</form>
<form>
		<button type=submit value="buchen">Journal buchen</button>
</form>


</body>
</html>