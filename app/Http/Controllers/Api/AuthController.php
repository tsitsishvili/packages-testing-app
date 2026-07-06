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
use Tsitsishvili\Documentator\Attributes\BodyParam;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\OperationId;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\ResponseHeader;
use Tsitsishvili\Documentator\Attributes\Summary;
use Tsitsishvili\Documentator\Attributes\TagDescription;

#[Group('Authentication')]
#[TagDescription('Register, authenticate, and manage the personal access token used as a `Bearer` credential on protected endpoints.')]
class AuthController extends Controller
{
    #[Summary('Register a new user')]
    #[Description('Creates a user account and returns a personal access token for subsequent authenticated requests.')]
    #[BodyParam('name', type: 'string', required: true, description: 'Display name.', example: 'Ada Lovelace')]
    #[BodyParam('email', type: 'string', required: true, description: 'Unique email address.', example: 'ada@example.com')]
    #[BodyParam('password', type: 'string', required: true, description: 'At least 8 characters.', example: 'secret-password')]
    #[BodyParam('password_confirmation', type: 'string', required: true, description: 'Must match `password`.', example: 'secret-password')]
    #[ApiResponse(status: 201, description: 'Account created.', example: ['token' => '1|abcdef...', 'user' => ['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']])]
    #[ResponseHeader(201, 'X-Request-Id', description: 'Correlation id assigned to the registration request.', example: 'req_9f40d932c4c0')]
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
    #[BodyParam('email', type: 'string', required: true, description: 'Registered email address.', example: 'ada@example.com')]
    #[BodyParam('password', type: 'string', required: true, description: 'Account password.', example: 'secret-password')]
    #[ApiResponse(status: 200, description: 'Authenticated.', example: ['token' => '1|abcdef...', 'user' => ['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']])]
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
    #[ApiResponse(status: 200, resource: UserResource::class)]
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
