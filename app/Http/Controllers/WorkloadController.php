<?php

namespace App\Http\Controllers;

use App\Queries\WorkloadBoard;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** The workload board. Any signed-in person may read it; it exposes counts and categories only. */
class WorkloadController extends Controller
{
    public function __construct(private readonly WorkloadBoard $board) {}

    public function index(): Response
    {
        return Inertia::render('workload', [
            'board' => $this->board->board(),
            'thresholds' => $this->thresholds(),
        ]);
    }

    public function api(): JsonResponse
    {
        return response()->json(['data' => $this->board->board(), 'thresholds' => $this->thresholds()]);
    }

    /** @return array<string, int> */
    private function thresholds(): array
    {
        return config('athar.workload');
    }
}
