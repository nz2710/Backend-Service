<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Services\UserService;
use App\Http\Requests\UpdateInfoUserRequest;
use App\Http\Requests\UserRequest\AvatarChangeRequest;
use App\Notifications\NewLoginNotification;

class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function changeInfo(UpdateInfoUserRequest $request) {
        $request->validated();

        $data = $this->userService->changeInfo($request);

        return $this->apiResponse(0, __('User info change successfully'), $data);
    }

    // Get User info
    public function getInfo(Request $request) {
        $data = $this->userService->getInfo($request);

        return response()->json([
            'success' => $data ? true : false,
            'message' => $data ? "Get User Info Success" : "Fail to get user info",
            'data' => $data ? $data : null
        ]);
    }

    public function changeAvatar(AvatarChangeRequest $request) {
        $data = $this->userService->changeAvatar($request);

        return $this->apiResponse(0, __('Avatar change successful'), $data);
    }

}
