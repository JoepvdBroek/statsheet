<?php

namespace App\Models;

use App\Enums\Muscle;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $bodyweight Current Bodyweight in kg, snapshotted into each Workout
 * @property string $timezone IANA identifier that decides where each Week starts and ends
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'bodyweight', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Europe/Amsterdam',
    ];

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
            'bodyweight' => 'decimal:2',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /**
     * @return HasMany<Routine, $this>
     */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    /**
     * @return HasMany<Workout, $this>
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /**
     * Every dated version of the owner's Goals.
     *
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * The owner's Weekly Reviews, one per Week.
     *
     * @return HasMany<WeeklyReview, $this>
     */
    public function weeklyReviews(): HasMany
    {
        return $this->hasMany(WeeklyReview::class);
    }

    /**
     * The weekly minimum in kg of each Muscle's Goal in force in the given Week: its latest version effective on or before it.
     * Muscles without a Goal, or whose Goal was removed, are left out.
     *
     * @param  CarbonImmutable  $week  A Week as WeekCalendar gives it
     * @return array<string, float>
     */
    public function goalsInForce(CarbonImmutable $week): array
    {
        return $this->goalsInForcePerWeek([$week])[$week->toDateString()];
    }

    /**
     * The weekly minimum in kg of each Muscle's Goal in force in each of the given Weeks, keyed by the Week's Monday date and then by Muscle.
     * Muscles without a Goal in a Week, or whose Goal was removed by then, are left out of that Week.
     *
     * @param  list<CarbonImmutable>  $weeks  Weeks as WeekCalendar gives them
     * @return array<string, array<string, float>>
     */
    public function goalsInForcePerWeek(array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }

        $mondays = array_map(fn (CarbonImmutable $week) => $week->toDateString(), $weeks);

        $versions = $this->goals()
            ->whereDate('effective_week', '<=', max($mondays))
            ->orderBy('effective_week')
            ->get();

        return collect($mondays)->mapWithKeys(fn (string $monday) => [
            $monday => $versions
                ->filter(fn (Goal $goal) => $goal->effective_week->toDateString() <= $monday)
                ->keyBy(fn (Goal $goal) => $goal->muscle->value)
                ->whereNotNull('weekly_minimum')
                ->map(fn (Goal $goal) => (float) $goal->weekly_minimum)
                ->all(),
        ])->all();
    }

    /**
     * Set or change a Muscle's Goal from this Week on. Earlier Weeks keep the Goal in force then.
     */
    public function setGoal(Muscle $muscle, string $weeklyMinimum): void
    {
        $this->putGoalInForceThisWeek($muscle, $weeklyMinimum);
    }

    /**
     * Remove a Muscle's Goal from this Week on. Earlier Weeks are still judged against it.
     */
    public function removeGoal(Muscle $muscle): void
    {
        $this->putGoalInForceThisWeek($muscle, null);
    }

    /**
     * Store this Week's version of a Muscle's Goal, replacing one set earlier this Week.
     * Nothing is stored when the Goal in force stays the same.
     */
    private function putGoalInForceThisWeek(Muscle $muscle, ?string $weeklyMinimum): void
    {
        $thisWeek = WeekCalendar::for($this)->currentWeek();
        $inForce = $this->goalsInForce($thisWeek)[$muscle->value] ?? null;

        if ($inForce === ($weeklyMinimum === null ? null : (float) $weeklyMinimum)) {
            return;
        }

        $effectiveWeek = $thisWeek->toDateString();

        $version = $this->goals()->where('muscle', $muscle)->whereDate('effective_week', $effectiveWeek)->first()
            ?? $this->goals()->make(['muscle' => $muscle, 'effective_week' => $effectiveWeek]);

        $version->weekly_minimum = $weeklyMinimum;
        $version->save();
    }
}
