<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pods;
use Illuminate\Http\JsonResponse;

class PodsController extends Controller
{
    public function index(): JsonResponse
    {
        $pods = Pods::with('skills')->get();

        return response()->json(['pods' => $pods]);
    }
}
