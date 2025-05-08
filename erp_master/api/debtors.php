<?php
require_once '../classes/Database.php';
require_once '../classes/Language.php';
Database::initialize();

$stmt = Database::$conn->prepare("
    SELECT d.bezeichnung AS name, SUM(op.betrag - op.bezahlt) AS offen
    FROM offene_posten_debitoren op
    JOIN debitoren d ON op.debitorennr = d.debitorennr
    WHERE op.status = 'offen'
    GROUP BY d.bezeichnung
    ORDER BY offen DESC
    LIMIT 5
");
$stmt->execute();

$data = [
    'labels' => [],
    'data' => [],
    'title' => Language::get('top_open_debtors'),
];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data['labels'][] = $row['name'];
    $data['data'][] = (float)$row['offen'];
}

header('Content-Type: application/json');
echo json_encode($data);
