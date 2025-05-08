<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
Database::initialize();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = Database::$conn;
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM offene_posten_kreditoren";
$params = [];
if ($statusFilter) {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY buchungsdatum DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posten = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query("
  SELECT status, COUNT(*) AS anzahl
  FROM offene_posten_kreditoren
  GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('open_items_creditor_title') ?></title>
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    th { background: #eee; }
    .summary { margin-top: 1em; font-weight: bold; }
  </style>
</head>
<body>
<h1>📁 <?= Language::get('open_items_creditor_title') ?></h1>

<form method="get">
  <label><?= Language::get('filter_status') ?>:
    <select name="status" onchange="this.form.submit()">
      <option value=""><?= Language::get('all') ?></option>
      <option value="offen" <?= $statusFilter === 'offen' ? 'selected' : '' ?>><?= Language::get('status_open') ?></option>
      <option value="bezahlt" <?= $statusFilter === 'bezahlt' ? 'selected' : '' ?>><?= Language::get('status_paid') ?></option>
    </select>
  </label>
</form>

<p class="summary">
  📊 <?= Language::get('summary') ?>:
  <?= Language::get('status_open') ?>: <?= $summary['offen'] ?? 0 ?> |
  <?= Language::get('status_paid') ?>: <?= $summary['bezahlt'] ?? 0 ?>
</p>

<table>
  <tr>
    <th><?= Language::get('invoice_number') ?></th>
    <th><?= Language::get('creditor') ?></th>
    <th><?= Language::get('amount') ?> (€)</th>
    <th><?= Language::get('paid') ?> (€)</th>
    <th><?= Language::get('due_date') ?></th>
    <th><?= Language::get('status') ?></th>
    <th><?= Language::get('note') ?></th>
  </tr>
  <?php foreach ($posten as $zeile): ?>
    <tr>
      <td><?= htmlspecialchars($zeile['rechnungsnr']) ?></td>
      <td><?= $zeile['kreditorennr'] ?></td>
      <td><?= number_format($zeile['betrag'], 2) ?></td>
      <td><?= number_format($zeile['bezahlt'], 2) ?></td>
      <td><?= $zeile['faelligkeit'] ?></td>
      <td style="color:<?= $zeile['status'] === 'bezahlt' ? 'green' : 'red' ?>">
        <?= Language::get('status_' . strtolower($zeile['status'])) ?>
      </td>
      <td><?= htmlspecialchars($zeile['bemerkung']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
</body>
</html>
