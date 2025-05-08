<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/ChartOfAccounts.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

Database::initialize();

$message = '';
$error = '';

// Load ENUM types except Debitor/Kreditor
$enumTypes = [];
$stmt = Database::$conn->prepare("SHOW COLUMNS FROM kontenstamm LIKE 'typ'");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (preg_match("/^enum\((.*)\)$/", $row['Type'], $matches)) {
    $enumTypes = array_filter(
        array_map(fn($val) => trim($val, "' "), explode(',', $matches[1])),
        fn($val) => !in_array($val, ['D', 'R'])
    );
}

// Kontorahmen list
$chartFrameworks = [];
$res = Database::$conn->query("SELECT id, bezeichnung FROM kontenrahmen ORDER BY id");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $chartFrameworks[$row['id']] = $row['bezeichnung'];
}

// Number ranges by type
$numberRanges = [
    'B' => [1000, 1999],
    'K' => [4000, 7999],
    'E' => [8000, 8999],
    'Z' => [9000, 9999]
];

// Save logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountNumber = (int)$_POST['kontonummer'];
    $name = trim($_POST['kontoname']);
    $type = $_POST['kontotyp'];
    $frameworkId = (int)$_POST['ktorahmen_id'];
    $class = (int)($_POST['klasse'] ?? 0);
    $isGroup = isset($_POST['sammelkonto']) ? 1 : 0;

    if (!in_array($type, array_keys($numberRanges))) {
        $error = Language::get('invalid_account_type');
    } else {
        [$min, $max] = $numberRanges[$type];

        if ($accountNumber < $min || $accountNumber > $max) {
            $error = Language::get('account_number_out_of_range', [
                'number' => $accountNumber,
                'type' => $type,
                'min' => $min,
                'max' => $max
            ]);
        } elseif (!array_key_exists($frameworkId, $chartFrameworks)) {
            $error = Language::get('invalid_chart_framework');
        } else {
            try {
                ChartOfAccounts::create([
                    'kontonr' => $accountNumber,
                    'bezeichnung' => $name,
                    'typ' => $type,
                    'klasse' => $class,
                    'sammelkonto' => $isGroup,
                    'ktorahmen_id' => $frameworkId
                ]);
                $message = Language::get('account_created_successfully', ['number' => $accountNumber, 'name' => $name]);
            } catch (Exception $e) {
                $error = Language::get('account_creation_error') . ": " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('create_account_title') ?></title>
  <style>
    label { display: block; margin-top: 10px; }
    input, select { width: 300px; padding: 5px; }
    .success { color: green; }
    .error { color: red; }
  </style>
</head>
<body>
  <h1><?= Language::get('create_account_title') ?></h1>

  <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <form method="post" id="accountForm">
    <label><?= Language::get('account_type') ?>:
      <select name="kontotyp" id="kontotyp" required>
        <option value=""><?= Language::get('please_select') ?></option>
        <?php foreach ($numberRanges as $code => [$min, $max]): ?>
          <option value="<?= $code ?>" <?= ($_POST['kontotyp'] ?? '') === $code ? 'selected' : '' ?>>
            <?= $code ?> – <?= Language::get('account_type_' . strtolower($code)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label><?= Language::get('account_number') ?>:
      <input type="number" name="kontonummer" id="kontonummer" value="<?= $_POST['kontonummer'] ?? '' ?>" required>
    </label>

    <label><?= Language::get('name') ?>:
      <input type="text" name="kontoname" required>
    </label>

    <label><?= Language::get('account_class') ?>:
      <input type="number" name="klasse" min="1" max="9" required>
    </label>

    <label><?= Language::get('group_account') ?>:
      <input type="checkbox" name="sammelkonto">
    </label>

    <label><?= Language::get('chart_framework') ?>:
      <select name="ktorahmen_id" required>
        <option value=""><?= Language::get('please_select') ?></option>
        <?php foreach ($chartFrameworks as $id => $desc): ?>
          <option value="<?= $id ?>" <?= ($_POST['ktorahmen_id'] ?? '') == $id ? 'selected' : '' ?>>
            <?= $id ?> – <?= htmlspecialchars($desc) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <br><br>
    <button type="submit">➕ <?= Language::get('create_account') ?></button>
  </form>

  <script>
    document.getElementById("kontotyp").addEventListener("change", async function () {
      const typ = this.value;
      if (!typ) return;

      const res = await fetch("next_kontonummer.php?typ=" + typ);
      const num = await res.text();
      document.getElementById("kontonummer").value = num;
    });
  </script>
</body>
</html>
