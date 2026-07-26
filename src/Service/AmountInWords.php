<?php

namespace App\Service;

/**
 * Montant en toutes lettres pour les recus (cahier des charges, section 16).
 *
 * S'appuie sur l'extension intl quand elle est disponible. Le projet ne la
 * declare pas en prerequis dans composer.json : en son absence, la methode
 * renvoie null et le recu omet simplement la ligne plutot que d'afficher un
 * libelle approximatif sur un document comptable.
 */
class AmountInWords
{
    public function __construct(private string $locale = 'fr_FR')
    {
    }

    public function isAvailable(): bool
    {
        return class_exists(\NumberFormatter::class);
    }

    public function format(float $amount, string $currency = 'ariary'): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        // L'ariary ne se divise pas en pratique : on arrondit a l'unite plutot
        // que d'ecrire une fraction que le guichet n'utilise jamais.
        $rounded = (int) round($amount);

        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::SPELLOUT);
        $words = $formatter->format($rounded);

        if ($words === false) {
            return null;
        }

        return ucfirst($words) . ' ' . $currency;
    }
}
