<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

include "header.php";
require_once 'classes/Database.php';
require_once 'classes/JournalLine.php';
require_once 'classes/Language.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
Database::initialize();

$creditorId = (int)($_GET['kreditorennr'] ?? 0);
$creditor = null;
$entries = [];

if ($creditorId > 0) {
    $stmt = Database::$conn->prepare("SELECT * FROM kreditoren WHERE kreditorennr = ?");
    $stmt->execute([$creditorId]);
    $creditor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($creditor) {
        $entries = JournalLine::findByAccount($creditorId);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('creditor') ?> <?= $creditorId ?> – <?= Language::get('details') ?></title>
  <style>
    table {
      border-collapse: collapse;
      width: 100%;
      margin-top: 1em;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px;
    }
    .danger { color: red; }
  </style>
</head>
<body>

<h1><?= Language::get('creditor_details_heading', ['name' => htmlspecialchars($creditor['bezeichnung'] ?? ''), 'id' => $creditorId]) ?></h1>

<?php if (!$creditor): ?>
  <p class="danger"><?= Language::get('creditor_not_found') ?></p>
<?php else: ?>
  <p><strong><?= Language::get('address') ?>:</strong> <?= htmlspecialchars($creditor['adresse'] ?? '-') ?></p>
  <p><strong><?= Language::get('email') ?>:</strong> <?= htmlspecialchars($creditor['email'] ?? '-') ?></p>

  <h3><?= Language::get('booking_history') ?></h3>

  <?php if (empty($entries)): ?>
    <p><?= Language::get('no_bookings_found') ?></p>
  <?php else: ?>
    <table>
      <tr>
        <th><?= Language::get('date') ?></th>
        <th><?= Language::get('type') ?></th>
        <th><?= Language::get('account') ?></th>
        <th><?= Language::get('amount') ?></th>
        <th><?= Language::get('description') ?></th>
      </tr>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td><?= $entry['buchungsdatum'] ?? $entry['datum'] ?></td>
          <td><?= $entry['typ'] ?? $entry['entry_type'] ?></td>
          <td><?= $entry['konto'] ?? $entry['kontosoll'] ?? $entry['kontohaben'] ?></td>
          <td><?= number_format($entry['betrag'] ?? $entry['amount'], 2) ?> €</td>
          <td><?= htmlspecialchars($entry['buchungstext'] ?? $entry['description'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>

</body>
</html>
