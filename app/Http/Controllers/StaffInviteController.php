<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Services\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffInviteController extends Controller
{
    public function __construct(
        protected StaffInvitationService $invitationService
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        if (auth('staff')->check()) {
            return redirect()->to(Filament::getPanel('admin')->getUrl());
        }

        $staff = $this->invitationService->findValidStaffByToken($token);
        if (! $staff instanceof Staff) {
            abort(404);
        }

        return view('staff.invite-password', [
            'staff' => $staff,
            'token' => $token,
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        if (auth('staff')->check()) {
            return redirect('/admin');
        }

        $staff = $this->invitationService->findValidStaffByToken($token);
        if (! $staff instanceof Staff) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->invitationService->completePasswordSetup($staff, $validated['password']);

        auth('staff')->login($staff);

        return redirect('/admin');
    }
}
