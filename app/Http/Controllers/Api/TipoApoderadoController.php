<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class TipoApoderadoController extends Controller
{
    public function index()
    {
        return \App\Models\TipoApoderado::all();
    }
}
