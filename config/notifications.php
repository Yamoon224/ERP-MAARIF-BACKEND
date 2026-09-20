<?php

return [

    /*
     * Pilote d'envoi des notifications aux tuteurs (convocations, sanctions).
     *
     * - "log"   : envoie reellement les e-mails via le mailer configure et
     *             journalise les SMS (par defaut, voir NOTIFICATIONS_DRIVER).
     * - "array" : conserve les messages en memoire sans les envoyer (tests).
     */
    'driver' => env('NOTIFICATIONS_DRIVER', 'log'),

];
