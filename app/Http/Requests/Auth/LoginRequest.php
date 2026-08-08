<?php

namespace App\Http\Requests\Auth;

use App\Models\Student;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
            ],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->string('login')->toString();
        $email = $login;

        if (! str_contains($login, '@')) {
            $student = Student::query()
                ->where('nis', $login)
                ->whereNotNull('user_id')
                ->first();

            $email = $student?->user?->email ?? $login;
        }

        if (! Auth::attempt([
            'email' => $email,
            'password' => $this->password,
            'is_active' => true,
        ], $this->boolean('remember'))) {
            RateLimiter::hit(
                $this->throttleKey()
            );

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $this->user()
            ->forceFill([
                'last_login_at' => now(),
            ])
            ->save();

        RateLimiter::clear(
            $this->throttleKey()
        );
    }

    public function ensureIsNotRateLimited(): void
    {
        if (
            ! RateLimiter::tooManyAttempts(
                $this->throttleKey(),
                5
            )
        ) {
            return;
        }

        event(
            new Lockout($this)
        );

        $seconds = RateLimiter::availableIn(
            $this->throttleKey()
        );

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower(
                $this->string('login')
            )
            .'|'
            .($this->ip() ?? 'unknown')
        );
    }
}