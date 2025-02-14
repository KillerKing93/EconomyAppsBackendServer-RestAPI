<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * Properti untuk mengaktifkan atau menonaktifkan logging detil.
     * Setel ke true untuk mengaktifkan logging detil, atau false untuk hanya log pesan utama.
     */
    protected $enableDetailedLogger = true;

    /**
     * Helper method untuk log info.
     */
    protected function logInfo($message, array $context = [])
    {
        if ($this->enableDetailedLogger && !empty($context)) {
            Log::info($message, $context);
        } else {
            Log::info($message);
        }
    }

    /**
     * Helper method untuk log debug.
     */
    protected function logDebug($message, array $context = [])
    {
        if ($this->enableDetailedLogger && !empty($context)) {
            Log::debug($message, $context);
        } else {
            Log::debug($message);
        }
    }

    /**
     * Helper method untuk log warning.
     */
    protected function logWarning($message, array $context = [])
    {
        if ($this->enableDetailedLogger && !empty($context)) {
            Log::warning($message, $context);
        } else {
            Log::warning($message);
        }
    }

    /**
     * Helper method untuk log error.
     */
    protected function logError($message, array $context = [])
    {
        if ($this->enableDetailedLogger && !empty($context)) {
            Log::error($message, $context);
        } else {
            Log::error($message);
        }
    }

    /**
     * Mendapatkan data user yang sedang terautentikasi.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->logInfo("Authenticated user retrieved", [
                'user_id'    => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            return response()->json($user, 200);
        } else {
            $this->logWarning("No authenticated user found", [
                'ip_address' => $request->ip()
            ]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
    }

    /**
     * Menampilkan daftar semua pengguna (untuk admin).
     */
    public function index()
    {
        try {
            $users = User::all();
            $this->logInfo("Fetched user list", ['total_users' => $users->count()]);
            return response()->json([
                'status' => 'success',
                'data'   => $users,
            ], 200);
        } catch (\Exception $e) {
            $this->logError("Error fetching users", ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Register pengguna secara manual.
     */
    public function register(Request $request)
    {
        // Log data sebelum validasi (hindari mencatat password)
        $this->logDebug("Data before validation (register)", $request->except(['password', 'password_confirmation']));

        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'nickname'      => 'nullable|string|max:255',
                'email'         => 'required|email|unique:users',
                'password'      => 'required|string|min:6|confirmed',
                // Field tambahan:
                'nisn'          => 'nullable|string',
                'tanggal_lahir' => 'nullable|date',
                'gender'        => 'nullable|string|in:Laki - Laki,Perempuan',
                'photo'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);
            $this->logDebug("Data after validation (register)", $validated);
        } catch (ValidationException $e) {
            $this->logError("Validation error in register", [
                'errors' => $e->errors(),
                'data'   => $request->except(['password', 'password_confirmation']),
            ]);
            throw $e;
        }

        try {
            $this->logInfo("Register user called", [
                'request_data' => $validated,
                'ip_address'   => $request->ip()
            ]);

            // Gunakan data hasil validasi
            $data = $validated;
            $data['password'] = Hash::make($validated['password']);
            $data['role'] = 'user';

            if ($request->hasFile('photo')) {
                $data['logo_path'] = $request->file('photo')->store('users/logo', 'public');
                $this->logInfo("Profile photo uploaded", ['logo_path' => $data['logo_path']]);
            }

            $user = User::create($data);
            $this->logInfo("User registered", ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'token' => $user->createToken('API Token')->plainTextToken
            ]);
        } catch (\Exception $e) {
            $this->logError("Error registering user", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Registration failed'], 500);
        }
    }

    /**
     * Metode store() untuk mendukung resource route.
     */
    public function store(Request $request)
    {
        return $this->register($request);
    }

    /**
     * Menampilkan detail satu pengguna.
     */
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            $this->logWarning("User not found", ['user_id' => $id]);
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data'   => $user,
        ], 200);
    }

    /**
     * Memperbarui data pengguna (update untuk resource route).
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            $this->logWarning("User not found for update", ['user_id' => $id]);
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $this->logDebug("Data before validation (update)", $request->all());

        try {
            $validated = $request->validate([
                'name'          => 'sometimes|required|string|max:255',
                'nickname'      => 'sometimes|string|max:255|unique:users,nickname,' . $user->id,
                'email'         => ['sometimes', 'required', 'email', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
                'nisn'          => 'nullable|string',
                'tanggal_lahir' => 'nullable|date',
                'gender'        => 'nullable|string|in:Laki - Laki,Perempuan',
                'password'      => 'sometimes|confirmed|min:6',
                'photo'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);
            $this->logDebug("Data after validation (update)", $validated);
        } catch (ValidationException $e) {
            $this->logError("Validation error in update", [
                'errors' => $e->errors(),
                'data'   => $request->all(),
            ]);
            throw $e;
        }

        if ($request->has('password') && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $this->logDebug("Photo file received", [
                'user_id'       => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);

            if ($file->isValid()) {
                if ($user->logo_path && Storage::disk('public')->exists($user->logo_path)) {
                    Storage::disk('public')->delete($user->logo_path);
                    $this->logInfo("Old profile photo deleted", [
                        'user_id'       => $user->id,
                        'old_logo_path' => $user->logo_path,
                    ]);
                } else {
                    $this->logInfo("No old profile photo found", ['user_id' => $user->id]);
                }
                $path = $file->store('users/logo', 'public');
                $validated['logo_path'] = $path;
                $this->logInfo("New profile photo uploaded", [
                    'user_id'       => $user->id,
                    'new_logo_path' => $path,
                ]);
            } else {
                $this->logWarning("Uploaded photo file is not valid", ['user_id' => $user->id]);
            }
        } else {
            $this->logInfo("No profile photo uploaded", ['user_id' => $user->id]);
        }

        if ($user->role === 'admin' && isset($validated['role'])) {
            $oldRole = $user->role;
            $user->role = $validated['role'];
            $this->logInfo("User role updated", [
                'user_id'  => $user->id,
                'old_role' => $oldRole,
                'new_role' => $validated['role'],
            ]);
        }

        $this->logInfo("Attempting to update profile fields", [
            'user_id'         => $user->id,
            'fields_attempted'=> array_keys($validated),
            'validated_data'  => $validated,
        ]);

        $originalData = $user->toArray();
        $user->update($validated);
        $changedFields = $user->getChanges();
        if (empty($changedFields)) {
            $this->logInfo("Profile updated but no changes detected", ['user_id' => $user->id]);
        } else {
            $this->logInfo("Profile successfully updated", [
                'user_id'       => $user->id,
                'original_data' => $originalData,
                'new_data'      => $user->toArray(),
                'changed_fields'=> $changedFields,
            ]);
        }

        return response()->json($user);
    }

    /**
     * Menghapus pengguna.
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            $this->logWarning("User not found for deletion", ['user_id' => $id]);
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }
        $user->delete();
        $this->logInfo("User deleted", ['user_id' => $id]);
        return response()->json([
            'status'  => 'success',
            'message' => 'User deleted successfully'
        ], 200);
    }

    /**
     * Login secara manual dengan dukungan fitur "Ingat saya" (remember me).
     */
    public function login(Request $request)
    {
        $this->logDebug("Data before validation (login)", $request->all());

        try {
            $validated = $request->validate([
                'email'       => 'required|email',
                'password'    => 'required',
                'remember_me' => 'nullable|boolean',
            ]);
            $this->logDebug("Data after validation (login)", $validated);
        } catch (ValidationException $e) {
            $this->logError("Validation error in login", [
                'errors' => $e->errors(),
                'data'   => $request->all()
            ]);
            throw $e;
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            $this->logWarning("Failed login attempt", ['email' => $validated['email']]);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials']
            ]);
        }

        $this->logInfo("User logged in successfully", [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'ip_address' => $request->ip()
        ]);

        $remember = $validated['remember_me'] ?? false;

        $tokenResult = $user->createToken('API Token');

        if ($remember) {
            $tokenResult->accessToken->expires_at = now()->addDays(30);
            $this->logInfo("Remember me diaktifkan, token berlaku selama 30 hari", [
                'user_id'    => $user->id,
                'expires_at' => $tokenResult->accessToken->expires_at,
            ]);
        } else {
            $tokenResult->accessToken->expires_at = now()->addDay();
            $this->logInfo("Remember me tidak diaktifkan, token berlaku selama 1 hari", [
                'user_id'    => $user->id,
                'expires_at' => $tokenResult->accessToken->expires_at,
            ]);
        }

        $tokenResult->accessToken->save();

        return response()->json([
            'token' => $tokenResult->plainTextToken
        ]);
    }

    /**
     * Social login/register (Google & Facebook).
     */
    public function redirectToProvider($provider)
    {
        $this->logInfo("Redirecting to provider for social login", ['provider' => $provider]);
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProviderCallback($provider)
    {
        $this->logDebug("handleProviderCallback called", [
            'provider' => $provider,
            'query'    => request()->all()
        ]);

        if (!request()->has('code')) {
            $this->logError("Social provider callback error", [
                'provider' => $provider,
                'error'    => 'Missing required parameter: code',
                'query'    => request()->all()
            ]);
            return response()->json(['error' => 'Missing required parameter: code'], 400);
        }
    
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $this->logDebug("Social user retrieved", [
                'provider'  => $provider,
                'socialUser'=> [
                    'id'    => $socialUser->getId(),
                    'name'  => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar'=> $socialUser->getAvatar()
                ]
            ]);
        } catch (\Exception $e) {
            $this->logError("Social provider callback error", [
                'provider' => $provider,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
                'query'    => request()->all()
            ]);
            return response()->json(['error' => 'Invalid credentials provided'], 422);
        }
    
        if ($currentUser = auth()->user()) {
            $currentUser->update([
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar'      => $socialUser->getAvatar(),
            ]);
            $this->logInfo("Social account connected", [
                'user_id'  => $currentUser->id,
                'provider' => $provider
            ]);
            return response()->json([
                'message' => 'Social account connected',
                'user'    => $currentUser
            ]);
        }
    
        $user = User::where('provider', $provider)
                    ->where('provider_id', $socialUser->getId())
                    ->first();
    
        if (!$user) {
            if (User::where('email', $socialUser->getEmail())->exists()) {
                $this->logWarning("Attempt to register social user with already registered email", [
                    'email' => $socialUser->getEmail()
                ]);
                throw ValidationException::withMessages([
                    'email' => 'This email is already registered with another method'
                ]);
            }
    
            $user = User::create([
                'name'        => $socialUser->getName(),
                'email'       => $socialUser->getEmail(),
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar'      => $socialUser->getAvatar(),
                'password'    => Hash::make(Str::random(24)),
            ]);
    
            $this->logInfo("New social user registered", [
                'user_id'  => $user->id,
                'provider' => $provider
            ]);
        } else {
            $this->logInfo("Existing social user logged in", [
                'user_id'  => $user->id,
                'provider' => $provider
            ]);
        }
    
        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }
    
    /**
     * Update profile (termasuk foto profil, nisn, tanggal_lahir, gender, dan nickname) dengan logging detil.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
    
        $this->logInfo("Starting profile update", [
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);
        $this->logDebug("Data before validation (updateProfile)", $request->except(['password', 'current_password']));
    
        $rules = [
            'name'             => 'sometimes|string|max:255',
            'nickname'         => 'sometimes|string|max:255|unique:users,nickname,' . $user->id,
            'email'            => 'sometimes|email|unique:users,email,' . $user->id,
            'current_password' => 'required_with:password',
            'password'         => 'sometimes|confirmed|min:6',
            'nisn'             => 'nullable|string',
            'tanggal_lahir'    => 'nullable|date',
            'gender'           => 'nullable|string|in:Laki - Laki,Perempuan',  // validasi gender
            'photo'            => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
        ];
    
        if ($user->role === 'admin') {
            $rules['role'] = 'sometimes|required|in:admin,user';
        }
        $this->logDebug("Validation rules (updateProfile)", $rules);
    
        try {
            $validated = $request->validate($rules);
            $this->logDebug("Data after validation (updateProfile)", $validated);
        } catch (ValidationException $ex) {
            $this->logError("Validation error in updateProfile", [
                'errors' => $ex->errors(),
                'data'   => $request->except(['password', 'current_password']),
            ]);
            throw $ex;
        }
    
        // Tambahkan log untuk memeriksa apakah gender sudah ada di dalam $validated
        if (isset($validated['gender'])) {
            $this->logInfo("Gender detected in validated data", [
                'user_id' => $user->id,
                'gender' => $validated['gender']
            ]);
        }
    
        // Pembaruan password
        if ($request->has('password') && !empty($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                $this->logWarning("Update profile failed: incorrect current password", ['user_id' => $user->id]);
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect']
                ]);
            }
            $validated['password'] = Hash::make($validated['password']);
            $this->logInfo("Password updated", ['user_id' => $user->id]);
        }
    
        // Pembaruan foto profil
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $this->logDebug("Photo file received", [
                'user_id'       => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
    
            if ($file->isValid()) {
                if ($user->logo_path && Storage::disk('public')->exists($user->logo_path)) {
                    Storage::disk('public')->delete($user->logo_path);
                    $this->logInfo("Old profile photo deleted", [
                        'user_id'       => $user->id,
                        'old_logo_path' => $user->logo_path,
                    ]);
                } else {
                    $this->logInfo("No old profile photo found", ['user_id' => $user->id]);
                }
                $path = $file->store('users/logo', 'public');
                $validated['logo_path'] = $path;
                $this->logInfo("New profile photo uploaded", [
                    'user_id'       => $user->id,
                    'new_logo_path' => $path,
                ]);
            } else {
                $this->logWarning("Uploaded photo file is not valid", ['user_id' => $user->id]);
            }
        } else {
            $this->logInfo("No profile photo uploaded", ['user_id' => $user->id]);
        }
    
        // Pembaruan peran jika admin
        if ($user->role === 'admin' && isset($validated['role'])) {
            $oldRole = $user->role;
            $user->role = $validated['role'];
            $this->logInfo("User role updated", [
                'user_id'  => $user->id,
                'old_role' => $oldRole,
                'new_role' => $validated['role'],
            ]);
        }
    
        // Log data yang akan di-update
        $this->logInfo("Attempting to update profile fields", [
            'user_id'         => $user->id,
            'fields_attempted'=> array_keys($validated),
            'validated_data'  => $validated,
        ]);
    
        $originalData = $user->toArray();
        $user->update($validated);
        $changedFields = $user->getChanges();
        if (empty($changedFields)) {
            $this->logInfo("Profile updated but no changes detected", ['user_id' => $user->id]);
        } else {
            $this->logInfo("Profile successfully updated", [
                'user_id'       => $user->id,
                'original_data' => $originalData,
                'new_data'      => $user->toArray(),
                'changed_fields'=> $changedFields,
            ]);
        }
    
        return response()->json($user);
    }    

    /**
     * Logout user dengan menghapus token yang digunakan saat ini.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            $this->logWarning("Logout gagal: user tidak terautentikasi", [
                'ip_address' => $request->ip()
            ]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $tokenId = $currentToken->id;
            $currentToken->delete();

            $this->logInfo("User berhasil logout dan token dihapus", [
                'user_id'    => $user->id,
                'token_id'   => $tokenId,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        $this->logWarning("User tidak memiliki token untuk dihapus", [
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
        ]);
        return response()->json(['error' => 'No active token found'], 400);
    }
}
