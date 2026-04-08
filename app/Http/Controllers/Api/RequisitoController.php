<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Requisito;
use Illuminate\Http\Request;

class RequisitoController extends Controller
{
    public function index()
    {
        return Requisito::orderBy('nombre', 'asc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:requisitos,nombre'
        ]);

        $requisito = Requisito::create($data);

        return response()->json([
            'message' => 'Requisito creado correctamente',
            'requisito' => $requisito
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $requisito = Requisito::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:requisitos,nombre,' . $requisito->id
        ]);

        $requisito->update($data);

        return response()->json([
            'message' => 'Requisito actualizado',
            'requisito' => $requisito
        ]);
    }

    public function destroy($id)
    {
        $requisito = Requisito::findOrFail($id);
        $requisito->delete();

        return response()->json(null, 204);
    }
}