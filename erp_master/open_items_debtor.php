<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';

Database::initialize();

$pdo = Database::$conn;
$statusFilter = $_GET['status'] ?? '';

// Filter anwenden
$sql = "SELECT * FROM offene_posten_debitoren";
$params = [];
if ($statusFilter) {
    $sql .= " WHERE status != 'bezahlt'";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY buchungsdatum DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zusammenfassung
$summary = $pdo->query("
  SELECT status, COUNT(*) AS anzahl
  FROM offene_posten_debitoren
  GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('open_items_debtor_title') ?></title>
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    th { background: #eee; }
    .summary { margin-top: 1em; font-weight: bold; }
    .btn { padding: 4px 8px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; }
    .btn:hover { background: #005fa3; }
  </style>
</head>
<body>
<h1>📂 <?= Language::get('open_items_debtor_title') ?></h1>

<form method="get">
  <label><?= Language::get('filter_by_status') ?>:
    <select name="status" onchange="this.form.submit()">
      <option value=""><?= Language::get('all') ?></option>
      <option value="offen" <?= $statusFilter === 'offen' ? 'selected' : '' ?>><?= Language::get('status_open') ?></option>
      <option value="teilbezahlt" <?= $statusFilter === 'teilbezahlt' ? 'selected' : '' ?>><?= Language::get('status_partially_paid') ?></option>
      <option value="bezahlt" <?= $statusFilter === 'bezahlt' ? 'selected' : '' ?>><?= Language::get('status_paid') ?></option>
    </select>
  </label>
</form>

<p class="summary">
  📊 <?= Language::get('summary') ?>:
  <?= Language::get('status_open') ?>: <?= $summary['offen'] ?? 0 ?> |
  <?= Language::get('status_partially_paid') ?>: <?= $summary['teilbezahlt'] ?? 0 ?> |
  <?= Language::get('status_paid') ?>: <?= $summary['bezahlt'] ?? 0 ?>
</p>

<table>
  <tr>
    <th><?= Language::get('invoice_number') ?></th>
    <th><?= Language::get('debtor') ?></th>
    <th><?= Language::get('amount') ?> (€)</th>
    <th><?= Language::get('paid') ?> (€)</th>
    <th><?= Language::get('due_date') ?></th>
    <th><?= Language::get('status') ?></th>
    <th><?= Language::get('note') ?></th>
    <th><?= Language::get('action') ?></th>
  </tr>
  <?php foreach ($posten as $zeile): ?>
    <tr>
      <td><?= htmlspecialchars($zeile['rechnungsnr']) ?></td>
      <td><?= $zeile['debitorennr'] ?></td>
      <td><?= number_format($zeile['betrag'], 2) ?></td>
      <td><?= number_format($zeile['bezahlt'], 2) ?></td>
      <td><?= $zeile['faelligkeit'] ?></td>
      <td style="color:
        <?= 
          $zeile['status'] === 'bezahlt' ? 'green' :
          ($zeile['status'] === 'teilbezahlt' ? 'orange' : 'red')
        ?>">
        <?= Language::get('status_' . $zeile['status']) ?>
      </td>
      <td><?= htmlspecialchars($zeile['bemerkung']) ?></td>
      <td>
        <?php if ($zeile['status'] !== 'bezahlt'): ?>
          <a class="btn" href="debitor_ausgleichen.php?rechnungsnr=<?= urlencode($zeile['rechnungsnr']) ?>">
            <?= Language::get('settle') ?>
          </a>
        <?php else: ?>
          ✔️
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</body>
</html>
