<?php
require_once '../classes/Database.php';
require_once '../classes/Language.php';
Database::initialize();

// Query for top 5 open creditor balances
$stmt = Database::$conn->prepare("
    SELECT k.bezeichnung AS name, SUM(op.betrag - op.bezahlt) AS open_amount
    FROM offene_posten_kreditoren op
    JOIN kreditoren k ON op.kreditorennr = k.kreditorennr
    WHERE op.status = 'offen'
    GROUP BY k.bezeichnung
    ORDER BY open_amount DESC
    LIMIT 5
");
$stmt->execute();

$data = [
    'labels' => [],
    'data' => [],
    'title' => Language::get('top_open_creditors'),
];

$data = ['labels' => [], 'data' => []];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data['labels'][] = $row['name'];
    $data['data'][] = (float)$row['open_amount'];
}

header('Content-Type: application/json');
echo json_encode($data);

