<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        return response()->json(Module::all());
    }

    public function store(Request $request)
    {
        $module = Module::create($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo_path' => 'nullable|string',
        ]));

        return response()->json($module, 201);
    }

    public function show(Module $module)
    {
        return response()->json($module);
    }

    public function update(Request $request, Module $module)
    {
        $module->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo_path' => 'nullable|string',
        ]));

        return response()->json($module);
    }

    public function destroy(Module $module)
    {
        $module->delete();
        return response()->json(null, 204);
    }
}
