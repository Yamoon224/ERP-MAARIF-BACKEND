<?php

return [

    /*
     * Passerelle de paiement mobile money.
     *
     * - "sandbox" : simulateur sans opérateur réel (par défaut). Une demande
     *               aboutit après `sandbox_delay_seconds`, sauf pour un numéro
     *               finissant par 00 (solde insuffisant : échec) ou par 99
     *               (jamais validée : expire).
     *
     * Un opérateur réel (Orange Money, MTN MoMo, Moov Money) se branche en
     * écrivant une classe qui implémente MobileMoneyGatewayContract, puis en la
     * déclarant dans DomainServiceProvider ; il faut pour cela les identifiants
     * marchands fournis par l'opérateur.
     */
    'driver' => env('MOBILE_MONEY_DRIVER', 'sandbox'),

    /* Minutes laissées au parent pour valider la demande sur son téléphone. */
    'expires_after_minutes' => (int) env('MOBILE_MONEY_EXPIRES_AFTER_MINUTES', 15),

    /* Simulateur : secondes avant qu'une demande soit confirmée. */
    'sandbox_delay_seconds' => (int) env('MOBILE_MONEY_SANDBOX_DELAY', 4),

];
