<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'student_code', 'active', 'entry_year', 'study_year', 'graduated', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => Role::class, 'active' => 'boolean', 'entry_year' => 'integer', 'study_year' => 'integer', 'graduated' => 'boolean', 'must_change_password' => 'boolean'];
    }

    public static function studyLevels(): array
    {
        return [1 => '1.º año', 2 => '2.º año', 3 => '3.º año', 4 => '4.º año', 5 => '5.º año', 'egresado' => 'Egresado'];
    }

    public function studyLevel(): int|string|null
    {
        return $this->graduated ? 'egresado' : $this->study_year;
    }

    public function scopeStudyLevel(Builder $query, int|string $level): void
    {
        $level === 'egresado' ? $query->where('graduated', true) : $query->where('study_year', $level)->where('graduated', false);
    }

    public function taughtOfferings(): HasMany
    {
        return $this->hasMany(Offering::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function nfcCredentials(): HasMany
    {
        return $this->hasMany(NfcCredential::class);
    }
}
