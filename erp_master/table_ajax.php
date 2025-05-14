<?php
require_once 'classes/Database.php';
require_once 'classes/Language.php';

Database::initialize();

$pdo = Database::getConnection();
$action = $_GET['action'] ?? '';
$tabelle = $_GET['tabelle'] ?? '';
$feld = $_GET['feld'] ?? '';
$wert = $_GET['wert'] ?? '';
$likeSearch = ($_GET['like'] ?? '') === 'true';

// Validierung des Tabellennamens (nur Buchstaben, Zahlen, Unterstriche)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabelle)) {
    exit(Language::get('invalid_table_selection'));
}

// Dynamische Whitelist aus der Datenbank generieren
$stmt = $pdo->query("SHOW TABLES");
$allowedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Tabelle nicht vorhanden
if (!in_array($tabelle, $allowedTables)) {
    exit(Language::get('table_not_allowed'));
}

function validateField(string $feld, array $validFields): bool {
    return in_array($feld, $validFields);
}

// 📦 Aktion: Felder der Tabelle abrufen
if ($action === 'felder') {
    $stmt = $pdo->query("SELECT * FROM `$tabelle` LIMIT 1");
    $felder = [];

    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $fieldName = $meta['name'];
        $enumWerte = Database::GetEnumValues($tabelle, $fieldName);
        $felder[] = [
            'name' => $fieldName,
            'enum' => $enumWerte
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($felder);
    exit;
}

// 📦 Aktion: Daten anzeigen
if ($action === 'daten') {
    header('Content-Type: text/html; charset=utf-8');

    $descStmt = $pdo->query("DESCRIBE `$tabelle`");
    $validFields = $descStmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT * FROM `$tabelle`";
    $params = [];

    if ($feld && $wert !== '' && validateField($feld, $validFields)) {
        if ($likeSearch) {
            $sql .= " WHERE `$feld` LIKE ?";
            $params[] = '%' . $wert . '%';
        } else {
            $sql .= " WHERE `$feld` = ?";
            $params[] = $wert;
        }
    }

    // Optional: sortieren nach Feld
    if ($feld && validateField($feld, $validFields)) {
        $sql .= " ORDER BY `$feld` ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<p>" . Language::get('no_records_found', ['table' => $tabelle]) . "</p>";
        exit;
    }

    echo "<table><tr>";
    foreach (array_keys($rows[0]) as $col) {
        echo "<th>" . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . "</th>";
    }
    echo "</tr>";

    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            $anzeige = htmlspecialchars((string)$val);
            if (strlen($anzeige) > 100) {
                $anzeige = substr($anzeige, 0, 100) . '...';
            }
            echo "<td>" . $anzeige . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
