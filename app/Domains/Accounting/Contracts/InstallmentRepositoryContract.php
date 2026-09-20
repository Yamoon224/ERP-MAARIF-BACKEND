<?php

namespace App\Domains\Accounting\Contracts;

use App\Models\TuitionInstallment;
use Illuminate\Support\Collection;

interface InstallmentRepositoryContract
{
    /** Echeances d'une inscription, du premier au dernier mois.
     *
     * @return Collection<int, TuitionInstallment>
     */
    public function forEnrollment(string $enrollmentId): Collection;

    /**
     * Echeances non reglees, du plus ancien mois au plus recent. Avec `$lock`,
     * les lignes sont verrouillees pour la transaction en cours : deux
     * encaissements simultanes ne peuvent pas regler le meme mois.
     *
     * @return Collection<int, TuitionInstallment>
     */
    public function unpaidForEnrollment(string $enrollmentId, bool $lock = false): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): TuitionInstallment;

    public function updateAmount(TuitionInstallment $installment, float $amount): void;

    /** @param  list<string>  $installmentIds */
    public function assignToPayment(array $installmentIds, string $paymentId): void;

    /** Libere les echeances reglees par ce paiement (annulation). */
    public function releasePayment(string $paymentId): void;
}
