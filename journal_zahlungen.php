<?php	
	$benutzer = "u48930";
	$passwort = "yGrG2LiBUPFikNAz9ZoBgqjnvkL8KX";
	//$buchdatum = $_POST['jahr_datum'] . "-" . $_POST['monat_datum'] . "-" . $_POST['tag_datum'];

    $con = "mysql:host=db22.variomedia.de;dbname=db48930;charset=utf8";

    if(session_status() !== PHP_SESSION_ACTIVE) 
    {
	    session_start();
		$_SESSION['con'] = $con;
		$_SESSION['benutzer'] = $benutzer;
		$_SESSION['passwort'] = $passwort;
	}
	    
    //$buchdatum = $_SESSION['buchdatum'];
    
	include_once "keyvalue.php";
	
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
		$konto = $_POST['soll'];
		$ggkonto = $_POST['haben'];
		$betrag_int = $_POST['betrag_int'];
		$betrag_dec = $_POST['betrag_dec'];
		$buchungstext = $_POST['buchungstext'];
		
		InsertJournalLines($con, $benutzer, $passwort, $journal_id, $belegnr, $journalzeile, $datum, $datum, $konto, $ggkonto, $betrag_int, $betrag_dec, $buchungstext, $vorgang);
	}
	
	function GetJournalNr()
	{
		$con = $_SESSION['con'];
		$benutzer = $_SESSION['benutzer'];
		$passwort = $_SESSION['passwort'];		

		try {
			$conn = new PDO($con, $benutzer, $passwort);

			$result = $conn->query("SELECT max(journal_id) FROM journal");
		    $row = $result->fetch();
	    	$max_id = $row[0];
			if ($max_id == '')
			{
				return 1;				
			} else {
		    	return $max_id;			
			}
		} catch (PDOException $ex) {
				echo $ex;
				return 1;
				//die('Die Datenbank ist momentan nicht erreichbar!');
		}
	}

	function GetJournalLines($journal_id, $vorgang)
	{
		$con = $_SESSION['con'];
		$benutzer = $_SESSION['benutzer'];
		$passwort = $_SESSION['passwort'];		

		
		try {
			$conn = new PDO($con, $benutzer, $passwort);

			$result = $conn->prepare("SELECT journalzeile,soll_haben,kontonr,belegnr,referenz,betrag,status,vorgang,buchungstext,buchungsdatum,belegdatum FROM journal WHERE journal_id = ? and vorgang = ?");
			$result->execute(array($journal_id,$vorgang))
	    		or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()));


			} catch (PDOException $ex) {
				echo $ex;
				die('Die Datenbank ist momentan nicht erreichbar!');
		}

			$rownum=0;
			echo "<hr>";
			echo "<h2>Journal: " . $journal_id . "</h2>";
			echo "<table>";
			echo "<tr><th>Zeile</th><th>Buchungsdatum</th><th>Belegdatum</th><th>S/H</th><th>Konto-Nr</th><th>BelegNr</th><th>Betrag</th><th>Status</th><th>Referenz</th><th>buchungstext</th></tr>";
			while ($row = $result->fetch()) {

			$rownum++;
			echo "<tr>";
				echo "<td>" . $row[0] . "</td><td>" . $row[1] . "</td><td>" . $row[2] . "</td><td>" . $row[3] . "</td><td>" . $row[4] . "</td><td>" . $row[5] . "</td><td>" . $row[6] . "</td><td>" . $row[7] . "</td><td>" . $row[8] . "</td><td>" . $row[9] . "</td>";
			echo "<tr>";

			// aufsummieren beleg javascript?

    	}
    	echo "<td><input name=\"journalzeile\" type=\"hidden\" value=\"" . $rownum+1 . "\"></td>";
		echo "</table>";
	}
	
	function InsertJournalLines($con, $benutzer, $passwort, $journal_id, $belegnr, $journalzeile, $buchungsdatum, $belegdatum, $konto, $ggkonto, $betrag_int, $betrag_dec, $buchungstext, $vorgang)
	{
		$betrag = $betrag_int + ($betrag_dec/100);
		// check Belegnr und Saldo Beleg -> mit JavaScript prüfen!
		
		// Objekt Beleg?
		try {
			$conn = new PDO($con, $benutzer, $passwort);

			$journalzeile++;
			$result = $conn->prepare("INSERT INTO journal (journal_id,journalzeile,kontonr,soll_haben,belegdatum,belegnr,betrag,buchungsdatum,buchungstext,referenz,status,vorgang) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
		$result->execute(array($journal_id,$journalzeile,$konto,"S",$belegdatum,$belegnr,$betrag,$buchungsdatum,$buchungstext,$referenz,"A","H"))
	    	or die('Fehler Insert konto');

			$journalzeile++;
			$result = $conn->prepare("INSERT INTO journal (journal_id,journalzeile,kontonr,soll_haben,belegdatum,belegnr,betrag,buchungsdatum,buchungstext,referenz,status,vorgang) values (?,?,?,?,?,?,?,?,?,?,?,?)");
		$result->execute(array($journal_id,$journalzeile,$ggkonto,"H",$belegdatum,$belegnr,$betrag,$buchungsdatum,$buchungstext,$referenz,"A","H"))
	    	or die ('Fehler INSERT ggkonto.');

			} catch (PDOException $ex) {
				echo $ex;
				die('INSERT Journallines: Die Datenbank ist momentan nicht erreichbar!');
		}


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

<form name="journal_hauptbuch" action="journal_hauptbuch.php" target = "main" method= "post">
<table>
<tr>
	<td><b>Journal-Nr</b></td>
	<td>
		<?php 
		if (!isset($_POST['journal_id']))
		{
			$journal_id = GetJournalNr();
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
		<select name="soll">
<?php

		$optionen = BuildHTMLOptions("kontenstamm","kontonr","bezeichnung");
		echo $optionen;			
?>
		</select>
	</td>
</tr>
<tr>
	<td>Haben</td>
	<td>
		<select name="haben">
<?php

		$optionen = BuildHTMLOptions("kontenstamm","kontonr","bezeichnung");
		echo $optionen;			
?>
		</select>
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
		<input name="neu" type=submit value="Neue Zeile" />
		<input name="abbruch" type=submit value="Journal abbrechen (l&ouml;schen)" />
</form>
<form>
		<input name="buchen" type=submit value="Journal buchen" />
</form>
<?php
	
	if (isset($_POST['journal_id'])) GetJournalLines($journal_id, "H");
	
?>

</body>
</html>