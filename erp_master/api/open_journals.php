<?php
require_once '../classes/Database.php';
Database::initialize();

header('Content-Type: application/json');

$vorgangFilter = $_GET['vorgang'] ?? '';
$limit = (int)($_GET['limit'] ?? 10);

// Begrenzung für Sicherheit
if ($limit <= 0 || $limit > 100) {
    $limit = 10;
}

try {
    $sql = "
SELECT 
    j.ID AS id,
    j.vorgang AS typ,
    j.datum,
    j.referenz,
    COALESCE(
        (SELECT MIN(z.status) FROM journalzeile z WHERE z.journal_id = j.ID),
        '–'
    ) AS status
FROM journal j
    ";
    $params = [];
    $conditions = [];

    if (!empty($vorgangFilter)) {
        $conditions[] = "j.vorgang = ?";
        $params[] = $vorgangFilter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY j.ID DESC LIMIT " . (int)$limit;

    $stmt = Database::$conn->prepare($sql);
    $stmt->execute($params);
    $journale = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($journale);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Fehler beim Laden der Journale: " . $e->getMessage()]);
}
