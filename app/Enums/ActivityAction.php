<?php

namespace App\Enums;

/**
 * What happened. Stored as a plain string on activity_logs (no DB CHECK) so new
 * milestones can add actions without a schema change.
 */
enum ActivityAction: string
{
    case RequestCreated = 'REQUEST_CREATED';
    case RequestUpdated = 'REQUEST_UPDATED';
    case PriorityChanged = 'PRIORITY_CHANGED';
    case DeadlineChanged = 'DEADLINE_CHANGED';
    case StatusChanged = 'STATUS_CHANGED';
    case TeamAssigned = 'TEAM_ASSIGNED';
    case TeamChanged = 'TEAM_CHANGED';
    case PicAssigned = 'PIC_ASSIGNED';
    case PicChanged = 'PIC_CHANGED';
    case PicUnassigned = 'PIC_UNASSIGNED';
    case CommentAdded = 'COMMENT_ADDED';
    case FileUploaded = 'FILE_UPLOADED';
    case DeliverableUploaded = 'DELIVERABLE_UPLOADED';
    case ReviewRequested = 'REVIEW_REQUESTED';
    case ReviewApproved = 'REVIEW_APPROVED';
    case RevisionRequested = 'REVISION_REQUESTED';
}
