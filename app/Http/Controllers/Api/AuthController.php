<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\OperationId;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;
use Tsitsishvili\Documentator\Attributes\TagDescription;

#[Group('Authentication')]
#[TagDescription('Register, authenticate, and manage the personal access token used as a `Bearer` credential on protected endpoints.')]
class AuthController extends Controller
{
    private const string AUTH_RESPONSE_TYPE = 'array{token: string, user: array{id: int, name: string, email: email, created_at: date-time, updated_at: date-time}}';

    private const array AUTH_RESPONSE_EXAMPLE = [
        'token' => '1|abcdef...',
        'user' => [
            'id' => 1,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'created_at' => '2026-08-04T12:00:00.000000Z',
            'updated_at' => '2026-08-04T12:00:00.000000Z',
        ],
    ];

    private const string USER_RESPONSE_TYPE = 'array{data: array{id: int, name: string, email: email, created_at: date-time, updated_at: date-time}}';

    private const array USER_RESPONSE_EXAMPLE = [
        'data' => [
            'id' => 1,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'created_at' => '2026-08-04T12:00:00.000000Z',
            'updated_at' => '2026-08-04T12:00:00.000000Z',
        ],
    ];

    #[Summary('Register a new user')]
    #[Description('Creates a user account and returns a personal access token for subsequent authenticated requests.')]
    #[ApiResponse(status: 201, type: self::AUTH_RESPONSE_TYPE, description: 'Account created.', example: self::AUTH_RESPONSE_EXAMPLE)]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user),
        ], Response::HTTP_CREATED);
    }

    #[Summary('Log in')]
    #[Description('Verifies credentials and returns a personal access token. The token is sent as a `Bearer` token on authenticated requests.')]
    #[OperationId('authLogin')]
    #[ApiResponse(status: 200, type: self::AUTH_RESPONSE_TYPE, description: 'Authenticated.', example: self::AUTH_RESPONSE_EXAMPLE)]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user),
        ]);
    }

    #[Summary('Log out')]
    #[Description('Revokes the access token used to make the request.')]
    #[Authenticated]
    #[ApiResponse(status: 204, description: 'Token revoked.')]
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    #[Summary('Get the authenticated user')]
    #[Description('Returns the profile of the user that owns the access token used for the request.')]
    #[Authenticated]
    #[ApiResponse(status: 200, type: self::USER_RESPONSE_TYPE, example: self::USER_RESPONSE_EXAMPLE)]
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
