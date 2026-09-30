<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_page_shows_the_saved_bodyweight_and_timezone_among_the_iana_timezones()
    {
        $user = User::factory()->withBodyweight('82.50')->create(['timezone' => 'America/New_York']);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.bodyweight', '82.50')
            ->where('auth.user.timezone', 'America/New_York')
            ->where('timezones', fn ($timezones) => collect($timezones)->contains('America/New_York'))
        );
    }

    public function test_bodyweight_and_timezone_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'bodyweight' => '82.5',
                'timezone' => 'America/New_York',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('82.50', $user->bodyweight);
        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_bodyweight_can_be_cleared()
    {
        $user = User::factory()->withBodyweight('80.00')->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'bodyweight' => '',
                'timezone' => 'Europe/Amsterdam',
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->bodyweight);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidBodyweights(): array
    {
        return [
            'not a number' => ['heavy', 'The bodyweight field must be a number.'],
            'zero' => ['0', 'The bodyweight field must be greater than 0.'],
            'negative' => ['-70', 'The bodyweight field must be greater than 0.'],
            'more than two decimals' => ['80.125', 'The bodyweight field must have 0-2 decimal places.'],
            'too heavy to store' => ['1000', 'The bodyweight field must not be greater than 999.99.'],
        ];
    }

    #[DataProvider('invalidBodyweights')]
    public function test_invalid_bodyweight_is_rejected(mixed $bodyweight, string $message)
    {
        $user = User::factory()->withBodyweight('80.00')->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'bodyweight' => $bodyweight,
                'timezone' => 'Europe/Amsterdam',
            ]);

        $response
            ->assertSessionHasErrors(['bodyweight' => $message])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('80.00', $user->refresh()->bodyweight);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidTimezones(): array
    {
        return [
            'missing' => ['', 'The timezone field is required.'],
            'not an iana identifier' => ['Mars/Olympus', 'The timezone field must be a valid timezone.'],
            'utc offset' => ['+02:00', 'The timezone field must be a valid timezone.'],
        ];
    }

    #[DataProvider('invalidTimezones')]
    public function test_invalid_timezone_is_rejected(string $timezone, string $message)
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $timezone,
            ]);

        $response
            ->assertSessionHasErrors(['timezone' => $message])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Europe/Amsterdam', $user->refresh()->timezone);
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'timezone' => 'Europe/Amsterdam',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'timezone' => 'Europe/Amsterdam',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
