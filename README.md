# ERP Maarif — Backend

API REST Laravel pour le suivi scolaire des eleves (notes, presences, convocations, sanctions), consultable par les parents via le matricule de leur enfant. Voir le [cahier des charges](../cahier%20de%20charge.pdf).

## Architecture

Le code metier vit dans `app/Domains/<Domaine>`, chacun suivant la meme structure :

```
Domains/<Domaine>/
  Contracts/    interfaces de persistance (inversion de dependance)
  Repositories/ implementations Eloquent des contrats
  Services/     regles metier, orchestrent repositories + notifications
  Http/
    Controllers/
    Requests/    validation des entrees
    Resources/   forme JSON des reponses
  Enums/
```

`App\Providers\DomainServiceProvider` cable chaque contrat a son implementation : un service ne connait jamais une classe Eloquent concrete, ce qui permet de substituer une implementation en test (voir `App\Domains\Notifications\Senders\ArrayNotificationSender`).

### Domaines

| Domaine | Responsabilite |
|---|---|
| `Auth` | Connexion du personnel (e-mail) et des parents (matricule de l'eleve) |
| `Users` | Comptes administrateurs et enseignants, roles Spatie |
| `Students` | Inscription des eleves, generation du matricule et du mot de passe initial |
| `Academics` | Classes, matieres, trimestres |
| `Grades` | Saisie des notes, calcul des bulletins ponderes par coefficient |
| `Attendance` | Presences, absences, retards |
| `Discipline` | Convocations et sanctions, avec notification automatique du tuteur |
| `Notifications` | Envoi e-mail/SMS au tuteur, journalise dans `notification_logs` |

### Authentification

Deux types de comptes, un seul mecanisme de jeton (Sanctum) :

- **Personnel** (`App\Models\User`) : `POST /api/login` avec e-mail + mot de passe. Roles `admin` / `teacher` via Spatie Permission.
- **Parent** (`App\Models\Student`) : `POST /api/parent/login` avec le **matricule de l'eleve** + mot de passe (cahier des charges 3.1). Le middleware `account_type:staff|parent` cloisonne les deux zones de l'API : un jeton parent ne peut jamais atteindre une route d'administration, et reciproquement.

## Mise en route

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurer DB_* dans .env (MySQL par defaut)
php artisan migrate --seed
php artisan serve
```

Le seeder cree un administrateur (`admin@maarif.test` / `password`), un enseignant (`enseignant@maarif.test` / `password`) et un jeu de demonstration (classe, matieres, trimestres, eleves, notes).

## Tests

```bash
composer test          # suite complete (tests/Unit + tests/Feature)
composer test:unit
composer test:feature
composer format         # Pint
composer lint           # Pint --test
```

Les tests tournent sur SQLite en memoire (voir `phpunit.xml`) : aucune base a preparer.
# ERP-MAARIF-BACKEND
