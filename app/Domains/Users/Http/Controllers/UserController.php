<?php

namespace App\Domains\Users\Http\Controllers;

use App\Domains\Users\Http\Requests\StoreUserRequest;
use App\Domains\Users\Http\Requests\UpdateUserRequest;
use App\Domains\Users\Http\Resources\UserResource;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Administration des comptes du personnel : reservee au role `admin` (voir routes/api.php). */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->users->list(
                $request->only('search', 'role', 'is_active', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->only('name', 'email', 'phone', 'password', 'is_active');
        /** @var list<string> $roles */
        $roles = $request->input('roles');

        return (new UserResource($this->users->create($data, $roles)->load('roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($this->users->find($user->id));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->safe()->only('name', 'email', 'phone', 'password', 'is_active');
        /** @var list<string>|null $roles */
        $roles = $request->input('roles');

        return new UserResource($this->users->update($user, $data, $roles)->load('roles'));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->users->delete($user, $request->user()?->id);

        return response()->noContent();
    }
}
