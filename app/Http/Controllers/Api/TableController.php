<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function showByToken(string $token): JsonResponse
    {
        $table = CafeTable::where('qr_token', $token)->where('is_active', true)->firstOrFail();

        return response()->json(['id' => $table->id, 'name' => $table->name]);
    }

    // STAFF
    public function index(): JsonResponse
    {
        return response()->json(CafeTable::orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50|unique:cafe_tables,name',
        ]);

        $table = CafeTable::create([
            'name' => $data['name'],
            'qr_token' => Str::random(24),
        ]);

        return response()->json($table, 201);
    }

    public function destroy(CafeTable $table): JsonResponse
    {
        $table->delete();

        return response()->json(null, 204);
    }
}
