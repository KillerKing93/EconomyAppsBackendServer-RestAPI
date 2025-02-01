<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register manually
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    // Login manually
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
        }

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    // Social login/register (Google & Facebook)
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid credentials provided'], 422);
        }
    
        // Cek jika user sudah login (untuk connect account)
        if ($currentUser = auth()->user()) {
            $currentUser->update([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
            
            return response()->json([
                'message' => 'Social account connected',
                'user' => $currentUser
            ]);
        }
    
        // Check existing user by provider ID
        $user = User::where('provider', $provider)
                    ->where('provider_id', $socialUser->getId())
                    ->first();
    
        if (!$user) {
            // Prevent email takeover
            if (User::where('email', $socialUser->getEmail())->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already registered with another method'
                ]);
            }
    
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(24)),
            ]);
        }
    
        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    // Tambahkan route untuk connect/disconnect social
    public function disconnectSocial(Request $request)
    {
        $user = $request->user();
        
        if (!$user->provider) {
            return response()->json(['message' => 'No social account connected'], 400);
        }

        $user->update([
            'provider' => null,
            'provider_id' => null,
            'avatar' => null
        ]);

        return response()->json(['message' => 'Social account disconnected']);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    // Get authenticated user
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'nickname' => 'sometimes|string|max:255|unique:users,nickname,'.$user->id,
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'current_password' => 'required_with:password',
            'password' => 'sometimes|confirmed|min:6',
        ]);

        if ($request->has('password')) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect']
                ]);
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password'
        ]);

        $request->user()->delete();
        
        return response()->json(['message' => 'Account deleted']);
    }
}
