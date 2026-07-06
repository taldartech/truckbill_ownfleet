<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportPaginator
{
    /**
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 25;

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return self::DEFAULT_PER_PAGE;
        }

        return $perPage;
    }

    public static function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra
     */
    public static function paginate(Request $request, array $rows, array $extra = []): JsonResponse
    {
        [$slice, $meta] = self::slice($request, $rows);

        return response()->json(array_merge($extra, [
            'data' => $slice,
            'meta' => $meta,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: array<string, int>}
     */
    public static function slice(Request $request, array $rows): array
    {
        $page = self::page($request);
        $perPage = self::perPage($request);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($rows, $offset, $perPage);
        $slice = self::renumberSerialNumbers($slice, $offset);

        $from = $total === 0 ? 0 : $offset + 1;
        $to = $total === 0 ? 0 : min($offset + count($slice), $total);

        return [
            $slice,
            [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    public static function extractGrandTotalRow(array $rows): array
    {
        $grandTotal = null;
        $data = [];

        foreach ($rows as $row) {
            if (($row['service_type'] ?? null) === '_grand_total') {
                $grandTotal = $row;

                continue;
            }

            $data[] = $row;
        }

        return [$data, $grandTotal];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function renumberSerialNumbers(array $rows, int $offset): array
    {
        $renumbered = [];

        foreach ($rows as $index => $row) {
            if (array_key_exists('s_no', $row)) {
                $row['s_no'] = $offset + $index + 1;
            }

            $renumbered[] = $row;
        }

        return $renumbered;
    }
}
