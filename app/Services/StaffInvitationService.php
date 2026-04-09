<?php

namespace App\Services;

use App\Mail\StaffInviteMail;
use App\Models\Staff;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffInvitationService
{
    public function sendInvitation(Staff $staff): void
    {
        $plain = Str::random(64);
        $staff->invite_token_hash = hash('sha256', $plain);
        $staff->invite_expires_at = now()->addDays(7);
        $staff->save();

        $url = route('staff.invite.show', ['token' => $plain]);

        Mail::to($staff->email)->send(new StaffInviteMail($staff, $url));
    }

    public function findValidStaffByToken(string $plainToken): ?Staff
    {
        if (strlen($plainToken) < 32) {
            return null;
        }

        $hash = hash('sha256', $plainToken);

        return Staff::query()
            ->where('invite_token_hash', $hash)
            ->where('invite_expires_at', '>', now())
            ->first();
    }

    public function clearInvite(Staff $staff): void
    {
        $staff->invite_token_hash = null;
        $staff->invite_expires_at = null;
        $staff->save();
    }

    public function completePasswordSetup(Staff $staff, string $plainPassword): void
    {
        $staff->password = $plainPassword;
        $staff->invite_token_hash = null;
        $staff->invite_expires_at = null;
        $staff->save();
    }
}
