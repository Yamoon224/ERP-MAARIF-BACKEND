<?php

namespace App\Domains\Results\Contracts;

use App\Models\PromotionDecision;
use Illuminate\Support\Collection;

interface PromotionDecisionRepositoryContract
{
    /**
     * @param  list<string>  $enrollmentIds
     * @return Collection<string, PromotionDecision> identifiant d'inscription => decision
     */
    public function forEnrollments(array $enrollmentIds): Collection;

    /** Cree la decision de l'inscription, ou remplace celle qui existe deja (une par inscription). */
    public function save(string $enrollmentId, string $decision, ?float $average, ?string $note, string $userId): PromotionDecision;
}
