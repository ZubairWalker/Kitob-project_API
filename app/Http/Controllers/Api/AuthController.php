<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Firebase\Auth\Token\Exception\InvalidToken;

class AuthController extends Controller
{
    /**
     * Firebase Authentication
     * 
     * In Firebase flow, the frontend handles sending the SMS.
     * This endpoint is kept for reference or can be used for custom logic.
     */
    #[OA\Post(
        path: "/auth/send-otp",
        summary: "Send OTP (Handled by Firebase Frontend)",
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function sendOtp(Request $request)
    {
        return response()->json([
            'message' => 'Please use Firebase SDK on the frontend to send the SMS code.',
        ]);
    }

    #[OA\Post(
        path: "/auth/verify-firebase",
        summary: "Verify Firebase Token and Login/Register",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["firebase_token"],
                properties: [
                    new OA\Property(property: "firebase_token", type: "string"),
                    new OA\Property(property: "name", type: "string", example: "John"),
                    new OA\Property(property: "surname", type: "string", example: "Doe")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Invalid Token")
        ]
    )]
    public function verifyFirebase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firebase_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Verify the ID token using Firebase Admin SDK
            $auth = Firebase::auth();
            $verifiedIdToken = $auth->verifyIdToken($request->firebase_token);
            
            // Get the phone number from the verified token
            $firebaseUser = $auth->getUser($verifiedIdToken->claims()->get('sub'));
            $phone = $firebaseUser->phoneNumber;

            if (!$phone) {
                return response()->json(['message' => 'Phone number not found in Firebase token.'], 422);
            }

            // Find or Create user
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                // If user doesn't exist, they need to provide name and surname for registration
                if (!$request->has('name') || !$request->has('surname')) {
                    return response()->json([
                        'message' => 'User not found. Please provide name and surname to register.',
                        'is_new_user' => true,
                        'phone' => $phone
                    ], 200);
                }

                $user = User::create([
                    'phone' => $phone,
                    'name' => $request->name,
                    'surname' => $request->surname,
                    'phone_verified_at' => Carbon::now(),
                ]);
            } else {
                $user->update(['phone_verified_at' => Carbon::now()]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Authentication successful.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user
            ]);

        } catch (InvalidToken $e) {
            return response()->json(['message' => 'The token is invalid: ' . $e->getMessage()], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
