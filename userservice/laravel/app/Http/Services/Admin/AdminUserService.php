<?php

namespace App\Http\Services\Admin;

use Exception;
use App\Models\Role;
use App\Http\Services\EncryptService;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use GuzzleHttp\Client;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminUserService
{
    #region User
    public function __construct(EncryptService $encryptService)
    {
        $this->encryptService = $encryptService;
    }

    public function create($request)
    {
        try {
            $request->validate([
                'username' => 'required|min:4|max:25|unique:users|regex:/(^[a-zA-Z]+[a-zA-Z0-9\\-]*$)/u',
                'email' => 'required|string|unique:users|email:rfc,dns,filter|max:255',
                'password' => ['required', 'confirmed', Rules\Password::default(), 'max:255'],
                'role_id' => 'required|exists:roles,id',
                'partner_id' => 'required_if:role_id,2',
            ]);

            if ($request->role_id == 2) {
                $token = $request->bearerToken();
                // Kiểm tra sự tồn tại của partner_id bằng cách gọi API đến managementservice
                $client = new Client();
                $response = $client->get('http://micro-nginx_managementservice-1/api/admin/partner/' . $request->partner_id, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new Exception('Invalid partner_id');
                }
            }

            $apikey = $this->encryptService->apikeyGen();

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'apikey' => $apikey,
                'partner_id' => $request->role_id == 2 ? $request->partner_id : null,
            ]);

            $user->roles()->attach($request->role_id);

            if ($user) {
                $data = [
                    'user' => $user,
                    'token' => $user->createToken('Api Token of ' . $user->email)->plainTextToken,
                ];

                new Registered($user);
                return $data;
            } else {
                throw new Exception('Something goes wrong when create your account');
            }
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }

    public function getall($request)
    {
        $username = $request->input('username');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $email = $request->input('email');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $user = User::with(['roles:name'])
            ->select('id', 'username', 'first_name', 'last_name', 'email', 'status', 'partner_id')
            ->orderBy($orderBy, $sortBy);

        if ($username) {
            $user = $user->where('username', 'like', '%' . $username . '%');
        }

        if ($first_name) {
            $user = $user->where('first_name', 'like', '%' . $first_name . '%');
        }

        if ($last_name) {
            $user = $user->where('last_name', 'like', '%' . $last_name . '%');
        }

        if ($email) {
            $user = $user->where('email', 'like', '%' . $email . '%');
        }

        return $user->paginate(!empty($request['pageSize']) ? (int)$request['pageSize'] : config('app.pagination'));
    }
    public function ban($request, $id)
    {
        try {
            if (!empty($id)) {
                $user = User::where('id', $request->id)->first();

                $user->status = 0;
                $user->save();

                return true;
            }

            return false;
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }

    public function unban($request, $id)
    {
        try {
            if (!empty($id)) {
                $user = User::where('id', $id)->first();

                $user->status = 1;
                $user->save();

                return true;
            }

            return false;
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                throw new Exception('User not found');
            }

            $user->delete();

            return true;
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }

    public function getUserDetails($id)
    {
        try {
            $user = User::with('roles')->find($id);

            if (!$user) {
                throw new Exception('User not found');
            }

            return $user;
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }
    #endregion

    #region Role
    public function getRole($request)
    {
        return Role::all();
    }

    public function createRole($request)
    {
        return Role::create([
            'name' => $request->role_name,
        ]);
    }

    public function deleteRole($id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                throw new Exception('Role not found');
            }

            $role->delete();

            return true;
        } catch (Exception $ex) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422));
        }
    }
}
