<?php

namespace App\Policies;

/**
 * A template is reference data — a blueprint, not a record of anyone's performance — so it takes
 * the same stance as teams and categories: ADMIN only.
 */
class KpiTemplatePolicy extends ReferenceDataPolicy {}
