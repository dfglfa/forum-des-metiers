<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'password', 'role', 'ldap_username', 'class', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, MustVerifyEmailTrait;

    const ROLE_ADMIN = 'admin';
    const ROLE_CONSULTANT = 'consultant';
    const ROLE_STUDENT = 'student';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isConsultant(): bool
    {
        return $this->role === self::ROLE_CONSULTANT;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (
                    $this->role === self::ROLE_CONSULTANT
                    && $this->relationLoaded('consultantProfile')
                    && $this->consultantProfile
                ) {
                    $full = trim(
                        ($this->consultantProfile->first_name ?? '') . ' ' .
                        ($this->consultantProfile->last_name  ?? '')
                    );
                    if ($full !== '') {
                        return $full;
                    }
                }
                return $this->getRawOriginal('name');
            }
        );
    }

    public function consultantProfile(): HasOne
    {
        return $this->hasOne(ConsultantProfile::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'consultant_id');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class, 'consultant_id');
    }

    public function tagPreferences(): HasMany
    {
        return $this->hasMany(StudentTagPreference::class, 'student_id');
    }

    public function schedule(): HasMany
    {
        return $this->hasMany(StudentSchedule::class, 'student_id');
    }
}
