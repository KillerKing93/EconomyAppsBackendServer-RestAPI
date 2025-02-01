<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use App\Models\MaterialProgress;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function index()
    {
        return response()->json(Material::with('module')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',     // <-- Deskripsi
            'logo_path' => 'nullable|string',
            'pdf_file' => 'required|file|mimes:pdf|max:2048',
        ]);

        $pdfPath = $request->file('pdf_file')->store('materials/pdf', 'public');

        $material = Material::create([
            'module_id' => $validated['module_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],  // <-- Deskripsi
            'logo_path' => $validated['logo_path'],
            'pdf_path' => $pdfPath,
        ]);
    
        return response()->json($material, 201);
    }

    public function show(Material $material)
    {
        return response()->json($material->load('module'));
    }

    public function update(Request $request, Material $material)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',     // <-- Deskripsi
            'logo_path' => 'nullable|string',
            'pdf_file' => 'nullable|file|mimes:pdf|max:2048',
        ]);
    
        $updateData = [
            'module_id' => $validated['module_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],  // <-- Deskripsi
            'logo_path' => $validated['logo_path'],
        ];

        if ($request->hasFile('pdf_file')) {
            Storage::disk('public')->delete($material->pdf_path);
            $pdfPath = $request->file('pdf_file')->store('materials/pdf', 'public');
            $updateData['pdf_path'] = $pdfPath;
        }
    
        $material->update($updateData);
    
        return response()->json($material);
    }

    public function destroy(Material $material)
    {
        // Delete associated files
        Storage::disk('public')->delete([
            $material->pdf_path,
            $material->logo_path
        ]);
        
        $material->delete();
        return response()->json(null, 204);
    }

    public function storeProgress(Request $request, Material $material)
{
    $request->validate([
        'progress' => 'required|numeric|min:0|max:100',
    ]);

    $progress = MaterialProgress::updateOrCreate(
        [
            'user_id' => auth()->id(),
            'material_id' => $material->id,
        ],
        [
            'progress' => $request->progress,
            'completed' => $request->progress >= 95 // Anggap 95% sebagai completed
        ]
    );

    return response()->json($progress);
}

public function getProgress(Material $material)
{
    $progress = MaterialProgress::where('user_id', auth()->id())
                ->where('material_id', $material->id)
                ->first();

    return response()->json($progress ?? [
        'progress' => 0,
        'completed' => false
    ]);
}
}
