<?php	
	    
    //$buchdatum = $_SESSION['buchdatum'];
    
	include_once "keyvalue.php";
	require_once('./classes/database.php');
	require_once('./classes/journal.php');
	require_once('./classes/journalzeile.php');
		
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
	
	if (isset($_POST['betrag_int']))
	{
		$journal_id = $_POST['journal_id'];
		$belegnr = $_POST['belegnr'];
		$journalzeile = $_POST['journalzeile'];
		$datum = $_POST['jahr_Datum'] . "-" . $_POST['monat_Datum'] . "-" . $_POST['tag_Datum'];
		$konto = $_POST['konto'];
		$kreditor = $_POST['kreditor'];
		$ust_kz = $_POST['ust_kz'];
		$betrag_int = $_POST['betrag_int'];
		$betrag_dec = $_POST['betrag_dec'];
		$buchungstext = $_POST['buchungstext'];
		
		Journalzeile::InsertJournalLines($con, $benutzer, $passwort, $journal_id, $belegnr, $journalzeile, $datum, $datum, $konto, $kreditor, $betrag_int, $betrag_dec, $buchungstext, $vorgang);
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

<form name="journal_er" action="journal_er.php" target = "main" method= "post">
<table>
<tr>
	<td><b>Journal-Nr</b></td>
	<td>
		<?php 
		if (!isset($_POST['journal_id']))
		{
			$journal_id = Journal::GetJournalNr();
			echo "<input name=\"journal_id\" type=\"text\" size=\"5\" value=\"" . $journal_id . "\"  readonly/>";
		} 
		
		?>

</td></tr>
<tr>
	<td><b>Beleg-Nr</b></td>
		<td><input name="belegnr" type="text" size="5" /> </td>";
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
		<select name="kreditor">
<?php

		$optionen = BuildHTMLOptions("kreditoren","kreditorennr","bezeichnung");
		echo $optionen;			
?>
		</select>
	</td>
</tr>
<tr>
	<td>Haben</td>
	<td>
		<select name="konto">
<?php

		$optionen = BuildHTMLOptions("kontenstamm","kontonr","bezeichnung");
		echo $optionen;			
?>
		</select>
	</td>
</tr>
<tr>
		<td>USt</td>
		<?php
			// Liste der USt-Sätze lesen, default setzen
			echo "<td><select name=\"ust_satz\">";
			$optionen = BuildHTMLOptions("ust_saetze","ust_code","ust_satz");
			echo "</select>";
			echo "<input name=\"ust_betrag\" type=\"text\" size=\"10\" readonly/></td>";
		?>
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
		<input name="neu" type=submit value="Neue Zeile" />
		<input name="abbruch" type=submit value="Journal abbrechen (l&ouml;schen)" />
</form>
<form>
		<input name="buchen" type=submit value="Journal buchen" />
</form>
<?php
	
	if (isset($_POST['journal_id'])) Journal::GetJournalLines($journal_id, "K");
	
?>

</body>
</html>