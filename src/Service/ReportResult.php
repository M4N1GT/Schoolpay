<?php

namespace App\Service;

/**
 * Rapport calcule, pret a etre affiche, imprime ou exporte.
 *
 * Les lignes sont des tableaux de valeurs deja ordonnees comme les en-tetes :
 * le meme resultat alimente le HTML et le CSV sans que le template ait a
 * connaitre la logique metier.
 */
final class ReportResult
{
    /**
     * @param string[]                             $headers
     * @param array<int, array<int, string|float>> $rows
     * @param array<int, string|float>             $totals    ligne de synthese, vide si non pertinente
     * @param int[]                                $amountColumns indices a formater comme des montants
     */
    public function __construct(
        public readonly string $label,
        public readonly array $headers,
        public readonly array $rows,
        public readonly array $totals = [],
        public readonly array $amountColumns = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function isAmountColumn(int $index): bool
    {
        return in_array($index, $this->amountColumns, true);
    }

    /**
     * Lignes mises en forme pour le CSV : decimale a la virgule et pas de
     * separateur de milliers, ce qu'attend un tableur configure en francais.
     *
     * @return array<int, array<int, string>>
     */
    public function csvRows(): array
    {
        $format = fn (int $index, string|float $value): string => $this->isAmountColumn($index)
            ? number_format((float) $value, 2, ',', '')
            : (string) $value;

        $rows = array_map(
            fn (array $row): array => array_map($format, array_keys($row), $row),
            $this->rows
        );

        if ($this->totals !== []) {
            $rows[] = array_map($format, array_keys($this->totals), $this->totals);
        }

        return $rows;
    }
}
