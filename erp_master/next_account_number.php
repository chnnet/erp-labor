<?php
require_once 'classes/Database.php';
Database::initialize();

$type = $_GET['typ'] ?? '';

$range = [
    'B' => [1000, 1999], // Balance accounts
    'K' => [4000, 7999], // Cost/expense accounts
    'E' => [8000, 8999], // Revenue accounts
    'Z' => [9000, 9999]  // Intermediate/temporary
];

if (!isset($range[$type])) {
    http_response_code(400);
    echo '';
    exit;
}

[$min, $max] = $range[$type];

$stmt = Database::$conn->prepare("
    SELECT MAX(kontonr)
    FROM kontenstamm
    WHERE typ = ? AND kontonr BETWEEN ? AND ?
");
$stmt->execute([$type, $min, $max]);

$maxUsed = (int) $stmt->fetchColumn();

// Output next available number or starting point
echo $maxUsed > 0 ? $maxUsed + 1 : $min;
