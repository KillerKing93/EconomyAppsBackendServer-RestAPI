<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModuleController extends Controller
{
    /**
     * Mengembalikan jumlah modul.
     */
    public function count()
    {
        try {
            $jumlahModul = Module::count();
            Log::info('Jumlah modul berhasil dihitung', ['jumlah' => $jumlahModul]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'jumlah_modul' => $jumlahModul
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghitung modul', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung modul'
            ], 500);
        }
    }

    /**
     * Display a listing of the modules.
     */
    public function index()
    {
        try {
            Log::info('Fetching all modules');

            $modules = Module::all();

            Log::info('Modules fetched successfully', ['count' => $modules->count()]);

            return response()->json([
                'status' => 'success',
                'data'   => $modules,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching modules', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error fetching modules',
            ], 500);
        }
    }

    /**
     * Display the specified module.
     */
    public function show(Module $module)
    {
        try {
            Log::info('Showing module', ['module_id' => $module->id]);

            return response()->json([
                'status' => 'success',
                'data'   => $module,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching module', ['error' => $e->getMessage(), 'module_id' => $module->id]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error fetching module',
            ], 500);
        }
    }

    /**
     * Store a newly created module in storage.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Store module request received', ['request' => $request->all()]);

            // Validasi input request, termasuk file logo jika dikirim
            $validated = $request->validate([
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'logo_path'   => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);
            Log::info('Module data validated', ['validated_data' => $validated]);

            // Proses penyimpanan file logo jika ada
            $logoPath = null;
            if ($request->hasFile('logo_path')) {
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('modules/logo', 'public');
                Log::info('Logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                Log::info('No logo file provided');
            }

            // Buat record Module baru
            $module = Module::create([
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'logo_path'   => $logoPath,
            ]);
            Log::info('Module created successfully', ['module_id' => $module->id]);

            return response()->json([
                'status' => 'success',
                'data'   => $module,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating module', [
                'error'   => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error creating module',
            ], 500);
        }
    }

    /**
     * Update the specified module in storage.
     */
    public function update(Request $request, Module $module)
    {
        try {
            Log::info('Update module request received', [
                'module_id' => $module->id,
                'request'   => $request->all()
            ]);

            // Validasi input request, termasuk file logo jika dikirim
            $validated = $request->validate([
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'logo_path'   => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);
            Log::info('Module update data validated', ['validated_data' => $validated]);

            // Siapkan data untuk update
            $updateData = [
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
            ];

            // Jika ada file logo baru, hapus file lama dan simpan yang baru
            if ($request->hasFile('logo_path')) {
                if ($module->logo_path) {
                    Storage::disk('public')->delete($module->logo_path);
                    Log::info('Old logo file deleted', ['old_logo_path' => $module->logo_path]);
                }
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('modules/logo', 'public');
                $updateData['logo_path'] = $logoPath;
                Log::info('New logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                // Jika tidak ada update logo, pertahankan nilai yang sudah ada
                $updateData['logo_path'] = $module->logo_path;
            }

            // Update module
            $module->update($updateData);
            Log::info('Module updated successfully', ['module_id' => $module->id]);

            return response()->json([
                'status' => 'success',
                'data'   => $module,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating module', [
                'error'     => $e->getMessage(),
                'module_id' => $module->id
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error updating module',
            ], 500);
        }
    }

    /**
     * Remove the specified module from storage.
     */
    public function destroy(Module $module)
    {
        try {
            Log::info('Delete module request received', ['module_id' => $module->id]);

            // Hapus file logo terkait jika ada
            if ($module->logo_path) {
                Storage::disk('public')->delete($module->logo_path);
                Log::info('Module logo file deleted', ['logo_path' => $module->logo_path]);
            }

            $module->delete();
            Log::info('Module deleted successfully', ['module_id' => $module->id]);

            // Kembalikan response 204 tanpa body
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting module', [
                'error'     => $e->getMessage(),
                'module_id' => $module->id
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error deleting module',
            ], 500);
        }
    }    
}
