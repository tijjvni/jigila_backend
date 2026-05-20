<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $users   = $this->userService->list($request->only(['type', 'status', 'search']), $perPage);

        return AdminUserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return (new AdminUserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): AdminUserResource
    {
        return new AdminUserResource($user->load('adminRoles'));
    }

    public function update(UpdateUserRequest $request, User $user): AdminUserResource
    {
        return new AdminUserResource($this->userService->update($user, $request->validated()));
    }

    public function archive(User $user): AdminUserResource
    {
        return new AdminUserResource($this->userService->archive($user));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
