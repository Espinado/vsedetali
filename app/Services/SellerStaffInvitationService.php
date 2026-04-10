<?php

namespace App\Services;

use App\Mail\SellerStaffInviteMail;
use App\Models\SellerStaff;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SellerStaffInvitationService
{
    public function sendInvitation(SellerStaff $staff): void
    {
        $plain = Str::random(64);
        $staff->invite_token_hash = hash('sha256', $plain);
        $staff->invite_expires_at = now()->addDays(7);
        $staff->save();

        $url = route('seller-staff.invite.show', ['token' => $plain]);

        Mail::to($staff->email)->send(new SellerStaffInviteMail($staff, $url));
    }

    public function findValidStaffByToken(string $plainToken): ?SellerStaff
    {
        if (strlen($plainToken) < 32) {
            return null;
        }

        $hash = hash('sha256', $plainToken);

        return SellerStaff::query()
            ->where('invite_token_hash', $hash)
            ->where('invite_expires_at', '>', now())
            ->first();
    }

    public function completePasswordSetup(SellerStaff $staff, string $plainPassword): void
    {
        $staff->password = $plainPassword;
        $staff->invite_token_hash = null;
        $staff->invite_expires_at = null;
        $staff->save();
    }
}
