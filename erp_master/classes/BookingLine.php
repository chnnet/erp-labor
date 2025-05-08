<?php
require_once 'Database.php';
require_once 'ChartOfAccounts.php';  // ehemals kontenstamm.php
require_once 'Language.php';      // dynamische Sprachunterstützung

class BookingLine {
    private int $accountId;
    private float $amount;
    private string $entryType; // 'soll' oder 'haben'

    public function __construct(int $accountId = 0, float $amount = 0.0, string $entryType = 'soll') {
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->entryType = $entryType;
    }

    public static function fromArray(array $data): BookingLine {
        if (!isset($data['konto_id'], $data['betrag'], $data['typ'])) {
            throw new InvalidArgumentException(Language::get('missing_fields'));
        }

        return new self(
            (int)$data['konto_id'],
            (float)$data['betrag'],
            $data['typ']
        );
    }

    public function validate(): void {
        if (!in_array($this->entryType, ['soll', 'haben'])) {
            throw new InvalidArgumentException(Language::get('invalid_type'));
        }

        if (!ChartOfAccounts::exists($this->accountId)) {
          throw new Exception(Language::get('account_not_found', ['id' => $this->accountId]));
      }

        if ($this->amount <= 0) {
            throw new Exception(Language::get('amount_invalid'));
        }
    }

    public function getAccountId(): int {
        return $this->accountId;
    }

    public function getAmount(): float {
        return $this->amount;
    }

    public function getEntryType(): string {
        return $this->entryType;
    }

    public function save(int $transactionId): void {
        $this->validate();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO transaction_entries (transaction_id, account_id, amount, entry_type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $transactionId,
            $this->accountId,
            $this->amount,
            $this->entryType
        ]);
    }

    public function generateCounterEntry(int $counterAccountId): BookingLine {
        $counterType = $this->entryType === 'soll' ? 'haben' : 'soll';
        return new BookingLine($counterAccountId, $this->amount, $counterType);
    }
}
