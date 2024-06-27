<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Services\Admin\AdminService;
use App\Http\Services\Admin\AdminUserService;
use App\Http\Requests\AdminRequest\AdminRequest;

class AdminUserController extends Controller
{
    private $adminUserService;
    public function __construct(AdminUserService $adminUserService)
    {
        $this->adminUserService = $adminUserService;
    }

    public function create(Request $request) {
        $data = $this->adminUserService->create($request);
        return $this->apiResponse(0, __('Create user successfully.'), $data);
    }

    #region User
    public function getAllUser(Request $request) {
        $data = $this->adminUserService->getall($request);
        return $this->apiResponse(0, __('Get users successfully.'), $data);
    }

    public function ban(Request $request, $id) {
        $data = $this->adminUserService->ban($request, $id);
        return $this->apiResponse(0, __('Ban Successfully.'), $data);
    }

    public function unban(Request $request, $id) {
        $data = $this->adminUserService->unban($request, $id);
        return $this->apiResponse(0, __('Unban Successfully.'), $data);
    }

    public function deleteUser($id) {
        $data = $this->adminUserService->deleteUser($id);
        return $this->apiResponse(0, __('User deleted successfully.'), $data);
    }

    public function getUserDetails($id) {
        $data = $this->adminUserService->getUserDetails($id);
        return $this->apiResponse(0, __('Get user details successfully.'), $data);
    }
    #endregion

    #region Role
    public function getRole(Request $request) {
        $data = $this->adminUserService->getRole($request);
        return $this->apiResponse(0, __('Get role for user successfully'), $data);
    }

    public function createRole(Request $request) {
        $data = $this->adminUserService->createRole($request);
        return $this->apiResponse(0, __('Get role for user successfully'), $data);
    }

    public function deleteRole($id) {
        $data = $this->adminUserService->deleteRole($id);
        return $this->apiResponse(0, __('Role deleted successfully.'), $data);
    }

    #endregion
}
