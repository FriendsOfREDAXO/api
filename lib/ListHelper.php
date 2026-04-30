<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

use const JSON_PRETTY_PRINT;

class ListHelper
{
    public const MAX_PER_PAGE = 1000;

    /**
     * Parses a sort string like "name:asc,createdate:desc" into an array of sort definitions.
     *
     * @param string|null $sort Raw sort string from query parameter
     * @param array<string> $allowedFields Whitelist of allowed field names
     * @param array<array{field: string, direction: string}> $default Default sort definitions
     * @return array<array{field: string, direction: string}>
     */
    public static function parseSort(?string $sort, array $allowedFields, array $default): array
    {
        if (null === $sort || '' === $sort) {
            return $default;
        }

        $parts = explode(',', $sort);
        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }

            $segments = explode(':', $part);
            $field = trim($segments[0]);
            $direction = isset($segments[1]) ? strtolower(trim($segments[1])) : 'asc';

            if (!in_array($field, $allowedFields, true)) {
                throw new InvalidArgumentException('Invalid sort field: ' . $field);
            }

            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Invalid sort direction: ' . $direction . '. Must be asc or desc');
            }

            $result[] = ['field' => $field, 'direction' => $direction];
        }

        return count($result) > 0 ? $result : $default;
    }

    /**
     * Builds an SQL ORDER BY clause from sort definitions.
     *
     * @param array<array{field: string, direction: string}> $sortDefs Sort definitions from parseSort()
     * @param array<string, string> $fieldMapping Optional mapping of API field names to SQL column names
     * @return string ORDER BY clause (without "ORDER BY" prefix), e.g. "`name` ASC, `createdate` DESC"
     */
    public static function buildSqlOrderBy(array $sortDefs, array $fieldMapping = []): string
    {
        if (0 === count($sortDefs)) {
            return '';
        }

        $parts = [];
        foreach ($sortDefs as $def) {
            $column = $fieldMapping[$def['field']] ?? $def['field'];
            // Use backtick quoting for column names
            if (!str_contains($column, '`')) {
                $column = '`' . $column . '`';
            }
            $parts[] = $column . ' ' . strtoupper($def['direction']);
        }

        return implode(', ', $parts);
    }

    /**
     * Calculates pagination values and meta information.
     *
     * @return array{offset: int, limit: int, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public static function paginate(int $page, int $perPage, int $total): array
    {
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        return [
            'offset' => $offset,
            'limit' => $perPage,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Wraps data and meta into the standard response format.
     *
     * @param array<mixed> $data The list data
     * @param array{page: int, per_page: int, total: int, total_pages: int} $meta Pagination meta
     * @return array{data: array<mixed>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public static function wrapResponse(array $data, array $meta): array
    {
        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    /**
     * Sorts a PHP array using the given sort definitions.
     *
     * @param array<array<string, mixed>> $items Array of associative arrays
     * @param array<array{field: string, direction: string}> $sortDefs Sort definitions
     * @return array<array<string, mixed>>
     */
    public static function sortArray(array $items, array $sortDefs): array
    {
        if (0 === count($sortDefs) || 0 === count($items)) {
            return $items;
        }

        usort($items, static function (array $a, array $b) use ($sortDefs): int {
            foreach ($sortDefs as $def) {
                $field = $def['field'];
                $valA = $a[$field] ?? null;
                $valB = $b[$field] ?? null;

                if ($valA === $valB) {
                    continue;
                }

                if (null === $valA) {
                    return 'asc' === $def['direction'] ? -1 : 1;
                }
                if (null === $valB) {
                    return 'asc' === $def['direction'] ? 1 : -1;
                }

                $cmp = is_string($valA) ? strnatcasecmp($valA, $valB) : ($valA <=> $valB);

                if (0 !== $cmp) {
                    return 'desc' === $def['direction'] ? -$cmp : $cmp;
                }
            }
            return 0;
        });

        return $items;
    }

    /**
     * Sorts, paginates and wraps a PHP array into the standard response format.
     *
     * @param array<array<string, mixed>> $items All items (unfiltered)
     * @param array<array{field: string, direction: string}> $sortDefs Sort definitions
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array{data: array<mixed>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public static function paginateArray(array $items, array $sortDefs, int $page, int $perPage): array
    {
        $items = self::sortArray($items, $sortDefs);
        $total = count($items);
        $pagination = self::paginate($page, $perPage, $total);
        $data = array_slice($items, $pagination['offset'], $pagination['limit']);

        return self::wrapResponse($data, $pagination['meta']);
    }

    /**
     * Creates a JSON error response for invalid sort parameters.
     */
    public static function sortErrorResponse(InvalidArgumentException $e): Response
    {
        return new JsonResponse(['error' => $e->getMessage()], 400);
    }
}
