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

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $users   = $this->userService->list($request->only(['type', 'status', 'search']), $perPage);

        return $this->okResponse(AdminUserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->createdResponse(new AdminUserResource($user));
    }

    public function show(User $user): JsonResponse
    {
        return $this->okResponse(new AdminUserResource($user->load('adminRoles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return $this->okResponse(new AdminUserResource($this->userService->update($user, $request->validated())));
    }

    public function archive(User $user): JsonResponse
    {
        return $this->okResponse(new AdminUserResource($this->userService->archive($user)));
    }

    public function activate(User $user): JsonResponse
    {
        return $this->okResponse(new AdminUserResource($this->userService->activate($user)));
    }

    public function resetPassword(User $user): JsonResponse
    {
        $this->userService->resetPasswordByAdmin($user);

        return $this->okResponse(['message' => 'Password reset and emailed to user.']);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return $this->messageResponse('User deleted successfully.');
    }
}
