<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Send a 4-digit OTP code to the given phone number via Twilio SMS.
     */
    #[OA\Post(
        path: "/auth/send-otp",
        summary: "Send OTP via Twilio SMS",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+998901234567")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OTP sent successfully"),
            new OA\Response(response: 422, description: "Validation Error"),
            new OA\Response(response: 500, description: "Failed to send OTP")
        ]
    )]
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sent = $this->otpService->sendOtp($request->phone);

        if (!$sent) {
            return response()->json(['message' => 'Failed to send OTP. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully.',
            'phone' => $request->phone,
        ]);
    }

    /**
     * Verify the OTP code and log in or register the user.
     */
    #[OA\Post(
        path: "/auth/verify-otp",
        summary: "Verify OTP and login/register",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+998901234567"),
                    new OA\Property(property: "code", type: "string", example: "1234"),
                    new OA\Property(property: "name", type: "string", example: "John"),
                    new OA\Property(property: "surname", type: "string", example: "Doe"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Authentication successful"),
            new OA\Response(response: 401, description: "Invalid or expired OTP"),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'code'  => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $valid = $this->otpService->verifyOtp($request->phone, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Invalid or expired OTP code.'], 401);
        }

        // Find or create user
        $user = User::where('phone', $request->phone)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            // If name/surname are provided in the same request, register immediately
            if ($request->filled('name') && $request->filled('surname')) {
                $user = User::create([
                    'phone'             => $request->phone,
                    'name'              => $request->name,
                    'surname'           => $request->surname,
                    'phone_verified_at' => Carbon::now(),
                ]);
            } else {
                // New user — ask them to complete their profile
                return response()->json([
                    'message'    => 'OTP verified. Please complete your profile.',
                    'is_new_user' => true,
                    'phone'      => $request->phone,
                ], 200);
            }
        } else {
            $user->update(['phone_verified_at' => Carbon::now()]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Authentication successful.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'is_new_user'  => $isNewUser,
            'user'         => $user,
        ]);
    }

    /**
     * Complete profile for a newly verified user.
     */
    #[OA\Post(
        path: "/auth/complete-profile",
        summary: "Complete profile for new users after OTP verification",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "name", "surname"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+998901234567"),
                    new OA\Property(property: "name", type: "string", example: "John"),
                    new OA\Property(property: "surname", type: "string", example: "Doe"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User registered successfully"),
            new OA\Response(response: 409, description: "User already exists"),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone'   => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'name'    => 'required|string|max:100',
            'surname' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure the user does not already exist
        if (User::where('phone', $request->phone)->exists()) {
            return response()->json(['message' => 'User already exists. Please verify OTP to log in.'], 409);
        }

        $user = User::create([
            'phone'             => $request->phone,
            'name'              => $request->name,
            'surname'           => $request->surname,
            'phone_verified_at' => Carbon::now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registration successful.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ], 201);
    }
}
