<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * UI-1 follow-up: users/account_setting.vue — the page linked from
     * the header's "My Account" dropdown — was, in its entirety, a
     * leftover copy of case-detail-viewing markup (case fields, a
     * documents table, a hearings table) with nothing related to
     * account settings anywhere in it. Every user who opened their own
     * account page saw a broken, nonsensical screen. This controller is
     * the real backend the rebuilt page (see the .vue file's own
     * comment) needed and never had.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        return $this->response(true, 'success', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'department' => $user->department,
        ], 200);
    }

    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? $user->phone_number,
            'updated_by' => $user->id,
        ]);

        return $this->response(true, 'Profile updated successfully.', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'department' => $user->department,
        ], 200);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->response(false, 'Current password is incorrect.', null, 422);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
            'updated_by' => $user->id,
        ]);

        return $this->response(true, 'Password updated successfully.', null, 200);
    }
}
