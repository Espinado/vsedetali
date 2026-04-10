<?php

namespace App\Http\Controllers;

use App\Models\SellerStaff;
use App\Services\SellerStaffInvitationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SellerStaffInviteController extends Controller
{
    public function __construct(
        protected SellerStaffInvitationService $invitationService
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        if (auth('seller_staff')->check()) {
            return redirect()->to(Filament::getPanel('seller')->getUrl());
        }

        $staff = $this->invitationService->findValidStaffByToken($token);
        if (! $staff instanceof SellerStaff) {
            abort(404);
        }

        return view('seller-staff.invite-password', [
            'staff' => $staff,
            'token' => $token,
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        if (auth('seller_staff')->check()) {
            return redirect()->to(Filament::getPanel('seller')->getUrl());
        }

        $staff = $this->invitationService->findValidStaffByToken($token);
        if (! $staff instanceof SellerStaff) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->invitationService->completePasswordSetup($staff, $validated['password']);

        auth('seller_staff')->login($staff);

        return redirect()->to(Filament::getPanel('seller')->getUrl());
    }
}
