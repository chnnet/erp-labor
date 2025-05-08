<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';
require_once 'classes/PaymentGuard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

Database::initialize();
$pdo = Database::$conn;

$fehler = '';
$meldung = '';
$journalId = (int)($_GET['id'] ?? 0);

if ($journalId <= 0) {
    die(Language::get('invalid_journal_id'));
}

if (PaymentGuard::isLocked($journalId)) {
    $fehler = PaymentGuard::getLockMessage($journalId);
}

// Stornierung durchführen
if (!$fehler && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Hauptbuch löschen
        $pdo->prepare("DELETE FROM hauptbuch WHERE journalzeile_id IN (
            SELECT id FROM journalzeile WHERE journal_id = ?
        )")->execute([$journalId]);

        // Journalzeilen löschen
        $pdo->prepare("DELETE FROM journalzeile WHERE journal_id = ?")->execute([$journalId]);

        // Journal selbst löschen
        $pdo->prepare("DELETE FROM journal WHERE id = ?")->execute([$journalId]);

        // Steuerjournal finden und löschen (Bezug über Buchungstext)
        $steuerStmt = $pdo->prepare("SELECT id FROM journal WHERE buchungstext LIKE ?");
        $steuerStmt->execute(["%Journal #$journalId%"]);
        $steuerId = $steuerStmt->fetchColumn();

        if ($steuerId) {
            $pdo->prepare("DELETE FROM hauptbuch WHERE journalzeile_id IN (
                SELECT id FROM journalzeile WHERE journal_id = ?
            )")->execute([$steuerId]);
            $pdo->prepare("DELETE FROM journalzeile WHERE journal_id = ?")->execute([$steuerId]);
            $pdo->prepare("DELETE FROM journal WHERE id = ?")->execute([$steuerId]);
        }

        $pdo->commit();

        $meldung = Language::get('journal_cancel_success', ['id' => $journalId]);
        if ($steuerId) {
            $meldung .= " " . Language::get('tax_journal_deleted', ['id' => $steuerId]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $fehler = Language::get('journal_cancel_failed') . ': ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= Language::get('cancel') ?> – Journal <?= $journalId ?></title>
</head>
<body>
  <h2><?= Language::get('cancel') ?> – Journal <?= $journalId ?></h2>

  <?php if ($fehler): ?>
    <p style="color:red"><?= htmlspecialchars($fehler) ?></p>
  <?php elseif ($meldung): ?>
    <p style="color:green"><?= htmlspecialchars($meldung) ?></p>
  <?php else: ?>
    <form method="post" onsubmit="return confirm('<?= Language::get('confirm_cancel_journal') ?>');">
      <button type="submit">🗑️ <?= Language::get('cancel_journal_button') ?></button>
      <p style="margin-top:10px;"><a href="journal_overview.php"><?= Language::get('back_to_overview') ?></a></p>
    </form>
  <?php endif; ?>
</body>
</html>
