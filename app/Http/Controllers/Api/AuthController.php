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

    #[OA\Post(
        path: "/auth/send-otp",
        summary: "Send OTP to phone number",
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
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+998[0-9]{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->otpService->sendOtp($request->phone);

        return response()->json([
            'message' => 'Verification code sent successfully.',
        ]);
    }

    #[OA\Post(
        path: "/auth/register",
        summary: "Register new user",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code", "name", "surname"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+998901234567"),
                    new OA\Property(property: "code", type: "string", example: "1234"),
                    new OA\Property(property: "name", type: "string", example: "John"),
                    new OA\Property(property: "surname", type: "string", example: "Doe")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 422, description: "Validation/OTP Error")
        ]
    )]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+998[0-9]{9}$/', 'unique:users,phone'],
            'code' => ['required', 'string', 'size:4'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$this->otpService->verifyOtp($request->phone, $request->code)) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user = User::create([
            'phone' => $request->phone,
            'name' => $request->name,
            'surname' => $request->surname,
            'phone_verified_at' => Carbon::now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    #[OA\Post(
        path: "/auth/login",
        summary: "Login with phone and OTP",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "code"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+998901234567"),
                    new OA\Property(property: "code", type: "string", example: "1234")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 404, description: "User Not Found"),
            new OA\Response(response: 422, description: "Validation/OTP Error")
        ]
    )]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+998[0-9]{9}$/'],
            'code' => ['required', 'string', 'size:4'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found. Please register first.'], 404);
        }

        if (!$this->otpService->verifyOtp($request->phone, $request->code)) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user->update(['phone_verified_at' => Carbon::now()]);
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }
}
