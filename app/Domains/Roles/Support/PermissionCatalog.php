<?php

namespace App\Domains\Roles\Support;

/**
 * Catalogue des permissions de la plateforme et des roles « systeme ».
 *
 * Les permissions sont definies par le code (chaque route en exige une, voir
 * routes/api.php) : l'interface d'administration peut les attribuer ou les
 * retirer a un role, pas en inventer, car une permission que aucune route ne
 * controle n'aurait aucun effet. Ce catalogue en donne le libelle lisible
 * affiche par l'ecran d'administration des roles.
 *
 * Les roles systeme (`admin`, `teacher`, `accountant`) sont references par le
 * code (ex. Academics\Rules\IsTeacher) : ils ne peuvent etre ni renommes ni
 * supprimes. Tout autre role est libre.
 */
final class PermissionCatalog
{
    /**
     * Permission => raison d'etre.
     *
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'users.manage' => 'Créer, modifier et désactiver les comptes du personnel.',
        'roles.manage' => 'Créer, modifier et supprimer les rôles, attribuer et retirer leurs permissions.',
        'academics.view' => 'Consulter les classes, matières et trimestres.',
        'academics.manage' => 'Administrer les classes, matières et trimestres.',
        'students.view' => 'Consulter le dossier des élèves et leurs bulletins.',
        'students.manage' => 'Inscrire, modifier et désactiver un élève.',
        'grades.manage' => 'Saisir et modifier les notes.',
        'attendance.manage' => 'Saisir les présences et absences.',
        'discipline.manage' => 'Créer des convocations et des sanctions.',
        'notifications.view' => 'Consulter le journal des notifications envoyées aux tuteurs.',
        'notifications.manage' => 'Renvoyer un message en échec depuis le journal des notifications.',
        'accounting.view' => 'Consulter les paiements de scolarité, les relevés et les impayés.',
        'accounting.manage' => 'Encaisser et annuler des paiements, fixer les frais de scolarité des classes.',
        'expenses.view' => "Consulter les dépenses et approvisionnements de l'établissement.",
        'expenses.manage' => 'Saisir, corriger et annuler les dépenses, gérer les catégories de dépenses.',
        'admissions.view' => "Consulter les candidatures d'admission.",
        'admissions.manage' => "Déposer, instruire et transformer en inscription les candidatures d'admission.",
        'results.view' => 'Consulter les résultats par trimestre, semestre et année.',
        'results.manage' => "Valider ou corriger les décisions de passage de fin d'année.",
    ];

    /**
     * Role systeme => libelle.
     *
     * @var array<string, string>
     */
    public const SYSTEM_ROLES = [
        'admin' => 'Administrateur',
        'teacher' => 'Enseignant',
        'accountant' => 'Comptable',
    ];

    /**
     * Domaine (prefixe de la permission) => libelle du groupe affiche.
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'users' => 'Utilisateurs',
        'roles' => 'Rôles',
        'academics' => 'Structure académique',
        'students' => 'Élèves',
        'grades' => 'Notes',
        'attendance' => 'Présences',
        'discipline' => 'Discipline',
        'notifications' => 'Notifications',
        'accounting' => 'Comptabilité',
        'expenses' => 'Dépenses',
        'admissions' => 'Admissions',
        'results' => 'Résultats',
    ];

    private function __construct() {}

    public static function isSystemRole(string $role): bool
    {
        return array_key_exists($role, self::SYSTEM_ROLES);
    }

    public static function roleLabel(string $role): string
    {
        return self::SYSTEM_ROLES[$role] ?? $role;
    }

    public static function description(string $permission): ?string
    {
        return self::PERMISSIONS[$permission] ?? null;
    }

    /** Prefixe de la permission : `students.view` appartient au groupe `students`. */
    public static function group(string $permission): string
    {
        return explode('.', $permission)[0];
    }

    public static function groupLabel(string $permission): string
    {
        $group = self::group($permission);

        return self::GROUPS[$group] ?? ucfirst($group);
    }
}
