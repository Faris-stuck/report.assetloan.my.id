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
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login')->toString());
        $email = $login;

        if (! str_contains($login, '@')) {
            $student = Student::query()
                ->where('nis', $login)
                ->whereNotNull('user_id')
                ->first();

            $email = $student?->user?->email ?? $login;
        }

        // Eloquent/Auth uses parameterized queries; no SQL is assembled from user input.
        if (! Auth::attempt([
            'email' => $email,
            'password' => $this->password,
            'is_active' => true,
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), 600);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $this->user()
            ->forceFill([
                'last_login_at' => now(),
            ])
            ->save();

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        $loginLimit = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $ipLimit = RateLimiter::tooManyAttempts($this->ipThrottleKey(), 20);

        if (! $loginLimit && ! $ipLimit) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey())
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
        return 'login:credential:' . Str::transliterate(
            Str::lower(trim($this->string('login')->toString()))
        ) . '|' . ($this->ip() ?? 'unknown');
    }

    public function ipThrottleKey(): string
    {
        return 'login:ip:' . ($this->ip() ?? 'unknown');
    }
}
