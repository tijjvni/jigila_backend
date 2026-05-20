<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\Admin\AdminRoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        return AdminRoleResource::collection($this->roleService->list($perPage));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return (new AdminRoleResource($role))->response()->setStatusCode(201);
    }

    public function show(Role $role): AdminRoleResource
    {
        return new AdminRoleResource($this->roleService->find($role));
    }

    public function update(UpdateRoleRequest $request, Role $role): AdminRoleResource
    {
        return new AdminRoleResource($this->roleService->update($role, $request->validated()));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    public function addUser(Role $role, User $user): AdminRoleResource
    {
        return new AdminRoleResource($this->roleService->addUser($role, $user));
    }

    public function removeUser(Role $role, User $user): AdminRoleResource
    {
        return new AdminRoleResource($this->roleService->removeUser($role, $user));
    }

    public function assign(AssignRoleRequest $request): JsonResponse
    {
        $this->roleService->assign(
            $request->validated('role_id'),
            $request->validated('user_ids'),
        );

        return response()->json(['message' => 'Role assigned successfully.']);
    }
}
