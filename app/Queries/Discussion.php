<?php

namespace App\Queries;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/** Comments and files of a request, as a given viewer may read them. Internal items never reach the requester. */
final class Discussion
{
    /** @return Collection<int, Comment> */
    public function comments(User $viewer, ServiceRequest $request): Collection
    {
        $includeInternal = Gate::forUser($viewer)->allows('viewInternal', $request);

        return Comment::query()
            ->where('request_id', $request->id)
            ->when(! $includeInternal, fn ($q) => $q->where('is_internal', false))
            ->with(['author:id,name', 'attachments' => fn ($q) => $q->when(! $includeInternal, fn ($a) => $a->where('is_internal', false))->with('uploader:id,name')])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Attachment> */
    public function attachments(User $viewer, ServiceRequest $request): Collection
    {
        $includeInternal = Gate::forUser($viewer)->allows('viewInternal', $request);

        return Attachment::query()
            ->where('request_id', $request->id)
            ->when(! $includeInternal, fn ($q) => $q->where('is_internal', false))
            ->with('uploader:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
