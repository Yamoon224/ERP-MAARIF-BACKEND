<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Exceptions\UserNotDeletableException;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Administration des comptes du personnel (cahier des charges 3.1 : "gestion
 * des droits d'acces").
 *
 * Un administrateur ne peut pas supprimer son propre compte : une plateforme
 * qui se retrouve sans administrateur ne se repare que par acces direct a la
 * base de donnees.
 */
final class UserService
{
    public function __construct(private readonly UserRepositoryContract $users) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    public function find(string $id): User
    {
        return $this->users->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    public function create(array $data, array $roles): User
    {
        return DB::transaction(function () use ($data, $roles): User {
            $user = $this->users->create($data);
            $user->syncRoles($roles);

            return $user->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles  null laisse les roles inchanges
     */
    public function update(User $user, array $data, ?array $roles = null): User
    {
        return DB::transaction(function () use ($user, $data, $roles): User {
            // Un mot de passe vide dans un formulaire d'edition signifie « ne
            // pas changer », jamais « effacer ».
            if (($data['password'] ?? null) === null) {
                unset($data['password']);
            }

            $updated = $this->users->update($user, $data);

            if ($roles !== null) {
                $updated->syncRoles($roles);
            }

            return $updated->refresh();
        });
    }

    /**
     * Definit un nouveau mot de passe a un compte (oubli, depart d'un poste...) et ferme ses sessions : celui qui
     * connaissait l'ancien mot de passe ne doit pas rester connecte. Si l'administrateur reinitialise le sien,
     * sa session courante est conservee.
     */
    public function resetPassword(User $user, string $newPassword, ?PersonalAccessToken $keep = null): void
    {
        DB::transaction(function () use ($user, $newPassword, $keep): void {
            $user->update(['password' => $newPassword]);

            $user->tokens()
                ->when($keep !== null, fn ($tokens) => $tokens->where('id', '!=', $keep->id))
                ->delete();
        });
    }

    /** @throws UserNotDeletableException */
    public function delete(User $user, ?string $currentUserId = null): void
    {
        if ($currentUserId !== null && $user->id === $currentUserId) {
            throw UserNotDeletableException::self();
        }

        $this->users->delete($user);
    }
}
