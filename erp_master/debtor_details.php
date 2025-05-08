<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/JournalLine.php';
require_once 'classes/Language.php';

Database::initialize();

$debtorId = (int)($_GET['debitorennr'] ?? 0);
if (!$debtorId) {
    die(Language::get('debtor_not_provided'));
}

$debtor = Database::$conn
    ->query("SELECT * FROM debitoren WHERE debitorennr = $debtorId")
    ->fetch(PDO::FETCH_ASSOC);

if (!$debtor) {
    die(Language::get('debtor_not_found'));
}

$entries = JournalLine::findByAccount($debtorId);
$balance = JournalLine::calculateBalance($debtorId);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('debtor_details_heading', ['name' => $debtor['bezeichnung'], 'id' => $debtorId]) ?></title>
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    p { margin-bottom: 0.5em; }
  </style>
</head>
<body>
  <h1><?= Language::get('debtor_details_heading', ['name' => $debtor['bezeichnung'], 'id' => $debtorId]) ?></h1>

  <p><strong><?= Language::get('address') ?>:</strong> <?= htmlspecialchars($debtor['adresse'] ?? '-') ?></p>
  <p><strong><?= Language::get('email') ?>:</strong> <?= htmlspecialchars($debtor['email'] ?? '-') ?></p>
  <p><strong><?= Language::get('account_balance', ['id' => $debtorId]) ?>:</strong> <?= number_format($balance, 2) ?> €</p>

  <h2><?= Language::get('booking_history') ?></h2>

  <?php if (empty($entries)): ?>
    <p><?= Language::get('no_bookings_found') ?></p>
  <?php else: ?>
    <table>
      <tr>
        <th><?= Language::get('date') ?></th>
        <th><?= Language::get('type') ?></th>
        <th><?= Language::get('amount') ?></th>
        <th><?= Language::get('text') ?></th>
      </tr>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td><?= $entry['transaction_date'] ?></td>
          <td><?= ucfirst($entry['entry_type']) ?></td>
          <td><?= number_format($entry['amount'], 2) ?> €</td>
          <td><?= htmlspecialchars($entry['description']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <p><a href="debtor_manage.php">← <?= Language::get('back_to_overview') ?></a></p>
</body>
</html>
