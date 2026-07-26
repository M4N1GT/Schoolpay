<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pagination des grandes listes du back-office.
 *
 * S'appuie sur le paginateur de Doctrine plutot que sur un bundle tiers : il
 * gere correctement le comptage en presence de jointures et evite d'ajouter
 * une dependance au projet.
 */
class PaginationService
{
    public const PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    /**
     * @param bool $fetchJoinCollection a laisser a true des que la requete
     *                                  joint une collection (to-many), sans
     *                                  quoi le total serait fausse par les
     *                                  lignes dupliquees
     */
    public function paginate(QueryBuilder $queryBuilder, int $page, int $perPage = self::PER_PAGE, bool $fetchJoinCollection = true): Pagination
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $page = max(1, $page);

        $queryBuilder
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new DoctrinePaginator($queryBuilder->getQuery(), $fetchJoinCollection);

        return new Pagination(iterator_to_array($paginator), count($paginator), $page, $perPage);
    }

    /**
     * Pagination d'une liste deja construite en PHP, lorsqu'aucune requete ne
     * peut exprimer le filtre.
     *
     * @param array<int, mixed> $items
     */
    public function paginateArray(array $items, int $page, int $perPage = self::PER_PAGE): Pagination
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $page = max(1, $page);

        return new Pagination(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $page,
            $perPage,
        );
    }

    public function currentPage(Request $request): int
    {
        return max(1, $request->query->getInt('page', 1));
    }
}
