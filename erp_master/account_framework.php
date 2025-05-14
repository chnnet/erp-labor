<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';

Database::initialize();
$accounts = KeyValueHelper::buildGroupedArray("kontenstamm", "kontonr", "bezeichnung", "typ");

$lang = $_SESSION['lang'] ?? 'de';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('account_framework_title') ?></title>
  <style>
    .group { margin-bottom: 2em; }
    h3 { background-color: #eee; padding: 0.5em; }
    ul { list-style: none; padding-left: 1em; }
    li { margin: 0.2em 0; }
  </style>
</head>
<body>
  <h2>📘 <?= Language::get('account_framework_title') ?></h2>

  <?php foreach ($accounts as $type => $entries): ?>
    <div class="group">
      <h3>📂 <?= strtoupper(Language::get('account_type_' . strtolower($type))) ?></h3>
      <ul>
        <?php foreach ($entries as $entry): ?>
          <li>🧾 <?= $entry['key'] ?> – <?= htmlspecialchars($entry['value']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</body>
</html>
