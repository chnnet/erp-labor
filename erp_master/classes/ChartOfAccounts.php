<?php
require_once 'Database.php';
require_once 'Language.php';

class ChartOfAccounts {
    public static function get(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM kontenstamm WHERE kontonr = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        return $account ?: null;
    }

    public static function exists(int $accountNumber): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kontenstamm WHERE kontonr = ?");
        $stmt->execute([$accountNumber]);
        return $stmt->fetchColumn() > 0;
    }

    public static function all(?string $type = null): array {
        $pdo = Database::getConnection();
        if ($type && in_array($type, ['B', 'E', 'K', 'Z'])) {
            $stmt = $pdo->prepare("SELECT * FROM kontenstamm WHERE typ = ? ORDER BY kontonr");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->query("SELECT * FROM kontenstamm ORDER BY kontonr");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO kontenstamm 
            (ktorahmen_id, kontonr, bezeichnung, typ, klasse, sammelkonto)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['ktorahmen_id'],
            $data['kontonr'],
            $data['bezeichnung'],
            $data['typ'],
            $data['klasse'],
            $data['sammelkonto']
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $accountNumber, array $data): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE kontenstamm SET
              ktorahmen_id = ?, 
              bezeichnung = ?, 
              typ = ?, 
              klasse = ?, 
              sammelkonto = ?
            WHERE kontonr = ?
        ");
        $stmt->execute([
            $data['ktorahmen_id'],
            $data['bezeichnung'],
            $data['typ'],
            $data['klasse'],
            $data['sammelkonto'],
            $accountNumber
        ]);
    }

    public static function delete(int $accountNumber): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM journalzeile WHERE kontonr = ?");
        $stmt->execute([$accountNumber]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception(Language::get('account_delete_error'));
        }

        $del = $pdo->prepare("DELETE FROM kontenstamm WHERE kontonr = ?");
        $del->execute([$accountNumber]);
    }

    public static function search(string $term): array {
        $pdo = Database::getConnection();
        $like = '%' . $term . '%';
        $stmt = $pdo->prepare("SELECT * FROM kontenstamm WHERE bezeichnung LIKE ? OR kontonr LIKE ?");
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
