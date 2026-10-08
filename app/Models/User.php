<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ChatPresenceStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'secondary_email', 'username', 'password', 'department_id', 'avatar_path', 'status', 'invited_by', 'invited_at', 'presence_status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    use HasRoles {
        hasPermissionTo as protected hasPermissionToIgnoringStatus;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'invited_at' => 'datetime',
            'presence_status' => ChatPresenceStatus::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get a temporary, signed URL for the user's avatar, if one has been uploaded.
     *
     * The "nakama-dashboard" bucket is private, so a plain public URL 403s —
     * mirrors FoFunnel::dokumenAkadUrl()'s presigned-URL approach.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('s3')->temporaryUrl($this->avatar_path, now()->addMinutes(15)) : null;
    }

    /**
     * Get the employee profile associated with the user.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Organizational tier derived from the employee's position, used to color-code
     * avatar rings and position badges consistently across the app.
     */
    public function positionTier(): ?string
    {
        $employee = $this->employee;
        $position = $employee?->positionStruktural ?? $employee?->positionFungsional;

        if (! $position) {
            return null;
        }

        $isCLevel = Str::contains(Str::lower($position->name), [
            'chief', 'direksi', 'komisaris', 'dewan penasihat', 'presiden direktur', 'vice president',
        ]);

        if ($isCLevel) {
            return 'c-level';
        }

        return $employee->positionStruktural ? 'struktural' : 'fungsional';
    }

    /**
     * Get the department this user belongs to (synced from their Employee record).
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Direksi/BOD: full visibility into business modules but read-only.
     */
    public function isReadOnlyViewer(): bool
    {
        return $this->hasRole('Direksi');
    }

    /**
     * Get the admin who invited this user, if the account originated from an invite.
     */
    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Conversations (chat threads) this user participates in.
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * A suspended account holds no permissions, whatever roles or direct grants it still has.
     * Overridden here because Spatie's own Gate::before resolves permissions before ours runs.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        return ! $this->isSuspended() && $this->hasPermissionToIgnoringStatus($permission, $guardName);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isInvited(): bool
    {
        return $this->status === UserStatus::Invited;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }
}
