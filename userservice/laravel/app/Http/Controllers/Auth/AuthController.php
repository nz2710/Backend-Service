<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Traits\HttpResponses;
use App\Http\Controllers\Controller;
use App\Http\Services\Auth\AuthService;
use App\Http\Requests\AuthRequest\LoginUserRequest;
use App\Http\Requests\AuthRequest\StoreUserRequest;
use App\Http\Requests\AuthRequest\ResetPasswordUserRequest;
use App\Http\Requests\AuthRequest\ChangePasswordUserRequest;
use App\Http\Requests\AuthRequest\ForgotPasswordUserRequest;
use App\Http\Requests\AuthRequest\ConfirmPasswordUserRequest;
use App\Http\Requests\AvatarChangeRequest;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginUserRequest $request){


        $request->validated();

        $data = $this->authService->login($request);

        return $this->apiResponse(0, __('Login successful'), $data);
    }

    public function logout() {
        $data = $this->authService->logout();

        return $this->apiResponse(0, __('Logout successful'), $data);
    }

    //New Password Controller
    public function forgotPassword(ForgotPasswordUserRequest $request) {

        $data = $this->authService->forgotPassword($request);

        return response()->json([
            'success' => $data ? true : false,
            'message' => $data ? $data : "User not found",
        ]);
    }

    public function reset(ResetPasswordUserRequest $request) {
        $data = $this->authService->reset($request);

        return response()->json([
            'success' => $data ? true : false,
            'message' => $data ? "Password reset successfully" : "Error occur..",
        ]);
    }

    // Change Password
    public function changePassword(ChangePasswordUserRequest $request) {
        $data = $this->authService->changePassword($request);

        return response()->json([
            'success' => $data ? true : false,
            'message' => $data ? "Password changed successfully" : "Old password is incorrect",
        ]);
    }

    public function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $token = PersonalAccessToken::findToken($token);

        if (!$token) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = $token->tokenable;

        $roles = $user->roles->pluck('name')->toArray();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $roles,
            'partner_id' => $user->partner_id,
        ]);
    }



}
