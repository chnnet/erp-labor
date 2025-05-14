<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'Language.php';

class Database
{
    private static $init = false;
    public static $conn;

    public static function initialize()
    {
        if (self::$init === true) return;
        self::$init = true;

        // Lokale Umgebung (1 = entfernt, 2 = lokal)
        $umgebung = 2;

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 10
            ];

            if ($umgebung == 1) {
                self::$conn = new PDO(
                    'mysql:host=192.168.0.199;port=3307;dbname=ERPLabor;charset=utf8',
                    'test',
                    '',
                    $options
                );
            } else {
                self::$conn = new PDO(
                    'mysql:host=localhost;port=3307;dbname=ERPLabor;charset=utf8',
                    'erpapp',
                    'hkYqpqTR55l6P0q',
                    $options
                );
            }
        } catch (PDOException $ex) {
            die("❌ " . Language::get('db_connection_error') . ": " . htmlspecialchars($ex->getMessage()));
        }
    }

    public static function getConnection()
    {
        self::initialize();
        return self::$conn;
    }

    public static function GetHTMLOptions($tabelle, $key, $bezeichnung, $wert)
    {
        self::initialize();
        $sql = "SELECT $key, $bezeichnung FROM $tabelle ORDER BY $key";

        $result = self::$conn->query($sql)
            or die(Language::get('db_query_error') . ' ' . htmlspecialchars($result->errorinfo()[2]));

        $HTMLstring = "";
        foreach ($result as $row) {
            $selected = ($row[0] == $wert) ? "selected" : "";
            $HTMLstring .= "<option value=\"" . $row[0] . "\" $selected>" . $row[1] . "</option>";
        }

        return $HTMLstring;
    }

    public static function GetHTMLOptionsMeta($tabelle)
    {
        self::initialize();
        $sql = "SELECT * FROM $tabelle LIMIT 1";

        $result = self::$conn->query($sql)
            or die(Language::get('db_query_error') . ' ' . htmlspecialchars($result->errorinfo()[2]));

        $cols = $result->columnCount();
        for ($i = 0; $i < $cols; $i++) {
            $meta = $result->getColumnMeta($i);
            echo "<option value=\"" . $meta['name'] . "\">" . $meta['name'] . "</option>";
        }

        return true;
    }

    public static function GetEnumValues($tabelle, $feld)
    {
        self::initialize();
        $sth = self::$conn->prepare("SHOW COLUMNS FROM $tabelle LIKE '$feld'");
        $sth->execute();
        if ($sth) {
            $option_array = explode("','", preg_replace("/(enum|set)\('(.+)'\)/", "\\2", $sth->fetchColumn(1)));
        }
        return $option_array ?? [];
    }
}
