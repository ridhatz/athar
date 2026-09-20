<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\CreateRequest;
use App\Actions\Requests\UpdateRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\AssignServiceRequest;
use App\Http\Requests\ServiceRequests\ChangeStatusRequest;
use App\Http\Requests\ServiceRequests\ListServiceRequestsRequest;
use App\Http\Requests\ServiceRequests\StoreServiceRequest;
use App\Http\Requests\ServiceRequests\UpdateServiceRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\RequestDetailResource;
use App\Http\Resources\RequestResource;
use App\Models\ServiceRequest;
use App\Queries\ActivityFeed;
use App\Queries\RequestListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Thin on purpose: parse, call the action, shape the response.
 * Business rules live in App\Actions and App\Policies.
 * {serviceRequest} is resolved (and visibility-checked → 404) by the route binding in AppServiceProvider.
 */
class RequestController extends Controller
{
    public function index(ListServiceRequestsRequest $request, RequestListQuery $query): AnonymousResourceCollection
    {
        $page = $query->build($request->user(), $request->validated())
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return RequestResource::collection($page);
    }

    public function store(StoreServiceRequest $request, CreateRequest $create): JsonResponse
    {
        $created = $create->handle($request->user(), $request->validated(), $request->file('files', []));

        return RequestDetailResource::make($this->hydrate($created))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.requests.show', $created->request_number));
    }

    public function show(ServiceRequest $serviceRequest): RequestDetailResource
    {
        return RequestDetailResource::make($this->hydrate($serviceRequest));
    }

    public function update(UpdateServiceRequest $request, ServiceRequest $serviceRequest, UpdateRequest $update): RequestDetailResource
    {
        $updated = $update->handle($request->user(), $serviceRequest, $request->validated());

        return RequestDetailResource::make($this->hydrate($updated));
    }

    public function assign(AssignServiceRequest $request, ServiceRequest $serviceRequest, AssignRequest $assign): RequestDetailResource
    {
        $assigned = $assign->handle($request->user(), $serviceRequest, $request->validated());

        return RequestDetailResource::make($this->hydrate($assigned));
    }

    public function status(ChangeStatusRequest $request, ServiceRequest $serviceRequest, ChangeRequestStatus $change): RequestDetailResource
    {
        $changed = $change->handle($request->user(), $serviceRequest, $request->toStatus(), $request->validated('note'));

        return RequestDetailResource::make($this->hydrate($changed));
    }

    public function activity(Request $request, ServiceRequest $serviceRequest, ActivityFeed $feed): AnonymousResourceCollection
    {
        return ActivityLogResource::collection($feed->for($request->user(), $serviceRequest));
    }

    /** Assignment history is working-side information, so requesters do not get it. */
    public function assignments(Request $request, ServiceRequest $serviceRequest): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewInternal', $serviceRequest);

        $history = $serviceRequest->assignments()
            ->with(['team:id,name', 'user:id,name', 'assigner:id,name'])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        return AssignmentResource::collection($history);
    }

    private function hydrate(ServiceRequest $request): ServiceRequest
    {
        return $request->load(['category', 'department', 'requester', 'assignedTeam', 'assignedUser']);
    }
}
