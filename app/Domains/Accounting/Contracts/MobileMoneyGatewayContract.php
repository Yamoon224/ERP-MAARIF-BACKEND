<?php

namespace App\Domains\Accounting\Contracts;

use App\Domains\Accounting\DTOs\MobileMoneyResult;
use App\Models\MobileMoneyTransaction;

/**
 * Dialogue avec l'opérateur de mobile money : lancer la demande de paiement
 * (le parent reçoit une invite sur son téléphone), puis en connaître l'issue.
 *
 * Aucune des deux méthodes ne lève d'exception pour un refus de l'opérateur :
 * un solde insuffisant ou un code PIN erroné sont des issues ordinaires,
 * rendues par un statut `Failed` avec la raison.
 */
interface MobileMoneyGatewayContract
{
    public function requestPayment(MobileMoneyTransaction $transaction): MobileMoneyResult;

    public function checkStatus(MobileMoneyTransaction $transaction): MobileMoneyResult;
}
