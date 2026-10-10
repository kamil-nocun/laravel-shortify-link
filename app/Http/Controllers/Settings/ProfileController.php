<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeleteUserAccount;
use App\Exceptions\AccountDeletionFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $user = $request->user();

        try {
            $deleteUserAccount($user);
        } catch (AccountDeletionFailed $exception) {
            throw ValidationException::withMessages(['password' => $exception->userMessage()]);
        }

        // Auth::logout() would save the deleted user again while rotating the remember token.
        Auth::guard('web')->forgetUser();
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
