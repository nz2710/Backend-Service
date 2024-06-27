<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class AuthAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        try {
            // Gửi yêu cầu đến UserService để xác minh token và lấy thông tin người dùng
            $userServiceUrl = 'http://micro-nginx_userservice-1';
            $client = new Client();
            $response = $client->get($userServiceUrl . '/api/user', [
                'headers' => ['Authorization' => $token],
            ]);

            $user = json_decode((string) $response->getBody(), true);

            if (!isset($user['roles']) || !in_array('admin', $user['roles'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $request->merge(['user' => $user]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
    }
}
