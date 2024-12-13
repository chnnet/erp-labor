<?php

	function BuildHTMLOptions($tabelle, $id, $bezeichnung)
	{

		Database::initialize();
		
		$result = Database::$conn->prepare("select " . $id . "," . $bezeichnung . " from " . $tabelle ." order by " . $id);
		$result->execute(array($id, $bezeichnung, $tabelle))
		    or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()));

            $rownum=0;
            while ($row = $result->fetch()) {

                    $rownum++;
                    // dann zu Klasse als Funktion
                    $jsstring = $jsstring . "<option value=\"" . $row[0] . "\">" . $row[0] . "|" . $row[1] . "</option>";

            }
            
		return $jsstring;
	}
?>