<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Academics\Http\Resources\SchoolClassResource;
use App\Domains\Academics\Services\SchoolClassService;
use App\Domains\Accounting\Http\Requests\UpdateClassFeeRequest;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;

/**
 * Scolarite mensuelle d'une classe. Route dediee, reservee a la comptabilite :
 * modifier la classe elle-meme (nom, niveau) reste un droit d'administration
 * academique distinct. Le changement de tarif se repercute sur les echeances
 * non reglees (voir SchoolClassFeeObserver).
 */
class FeeController extends Controller
{
    public function __construct(private readonly SchoolClassService $classes) {}

    public function update(UpdateClassFeeRequest $request, SchoolClass $schoolClass): SchoolClassResource
    {
        return new SchoolClassResource(
            $this->classes->update($schoolClass, ['monthly_fee' => $request->validated('monthly_fee')]),
        );
    }
}
