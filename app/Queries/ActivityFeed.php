<?php

namespace App\Queries;

use App\Models\ActivityLog;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/** The history of a request as a particular viewer is allowed to read it, oldest first. */
final class ActivityFeed
{
    /** @return Collection<int, ActivityLog> */
    public function for(User $viewer, ServiceRequest $request): Collection
    {
        $includeInternal = Gate::forUser($viewer)->allows('viewInternal', $request);

        return ActivityLog::query()
            ->where('request_id', $request->id)
            ->when(! $includeInternal, fn ($q) => $q->where('is_internal', false))
            ->with('actor:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
