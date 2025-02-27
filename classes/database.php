<?php
	//Test
	session_start();

class Database
{
    /** TRUE if static variables have been initialized. FALSE otherwise
    */
    private static $init = FALSE;
    /** The mysqli connection object
    */
    public static $conn;
    	    
    // erweitern um db bei login (globale Variable setzen) und hier als Parameter übergeben
    public static function initialize()
    {
		if (isset($_SESSION['umgebung']))
		{
			$umgebung = $_SESSION['umgebung']; // gewählte DB der Applikation bzw Test/Live-Mode;
		}
		else
		{
			$umgebung = 3;
		}

    	try
    	{
	        if (self::$init===TRUE)return;
    	    self::$init = TRUE;

	    	if ($umgebung == 1)
		        $con = new PDO('mysql:host=192.168.0.199;port=3307;dbname=datenarchiv;charset=utf8', "test", "");

         	self::$conn = $con;
        } catch (PDOException $ex)
		{
			echo $ex;
		}
    }
    
    // Select Tabellendaten in HTML-Options
	public static function GetHTMLOptions($tabelle, $key, $bezeichnung, $wert)
	{

    	Database::initialize($GLOBALS['umgebung']);

		$sql = "select " . $key . "," . $bezeichnung . " from ". $tabelle . " order by " . $key;

		$result = Database::$conn->query($sql)
			or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()[2]));

            $rownum=0;
            foreach ($result as $row) {

                    $rownum++;
                    // check Syntax 0,1 bei fetch falls notwendig
					if ($row[0] == $wert)
					{
						$HTMLstring = $HTMLstring . "<option value=\"" . $row[0] . "\" selected>" . $row[1] . "</option>";
					}
					else
					{
						$HTMLstring = $HTMLstring . "<option value=\"" . $row[0] . "\">" . $row[1] . "</option>";
					}

            }
            
		return $HTMLstring;
	}

	public static function GetHTMLOptionsMeta($tabelle)
	{

    	Database::initialize($GLOBALS['umgebung']);

		$sql = "select * from ". $tabelle . " LIMIT 1";

		$result = Database::$conn->query($sql)
			or die ('Fehler in der Abfrage. ' . htmlspecialchars($result->errorinfo()[2]));

			$cols = $result->columnCount();
			$zaehler = 0;
			while ($zaehler <  $cols)
			{
				$meta = $result->getColumnMeta($zaehler);
				echo "<option value=\"" . $meta['name'] . "\">" . $meta['name'] . "</option>";
				$zaehler++;
			}
			return $meta;
	}

	function GetEnumValues($tabelle, $feld)
	{
		Database::initialize($GLOBALS['umgebung']);

		$sth = Database::$conn->prepare("SHOW COLUMNS FROM " . $tabelle . " LIKE '" . $feld . "'");
		$sth->execute();
		if ($sth) 
		{
    		$option_array = explode("','",preg_replace("/(enum|set)\('(.+?)'\)/","\\2", $sth->fetchColumn(1)));
		}
		return $option_array;
	}
}

?>
