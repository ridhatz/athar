<?php

namespace App\Http\Controllers;

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\CreateRequest;
use App\Actions\Requests\UpdateRequest;
use App\Enums\AttachmentKind;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Http\Requests\ServiceRequests\AssignServiceRequest;
use App\Http\Requests\ServiceRequests\ChangeStatusRequest;
use App\Http\Requests\ServiceRequests\ListServiceRequestsRequest;
use App\Http\Requests\ServiceRequests\StoreServiceRequest;
use App\Http\Requests\ServiceRequests\UpdateServiceRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\RequestDetailResource;
use App\Http\Resources\RequestResource;
use App\Http\Resources\ReviewResource;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use App\Models\Team;
use App\Models\User;
use App\Queries\ActivityFeed;
use App\Queries\Discussion;
use App\Queries\RequestListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The web UI's face of the Request Engine. Like the API controller it holds no rules:
 * it calls the same Actions, and reads the same Resources, so web and API can never disagree.
 */
class RequestPageController extends Controller
{
    public function index(ListServiceRequestsRequest $request, RequestListQuery $query): Response
    {
        $filters = $request->validated();
        $page = $query->build($request->user(), $filters)
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('requests/index', [
            'requests' => RequestResource::collection($page),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'category_id' => isset($filters['category_id']) ? (string) $filters['category_id'] : '',
                'mine' => (bool) ($filters['mine'] ?? false),
                'assigned_to_me' => (bool) ($filters['assigned_to_me'] ?? false),
                'overdue' => (bool) ($filters['overdue'] ?? false),
                'open' => (bool) ($filters['open'] ?? false),
                'my_teams' => (bool) ($filters['my_teams'] ?? false),
                'due_today' => (bool) ($filters['due_today'] ?? false),
                'unrouted' => (bool) ($filters['unrouted'] ?? false),
            ],
            'categories' => $this->categories(),
            'statuses' => RequestStatus::values(),
            'priorities' => RequestPriority::values(),
            'canCreate' => Gate::forUser($request->user())->allows('create', ServiceRequest::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('create', ServiceRequest::class);

        return Inertia::render('requests/create', [
            'categories' => $this->categories(),
            'priorities' => RequestPriority::values(),
            // Only asked for when we cannot infer it from the requester's own department.
            'departments' => $request->user()->department_id === null || $request->user()->isAdmin()
                ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'ownDepartment' => $request->user()->department?->name,
        ]);
    }

    public function store(StoreServiceRequest $request, CreateRequest $create): RedirectResponse
    {
        $created = $create->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('requests.flash.created', ['number' => $created->request_number])]);

        return redirect()->route('requests.show', $created->request_number);
    }

    public function show(Request $request, ServiceRequest $serviceRequest, ActivityFeed $feed, Discussion $discussion): Response
    {
        $serviceRequest->load(['category', 'department', 'requester', 'assignedTeam', 'assignedUser']);
        $viewer = $request->user();
        $gate = Gate::forUser($viewer);
        $canAssign = $gate->allows('assign', $serviceRequest);

        return Inertia::render('requests/show', [
            'request' => RequestDetailResource::make($serviceRequest),
            'activity' => ActivityLogResource::collection($feed->for($viewer, $serviceRequest)),
            // Working-side detail only.
            'assignments' => $gate->allows('viewInternal', $serviceRequest)
                ? AssignmentResource::collection(
                    $serviceRequest->assignments()->with(['team:id,name', 'user:id,name', 'assigner:id,name'])->orderBy('assigned_at')->orderBy('id')->get(),
                )
                : [],
            'comments' => CommentResource::collection($discussion->comments($viewer, $serviceRequest)),
            'attachments' => AttachmentResource::collection($discussion->attachments($viewer, $serviceRequest)),
            'reviews' => ReviewResource::collection(
                $serviceRequest->reviews()->with(['requester:id,name', 'reviewer:id,name'])->orderBy('round')->get(),
            ),
            'attachmentKinds' => AttachmentKind::values(),
            'maxUploadMb' => (int) round(config('athar.attachments.max_kb') / 1024),
            'assignables' => $canAssign ? $this->assignables() : null,
            'categories' => $gate->allows('editDetails', $serviceRequest) ? $this->categories() : [],
            'priorities' => RequestPriority::values(),
        ]);
    }

    public function update(UpdateServiceRequest $request, ServiceRequest $serviceRequest, UpdateRequest $update): RedirectResponse
    {
        $update->handle($request->user(), $serviceRequest, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('requests.flash.updated')]);

        return back();
    }

    public function assign(AssignServiceRequest $request, ServiceRequest $serviceRequest, AssignRequest $assign): RedirectResponse
    {
        $assign->handle($request->user(), $serviceRequest, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('requests.flash.assigned')]);

        return back();
    }

    public function status(ChangeStatusRequest $request, ServiceRequest $serviceRequest, ChangeRequestStatus $change): RedirectResponse
    {
        $change->handle($request->user(), $serviceRequest, $request->toStatus(), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('requests.flash.status')]);

        return back();
    }

    /** @return array<int, array{id: int, slug: string, name: string, guidance: string|null}> */
    private function categories(): array
    {
        return RequestCategory::query()->active()->orderBy('sort_order')->get()
            ->map(fn (RequestCategory $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->label(),
                'guidance' => $c->guidance(),
            ])->all();
    }

    /**
     * Teams and the people who can be given work, for the assignment panel.
     *
     * @return array{teams: array<int, mixed>, users: array<int, mixed>}
     */
    private function assignables(): array
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
            ->with('teams:id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'team_ids' => $u->teams->pluck('id')->values()]);

        return [
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users' => $users,
        ];
    }
}
