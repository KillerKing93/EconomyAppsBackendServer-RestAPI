<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Material; // Untuk validasi foreign key
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ChallengeController extends Controller
{
    /**
     * Menghitung jumlah challenge.
     */
    public function count()
    {
        try {
            $jumlahChallenge = Challenge::count();
            Log::info('Jumlah challenge berhasil dihitung', ['jumlah' => $jumlahChallenge]);

            return response()->json([
                'status' => 'success',
                'data'   => ['jumlah_challenge' => $jumlahChallenge]
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghitung challenge', ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghitung challenge'
            ], 500);
        }
    }

    /**
     * Menampilkan semua challenge.
     */
    public function index()
    {
        Log::info('Fetching all challenges');
        try {
            $challenges = Challenge::with('material')->get();
            Log::info('Successfully fetched challenges', ['count' => $challenges->count()]);
            return response()->json([
                'status' => 'success',
                'data'   => $challenges
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching challenges', ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch challenges'
            ], 500);
        }
    }

    /**
     * Mengambil challenge berdasarkan materi tertentu.
     */
    public function getChallengesByMaterial($materialId)
    {
        try {
            // Cari material beserta challenge-nya
            $material = Material::with('challenges')->findOrFail($materialId);
            $challenges = $material->challenges;
            Log::info('Successfully fetched challenges for material', [
                'material_id' => $material->id,
                'count'       => count($challenges)
            ]);
            return response()->json([
                'status' => 'success',
                'data'   => $challenges,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching challenges for material', [
                'material_id' => $materialId,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch challenges for material'
            ], 500);
        }
    }
    

    /**
     * Menampilkan detail challenge.
     */
    public function show(Challenge $challenge)
    {
        Log::info('Showing challenge', ['challenge_id' => $challenge->id]);
        try {
            $challenge->load('material');
            Log::info('Challenge details loaded', ['challenge_id' => $challenge->id]);
            return response()->json($challenge);
        } catch (\Exception $e) {
            Log::error('Error showing challenge', [
                'challenge_id' => $challenge->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to load challenge details'
            ], 500);
        }
    }

    /**
     * Menyimpan challenge baru.
     * Perhatikan: tidak ada penanganan pdf_file.
     */
    public function store(Request $request)
    {
        Log::info('Store challenge request received', ['request' => $request->all()]);
        try {
            $validated = $request->validate([
                'material_id'       => 'required|exists:materials,id',
                'title'             => 'required|string|max:255',
                'content'           => 'nullable|string',
                'logo_path'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
                'jumlah_pertanyaan' => 'nullable|integer|min:0',
            ]);
            Log::info('Validation passed', ['validated_data' => $validated]);

            // Proses penyimpanan file logo (jika ada)
            $logoPath = null;
            if ($request->hasFile('logo_path')) {
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('challenges/logo', 'public');
                Log::info('Logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                Log::info('No logo file provided');
            }

            // Buat record Challenge baru (tanpa pdf)
            $challenge = Challenge::create([
                'material_id'       => $validated['material_id'],
                'title'             => $validated['title'],
                'content'           => $validated['content'] ?? null,
                'logo_path'         => $logoPath,
                'jumlah_pertanyaan' => $validated['jumlah_pertanyaan'] ?? 0,
            ]);
            Log::info('Challenge created successfully', ['challenge_id' => $challenge->id]);

            return response()->json($challenge, 201);
        } catch (\Exception $e) {
            Log::error('Error storing challenge', [
                'error'   => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to store challenge'
            ], 500);
        }
    }

    /**
     * Memperbarui challenge yang sudah ada.
     */
    public function update(Request $request, Challenge $challenge)
    {
        Log::info('Update challenge request received', [
            'challenge_id' => $challenge->id,
            'request'      => $request->all()
        ]);
        try {
            $validated = $request->validate([
                'material_id'       => 'nullable|exists:materials,id',
                'title'             => 'required|string|max:255',
                'content'           => 'nullable|string',
                'logo_path'         => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
                'jumlah_pertanyaan' => 'nullable|integer|min:0',
            ]);
            Log::info('Validation passed for update', ['validated_data' => $validated]);

            $updateData = [
                'title'             => $validated['title'],
                'content'           => $validated['content'] ?? null,
                'jumlah_pertanyaan' => $validated['jumlah_pertanyaan'] ?? $challenge->jumlah_pertanyaan,
            ];

            // Update material_id jika diberikan
            if (!empty($validated['material_id'])) {
                $updateData['material_id'] = $validated['material_id'];
            }

            // Jika ada file logo baru, hapus file lama dan simpan yang baru
            if ($request->hasFile('logo_path')) {
                Log::info('Logo file found for update, deleting old logo file', [
                    'old_logo_path' => $challenge->logo_path
                ]);
                Storage::disk('public')->delete($challenge->logo_path);
                $logoPath = $request->file('logo_path')->store('challenges/logo', 'public');
                $updateData['logo_path'] = $logoPath;
                Log::info('New logo file stored successfully', ['logoPath' => $logoPath]);
            } else {
                $updateData['logo_path'] = $challenge->logo_path;
            }

            $challenge->update($updateData);
            Log::info('Challenge updated successfully', ['challenge_id' => $challenge->id]);

            return response()->json($challenge);
        } catch (\Exception $e) {
            Log::error('Error updating challenge', [
                'challenge_id' => $challenge->id,
                'error'        => $e->getMessage(),
                'request'      => $request->all()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update challenge'
            ], 500);
        }
    }

    /**
     * Menghapus challenge beserta file logo yang terkait.
     */
    public function destroy(Challenge $challenge)
    {
        Log::info('Destroy challenge request received', ['challenge_id' => $challenge->id]);
        try {
            Log::info('Deleting associated logo file', [
                'logo_path' => $challenge->logo_path
            ]);
            Storage::disk('public')->delete($challenge->logo_path);
            $challenge->delete();
            Log::info('Challenge deleted successfully', ['challenge_id' => $challenge->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting challenge', [
                'challenge_id' => $challenge->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete challenge'
            ], 500);
        }
    }

    // Jika diperlukan, Anda juga dapat menambahkan metode progress tracking (storeProgress, getProgress)
    // yang mirip dengan MaterialController, namun mengacu ke challenge.
}
