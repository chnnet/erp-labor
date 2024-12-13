<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<?php

class Journalzeile
{
	function GetJournalLines($journal_id, $vorgang)
	{
		$con = $_SESSION['con'];
		$benutzer = $_SESSION['benutzer'];
		$passwort = $_SESSION['passwort'];		

		
		try {
			Database::initialize();

			$result = Database::$conn->prepare("SELECT journal_id,journalzeile,kontosoll,kontohaben,belegnr,referenz,betrag,status,vorgang,buchungstext,buchungsdatum,belegdatum FROM journalzeile WHERE journal_id = ? and vorgang = ?");
			$result->execute(array($journal_id,$vorgang))
	    		or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()));


			} catch (PDOException $ex) {
				echo $ex;
				die('Die Datenbank ist momentan nicht erreichbar!');
		}

			$rownum=0;
			echo "<hr>";
			echo "<h2>Journal: " . $journal_id . "</h2>";
			echo "<table border = 1>";
			echo "<tr><th>JournalID</th><th>Zeile</th><th>Soll</th><th>Haben</th><th>BelegNr</th><th>Referenz</th><th>Betrag</th><th>Status</th><th>Vorgang</th><th>buchungstext</th><th>Buchungsdatum</th><th>Belegdatum</th></tr>";
			while ($row = $result->fetch()) {

			$rownum++;
			echo "<tr>";
				echo "<td>" . $row[0] . "</td><td>" . $row[1] . "</td><td>" . $row[2] . "</td><td>" . $row[3] . "</td><td>" . $row[4] . "</td><td>" . $row[5] . "</td><td>" . $row[6] . "</td><td>" . $row[7] . "</td><td>" . $row[8] . "</td><td>" . $row[9] . "</td><td>" . $row[10] . "</td><td>" . $row[11] . "</td><td>";
			echo "<tr>";

			// aufsummieren beleg javascript?

    	}
    	echo "<td><input name=\"journalzeile\" type=\"hidden\" value=\"" . $rownum . "\"></td>";
		echo "</table>";
	}
	
	// To Do Vorgang mitgeben
	// alt $con, $benutzer, $passwort, $journal_id, $belegnr, $journalzeile, $buchungsdatum, $belegdatum, $konto, $ggkonto, $betrag_int, $betrag_dec, $buchungstext, $vorgang
	function InsertJournalLines($oJournalzeile, $vorgang)
	{
		// Im Dialog richtig mitgeben
		$betrag = $betrag_int + ($betrag_dec/100);
		// check Belegnr und Saldo Beleg -> mit JavaScript prüfen!
		
		// Objekt Beleg?
		try
		{
			Database::initialize();

// Benutzer mitschreiben, alles auf Objekte umstellen
			$journalzeile++;
			$result = Database::$conn->prepare("INSERT INTO journalzeile (journal_id,journalzeile,kontosoll,kontohaben,belegnr,referenz,betrag,status,vorgang,buchungstext,buchungsdatum,belegdatum) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
			$result->execute(array($oJournalzeile->$journal_id,$oJournalzeile->$journalzeile,$oJournalzeile->$konto,$oJournalzeile->$ggkonto,$oJournalzeile->$belegnr,0,$oJournalzeile->$betrag,"A",$vorgang,$oJournalzeile->$buchungstext,$oJournalzeile->$buchungsdatum,$oJournalzeile->$belegdatum))
	    		or die('Fehler Insert journalzeile!');

			} catch (PDOException $ex) {
				echo $ex;
				die('INSERT Journallines: Die Datenbank ist momentan nicht erreichbar!');
		}


	}
	
		// ER !!! check Integrieren oder eigene Funktion	
	function InsertJournalLinesER($con, $benutzer, $passwort, $journal_id, $belegnr, $journalzeile, $buchungsdatum, $belegdatum, $konto, $kreditor, $betrag_int, $betrag_dec, $buchungstext, $vorgang)
	{
		$betrag = $betrag_int + ($betrag_dec/100);
		// check Belegnr und Saldo Beleg -> mit JavaScript prüfen!
		
		// Objekt Beleg?
		try {
			$conn = new PDO($con, $benutzer, $passwort);

			// erweitern um kontotyp um Kreditorenkonten etc korrekt abzuhandeln, Ust als eigene Journalzeile ergänzen
			$journalzeile++;
			$result = $conn->prepare("INSERT INTO journal (journal_id,journalzeile,kontonr,soll_haben,belegdatum,belegnr,betrag,buchungsdatum,buchungstext,referenz,status,vorgang) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
		$result->execute(array($journal_id,$journalzeile,$kreditor,"S",$belegdatum,$belegnr,$betrag,$buchungsdatum,$buchungstext,$referenz,"A","K"))
	    	or die('Fehler Insert kreditor');

			$journalzeile++;
			$result = $conn->prepare("INSERT INTO journal (journal_id,journalzeile,kontonr,soll_haben,belegdatum,belegnr,betrag,buchungsdatum,buchungstext,referenz,status,vorgang) values (?,?,?,?,?,?,?,?,?,?,?,?)");
		$result->execute(array($journal_id,$journalzeile,$konto,"H",$belegdatum,$belegnr,$betrag,$buchungsdatum,$buchungstext,$referenz,"A","K"))
	    	or die ('Fehler INSERT konto.');

			// USt einfügen Konto zu Satz holen und einfügen
			$journalzeile++;
			$result = $conn->prepare("INSERT INTO journal (journal_id,journalzeile,kontonr,soll_haben,belegdatum,belegnr,betrag,buchungsdatum,buchungstext,referenz,status,vorgang) values (?,?,?,?,?,?,?,?,?,?,?,?)");
		$result->execute(array($journal_id,$journalzeile,$konto,"H",$belegdatum,$belegnr,$betrag,$buchungsdatum,$buchungstext,$referenz,"A","K"))
	    	or die ('Fehler INSERT USt.');

			} catch (PDOException $ex) {
				echo $ex;
				die('INSERT Journallines: Die Datenbank ist momentan nicht erreichbar!');
		}


	}

}
	
?>