<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $req)
    {
        $data = $req->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:6'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);
        $user = User::create($data);
        $token = $user->createToken('api-token', ['*'])->plainTextToken;
        return response()->api(['user' => $user, 'token' => $token, 'token_type' => 'Bearer'], 201);
    }

    public function login(Request $req)
    {
        $cred = $req->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $cred['email'])->first();

        if (!$user || !Hash::check($cred['password'], $user->password)) {
            return response()->apiError('Invalid credentials', [], 401);
        }

        if (!$user->is_active) {
            return response()->apiError('Account inactive', [], 403);
        }

        $expiresAt = now()->addDays(7)->setTimezone('Asia/Jakarta');
        $newAccessToken = $user->createToken('api-token', ['*'], $expiresAt);

        // Ambil plain string & model token (punya kolom expires_at)
        $plainToken = $newAccessToken->plainTextToken;
        $tokenModel = $newAccessToken->accessToken; // PersonalAccessToken instance\
        $expUnix  = $expiresAt?->getTimestamp();

        return response()->api([
            'user'       => $user,
            'token'      => $plainToken,
            'expires_at'    => $expUnix,


        ], 'Login successful');
    }

    public function me(Request $req)
    {
        return response()->api($req->user());
    }

    public function logout(Request $req)
    {
        $req->user()->currentAccessToken()->delete();
        return response()->api(['message' => 'Logged out']);
    }
}
