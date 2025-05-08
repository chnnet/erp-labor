<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
require_once 'classes/ChartOfAccounts.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

Database::initialize();
$pdo = Database::getConnection();

$accountId = $_GET['account'] ?? null;
$allAccounts = ChartOfAccounts::all();

function renderAccountSelect(array $allAccounts, $selected = null): void {
    echo "<form method='GET'><label>" . Language::get('select_account') . ": ";
    echo "<select name='account' onchange='this.form.submit()'>";
    echo "<option value=''>" . Language::get('please_select') . "</option>";
    foreach ($allAccounts as $account) {
        $isSelected = ($account['kontonr'] == $selected) ? 'selected' : '';
        echo "<option value='{$account['kontonr']}' $isSelected>{$account['kontonr']} - {$account['bezeichnung']}</option>";
    }
    echo "</select></label></form>";
}

$lang = $_SESSION['lang'] ?? 'de';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= Language::get('account_details') ?></title>
    <style>
        body { font-family: Arial; padding: 2em; }
        table { border-collapse: collapse; width: 60%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #eee; width: 30%; }
    </style>
</head>
<body>
<h2>📄 <?= Language::get('account_detail_view') ?></h2>

<?php renderAccountSelect($allAccounts, $accountId); ?>

<?php if ($accountId): ?>
    <?php
    $sql = "SELECT * FROM kontenstamm WHERE kontonr = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$accountId]);
    $konto = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <?php if ($konto): ?>
        <table>
            <tr><th><?= Language::get('account_number') ?></th><td><?= $konto['kontonr'] ?></td></tr>
            <tr><th><?= Language::get('description') ?></th><td><?= htmlspecialchars($konto['bezeichnung']) ?></td></tr>
            <tr><th><?= Language::get('type') ?></th><td><?= Language::get('account_type_' . strtolower($konto['typ'])) ?></td></tr>
            <?php if (isset($konto['anfangssaldo'])): ?>
                <tr><th><?= Language::get('opening_balance') ?></th><td><?= number_format($konto['anfangssaldo'], 2) ?> €</td></tr>
            <?php endif; ?>
        </table>
    <?php else: ?>
        <p>⚠️ <?= Language::get('account_not_found') ?></p>
    <?php endif; ?>
<?php else: ?>
    <p>ℹ️ <?= Language::get('please_select_account') ?></p>
<?php endif; ?>
</body>
</html>
