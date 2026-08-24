<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Mail\ForgetPassword;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class APIController extends Controller
{
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (! empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];

        return response()->json($response, 200);
    }

    // custom login
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:250',
            'password' => 'required|max:250',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), [], 422);
        }

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->sendError("Too many login attempts. Please try again in {$seconds} seconds.", [], 429);
        }

        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);

            $request->session()->regenerate();

            $user = Auth::user();
            // checkPermissionTo() returns false when the permission row does
            // not exist, where hasPermissionTo() throws PermissionDoesNotExist
            // (a 500 on any login before permissions are seeded).
            $isAllowed = Auth::user()->checkPermissionTo(ModulePermissions::VIEW_DASHBOARD);
            $roles = $user->getRoleNames();
            $permissions = $user->getAllPermissions()->pluck('name');

            return $this->sendResponse([$user, $isAllowed, $roles, $permissions], 'success');
        }

        RateLimiter::hit($throttleKey, 60);

        return $this->sendError('Credentials do not match our records', [], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Revoke any personal access tokens the user may still hold, then
        // end the session-based (cookie) authentication used by the SPA.
        $user?->tokens()->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->sendResponse(null, 'Successfully logged out');
    }

    // reset password
    public function reset_pass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            // UI-6: previously only the first error string was returned
            // (via ->first()) with no 'errors' key — unlike every
            // FormRequest-validated endpoint, whose automatic 422 shape
            // ({message, errors: {field: [...]}}) is what the frontend's
            // shared useFormErrors composable parses. Without a real
            // 'errors' object here, resetPassword.vue had no way to show
            // a field-specific message (e.g. "password too short") no
            // matter what the template did with the response.
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $record = DB::table('password_resets')->where('email', $request->email)->first();

            if (! $record || ! Hash::check($request->token, $record->token)) {
                return $this->sendError('Invalid or expired token.', [], 400);
            }

            $expiryMinutes = 60;
            if (Carbon::parse($record->created_at)->addMinutes($expiryMinutes)->isPast()) {
                DB::table('password_resets')->where('email', $request->email)->delete();

                return $this->sendError('Invalid or expired token.', [], 400);
            }

            // update users password
            User::where('email', $record->email)->update(['password' => Hash::make($request->password)]);

            // delete old data from database
            DB::table('password_resets')->where('email', $record->email)->delete();

            return $this->sendResponse(null, 'success');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return $this->sendError('Something went wrong, please contact support.', [], 500);
        }
    }

    public function passwordRecovery(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $token_str = Str::random(64);

            DB::table('password_resets')->where('email', $request->email)->delete();

            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => Hash::make($token_str),
                'created_at' => Carbon::now(),
            ]);

            Mail::to($user->email)->send(new ForgetPassword($user->name, $token_str));
        }

        // Always respond the same way whether or not the email exists,
        // so this endpoint can't be used to enumerate registered users.
        return $this->sendResponse(null, 'If that email is registered, a reset link has been sent.');
    }
}
