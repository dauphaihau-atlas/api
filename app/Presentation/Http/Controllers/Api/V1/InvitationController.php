<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class InvitationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $email = $this->validatedEmail($request);
        $token = $this->validatedToken($request);
        $user = $this->validInvitedUser($email, $token);

        return ApiResponse::ok([
            'name' => $user->name,
            'email' => $user->email->getValue(),
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException('Validation failed');
        }

        $validated = $validator->validated();
        $user = $this->validInvitedUser($validated['email'], $validated['token']);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return ApiResponse::ok([
            'email' => $user->email->getValue(),
        ], 'Invite accepted.');
    }

    private function validatedEmail(Request $request): string
    {
        $email = (string) $request->query('email', '');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Valid email is required.');
        }

        return $email;
    }

    private function validatedToken(Request $request): string
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            throw new ValidationException('Valid invite token is required.');
        }

        return $token;
    }

    private function validInvitedUser(string $email, string $token): UserModel
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (
            $record === null
            || $record->created_at < now()->subDay()->toDateTimeString()
            || ! Hash::check($token, $record->token)
        ) {
            throw new NotFoundException('Invite not found or expired.');
        }

        $user = UserModel::where('email', $email)->first();
        if ($user === null) {
            throw new NotFoundException('Invite not found or expired.');
        }

        return $user;
    }
}
