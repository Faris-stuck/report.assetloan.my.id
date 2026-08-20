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
    // IP-independent ceiling for a single account. Deliberately higher than the per-IP
    // limiter so shared-NAT users are not locked out, but low enough that rotating
    // source addresses no longer buys an attacker unlimited guesses on one account.
    private const ACCOUNT_MAX_ATTEMPTS = 25;
    private const ACCOUNT_DECAY_SECONDS = 900;

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
            RateLimiter::hit($this->accountThrottleKey(), self::ACCOUNT_DECAY_SECONDS);

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
        RateLimiter::clear($this->accountThrottleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        $loginLimit = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $ipLimit = RateLimiter::tooManyAttempts($this->ipThrottleKey(), 20);
        $accountLimit = RateLimiter::tooManyAttempts($this->accountThrottleKey(), self::ACCOUNT_MAX_ATTEMPTS);

        if (! $loginLimit && ! $ipLimit && ! $accountLimit) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey()),
            RateLimiter::availableIn($this->accountThrottleKey())
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

    // Keyed on the normalised login identifier only, so rotating source addresses does
    // not reset the counter for a targeted account.
    public function accountThrottleKey(): string
    {
        return 'login:account:' . Str::transliterate(
            Str::lower(trim($this->string('login')->toString()))
        );
    }
}
