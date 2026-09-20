<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Jeton a cle UUID, alignee sur le reste du schema.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /*
         * Une relation non chargee explicitement declenche une requete
         * silencieuse par ligne : sur un tableau de classe qui affiche vingt
         * eleves avec leur classe et leurs dernieres notes, cela fait
         * soixante requetes invisibles, decouvertes seulement en production.
         *
         * Desactive en production : une relation oubliee doit degrader la
         * performance, pas casser l'ecran d'un parent en train de consulter
         * le bulletin de son enfant.
         */
        Model::preventLazyLoading(! $this->app->isProduction());

        // Une ecriture sur un attribut absent de `$fillable` est une erreur de
        // developpement, pas une donnee a ignorer en silence.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
