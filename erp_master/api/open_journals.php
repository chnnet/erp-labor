<?php
require_once '../classes/Database.php';
Database::initialize();

header('Content-Type: application/json');

$vorgangFilter = $_GET['vorgang'] ?? '';
$limit = (int)($_GET['limit'] ?? 10);

if ($limit <= 0 || $limit > 100) {
    $limit = 10;
}

try {
    $sql = "
        SELECT DISTINCT journal_id AS id, vorgang AS typ, status
        FROM journalzeile
        WHERE status = 'A'
    ";
    $params = [];

    if (!empty($vorgangFilter)) {
        $sql .= " AND vorgang = ?";
        $params[] = $vorgangFilter;
    }

    $sql .= " ORDER BY journal_id ASC LIMIT " . (int)$limit;

    $stmt = Database::$conn->prepare($sql);
    $stmt->execute($params);
    $journale = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($journale);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Fehler beim Laden der Journale: " . $e->getMessage()]);
}
