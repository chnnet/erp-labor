<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<?php

class Journal
{
	private $journal_id;
	public $vorgang;
	
	function GetJournalNr()
	{
		Database::initialize();
		
		try {

			$result = Database::$conn->query("SELECT max(ID) FROM journal");
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

	function GetOpenJournals()
	{
		Database::initialize();

		$result = Database::$conn->query("SELECT journal_id FROM journalzeile WHERE status = 'A'");
		$result->execute()
				or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()[2]));
		// Datenmodell hat sicher geändert!
	
	    $rownum=0;
	    while ($row = $result->fetch()) {
	
			$rownum++;
			echo "<tr><td><a href=\"journal_hauptbuch.php?journal_id=" . $row[0] . "\">" . $row[0] . "</a><tr><td>";
	
	    }
    }
    
    // Split-Kennzeichen in der Zeile?
    function GetBelegNr($journal_id)
    {
		Database::initialize();
		
		try {

			$result = Database::$conn->query("SELECT max(belegnr) FROM journal where journal_id =" . $journal_id);
		    $row = $result->fetch();
	    	$max_id = $row[0];
			if ($max_belegnr == '')
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
    
	function Loeschen($journal_id)
	{
		$sql = "DELETE FROM journal where journal_ID = " . $journal_id;
	}
    

	function Buchen($journal_id)
	{
		$sql = "INSERT INTO hauptbuch () values ()";
	}

	
}
	
?>