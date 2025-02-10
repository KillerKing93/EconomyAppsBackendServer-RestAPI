<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    /**
     * Mengambil file dari disk public berdasarkan path yang diberikan.
     *
     * Contoh URL: GET /api/files/materials/pdf/namafile.pdf
     *
     * @param  string  $path  Path file relatif pada disk 'public'
     * @return \Illuminate\Http\Response
     */
    public function getFile($path)
    {
        // Logging permintaan file
        Log::info('Request file', ['path' => $path]);

        // Pastikan path tidak kosong
        $path = trim($path);
        if (empty($path) || !Storage::disk('public')->exists($path)) {
            Log::warning('File tidak ditemukan', ['path' => $path]);
            return response()->json([
                'error' => 'File tidak ditemukan'
            ], Response::HTTP_NOT_FOUND);
        }

        // Dapatkan MIME type file
        $mimeType = Storage::disk('public')->mimeType($path);
        Log::info('MIME type file ditemukan', ['mime_type' => $mimeType, 'path' => $path]);

        // Ambil isi file dari disk public
        $fileContent = Storage::disk('public')->get($path);
        Log::info('Isi file berhasil diambil', ['path' => $path, 'size' => strlen($fileContent)]);

        // Log sebelum file dikirim
        Log::info('File akan disajikan', ['path' => $path]);

        // Mengembalikan file dengan header Content-Type dan Content-Disposition (inline untuk tampilan di browser)
        return response($fileContent, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    }
}
