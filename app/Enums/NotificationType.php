<?php

namespace App\Enums;

/** What a notification is about. The values double as translation keys (notify.<VALUE>). */
enum NotificationType: string
{
    case AssignedPic = 'ASSIGNED_PIC';
    case AssignedTeam = 'ASSIGNED_TEAM';
    case StatusChanged = 'STATUS_CHANGED';
    case ReviewRequested = 'REVIEW_REQUESTED';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Completed = 'COMPLETED';
    case DeadlineApproaching = 'DEADLINE_APPROACHING';
    case Overdue = 'OVERDUE';
}
