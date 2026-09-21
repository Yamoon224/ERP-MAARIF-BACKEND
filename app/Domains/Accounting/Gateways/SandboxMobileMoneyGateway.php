<?php

namespace App\Domains\Accounting\Gateways;

use App\Domains\Accounting\Contracts\MobileMoneyGatewayContract;
use App\Domains\Accounting\DTOs\MobileMoneyResult;
use App\Domains\Accounting\Enums\MobileMoneyStatus;
use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Str;

/**
 * Simulateur d'opérateur, sans aucun appel réseau : sert au développement, aux
 * démonstrations et aux tests. Le scénario dépend des deux derniers chiffres
 * du numéro, pour pouvoir rejouer chaque issue à la main :
 *
 * - finit par 00 : refusé (solde insuffisant) ;
 * - finit par 99 : le parent ne valide jamais, la demande finit par expirer ;
 * - autre numéro : confirmée après `mobile_money.sandbox_delay_seconds`.
 */
final class SandboxMobileMoneyGateway implements MobileMoneyGatewayContract
{
    public function requestPayment(MobileMoneyTransaction $transaction): MobileMoneyResult
    {
        return new MobileMoneyResult(MobileMoneyStatus::Pending, 'SBX-'.Str::upper(Str::random(10)));
    }

    public function checkStatus(MobileMoneyTransaction $transaction): MobileMoneyResult
    {
        $reference = $transaction->provider_reference;
        $lastDigits = substr($transaction->phone, -2);

        if ($lastDigits === '00') {
            return new MobileMoneyResult(MobileMoneyStatus::Failed, $reference, 'Solde insuffisant sur le compte mobile money.');
        }

        $delay = (int) config('mobile_money.sandbox_delay_seconds', 4);
        $confirmed = $lastDigits !== '99' && ! $transaction->created_at->copy()->addSeconds($delay)->isFuture();

        return new MobileMoneyResult($confirmed ? MobileMoneyStatus::Successful : MobileMoneyStatus::Pending, $reference);
    }
}
