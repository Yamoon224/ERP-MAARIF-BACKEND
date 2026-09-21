<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Accounting\Enums\MobileMoneyStatus;
use App\Domains\Accounting\Http\Resources\MobileMoneyTransactionResource;
use App\Domains\Accounting\Services\MobileMoneyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Suivi, par la comptabilité, des paiements mobile money initiés par les parents. */
class MobileMoneyController extends Controller
{
    public function __construct(private readonly MobileMoneyService $mobileMoney) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(MobileMoneyStatus::values())],
            'student_id' => ['nullable', 'uuid'],
        ]);

        return MobileMoneyTransactionResource::collection(
            $this->mobileMoney->list($request->only('status', 'student_id'), $request->integer('per_page', 15)),
        );
    }
}
