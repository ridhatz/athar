<?php

namespace App\Http\Controllers;

use App\Queries\DashboardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The three dashboards. Each person is offered only the ones their role allows. */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardQuery $dashboards) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $perspectives = $this->dashboards->perspectivesFor($user);

        return Inertia::render('dashboard', [
            'perspectives' => $perspectives,
            'data' => collect($perspectives)->mapWithKeys(fn (string $p) => [$p => $this->data($p, $request)])->all(),
        ]);
    }

    /** API: one perspective. */
    public function show(Request $request, string $perspective): JsonResponse
    {
        abort_unless(in_array($perspective, $this->dashboards->perspectivesFor($request->user()), true), 403);

        return response()->json(['data' => $this->data($perspective, $request)]);
    }

    /** @return array<mixed> */
    private function data(string $perspective, Request $request): array
    {
        $user = $request->user();

        return match ($perspective) {
            'requester' => ['cards' => $this->dashboards->requester($user)],
            'staff' => ['cards' => $this->dashboards->staff($user)],
            'management' => $this->dashboards->management($user),
        };
    }
}
