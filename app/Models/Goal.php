<?php

namespace App\Models;

use App\Enums\Muscle;
use Carbon\CarbonImmutable;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated version of the owner's Goal for a Muscle. The Goal in force in a Week is the latest version effective on or before it.
 *
 * @property int $id
 * @property int $user_id
 * @property Muscle $muscle
 * @property string|null $weekly_minimum The weekly minimum Volume in kg; empty when this version removes the Goal
 * @property CarbonImmutable $effective_week The Monday of the Week this version takes effect
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['muscle', 'weekly_minimum', 'effective_week'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'muscle' => Muscle::class,
            'weekly_minimum' => 'decimal:2',
            'effective_week' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
