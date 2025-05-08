<?php
include "header.php";
require_once 'classes/database.php';
require_once 'classes/language.php';
require_once 'classes/ChartOfAccounts.php';
require_once 'classes/JournalLine.php';

Database::initialize();
$lang = $_SESSION['lang'] ?? 'de';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$konten = ChartOfAccounts::all();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
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
  </style>
</head>
<body>
<h2>📚 <?= Language::get('account_overview_title') ?></h2>

<table>
  <tr>
    <th><?= Language::get('account') ?></th>
    <th><?= Language::get('description') ?></th>
    <th><?= Language::get('type') ?></th>
    <th><?= Language::get('account_balance') ?></th>
    <th><?= Language::get('actions') ?></th>
  </tr>

  <?php if (empty($konten)): ?>
    <tr>
      <td colspan="5"><?= Language::get('no_accounts_found') ?? 'Keine Konten gefunden.' ?></td>
    </tr>
  <?php else: ?>
    <?php foreach ($konten as $k): 
      $saldo = JournalLine::calculateBalance($k['kontonr']);
      if (!is_numeric($saldo)) {
          $saldo = 0.00;
      }
    ?>
      <tr>
        <td><?= $k['kontonr'] ?></td>
        <td><?= htmlspecialchars($k['bezeichnung']) ?></td>
        <td><?= Language::get('account_type_' . strtolower($k['typ'])) ?></td>
        <td class="<?= $saldo < 0 ? 'negativ' : '' ?>"><?= number_format($saldo, 2, ',', '.') ?> €</td>
        <td><a href="account_details.php?kontonr=<?= $k['kontonr'] ?>">🔍 <?= Language::get('details') ?></a></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</table>
</body>
</html>
