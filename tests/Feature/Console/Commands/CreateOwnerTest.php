<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_verified_owner_from_options()
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Joep',
            '--email' => 'owner@example.com',
            '--password' => 'secret-password',
        ])
            ->expectsOutputToContain('Owner account created for owner@example.com.')
            ->assertSuccessful();

        $owner = User::sole();
        $this->assertSame('Joep', $owner->name);
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertTrue(Hash::check('secret-password', $owner->password));
        $this->assertTrue($owner->hasVerifiedEmail());
    }

    public function test_new_owner_has_the_amsterdam_timezone_and_no_bodyweight()
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Joep',
            '--email' => 'owner@example.com',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $owner = User::sole();
        $this->assertSame('Europe/Amsterdam', $owner->timezone);
        $this->assertNull($owner->bodyweight);
    }

    public function test_prompts_for_values_that_are_not_given_as_options()
    {
        $this->artisan('app:create-owner')
            ->expectsQuestion('Name', 'Joep')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->assertSuccessful();

        $this->assertSame('owner@example.com', User::sole()->email);
    }

    public function test_refuses_an_email_address_that_is_already_taken()
    {
        $existing = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('app:create-owner', [
            '--name' => 'Someone Else',
            '--email' => 'owner@example.com',
            '--password' => 'secret-password',
        ])
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->name, $existing->fresh()->name);
    }
}
