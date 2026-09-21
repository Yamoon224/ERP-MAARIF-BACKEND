<?php

namespace App\Domains\Expenses\Http\Controllers;

use App\Domains\Expenses\Http\Requests\CancelExpenseRequest;
use App\Domains\Expenses\Http\Requests\ExpenseRequest;
use App\Domains\Expenses\Http\Resources\ExpenseResource;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Registre des depenses et approvisionnements de l'etablissement. */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            ...Period::rules(),
            'expense_category_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:valid,cancelled'],
        ]);

        return ExpenseResource::collection($this->expenses->list(
            $request->only('expense_category_id', 'method', 'status', 'search', 'academic_year', 'term_id', 'month', 'sort', 'direction'),
            $request->integer('per_page', 15),
        ));
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($this->expenses->find($expense->id));
    }

    /** Fournisseurs deja saisis, pour la saisie semi-automatique. */
    public function suppliers(): JsonResponse
    {
        return response()->json(['data' => $this->expenses->suppliers()]);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->register($request->validated(), $request->user()->id);

        return (new ExpenseResource($this->expenses->find($expense->id)))->response()->setStatusCode(201);
    }

    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        return new ExpenseResource($this->expenses->update($expense, $request->validated()));
    }

    public function cancel(CancelExpenseRequest $request, Expense $expense): ExpenseResource
    {
        return new ExpenseResource(
            $this->expenses->cancel($expense, $request->validated('reason'), $request->user()->id),
        );
    }
}
