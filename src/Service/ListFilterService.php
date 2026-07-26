<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Briques de filtrage partagees par les listes du back-office.
 *
 * Volontairement un jeu de petites methodes composables plutot qu'un moteur
 * generique : chaque controleur reste lisible et declare explicitement ce
 * qu'il accepte, ce qui evite qu'un parametre d'URL inattendu atteigne la
 * requete.
 */
class ListFilterService
{
    /**
     * Recherche texte sur plusieurs champs, insensible a la casse.
     *
     * @param string[] $fields expressions DQL completes, alias compris
     */
    public function applySearch(QueryBuilder $queryBuilder, array $fields, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '' || $fields === []) {
            return;
        }

        $clauses = array_map(
            static fn (string $field): string => sprintf('LOWER(%s) LIKE :searchTerm', $field),
            $fields
        );

        $queryBuilder
            ->andWhere('(' . implode(' OR ', $clauses) . ')')
            ->setParameter('searchTerm', '%' . mb_strtolower($term) . '%');
    }

    /**
     * Egalite stricte, ignoree si la valeur est vide.
     */
    public function applyExact(QueryBuilder $queryBuilder, string $field, mixed $value, string $parameter): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s = :%s', $field, $parameter))
            ->setParameter($parameter, $value);
    }

    /**
     * Bornes de periode. La borne haute est exclusive au jour suivant, sinon
     * les enregistrements du dernier jour seraient exclus des que l'heure
     * n'est pas minuit.
     */
    public function applyDateRange(QueryBuilder $queryBuilder, string $field, ?string $from, ?string $to): void
    {
        if ($from = $this->parseDate($from)) {
            $queryBuilder
                ->andWhere(sprintf('%s >= :dateFrom', $field))
                ->setParameter('dateFrom', $from);
        }

        if ($to = $this->parseDate($to)) {
            $queryBuilder
                ->andWhere(sprintf('%s < :dateTo', $field))
                ->setParameter('dateTo', $to->modify('+1 day'));
        }
    }

    /**
     * Tri sur une liste blanche : une valeur inconnue retombe sur le tri par
     * defaut au lieu d'etre injectee dans le DQL.
     *
     * @param array<string, string> $allowed libelle public => expression DQL
     */
    public function applySort(QueryBuilder $queryBuilder, array $allowed, Request $request, string $defaultField, string $defaultDirection = 'ASC'): void
    {
        $requested = (string) $request->query->get('sort', '');
        $field = $allowed[$requested] ?? $defaultField;
        $direction = strtoupper((string) $request->query->get('dir', '')) === 'DESC' ? 'DESC' : 'ASC';

        if ($requested === '' || !isset($allowed[$requested])) {
            $direction = $request->query->has('dir') ? $direction : $defaultDirection;
        }

        $queryBuilder->orderBy($field, $direction);
    }

    /**
     * Vrai des qu'au moins un filtre est actif, pour n'afficher le bouton de
     * remise a zero que lorsqu'il sert (section 24).
     */
    public function hasActiveFilters(Request $request, array $ignored = ['page']): bool
    {
        foreach ($request->query->all() as $key => $value) {
            if (!in_array($key, $ignored, true) && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable ? $date->setTime(0, 0) : null;
    }
}
