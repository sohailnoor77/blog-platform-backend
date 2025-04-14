<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Mail\SendOtpMail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(StoreUserRequest $request)
    {
        try {
            // Generate OTP
            $randomBytes = random_bytes(2); // 2 bytes = 16 bits
            $numericCode = hexdec(bin2hex($randomBytes)) % 10000; // Ensure it's 4 digits
            $otp_code = str_pad($numericCode, 4, '0', STR_PAD_LEFT);

            $user = User::create([
                'name' => $request->post('name'),
                'email' => $request->post('email'),
                'otp_code' => $otp_code,
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            // Send OTP via email
            Mail::to($user->email)->send(new SendOtpMail($otp_code));

            return response()->json([
                'message' => 'OTP sent to email.',
                'email' => $user->email
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->post('email'))->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Generate OTP
            $randomBytes = random_bytes(2); // 2 bytes = 16 bits
            $numericCode = hexdec(bin2hex($randomBytes)) % 10000; // Ensure it's 4 digits
            $otp_code = str_pad($numericCode, 4, '0', STR_PAD_LEFT);

            // Send OTP via email
            Mail::to($user->email)->send(new SendOtpMail($otp_code));

            $user->otp_code = $otp_code;
            $user->otp_expires_at = now()->addMinutes(5);
            $user->save();

            return response()->json([
                'message' => 'OTP sent to email.',
                'email' => $user->email,

            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        try {

            $user = User::where('email', $request->post('email'))->first();

            if (!$user || $user->otp_code !== $request->post('otp_code') || now()->gt($user->otp_expires_at)) {
                return response()->json(['message' => 'Invalid or expired OTP'], 401);
            }

            $token = auth('api')->login($user);

            // clear OTP
            $user->email_verified_at = now();
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();

            return response()->json([
                'message' => 'User OTP verified successfully.',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function logout()
    {
        try {
            auth('api')->logout();

            return response()->json(['message' => 'Successfully logged out']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Logout failed'], 401);
        }
    }

    public function getUser()
    {
        try {
            $user = User::select([
                'id',
                'name',
                'email',
            ])->find(auth('api')->user()->id);

            return response()->json([
                'message' => 'Data fetched successfully.',
                'user' => $user,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
