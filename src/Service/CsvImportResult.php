<?php

namespace App\Service;

/**
 * Bilan d'un import CSV : ce qui est entre en base et ce qui a ete rejete,
 * ligne par ligne, pour que l'utilisateur puisse corriger son fichier.
 */
final class CsvImportResult
{
    /** @var array<int, array{line: int|null, message: string}> */
    private array $errors = [];

    private int $rows = 0;

    private int $imported = 0;

    public function countRow(): void
    {
        ++$this->rows;
    }

    public function countImported(): void
    {
        ++$this->imported;
    }

    /**
     * @param int|null $line numero de ligne dans le fichier, null pour une
     *                       erreur globale (en-tete manquant, annee absente)
     */
    public function addError(?int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

    /** @return array<int, array{line: int|null, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function rows(): int
    {
        return $this->rows;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function rejected(): int
    {
        return $this->rows - $this->imported;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
