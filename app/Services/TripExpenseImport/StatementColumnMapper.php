<?php

namespace App\Services\TripExpenseImport;

class StatementColumnMapper
{
    public const TYPE_FASTAG = 'fastag';

    public const TYPE_FUEL = 'fuel';

    /**
     * @return list<array{key: string, label: string, required: bool}>
     */
    public function fieldsFor(string $importType): array
    {
        if ($importType === self::TYPE_FUEL) {
            return [
                ['key' => 'transaction_date', 'label' => 'Date', 'required' => false],
                ['key' => 'transaction_time', 'label' => 'Time', 'required' => false],
                ['key' => 'date_time', 'label' => 'Date & time (single column)', 'required' => false],
                ['key' => 'vrn', 'label' => 'Vehicle number (VRN)', 'required' => false],
                ['key' => 'pump', 'label' => 'Pump / outlet', 'required' => true],
                ['key' => 'volume', 'label' => 'Volume (liters)', 'required' => true],
                ['key' => 'rate', 'label' => 'Rate (₹/L)', 'required' => false],
                ['key' => 'amount', 'label' => 'Amount', 'required' => false],
                ['key' => 'reference', 'label' => 'Transaction ID', 'required' => false],
                ['key' => 'product', 'label' => 'Product', 'required' => false],
                ['key' => 'credit_debit', 'label' => 'Credit / Debit', 'required' => false],
                ['key' => 'txn_type', 'label' => 'Transaction type', 'required' => false],
            ];
        }

        return [
            ['key' => 'transaction_date', 'label' => 'Date', 'required' => false],
            ['key' => 'transaction_time', 'label' => 'Time', 'required' => false],
            ['key' => 'date_time', 'label' => 'Date & time (single column)', 'required' => false],
            ['key' => 'vrn', 'label' => 'Vehicle number (VRN)', 'required' => true],
            ['key' => 'debit', 'label' => 'Debit / Amount', 'required' => true],
            ['key' => 'reference', 'label' => 'Transaction ID', 'required' => false],
            ['key' => 'description', 'label' => 'Description / Plaza', 'required' => false],
            ['key' => 'type', 'label' => 'Transaction type', 'required' => false],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function aliasesFor(string $importType): array
    {
        $sharedDate = [
            'transactiondate' => ['transactiondate', 'txndate', 'date', 'transdate', 'transactiondt'],
            'transactiontime' => ['transactiontime', 'txntime', 'time', 'transtime'],
            'datetime' => ['datetime', 'transactiondatetime', 'txndatetime', 'dateandtime', 'transdatetime'],
            'vrn' => ['vrn', 'vehiclenumber', 'vehicleno', 'vehicleregistration', 'registrationnumber', 'regnno', 'regno', 'vehicleregno', 'trucknumber', 'truckno'],
            'reference' => ['reference', 'txnid', 'transactionid', 'referenceno', 'refno', 'utr', 'rrn', 'transactionref'],
        ];

        if ($importType === self::TYPE_FUEL) {
            return [
                'transaction_date' => $sharedDate['transactiondate'],
                'transaction_time' => $sharedDate['transactiontime'],
                'date_time' => $sharedDate['datetime'],
                'vrn' => $sharedDate['vrn'],
                'pump' => ['pump', 'pumpname', 'outlet', 'outletname', 'retailoutlet', 'station', 'stationname', 'fuelstation', 'merchant', 'merchantname', 'dealer'],
                'volume' => ['volume', 'liters', 'litres', 'qty', 'quantity', 'fuellitres', 'fuelliters'],
                'rate' => ['rate', 'unitprice', 'price', 'priceperltr', 'priceperliter', 'priceperlitre', 'fuerrate'],
                'amount' => ['amount', 'txnamount', 'transactionamount', 'total', 'totalamount', 'debit', 'debitamount'],
                'reference' => $sharedDate['reference'],
                'product' => ['product', 'productname', 'fueltype', 'producttype', 'item'],
                'credit_debit' => ['creditdebit', 'crdr', 'drcr', 'transtype', 'transactionindicator', 'debitcredit'],
                'txn_type' => ['txntype', 'transactiontype', 'type', 'narrationtype'],
            ];
        }

        return [
            'transaction_date' => $sharedDate['transactiondate'],
            'transaction_time' => $sharedDate['transactiontime'],
            'date_time' => $sharedDate['datetime'],
            'vrn' => $sharedDate['vrn'],
            'debit' => ['debit', 'debitamount', 'amount', 'txnamount', 'transactionamount', 'tollamount', 'toll'],
            'reference' => $sharedDate['reference'],
            'description' => ['description', 'plaza', 'plazaname', 'narration', 'particulars', 'merchant', 'tollplaza'],
            'type' => ['type', 'txntype', 'transactiontype', 'crdr', 'creditdebit'],
        ];
    }

    public function normalizeHeader(?string $label): string
    {
        $normalized = strtolower(trim((string) $label));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';

        return $normalized;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{header_row: int, candidates: list<array{index: int, label: string}>}
     */
    public function detectHeaderRow(array $rows): array
    {
        $candidates = [];
        $bestScore = -1;
        $bestIndex = 1;

        $limit = min(count($rows), 30);

        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i] ?? [];
            $labels = [];
            $nonEmpty = 0;

            foreach ($row as $cell) {
                if ($cell === null || $cell === '') {
                    continue;
                }
                $nonEmpty++;
                $labels[] = is_scalar($cell) ? (string) $cell : '';
            }

            if ($nonEmpty < 2) {
                continue;
            }

            $score = $nonEmpty;
            foreach ($labels as $label) {
                $norm = $this->normalizeHeader($label);
                if ($norm === '') {
                    continue;
                }
                if (preg_match('/date|time|vrn|vehicle|amount|debit|liter|litre|volume|pump|plaza|ref|txn/', $norm)) {
                    $score += 3;
                }
                if (is_numeric($label) && (float) $label > 1000) {
                    $score -= 2;
                }
            }

            $preview = implode(' · ', array_slice($labels, 0, 6));
            $candidates[] = [
                'index' => $i + 1,
                'label' => 'Row '.($i + 1).': '.$preview,
            ];

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i + 1;
            }
        }

        if ($candidates === []) {
            $candidates[] = ['index' => 1, 'label' => 'Row 1'];
        }

        return [
            'header_row' => $bestIndex,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  list<mixed>  $headerCells
     * @return array{columns: list<array{index: int, label: string}>, suggested: array<string, int|null>}
     */
    public function analyzeHeaders(string $importType, array $headerCells): array
    {
        $columns = [];
        foreach ($headerCells as $index => $cell) {
            $label = $cell === null || $cell === '' ? 'Column '.($index + 1) : (string) $cell;
            $columns[] = [
                'index' => $index,
                'label' => $label,
            ];
        }

        $suggested = [];
        $used = [];
        $aliases = $this->aliasesFor($importType);

        foreach ($aliases as $field => $fieldAliases) {
            $match = $this->suggestColumn($headerCells, $fieldAliases, $used);
            $suggested[$field] = $match;
            if ($match !== null) {
                $used[$match] = true;
            }
        }

        return [
            'columns' => $columns,
            'suggested' => $suggested,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $map
     * @return list<string>
     */
    public function validateMap(string $importType, array $map): array
    {
        $errors = [];
        $hasDateTime = $this->isMapped($map, 'date_time');
        $hasDate = $this->isMapped($map, 'transaction_date');

        if (! $hasDateTime && ! $hasDate) {
            $errors[] = 'Map either Date & time or Transaction date.';
        }

        if ($importType === self::TYPE_FASTAG) {
            if (! $this->isMapped($map, 'vrn')) {
                $errors[] = 'Vehicle number is required.';
            }
            if (! $this->isMapped($map, 'debit')) {
                $errors[] = 'Debit / Amount is required.';
            }
        }

        if ($importType === self::TYPE_FUEL) {
            if (! $this->isMapped($map, 'pump')) {
                $errors[] = 'Pump / outlet name is required.';
            }
            if (! $this->isMapped($map, 'volume')) {
                $errors[] = 'Volume (liters) is required.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, int|string|null>  $map
     */
    public function isMapped(array $map, string $key): bool
    {
        return array_key_exists($key, $map)
            && $map[$key] !== null
            && $map[$key] !== ''
            && $map[$key] !== 'null';
    }

    /**
     * @param  list<mixed>  $headerCells
     * @param  list<string>  $aliases
     * @param  array<int, bool>  $used
     */
    private function suggestColumn(array $headerCells, array $aliases, array $used): ?int
    {
        $normalizedAliases = array_values(array_unique(array_map(fn ($a) => $this->normalizeHeader($a), $aliases)));

        foreach ($headerCells as $index => $cell) {
            if (isset($used[$index])) {
                continue;
            }
            $norm = $this->normalizeHeader(is_scalar($cell) ? (string) $cell : '');
            if ($norm === '') {
                continue;
            }
            if (in_array($norm, $normalizedAliases, true)) {
                return $index;
            }
        }

        foreach ($headerCells as $index => $cell) {
            if (isset($used[$index])) {
                continue;
            }
            $norm = $this->normalizeHeader(is_scalar($cell) ? (string) $cell : '');
            if ($norm === '' || strlen($norm) < 4) {
                continue;
            }
            foreach ($normalizedAliases as $alias) {
                if (strlen($alias) < 4) {
                    continue;
                }
                if (str_contains($norm, $alias) || str_contains($alias, $norm)) {
                    return $index;
                }
            }
        }

        return null;
    }
}
