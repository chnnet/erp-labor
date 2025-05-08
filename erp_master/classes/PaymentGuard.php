<?php
require_once 'Database.php';

class PaymentGuard
{
    /**
     * Prüft, ob ein Journal gesperrt ist – entweder durch Zahlungseingang Debitor oder Zahllauf Kreditor.
     */
    public static function isLocked(int $journalId): bool
    {
        $pdo = Database::getConnection();

        // Zahlungseingänge Debitor prüfen
        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM zahlungseingaege_debitor WHERE journal_id = ?");
        $stmt1->execute([$journalId]);
        if ($stmt1->fetchColumn() > 0) {
            return true;
        }

        // Zahllauf Kreditor prüfen
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM zahllaeufe_kreditor WHERE FIND_IN_SET(?, journal_ids)");
        $stmt2->execute([$journalId]);
        if ($stmt2->fetchColumn() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Gibt eine passende Fehlermeldung zurück, warum das Journal gesperrt ist.
     */
    public static function getLockMessage(int $journalId): string
    {
        return Language::get('journal_already_paid', ['id' => $journalId]);
    }
}
