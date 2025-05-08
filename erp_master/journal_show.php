<?php
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';

Database::initialize();

$journalId = (int)($_GET['id'] ?? 0);
if (!$journalId) {
    echo "<p style='color:red'>" . Language::get('no_journal_id_provided') . "</p>";
    exit;
}

// Journal-Kopfdaten
$stmt = Database::$conn->prepare("
    SELECT j.*, u.username 
    FROM journal j 
    LEFT JOIN users u ON j.benutzer_id = u.id 
    WHERE j.ID = ?
");
$stmt->execute([$journalId]);
$journal = $stmt->fetch(PDO::FETCH_ASSOC);

// Zeilen
$stmt = Database::$conn->prepare("SELECT * FROM journalzeile WHERE journal_id = ? ORDER BY journalzeile");
$stmt->execute([$journalId]);
$zeilen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vollständige Stornierung prüfen
$vollStorniert = !empty($zeilen) && array_reduce($zeilen, fn($carry, $z) => $carry && $z['status'] === 'S', true);
?>

<h2>📘 <?= Language::get('journal_details') ?> #<?= $journalId ?></h2>

<?php if (!$journal): ?>
  <p style="color:red;"><?= Language::get('journal_not_found') ?></p>
<?php else: ?>
  <?php if ($vollStorniert): ?>
    <p style="color:crimson;"><strong><?= Language::get('journal_fully_cancelled') ?></strong></p>
  <?php endif; ?>

  <p>
  <strong>Type:</strong> <?= Language::get('vorgang_' . strtolower($journal['vorgang'])) ?> <br>
  <strong>Date:</strong> <?= $journal['datum'] ?> <br>
  <strong>User:</strong> <?= htmlspecialchars($journal['username'] ?? '–') ?> <br>
  <strong>Payment Terms:</strong> <?= $journal['zahlungsbedingungen'] ?> days
</p>

  <h3><?= Language::get('journal_entries') ?></h3>
  <table border="1" cellpadding="6" cellspacing="0">
    <tr>
      <th>#</th>
      <th><?= Language::get('debit') ?></th>
      <th><?= Language::get('credit') ?></th>
      <th><?= Language::get('amount') ?></th>
      <th><?= Language::get('text') ?></th>
      <th><?= Language::get('document_date') ?></th>
      <th><?= Language::get('booking_date') ?></th>
      <th><?= Language::get('status') ?></th>
    </tr>
    <?php foreach ($zeilen as $z): ?>
      <tr>
        <td><?= $z['journalzeile'] ?></td>
        <td>
          <?php if ($z['kontosoll']): ?>
            <a href="account_show.php?kontonr=<?= $z['kontosoll'] ?>"><?= $z['kontosoll'] ?></a>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($z['kontohaben']): ?>
            <a href="account_show.php?kontonr=<?= $z['kontohaben'] ?>"><?= $z['kontohaben'] ?></a>
          <?php endif; ?>
        </td>
        <td><?= number_format($z['betrag'], 2) ?> €</td>
        <td><?= htmlspecialchars($z['buchungstext']) ?></td>
        <td><?= $z['belegdatum'] ?></td>
        <td><?= $z['buchungsdatum'] ?></td>
        <td><?= $z['status'] === 'S' ? '❌ ' . Language::get('cancelled') : '✔ ' . Language::get('active') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p style="margin-top:20px;">
    <a href="journal_selection.php">← <?= Language::get('back_to_overview') ?></a>
    <?php if (!$vollStorniert): ?>
      | <a href="journal_cancel.php?id=<?= $journalId ?>" onclick="return confirm('<?= Language::get('confirm_cancel_journal') ?>')">
        ❌ <?= Language::get('cancel_this_journal') ?>
      </a>
    <?php endif; ?>
  </p>
<?php endif; ?>
