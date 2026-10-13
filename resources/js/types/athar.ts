export const REQUEST_STATUSES = [
    'NEW',
    'TRIAGED',
    'ASSIGNED',
    'IN_PROGRESS',
    'REVIEW',
    'COMPLETED',
    'BLOCKED',
    'CANCELLED',
] as const;

export const REQUEST_PRIORITIES = [
    'LOW',
    'NORMAL',
    'HIGH',
    'CRITICAL',
] as const;

export type RequestStatus = (typeof REQUEST_STATUSES)[number];
export type RequestPriority = (typeof REQUEST_PRIORITIES)[number];

export type NamedRef = { id: number; name: string };

export type Category = {
    id: number;
    slug: string;
    name: string;
    guidance: string | null;
};

/** A request as it appears in lists. Mirrors App\Http\Resources\RequestResource. */
export type RequestListItem = {
    id: number;
    request_number: string;
    title: string;
    status: RequestStatus;
    priority: RequestPriority;
    deadline: string;
    is_overdue: boolean;
    category?: { id: number; slug: string; name: string };
    department?: NamedRef;
    requester?: NamedRef;
    assigned_team?: NamedRef | null;
    assigned_user?: NamedRef | null;
    created_at: string | null;
    updated_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
};

export type AllowedTransition = {
    status: RequestStatus;
    requires_note: boolean;
};

/** One request plus what the caller may do with it. Mirrors RequestDetailResource. */
export type RequestDetail = RequestListItem & {
    description: string;
    blocked_from_status: RequestStatus | null;
    permissions: {
        edit_details: boolean;
        edit_priority: boolean;
        assign: boolean;
        view_internal: boolean;
        comment: boolean;
        comment_internal: boolean;
        attach: boolean;
        attach_work_product: boolean;
        review_decision: boolean;
    };
    allowed_transitions: AllowedTransition[];
};

export type ActivityAction =
    | 'REQUEST_CREATED'
    | 'REQUEST_UPDATED'
    | 'PRIORITY_CHANGED'
    | 'DEADLINE_CHANGED'
    | 'STATUS_CHANGED'
    | 'TEAM_ASSIGNED'
    | 'TEAM_CHANGED'
    | 'PIC_ASSIGNED'
    | 'PIC_CHANGED'
    | 'PIC_UNASSIGNED'
    | 'COMMENT_ADDED'
    | 'FILE_UPLOADED'
    | 'DELIVERABLE_UPLOADED'
    | 'REVIEW_REQUESTED'
    | 'REVIEW_APPROVED'
    | 'REVISION_REQUESTED';

export type ActivityEntry = {
    id: number;
    action: ActivityAction;
    field: string | null;
    old_value: string | null;
    new_value: string | null;
    note: string | null;
    is_internal: boolean;
    actor: NamedRef | null;
    created_at: string;
};

export type AssignmentEntry = {
    id: number;
    team: NamedRef | null;
    user: NamedRef | null;
    assigned_by: NamedRef;
    note: string | null;
    is_current: boolean;
    assigned_at: string;
    ended_at: string | null;
};

export type Assignables = {
    teams: NamedRef[];
    users: {
        id: number;
        name: string;
        team_ids: number[];
        open: number;
        level: LoadLevel;
    }[];
};

/** Laravel's paginated resource collection. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export type RequestFilters = {
    q: string;
    status: string;
    priority: string;
    category_id: string;
    mine: boolean;
    assigned_to_me: boolean;
    overdue: boolean;
    open: boolean;
    my_teams: boolean;
    due_today: boolean;
    unrouted: boolean;
};

export type AttachmentKind =
    | 'BRIEF'
    | 'REFERENCE'
    | 'LOGO'
    | 'DOCUMENT'
    | 'DRAFT'
    | 'REVISION'
    | 'DELIVERABLE'
    | 'OTHER';

export type AttachmentEntry = {
    id: number;
    kind: AttachmentKind;
    original_name: string;
    mime_type: string;
    size: number;
    version: number;
    is_internal: boolean;
    comment_id: number | null;
    uploader?: NamedRef;
    created_at: string;
    download_url: string;
};

export type CommentEntry = {
    id: number;
    body: string;
    is_internal: boolean;
    author?: NamedRef;
    attachments?: AttachmentEntry[];
    created_at: string;
};

export type ReviewDecisionCode = 'PENDING' | 'APPROVED' | 'REVISION_REQUESTED';

export type ReviewEntry = {
    id: number;
    round: number;
    decision: ReviewDecisionCode;
    decision_note: string | null;
    requested_by?: NamedRef;
    reviewer?: NamedRef | null;
    requested_at: string;
    decided_at: string | null;
};

export type NotificationEntry = {
    id: string;
    type: string;
    request_number: string | null;
    title: string | null;
    params: Record<string, string | number | null>;
    read_at: string | null;
    created_at: string;
};

export type DashboardCard = {
    key: string;
    count: number;
    filters: Record<string, string | number>;
};

export type GroupCount = {
    id: number | null;
    name: string | null;
    count: number;
};

export type ManagementDashboard = {
    cards: DashboardCard[];
    by_category: GroupCount[];
    by_team: GroupCount[];
    by_department: GroupCount[];
    avg_completion_hours: number | null;
};

export type Perspective = 'requester' | 'staff' | 'management';

export type DashboardData = {
    requester?: { cards: DashboardCard[] };
    staff?: { cards: DashboardCard[] };
    management?: ManagementDashboard;
};

export type LoadLevel = 'green' | 'yellow' | 'orange' | 'red';

export type WorkloadEntry = {
    rank: number;
    id: number;
    name: string;
    teams: string[];
    open: number;
    level: LoadLevel;
    working_on: { category: string; count: number }[];
    completed_30d: number;
};

export type RoleCodeName =
    | 'REQUESTER'
    | 'STAFF'
    | 'TEAM_LEAD'
    | 'ADMIN'
    | 'MANAGEMENT';

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    locale: 'id' | 'en';
    department: NamedRef | null;
    roles: RoleCodeName[];
    teams: NamedRef[];
    created_at: string | null;
};

export type Credentials = {
    name: string;
    email: string;
    password: string | null;
};

/**
 * Reference data as the administrator sees it. None of these are ever deleted; `is_active` is
 * the only destructive move, and the server refuses the ones that would break something.
 */
export type AdminTeam = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    members_count: number;
    /** Workers left with no active team if this one closed. Empty means closing is allowed. */
    stranded_members?: string[];
    created_at: string | null;
};

export type AdminCategory = {
    id: number;
    /** Fixed when the category is created; the form shows it but never sends it back. */
    slug: string;
    name_id: string;
    name_en: string;
    guidance_id: string | null;
    guidance_en: string | null;
    sort_order: number;
    is_active: boolean;
    requests_count: number;
    created_at: string | null;
};

export type AdminDepartment = {
    id: number;
    name: string;
    is_active: boolean;
    users_count: number;
    created_at: string | null;
};
