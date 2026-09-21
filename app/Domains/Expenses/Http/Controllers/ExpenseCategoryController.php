<?php

namespace App\Domains\Expenses\Http\Controllers;

use App\Domains\Expenses\Http\Requests\ExpenseCategoryRequest;
use App\Domains\Expenses\Http\Resources\ExpenseCategoryResource;
use App\Domains\Expenses\Services\ExpenseCategoryService;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Postes de depense : liste complete (elle reste courte), creation, renommage, desactivation. */
class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $categories) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseCategoryResource::collection($this->categories->list($request->boolean('active_only')));
    }

    public function store(ExpenseCategoryRequest $request): JsonResponse
    {
        return (new ExpenseCategoryResource($this->categories->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): ExpenseCategoryResource
    {
        return new ExpenseCategoryResource($this->categories->update($expenseCategory, $request->validated()));
    }

    public function destroy(ExpenseCategory $expenseCategory): Response
    {
        $this->categories->delete($expenseCategory);

        return response()->noContent();
    }
}
