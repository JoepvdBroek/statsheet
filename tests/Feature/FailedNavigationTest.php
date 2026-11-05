<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * In production, a page change that fails on the server is answered as an Inertia response with the failure's
 * status, so the app shows its in-page error state instead of Inertia's raw-HTML modal. In development the modal
 * keeps Laravel's debug page.
 */
class FailedNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/failing/{status}', fn (int $status) => abort($status));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function failureStatuses(): array
    {
        return [
            'forbidden' => [403],
            'not found' => [404],
            'server error' => [500],
        ];
    }

    #[DataProvider('failureStatuses')]
    public function test_a_failed_inertia_visit_returns_an_inertia_response_with_its_status_in_production(int $status)
    {
        config(['app.debug' => false]);

        $response = $this->withHeaders($this->inertiaVisit())->get("/failing/{$status}");

        $response->assertStatus($status);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('props.status', $status);
    }

    public function test_opening_another_users_workout_returns_an_inertia_404_in_production()
    {
        config(['app.debug' => false]);
        $workout = Workout::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders($this->inertiaVisit())
            ->get(route('workouts.show', $workout));

        $response->assertNotFound();
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('props.status', 404);
    }

    public function test_a_failed_inertia_visit_returns_the_html_debug_page_in_development()
    {
        config(['app.debug' => true]);

        $response = $this->withHeaders($this->inertiaVisit())->get('/failing/500');

        $response->assertInternalServerError();
        $response->assertHeaderMissing('X-Inertia');
    }

    public function test_a_failed_full_page_load_keeps_the_html_error_page_in_production()
    {
        config(['app.debug' => false]);

        $response = $this->get('/failing/404');

        $response->assertNotFound();
        $response->assertHeaderMissing('X-Inertia');
    }

    /**
     * Headers of an Inertia visit from the app's current build, as the client sends them.
     *
     * @return array<string, string>
     */
    private function inertiaVisit(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
