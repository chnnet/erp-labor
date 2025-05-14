<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
require_once 'classes/ChartOfAccounts.php';

Database::initialize();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$lang = $_SESSION['lang'] ?? 'de';
$selectedKonto = $_GET['konto'] ?? '';

$allKonten = ChartOfAccounts::all();
$konten = $selectedKonto !== '' ? array_filter($allKonten, fn($k) => $k['kontonr'] == $selectedKonto) : $allKonten;
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Language::get('account_overview_title') ?></title>
  <style>
    table {
      border-collapse: collapse;
      width: 100%;
      margin-top: 1em;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
    }
    th {
      background-color: #f0f0f0;
    }
    .negativ {
      color: crimson;
    }
    .total-row {
      font-weight: bold;
      background-color: #f9f9f9;
    }
    .text-right {
      text-align: right;
    }
    form {
      margin-bottom: 1em;
    }
  </style>
</head>
<body>

<h2>📚 <?= Language::get('account_overview_title') ?></h2>

<form method="get">
  <label for="konto"><?= Language::get('select_account') ?? 'Konto wählen' ?>:</label>
  <select name="konto" id="konto" onchange="this.form.submit()">
    <option value=""><?= Language::get('all_accounts') ?? 'Alle Konten' ?></option>
    <?php foreach ($allKonten as $k): ?>
      <option value="<?= $k['kontonr'] ?>" <?= $selectedKonto == $k['kontonr'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($k['kontonr'] . ' – ' . $k['bezeichnung']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<table>
  <tr>
    <th><?= Language::get('account') ?></th>
    <th><?= Language::get('description') ?></th>
    <th><?= Language::get('type') ?></th>
    <th><?= Language::get('account_balance') ?></th>
  </tr>

  <?php if (empty($konten)): ?>
    <tr>
      <td colspan="4"><?= Language::get('no_accounts_found') ?? 'Keine Konten gefunden.' ?></td>
    </tr>
  <?php else: ?>
    <?php
    $totalBalance = 0;
    foreach ($konten as $k):
        $betrag = (float)$k['open_amount'];
        $totalBalance += $betrag;
    ?>
      <tr>
        <td><?= htmlspecialchars($k['kontonr']) ?></td>
        <td><?= htmlspecialchars($k['bezeichnung']) ?></td>
        <td><?= htmlspecialchars($k['typ']) ?></td>
        <td class="<?= $betrag < 0 ? 'negativ' : '' ?> text-right">
          <?= number_format($betrag, 2, ',', '.') ?> €
        </td>
      </tr>
    <?php endforeach; ?>

    <tr class="total-row">
      <td colspan="3" class="text-right"><?= Language::get('total') ?? 'Gesamtsumme' ?>:</td>
      <td class="<?= $totalBalance < 0 ? 'negativ' : '' ?> text-right">
        <?= number_format($totalBalance, 2, ',', '.') ?> €
      </td>
    </tr>
  <?php endif; ?>
</table>

</body>
</html>
