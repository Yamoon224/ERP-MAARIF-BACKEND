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
| `Users` | Comptes du personnel (administrateurs, enseignants, comptables), roles Spatie |
| `Students` | Inscription des eleves, matricule, mot de passe initial, **inscriptions annuelles** |
| `Academics` | Classes, matieres, trimestres, annees scolaires |
| `Grades` | Saisie des notes, calcul des bulletins ponderes par coefficient |
| `Attendance` | Presences, **appel de classe**, absences, retards, justification |
| `Discipline` | Convocations et sanctions, avec notification automatique du tuteur |
| `Accounting` | **Scolarite mensuelle**, paiements (mois, trimestre, semestre, annee), recus, impayes |
| `Expenses` | **Depenses et approvisionnements** (craies, registres, factures...) : numero `DEP-annee-sequence`, montant = quantite x prix unitaire, categories, annulation avec motif, bilan par categorie / mode / mois |
| `Reporting` | Tableau de bord et detail d'un trimestre (agregats sur plusieurs domaines) |
| `Notifications` | Envoi e-mail/SMS au tuteur, journalise dans `notification_logs` |

### Authentification

Deux types de comptes, un seul mecanisme de jeton (Sanctum) :

- **Personnel** (`App\Models\User`) : `POST /api/login` avec e-mail + mot de passe. Roles `admin` / `teacher` / `accountant` via Spatie Permission. `PUT /api/me` et `PUT /api/me/password` : profil et mot de passe (les autres sessions sont fermees au changement de mot de passe).
- **Parent** (`App\Models\Student`) : `POST /api/parent/login` avec le **matricule de l'eleve** + mot de passe (cahier des charges 3.1). Le middleware `account_type:staff|parent` cloisonne les deux zones de l'API : un jeton parent ne peut jamais atteindre une route d'administration, et reciproquement.

**Se souvenir de moi.** Les deux `login` acceptent `remember` (booleen). Le jeton expire au bout de 12 h sans la case cochee, de 30 jours avec (`AUTH_TOKEN_TTL_MINUTES` / `AUTH_REMEMBERED_TOKEN_TTL_MINUTES`, voir `config/auth.php`).

**Mot de passe oublie.** `POST /api/forgot-password` (`email`) et `POST /api/parent/forgot-password` (`matricule`) envoient un lien a usage unique, valable 60 minutes : par e-mail au compte du personnel, au tuteur de l'eleve (e-mail, sinon SMS) pour un parent. La reponse est identique que le compte existe ou non, et le lien ne part jamais dans le journal des notifications. `POST /api/reset-password` et `POST /api/parent/reset-password` (`token`, `email` ou `matricule`, `password`, `password_confirmation`) choisissent le nouveau mot de passe et ferment toutes les sessions. Le lien pointe vers l'interface (`FRONTEND_URL`), pas vers l'API. En developpement (`MAIL_MAILER=log`), le lien se lit dans `storage/logs/laravel.log`.

**Depenses.** `GET/POST /api/expenses`, `PUT /api/expenses/{id}`, `POST /api/expenses/{id}/cancel` (le montant est calcule cote serveur ; une depense annulee sort des totaux mais reste au registre), `GET /api/expenses/summary` (filtres de periode ; une annee entiere va du 1er septembre au 31 aout pour inclure les achats de rentree), `GET /api/expenses/suppliers` (saisie semi-automatique), `/api/expense-categories` (liste, creation, renommage, desactivation ; une categorie utilisee ne se supprime pas). Permissions `expenses.view` / `expenses.manage` (administrateur et comptable) ; apres mise a jour : `php artisan migrate`, puis `db:seed --class=RolesAndPermissionsSeeder` et `--class=ExpenseCategorySeeder`.

### Annee scolaire, trimestres, filtres

Une annee scolaire (`2025-2026`) compte **trois trimestres**. Un eleve s'inscrit pour **l'annee entiere** : la table `enrollments` garde une inscription par eleve et par annee (`students.school_class_id` reste la classe *actuelle*, synchronisee par `StudentEnrollmentObserver`). Reinscrire un eleve n'efface donc jamais son historique : `POST /api/students/{id}/enrollments`.

Les listes et indicateurs (presences, notes, sanctions, convocations, paiements, tableau de bord, comptabilite) acceptent les memes filtres de periode, resolus par `App\Domains\Shared\Support\Period` :

| Parametre | Exemple | Sens |
|---|---|---|
| `academic_year` | `2025-2026` | de la premiere a la derniere date des trimestres de l'annee |
| `term_id` | uuid | dates du trimestre |
| `month` | `2025-11` | le mois (l'emporte sur les deux autres) |

`GET /api/academic-years` liste les annees avec leurs trimestres (portail parent : `/api/parent/academic-years`). `GET /api/terms/{id}/overview|subjects|students` donne le detail d'un trimestre ; ses notes, sanctions, convocations et presences se lisent avec les listes habituelles filtrees par `term_id`.

### Absences

`GET /api/attendance-records/roll-call` (feuille d'appel d'une classe), `POST /api/attendance-records/bulk` (appel enregistre en un seul bloc, tout ou rien), `GET /api/attendance-records/summary` (bilan et eleves les plus absents). Une absence se justifie apres coup par `PUT /api/attendance-records/{id}` (`justified`, `reason`).

### Scolarite et comptabilite

La scolarite est **mensuelle** (`school_classes.monthly_fee`) ; la famille la regle par mois, trimestre, semestre ou annee : ce n'est que le nombre de mois regles d'un coup (1, 3, 6, tous les restants), toujours en commencant par le plus ancien impaye. Les mois d'une annee se deduisent de ses trimestres (octobre a juin = 9 mois) et forment un echeancier (`tuition_installments`) genere a l'inscription, a la modification du tarif de la classe et a celle des trimestres. Le montant est calcule par le serveur, jamais fourni par le client.

Permissions : `accounting.view` (paiements, releves, impayes) et `accounting.manage` (encaisser, annuler, fixer les tarifs). Un paiement n'est jamais supprime : l'annuler libere ses mois et garde le recu. L'encaissement d'une *annee* compte les paiements de ses inscriptions (donc les paiements anticipes) ; celui d'un *trimestre* ou d'un *mois* suit la date du paiement.

## Mise en route

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurer DB_* dans .env (MySQL par defaut)
php artisan migrate --seed
php artisan serve
```

Le seeder cree un administrateur (`admin@maarif.test`), un enseignant (`enseignant@maarif.test`), un comptable (`comptable@maarif.test`), tous en `password`, et un jeu de demonstration sur deux annees scolaires (classes, matieres, eleves reinscrits, notes, presences, sanctions, paiements).

**Base deja en service** : apres `php artisan migrate`, relancer `php artisan db:seed --class=RolesAndPermissionsSeeder` (idempotent) pour creer les permissions `accounting.*` et le role `accountant`. Les eleves deja affectes a une classe recoivent automatiquement leur inscription (migration).

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
