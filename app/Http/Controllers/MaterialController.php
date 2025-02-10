<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Material;
use Illuminate\Http\Request;
use App\Models\MaterialProgress;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MaterialController extends Controller
{
    /**
     * Mengembalikan jumlah materi.
     */
    public function count()
    {
        try {
            $jumlahMateri = Material::count();
            Log::info('Jumlah materi berhasil dihitung', ['jumlah' => $jumlahMateri]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'jumlah_materi' => $jumlahMateri
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghitung materi', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung materi'
            ], 500);
        }
    }

    /**
     * Menampilkan daftar semua materi.
     */
    public function index()
    {
        Log::info('Fetching all materials');
        try {
            $materials = Material::with('module')->get();
            Log::info('Successfully fetched materials', ['count' => $materials->count()]);
            return response()->json([
                'status' => 'success',
                'data' => $materials
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching materials', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch materials'
            ], 500);
        }
    }

    /**
     * Mengambil materi berdasarkan modul tertentu.
     */
    public function getMaterialsByModule(Module $module)
    {
        Log::info('Fetching materials for module', ['module_id' => $module->id]);
        try {
            $materials = $module->materials()->with('module')->get();
            Log::info('Successfully fetched materials for module', [
                'module_id' => $module->id,
                'count' => count($materials)
            ]);
            return response()->json([
                'status' => 'success',
                'data' => $materials,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching materials by module', [
                'module_id' => $module->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch materials for module'
            ], 500);
        }
    }

    /**
     * Menampilkan detail materi.
     */
    public function show(Material $material)
    {
        Log::info('Showing material', ['material_id' => $material->id]);
        try {
            $material->load('module');
            Log::info('Material details loaded', ['material_id' => $material->id]);
            return response()->json($material);
        } catch (\Exception $e) {
            Log::error('Error showing material', [
                'material_id' => $material->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load material details'
            ], 500);
        }
    }

    /**
     * Menyimpan materi baru.
     */
    public function store(Request $request)
    {
        Log::info('Store material request received', ['request' => $request->all()]);
        try {
            // Validasi request, pastikan pdf_file dan logo_path dikirim sebagai file
            $validated = $request->validate([
                'module_id'         => 'required|exists:modules,id',
                'title'             => 'required|string|max:255',
                'content'           => 'nullable|string',
                'logo_path'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
                'pdf_file'          => 'required|file|mimes:pdf|max:2048',
                'points'    => 'nullable|integer|min:0',
            ]);
            Log::info('Validation passed', ['validated_data' => $validated]);

            // Proses penyimpanan file PDF
            if ($request->hasFile('pdf_file')) {
                $pdfFile = $request->file('pdf_file');
                $pdfPath = $pdfFile->store('materials/pdf', 'public');
                Log::info('PDF file stored successfully', ['pdfPath' => $pdfPath]);
            } else {
                Log::warning('No PDF file found in the request');
                return response()->json([
                    'status' => 'error',
                    'message' => 'PDF file is missing'
                ], 400);
            }

            // Proses penyimpanan file logo jika ada
            $logoPath = null;
            if ($request->hasFile('logo_path')) {
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('materials/logo', 'public');
                Log::info('Logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                Log::info('No logo file provided');
            }

            // Buat record Material baru dengan kolom jumlah_pertanyaan
            $material = Material::create([
                'module_id'         => $validated['module_id'],
                'title'             => $validated['title'],
                'content'           => $validated['content'],
                'logo_path'         => $logoPath,
                'pdf_path'          => $pdfPath,
                'points'    => $validated['points'] ?? 0,
            ]);
            Log::info('Material created successfully', ['material_id' => $material->id]);

            return response()->json($material, 201);
        } catch (\Exception $e) {
            Log::error('Error storing material', [
                'error'   => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to store material'
            ], 500);
        }
    }

    /**
     * Memperbarui materi yang sudah ada.
     */
    public function update(Request $request, Material $material)
    {
        Log::info('Update material request received', [
            'material_id' => $material->id,
            'request'     => $request->all()
        ]);
        try {
            // Ubah aturan validasi agar module_id bersifat opsional
            $validated = $request->validate([
                'module_id'         => 'nullable|exists:modules,id', // module_id tidak wajib
                'title'             => 'required|string|max:255',
                'content'           => 'nullable|string',
                'logo_path'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
                'pdf_file'          => 'nullable|file|mimes:pdf|max:2048',
                'points'    => 'nullable|integer|min:0',
            ]);
            Log::info('Validation passed for update', ['validated_data' => $validated]);

            // Siapkan data yang akan diupdate, termasuk jumlah_pertanyaan
            $updateData = [
                'title'             => $validated['title'],
                'content'           => $validated['content'] ?? null,
                'points'  => $validated['points'] ?? $material->points,
            ];

            // Hanya update module_id jika field tersebut dikirimkan (tidak null)
            if (!empty($validated['module_id'])) {
                $updateData['module_id'] = $validated['module_id'];
            }

            // Jika ada file PDF baru, hapus yang lama dan simpan yang baru
            if ($request->hasFile('pdf_file')) {
                Log::info('PDF file found for update, deleting old PDF file', [
                    'old_pdf_path' => $material->pdf_path
                ]);
                Storage::disk('public')->delete($material->pdf_path);
                $pdfPath = $request->file('pdf_file')->store('materials/pdf', 'public');
                $updateData['pdf_path'] = $pdfPath;
                Log::info('New PDF file stored successfully', ['pdfPath' => $pdfPath]);
            }

            // Jika ada file logo baru, hapus yang lama dan simpan yang baru
            if ($request->hasFile('logo_path')) {
                Log::info('Logo file found for update, deleting old logo file', [
                    'old_logo_path' => $material->logo_path
                ]);
                Storage::disk('public')->delete($material->logo_path);
                $logoPath = $request->file('logo_path')->store('materials/logo', 'public');
                $updateData['logo_path'] = $logoPath;
                Log::info('New logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                // Jika tidak ada update logo, pertahankan nilai yang sudah ada
                $updateData['logo_path'] = $material->logo_path;
            }

            $material->update($updateData);
            Log::info('Material updated successfully', ['material_id' => $material->id]);

            return response()->json($material);
        } catch (\Exception $e) {
            Log::error('Error updating material', [
                'material_id' => $material->id,
                'error'       => $e->getMessage(),
                'request'     => $request->all()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update material'
            ], 500);
        }
    }

    /**
     * Menghapus materi beserta file yang terkait.
     */
    public function destroy(Material $material)
    {
        Log::info('Destroy material request received', ['material_id' => $material->id]);
        try {
            Log::info('Deleting associated files', [
                'pdf_path'  => $material->pdf_path,
                'logo_path' => $material->logo_path
            ]);
            Storage::disk('public')->delete([$material->pdf_path, $material->logo_path]);
            $material->delete();
            Log::info('Material deleted successfully', ['material_id' => $material->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting material', [
                'material_id' => $material->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete material'
            ], 500);
        }
    }

    /**
     * Menyimpan atau memperbarui progress materi untuk pengguna yang sedang login.
     */
    public function storeProgress(Request $request, Material $material)
    {
        Log::info('Store progress request received', [
            'material_id' => $material->id,
            'user_id'     => auth()->id(),
            'request'     => $request->all()
        ]);

        try {
            $request->validate([
                'progress' => 'required|numeric|min:0|max:100',
            ]);

            $newProgress = $request->progress;

            // Ambil semua progress record untuk user dan materi ini
            $existingRecords = MaterialProgress::where('user_id', auth()->id())
                ->where('material_id', $material->id)
                ->get();

            // Jika user sudah menyelesaikan materi (completed true), jangan simpan perubahan
            if ($existingRecords->where('completed', true)->isNotEmpty()) {
                $completedRecord = $existingRecords->where('completed', true)->first();
                Log::info('User sudah menyelesaikan materi. Tidak ada perubahan.', [
                    'material_id' => $material->id,
                    'user_id'     => auth()->id(),
                    'progress'    => $completedRecord->progress
                ]);
                return response()->json($completedRecord);
            }

            // Jika ada record progress sebelumnya
            if ($existingRecords->isNotEmpty()) {
                // Cari progress tertinggi yang sudah disimpan
                $maxProgress = $existingRecords->max('progress');

                // Jika progress baru tidak lebih besar, maka kembalikan record dengan progress tertinggi
                if ($newProgress <= $maxProgress) {
                    $record = $existingRecords
                        ->sortByDesc('updated_at')
                        ->first();
                    Log::info('Progress baru tidak lebih besar dari progress yang sudah tersimpan. Tidak ada update.', [
                        'material_id'       => $material->id,
                        'user_id'           => auth()->id(),
                        'existing_progress' => $maxProgress,
                        'incoming_progress' => $newProgress,
                    ]);
                    return response()->json($record);
                } else {
                    // Jika progress baru lebih besar, hapus semua record lama
                    MaterialProgress::where('user_id', auth()->id())
                        ->where('material_id', $material->id)
                        ->delete();
                }
            }

            // Simpan record progress baru
            $progressRecord = MaterialProgress::create([
                'user_id'     => auth()->id(),
                'material_id' => $material->id,
                'progress'    => $newProgress,
                'completed'   => $newProgress >= 95, // misalnya progress >= 95 dianggap selesai
            ]);

            Log::info('Progress updated or created successfully', [
                'material_id' => $material->id,
                'user_id'     => auth()->id(),
                'progress'    => $newProgress
            ]);

            return response()->json($progressRecord);
        } catch (\Exception $e) {
            Log::error('Error storing progress', [
                'material_id' => $material->id,
                'error'       => $e->getMessage(),
                'request'     => $request->all()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to store progress'
            ], 500);
        }
    }

    /**
     * Mengambil progress materi untuk pengguna yang sedang login.
     */
    public function getProgress(Material $material)
    {
        Log::info('Fetching progress for material', [
            'material_id' => $material->id,
            'user_id'     => auth()->id()
        ]);

        try {
            // Ambil record dengan progress terbesar dan paling baru
            $progress = MaterialProgress::where('user_id', auth()->id())
                ->where('material_id', $material->id)
                ->orderBy('progress', 'desc')
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($progress) {
                Log::info('Progress found', [
                    'material_id' => $material->id,
                    'progress'    => $progress->progress
                ]);
            } else {
                Log::info('No progress found for material', ['material_id' => $material->id]);
            }

            return response()->json($progress ?? [
                'progress'  => 0,
                'completed' => false
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching progress', [
                'material_id' => $material->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch progress'
            ], 500);
        }
    }
}
