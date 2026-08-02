<?php

namespace App\Policies;

use App\Models\ReportAttachment;
use App\Models\User;

class ReportAttachmentPolicy
{
    public function download(User $user, ReportAttachment $attachment): bool
    {
        return $user->can('view', $attachment->report);
    }
}
