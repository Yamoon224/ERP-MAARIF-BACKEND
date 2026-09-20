<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moyenne de passage
    |--------------------------------------------------------------------------
    |
    | Moyenne generale (sur 20) a partir de laquelle un eleve est propose a
    | l'admission en classe superieure a la fin de l'annee. Elle n'est qu'une
    | suggestion : la decision finale est validee par l'administration (voir
    | App\Domains\Results\Services\ResultsService).
    |
    */

    'pass_mark' => (float) env('SCHOOL_PASS_MARK', 10),

];
