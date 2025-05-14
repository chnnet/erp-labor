<?php

class Posting {
    private int $id;
    private string $description;
    private string $date;
    private string $type;
    private array $lines = [];
    private string $postingText;

    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setPostingText(string $postingText): void {
        $this->postingText = $postingText;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function getDate(): string {
        return $this->date;
    }

    public function setDate(string $date): void {
        $this->date = $date;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): void {
        $this->type = $type;
    }

    public function getLines(): array {
        return $this->lines;
    }

    public function setLines(array $lines): void {
        $this->lines = $lines;
    }

    public function save(): void {
        Database::initialize();

        $stmt = Database::$conn->prepare("SELECT MAX(id) FROM buchungssaetze");
        $stmt->execute();
        $maxId = $stmt->fetchColumn();

        $this->id = ($maxId !== null ? $maxId + 1 : 500000);

        $insert = Database::$conn->prepare("INSERT INTO buchungssaetze (id, beschreibung, datum, typ) VALUES (?, ?, ?, ?)");
        $insert->execute([$this->id, $this->description, $this->date, $this->type]);
    }
}
