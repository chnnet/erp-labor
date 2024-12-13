<?php
	
	$journal = $_POST['journal'];
	
	
	function GetJournLines($journal)
	{
		$con = $_SESSION['con'];
        $benutzer = $_SESSION['benutzer'];
        $passwort = $_SESSION['passwort'];

		try {
			$conn = new PDO($con, $benutzer, $passwort);

		} catch (PDOException $ex) {
			echo $ex;
			die('Die Datenbank ist momentan nicht erreichbar!');
		}
		$result = $conn->prepare("SELECT * from journal where journal_id = " . $journal . " ORDER BY belegnr, soll_haben");
		$result->execute(array($journal))
		    or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()));

            $rownum=0;
            while ($row = $result->fetch())
            {
	            $saldo = 0;
	            
				if ($belegnr = $row[])
				{
					if ($shkz = $row[])
					{
						
					} 
				} else {
					
					$belegnr = $row[];
				}
            }

	}
?>