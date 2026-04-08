<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apoderado;
use App\Models\Confirmando;
use App\Models\Sacramento; // <-- Importar Apoderado
use Illuminate\Http\Request;

class ConfirmandoController extends Controller
{
    public function index()
    {
        // Añadimos 'apoderados' al eager loading por si los necesitas en la lista
        return Confirmando::with(['grupo', 'sacramentos', 'apoderados'])->latest()->get();
    }

    public function show($id)
    {
        // Cargamos 'apoderados' para que se vean al editar
        return Confirmando::with(['grupo', 'sacramentos', 'requisitos', 'apoderados'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $maxDate = now()->subYears(14)->format('Y-m-d');

        // 1. Validar Datos del Confirmando
        $validate = $request->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'celular' => ['nullable', 'string', 'max:9'],
            'genero' => ['nullable', 'string', 'max:1'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:'.$maxDate],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'sacramento_faltante_id' => ['required', 'exists:sacramentos,id'],
        ]);

        // 2. Validar Apoderados (en un paso separado o junto, da igual)
        $apoderadosData = $request->validate([
            'apoderados' => ['nullable', 'array'],
            'apoderados.*.nombres' => ['required_with:apoderados', 'string'],
            'apoderados.*.apellidos' => ['required_with:apoderados', 'string'],
            'apoderados.*.tipo_apoderado_id' => ['required_with:apoderados', 'exists:tipo_apoderados,id'],
            'apoderados.*.celular' => ['nullable', 'string', 'max:9'],
        ]);

        // 3. Crear Confirmando
        $confirmando = Confirmando::create([
            'nombres' => $validate['nombres'],
            'apellidos' => $validate['apellidos'],
            'celular' => $validate['celular'] ?? null,
            'genero' => $validate['genero'] ?? null,
            'fecha_nacimiento' => $validate['fecha_nacimiento'],
            'grupo_id' => $validate['grupo_id'] ?? null,
        ]);

        // 4. Lógica de Negocio (Sacramentos y Requisitos)
        $this->asignarRutaSacramental($confirmando, $validate['sacramento_faltante_id']);

        // 5. Guardar Apoderados (NUEVO)
        if (! empty($apoderadosData['apoderados'])) {
            $this->syncApoderados($confirmando, $apoderadosData['apoderados']);
        }

        $confirmando->load('grupo', 'sacramentos', 'apoderados');

        return response()->json([
            'message' => 'Confirmando creado y ruta sacramental asignada',
            'confirmando' => $confirmando,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $confirmando = Confirmando::findOrFail($id);
        $maxDate = now()->subYears(14)->format('Y-m-d');

        // Validación Confirmando
        $data = $request->validate([
            'nombres' => ['sometimes', 'string'],
            'apellidos' => ['sometimes', 'string'],
            'celular' => ['sometimes', 'nullable', 'string', 'max:9'],
            'genero' => ['sometimes','nullable','string','max:1'],
            'fecha_nacimiento' => ['sometimes', 'date', 'before_or_equal:'.$maxDate],
            'grupo_id' => ['sometimes', 'nullable', 'exists:grupos,id'],
            'sacramento_faltante_id' => ['sometimes', 'exists:sacramentos,id'],
            'requisitos_actualizar' => ['nullable', 'array'],
        ]);

        // Validación Apoderados
        $apoderadosData = $request->validate([
            'apoderados' => ['nullable', 'array'],
            'apoderados.*.nombres' => ['required_with:apoderados', 'string'],
            'apoderados.*.apellidos' => ['required_with:apoderados', 'string'],
            'apoderados.*.tipo_apoderado_id' => ['required_with:apoderados', 'exists:tipo_apoderados,id'],
            'apoderados.*.celular' => ['nullable', 'string', 'max:9'],
        ]);

        // Actualizar Confirmando
        $confirmando->update([
            'nombres' => $data['nombres'] ?? $confirmando->nombres,
            'apellidos' => $data['apellidos'] ?? $confirmando->apellidos,
            'celular' => array_key_exists('celular', $data) ? $data['celular'] : $confirmando->celular,
            'genero' => $data['genero'] ?? $confirmando->genero,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? $confirmando->fecha_nacimiento,
            'grupo_id' => array_key_exists('grupo_id', $data) ? $data['grupo_id'] : $confirmando->grupo_id,
        ]);

        // Re-calcular Ruta Sacramental
        if (isset($data['sacramento_faltante_id'])) {
            $this->asignarRutaSacramental($confirmando, $data['sacramento_faltante_id']);
        }

        // Actualizar Apoderados (NUEVO)
        if (isset($apoderadosData['apoderados'])) {
            $this->syncApoderados($confirmando, $apoderadosData['apoderados']);
        }

        if ($request->has('requisitos_actualizar')) {
            $requisitos = $request->input('requisitos_actualizar');

            foreach ($requisitos as $req) {
                // Actualizamos la tabla pivote 'requisito_confirmando'
                // Solo actualizamos el estado y la fecha de entrega
                $confirmando->requisitos()->updateExistingPivot($req['id'], [
                    'estado' => $req['estado'],
                    'fecha_entrega' => $req['estado'] === 'entregado' ? now() : null,
                ]);
            }
        }

        $confirmando->load('grupo', 'sacramentos', 'apoderados');

        return response()->json([
            'message' => 'Confirmando actualizado correctamente',
            'confirmando' => $confirmando,
        ]);
    }

    public function destroy($id)
    {
        $confirmando = Confirmando::findOrFail($id);
        $confirmando->delete();

        return response()->json(null, 204);
    }

    private function asignarRutaSacramental(Confirmando $confirmando, $faltanteId)
    {
        // Busca modelos (Asegúrate de que los nombres coincidan con tu BD)
        $bautismo = Sacramento::with('requisitos')->where('nombre', 'Bautismo')->first();
        $comunion = Sacramento::with('requisitos')->where('nombre', 'Primera Comunión')->first();
        $confirmacion = Sacramento::with('requisitos')->where('nombre', 'Confirmación')->first();

        if (! $bautismo || ! $comunion || ! $confirmacion) {
            return;
        }

        $requisitosAcumulados = collect();
        $sacramentosSyncData = [];

        // Lógica en cascada
        if ($faltanteId == $bautismo->id) {
            // Falta todo
            $sacramentosSyncData = [
                $bautismo->id => ['estado' => 'pendiente'],
                $comunion->id => ['estado' => 'pendiente'],
                $confirmacion->id => ['estado' => 'pendiente'],
            ];
            $requisitosAcumulados = $requisitosAcumulados
                ->merge($bautismo->requisitos)->merge($comunion->requisitos)->merge($confirmacion->requisitos);

        } elseif ($faltanteId == $comunion->id) {
            // Tiene Bautismo
            $sacramentosSyncData = [
                $bautismo->id => ['estado' => 'recibido'],
                $comunion->id => ['estado' => 'pendiente'],
                $confirmacion->id => ['estado' => 'pendiente'],
            ];
            $requisitosAcumulados = $requisitosAcumulados
                ->merge($comunion->requisitos)->merge($confirmacion->requisitos);

        } elseif ($faltanteId == $confirmacion->id) {
            // Solo falta Confirmación
            $sacramentosSyncData = [
                $bautismo->id => ['estado' => 'recibido'],
                $comunion->id => ['estado' => 'recibido'],
                $confirmacion->id => ['estado' => 'pendiente'],
            ];
            $requisitosAcumulados = $requisitosAcumulados
                ->merge($confirmacion->requisitos);
        }

        $confirmando->sacramentos()->sync($sacramentosSyncData);

        // Sincronización de requisitos inteligente (mantiene avance si es posible)
        $idsUnicos = $requisitosAcumulados->pluck('id')->unique();
        $requisitosActuales = $confirmando->requisitos()->get()->keyBy('id');
        $reqsSyncData = [];

        foreach ($idsUnicos as $idReq) {
            // Si ya existe, mantenemos su estado ('Entregado')
            if ($requisitosActuales->has($idReq)) {
                $reqsSyncData[$idReq] = [
                    'estado' => $requisitosActuales[$idReq]->pivot->estado,
                    'fecha_entrega' => $requisitosActuales[$idReq]->pivot->fecha_entrega,
                ];
            } else {
                // Si es nuevo, Pendiente
                $reqsSyncData[$idReq] = ['estado' => 'pendiente'];
            }
        }

        $confirmando->requisitos()->sync($reqsSyncData);
    }

    /**
     * Sincroniza los apoderados evitando duplicados por nombre.
     */
    private function syncApoderados(Confirmando $confirmando, array $listaApoderados)
    {
        $idsParaSincronizar = [];

        foreach ($listaApoderados as $datosAp) {
            // Buscamos o creamos al apoderado (por nombre y apellido)
            // Si ya existe alguien con ese nombre, lo reutilizamos
            $apoderado = Apoderado::firstOrCreate(
                [
                    'nombres' => $datosAp['nombres'],
                    'apellidos' => $datosAp['apellidos'],
                ],
                [
                    'celular' => $datosAp['celular'] ?? null,
                ]
            );

            // Si el apoderado ya existía, actualizamos su celular si nos mandaron uno nuevo
            if (isset($datosAp['celular'])) {
                $apoderado->update(['celular' => $datosAp['celular']]);
            }

            // Preparamos el ID y el dato pivote (tipo de parentesco)
            $idsParaSincronizar[$apoderado->id] = [
                'tipo_apoderado_id' => $datosAp['tipo_apoderado_id'],
            ];
        }

        // Sincronizamos (esto borra relaciones antiguas de este confirmando y pone las nuevas)
        $confirmando->apoderados()->sync($idsParaSincronizar);
    }
}
