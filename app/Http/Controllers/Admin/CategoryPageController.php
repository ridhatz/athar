<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateCategory;
use App\Actions\Admin\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryAdminResource;
use App\Models\RequestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "Categories" screen. ADMIN only; the rules live in the Actions and RequestCategoryPolicy. */
class CategoryPageController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', RequestCategory::class);

        $q = trim((string) $request->query('q', ''));

        $categories = RequestCategory::query()
            ->withCount('requests')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name_id', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('slug', 'like', $like));
            })
            // The admin sees them in the same order the request form offers them.
            ->orderBy('sort_order')
            ->orderBy('name_id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/categories', [
            'categories' => CategoryAdminResource::collection($categories),
            'filters' => ['q' => $q],
            // Below this, switching the last one off is refused; the screen greys the toggle out.
            'activeCount' => RequestCategory::query()->where('is_active', true)->count(),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $create): RedirectResponse
    {
        $category = $create->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.category_created', ['name' => $category->name_id])]);

        return back();
    }

    public function update(UpdateCategoryRequest $request, RequestCategory $category, UpdateCategory $update): RedirectResponse
    {
        $update->handle($request->user(), $category, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.updated')]);

        return back();
    }
}
