<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteMonthlyExpenseRequest;
use App\MonthlyExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MonthlyExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('monthly_expenses.view');

        $perPage = min(max((int) $request->input('per_page', 25), 1), 500);
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $month = $request->filled('month') ? $request->string('month')->toString() : null;

        $query = MonthlyExpense::query()
            ->latest('month')
            ->latest('created_at');

        if ($search !== null) {
            $query->where('particulars', 'like', "%{$search}%");
        }

        if ($month !== null) {
            $query->where('month', $month);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (MonthlyExpense $expense): array => $this->toArray($expense))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('monthly_expenses.create');

        $validated = $this->validatedPayload($request);
        $expense = MonthlyExpense::query()->create($validated);

        return response()->json(['data' => $this->toArray($expense)], 201);
    }

    public function show(MonthlyExpense $monthlyExpense): JsonResponse
    {
        $this->authorize('monthly_expenses.view');

        return response()->json(['data' => $this->toArray($monthlyExpense)]);
    }

    public function update(Request $request, MonthlyExpense $monthlyExpense): JsonResponse
    {
        $this->authorize('monthly_expenses.edit');

        $monthlyExpense->update($this->validatedPayload($request));

        return response()->json(['data' => $this->toArray($monthlyExpense->fresh())]);
    }

    public function destroy(
        DeleteMonthlyExpenseRequest $request,
        MonthlyExpense $monthlyExpense
    ): Response {
        $this->authorize('monthly_expenses.edit');

        $monthlyExpense->update([
            'deleted_reason' => $request->validated('deleted_reason'),
            'deleted_by' => $request->user()?->id,
        ]);

        $monthlyExpense->delete();

        return response()->noContent();
    }

    /**
     * @return array{month: string, particulars: string, amount: float|int|string}
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'particulars' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'month.required' => 'Month is required.',
            'month.date_format' => 'Month must be in YYYY-MM format.',
            'particulars.required' => 'Particulars are required.',
            'amount.required' => 'Amount is required.',
            'amount.gt' => 'Amount must be greater than zero.',
        ]);
    }

    /**
     * @return array{id: string, month: string, particulars: string, amount: float, created_at: string|null, updated_at: string|null}
     */
    private function toArray(MonthlyExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'month' => $expense->month,
            'particulars' => $expense->particulars,
            'amount' => round((float) $expense->amount, 2),
            'created_at' => $expense->created_at?->toIso8601String(),
            'updated_at' => $expense->updated_at?->toIso8601String(),
        ];
    }
}
