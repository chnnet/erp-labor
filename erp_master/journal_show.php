<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

include "header.php";
require_once 'classes/Database.php';
require_once 'classes/KeyValue.php';
require_once 'classes/Language.php';
require_once 'classes/PaymentGuard.php';

Database::initialize();
$pdo = Database::$conn;

$fehler = '';
$meldung = '';
$journalId = (int)($_GET['id'] ?? 0);

if (!$journalId) {
    echo "<p style='color:red'>" . Language::get('no_journal_id_provided') . "</p>";
    exit;
}

// Journal laden
$stmt = $pdo->prepare("SELECT * FROM journal WHERE id = ?");
$stmt->execute([$journalId]);
$journal = $stmt->fetch(PDO::FETCH_ASSOC);

// Journalzeilen laden
$stmt = $pdo->prepare("SELECT * FROM journalzeile WHERE journal_id = ?");
$stmt->execute([$journalId]);
$zeilen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Steuerbuchungen laden
$stmt = $pdo->prepare("SELECT * FROM steuerbuchungen WHERE journal_id = ?");
$stmt->execute([$journalId]);
$steuerzeilen = $stmt->fetchAll(PDO::FETCH_ASSOC);

$vollStorniert = !empty($zeilen) && array_reduce($zeilen, fn($carry, $z) => $carry && $z['status'] === 'S', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_journal'])) {
    try {
        if (PaymentGuard::isLocked($journalId)) {
            throw new Exception(PaymentGuard::getLockMessage($journalId));
        }

        $pdo->beginTransaction();

        $referenz = $journal['referenz'];
        $vorgang = $journal['vorgang'];

        // Betroffene Konten sammeln
        $betroffeneKonten = [];
        foreach ($zeilen as $z) {
            if ($z['kontosoll']) $betroffeneKonten[$z['kontosoll']] = true;
            if ($z['kontohaben']) $betroffeneKonten[$z['kontohaben']] = true;
        }
        foreach ($steuerzeilen as $s) {
            $betroffeneKonten[$s['konto']] = true;
        }

        // Debitor/Kreditor behandeln
        if ($vorgang === 'D') {
            $partnerTabelle = 'debitoren';
            $partnerFeld = 'debitorennr';
        } elseif ($vorgang === 'K') {
            $partnerTabelle = 'kreditoren';
            $partnerFeld = 'kreditorennr';
        } else {
            $partnerTabelle = null;
            $partnerFeld = null;
        }

        if ($partnerTabelle && $partnerFeld) {
            foreach ($zeilen as $z) {
                $buchungsbetrag = $z['betrag'];

                if ($z['kontosoll']) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $partnerTabelle WHERE $partnerFeld = ?");
                    $stmt->execute([$z['kontosoll']]);
                    if ($stmt->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE $partnerTabelle SET offener_betrag = offener_betrag + ? WHERE $partnerFeld = ?")
                            ->execute([$buchungsbetrag, $z['kontosoll']]);
                    }
                }

                if ($z['kontohaben']) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $partnerTabelle WHERE $partnerFeld = ?");
                    $stmt->execute([$z['kontohaben']]);
                    if ($stmt->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE $partnerTabelle SET offener_betrag = GREATEST(offener_betrag - ?, 0) WHERE $partnerFeld = ?")
                            ->execute([$buchungsbetrag, $z['kontohaben']]);
                    }
                }
            }
        }

        // Steuerbuchungen löschen
        $pdo->prepare("DELETE FROM steuerbuchungen WHERE journal_id = ? OR journal_id IN (
            SELECT j.id FROM journal j
            JOIN journalzeile z ON z.journal_id = j.id
            WHERE z.buchungstext LIKE ?)")
            ->execute([$journalId, "%Journal #$journalId%"]);

        // Hauptbuch, Journalzeilen und Journal löschen
        $pdo->prepare("DELETE FROM hauptbuch WHERE journal_id = ?")->execute([$journalId]);
        $pdo->prepare("DELETE FROM journalzeile WHERE journal_id = ?")->execute([$journalId]);
        $pdo->prepare("DELETE FROM journal WHERE id = ?")->execute([$journalId]);

        // Steuerjournal optional löschen
        $steuerStmt = $pdo->prepare("SELECT DISTINCT j.id FROM journal j JOIN journalzeile z ON z.journal_id = j.id WHERE z.buchungstext LIKE ?");
        $steuerStmt->execute(["%Journal #$journalId%"]); 
        $steuerId = $steuerStmt->fetchColumn();
        if ($steuerId) {
            $pdo->prepare("DELETE FROM hauptbuch WHERE journal_id = ?")->execute([$steuerId]);
            $pdo->prepare("DELETE FROM journalzeile WHERE journal_id = ?")->execute([$steuerId]);
            $pdo->prepare("DELETE FROM journal WHERE id = ?")->execute([$steuerId]);
        }

        // Offene Posten entfernen
        $pdo->prepare("DELETE FROM offene_posten_debitoren WHERE rechnungsnr = ?")->execute([$referenz]);
        $pdo->prepare("DELETE FROM offene_posten_kreditoren WHERE rechnungsnr = ?")->execute([$referenz]);

        // Salden aktualisieren
        $saldoStmt = $pdo->prepare("UPDATE kontenstamm SET open_amount = (
            SELECT COALESCE(SUM(CASE WHEN soll_haben = 'S' THEN betrag ELSE -betrag END), 0)
            FROM hauptbuch WHERE kontonr = ?) WHERE kontonr = ?");

        foreach (array_keys($betroffeneKonten) as $konto) {
            $saldoStmt->execute([$konto, $konto]);
        }

        $pdo->commit();
        $meldung = Language::get('journal_cancel_success', ['id' => $journalId]);
        echo "<script>alert('{$meldung}'); window.location.href='journal_selection.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $fehler = Language::get('journal_cancel_failed') . ': ' . $e->getMessage();
    }
}
?>

<!-- HTML Ausgabe -->
<style>
  .btn-link {
    display: inline-block;
    padding: 6px 12px;
    margin-top: 20px;
    text-decoration: none;
    background-color: #eee;
    border: 1px solid #ccc;
    border-radius: 4px;
    color: black;
    font-weight: bold;
  }
  .btn-link:hover { background-color: #ddd; }
  .btn-cancel {
    color: crimson;
    border-color: crimson;
  }
  .btn-cancel:hover { background-color: #fdd; }
</style>

<h2>📘 <?= Language::get('journal_details') ?> #<?= $journalId ?></h2>

<?php if ($fehler): ?>
  <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
<?php endif; ?>

<?php if (!$journal): ?>
  <p style="color:red;"><?= Language::get('journal_not_found') ?></p>
<?php else: ?>
  <?php if ($vollStorniert): ?>
    <p style="color:crimson;"><strong><?= Language::get('journal_fully_cancelled') ?></strong></p>
  <?php endif; ?>

  <p>
    <strong>Type:</strong> <?= Language::get('vorgang_' . strtolower($journal['vorgang'])) ?><br>
    <strong>Date:</strong> <?= $journal['datum'] ?><br>
    <strong>User:</strong> <?= htmlspecialchars($journal['username'] ?? '–') ?><br>
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
        <td><?= $z['kontosoll'] ? '<a href="account_overview.php?konto=' . $z['kontosoll'] . '">' . $z['kontosoll'] . '</a>' : '' ?></td>
        <td><?= $z['kontohaben'] ? '<a href="account_overview.php?konto=' . $z['kontohaben'] . '">' . $z['kontohaben'] . '</a>' : '' ?></td>
        <td><?= number_format($z['betrag'], 2, ',', '.') ?> €</td>
        <td><?= htmlspecialchars($z['buchungstext']) ?></td>
        <td><?= $z['belegdatum'] ?></td>
        <td><?= $z['buchungsdatum'] ?></td>
        <td><?= $z['status'] === 'S' ? '❌ ' . Language::get('cancelled') : '✔ ' . Language::get('active') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if (!empty($steuerzeilen)): ?>
    <h3><?= Language::get('tax_entries') ?></h3>
    <table border="1" cellpadding="6" cellspacing="0">
      <tr>
        <th>#</th>
        <th><?= Language::get('tax_code') ?></th>
        <th><?= Language::get('amount') ?></th>
        <th><?= Language::get('account') ?></th>
        <th><?= Language::get('document_date') ?></th>
        <th><?= Language::get('booking_date') ?></th>
      </tr>
      <?php foreach ($steuerzeilen as $s): ?>
        <tr>
          <td><?= $s['journalzeile'] ?></td>
          <td><?= htmlspecialchars($s['steuer_code']) ?></td>
          <td><?= number_format($s['steuer_betrag'], 2, ',', '.') ?> €</td>
          <td><a href="account_overview.php?konto=<?= $s['konto'] ?>"><?= $s['konto'] ?></a></td>
          <td><?= $s['belegdatum'] ?></td>
          <td><?= $s['buchungsdatum'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <div style="margin-top: 20px;">
    <a href="journal_selection.php" class="btn-link">← <?= Language::get('back_to_overview') ?></a>
    <?php if (!$vollStorniert): ?>
      <form method="post" style="display:inline;" onsubmit="return confirm('<?= Language::get('confirm_cancel_journal') ?>')">
        <button type="submit" name="cancel_journal" class="btn-link btn-cancel">❌ <?= Language::get('cancel_this_journal') ?></button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>