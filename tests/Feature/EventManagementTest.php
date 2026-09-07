<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_url_renders_events_index(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_public_can_view_published_events(): void
    {
        $publishedEvent = Event::factory()->published()->create(['title' => 'Laravel Conference']);
        $draftEvent = Event::factory()->draft()->create(['title' => 'Secret Internal Draft']);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertSee('Laravel Conference');
        $response->assertDontSee('Secret Internal Draft');
    }

    public function test_public_can_search_published_events(): void
    {
        Event::factory()->published()->create(['title' => 'Vue Summit', 'location' => 'Chicago']);
        Event::factory()->published()->create(['title' => 'React Meetup', 'location' => 'Boston']);

        $response = $this->get('/events?search=Chicago');

        $response->assertStatus(200);
        $response->assertSee('Vue Summit');
        $response->assertDontSee('React Meetup');
    }

    public function test_public_can_view_published_event_details(): void
    {
        $event = Event::factory()->published()->create([
            'title' => 'AI World 2026',
            'description' => 'A deep dive into frontier AI.',
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertStatus(200);
        $response->assertSee('AI World 2026');
        $response->assertSee('A deep dive into frontier AI.');
    }

    public function test_guest_cannot_view_draft_event(): void
    {
        $draftEvent = Event::factory()->draft()->create();

        $response = $this->get(route('events.show', $draftEvent));

        $response->assertStatus(403);
    }

    public function test_organizer_can_view_their_own_draft_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $draftEvent = Event::factory()->draft()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get(route('events.show', $draftEvent));

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_when_accessing_organizer_routes(): void
    {
        $response = $this->get(route('organizer.events.index'));
        $response->assertRedirect('/login');
    }

    public function test_participant_cannot_access_organizer_event_management(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->get(route('organizer.events.index'));
        $response->assertStatus(403);

        $responseCreate = $this->actingAs($participant)->get(route('organizer.events.create'));
        $responseCreate->assertStatus(403);
    }

    public function test_organizer_can_view_their_managed_events(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        $event1 = Event::factory()->create(['organizer_id' => $organizer1->id, 'title' => 'Organizer 1 Event']);
        $event2 = Event::factory()->create(['organizer_id' => $organizer2->id, 'title' => 'Organizer 2 Event']);

        $response = $this->actingAs($organizer1)->get(route('organizer.events.index'));

        $response->assertStatus(200);
        $response->assertSee('Organizer 1 Event');
        $response->assertDontSee('Organizer 2 Event');
    }

    public function test_organizer_can_create_an_event_with_valid_data(): void
    {
        Storage::fake('public');
        $organizer = User::factory()->organizer()->create();

        $payload = [
            'title' => 'DevOps Con 2026',
            'description' => 'Containerization, k8s, and CI/CD pipelines.',
            'location' => 'Berlin Expo Center',
            'start_date' => now()->addDays(10)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(10)->addHours(4)->format('Y-m-d H:i'),
            'status' => EventStatus::Published->value,
            'banner_image' => UploadedFile::fake()->create('banner.jpg', 100, 'image/jpeg'),
        ];

        $response = $this->actingAs($organizer)->post(route('organizer.events.store'), $payload);

        $response->assertRedirect(route('organizer.events.index'));
        $this->assertDatabaseHas('events', [
            'organizer_id' => $organizer->id,
            'title' => 'DevOps Con 2026',
            'location' => 'Berlin Expo Center',
            'status' => EventStatus::Published->value,
        ]);

        $event = Event::where('title', 'DevOps Con 2026')->first();
        $this->assertNotNull($event->banner_image);
        Storage::disk('public')->assertExists($event->banner_image);
    }

    public function test_event_creation_validates_dates(): void
    {
        $organizer = User::factory()->organizer()->create();

        $payload = [
            'title' => 'Invalid Date Event',
            'start_date' => now()->addDays(10)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(9)->format('Y-m-d H:i'), // end before start!
            'status' => EventStatus::Draft->value,
        ];

        $response = $this->actingAs($organizer)->post(route('organizer.events.store'), $payload);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseMissing('events', ['title' => 'Invalid Date Event']);
    }

    public function test_organizer_can_update_their_own_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Original Title',
        ]);

        $payload = [
            'title' => 'Updated Event Title',
            'description' => 'Updated description content.',
            'location' => 'New Venue',
            'start_date' => now()->addDays(5)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(5)->addHours(3)->format('Y-m-d H:i'),
            'status' => EventStatus::Published->value,
        ];

        $response = $this->actingAs($organizer)->put(route('organizer.events.update', $event), $payload);

        $response->assertRedirect(route('organizer.events.index'));
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Event Title',
            'location' => 'New Venue',
        ]);
    }

    public function test_organizer_cannot_update_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Untouchable Event',
        ]);

        $payload = [
            'title' => 'Hacked Title',
            'start_date' => now()->addDays(5)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(5)->addHours(3)->format('Y-m-d H:i'),
            'status' => EventStatus::Published->value,
        ];

        $response = $this->actingAs($organizer2)->put(route('organizer.events.update', $event), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', ['title' => 'Hacked Title']);
    }

    public function test_organizer_can_delete_their_own_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->delete(route('organizer.events.destroy', $event));

        $response->assertRedirect(route('organizer.events.index'));
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_organizer_cannot_delete_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);

        $response = $this->actingAs($organizer2)->delete(route('organizer.events.destroy', $event));

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_admin_can_update_and_delete_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($admin)->put(route('organizer.events.update', $event), [
            'title' => 'Admin Moderated Event',
            'start_date' => now()->addDays(2)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),
            'status' => EventStatus::Cancelled->value,
        ]);

        $response->assertRedirect(route('organizer.events.index'));
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Admin Moderated Event',
            'status' => EventStatus::Cancelled->value,
        ]);

        $deleteResponse = $this->actingAs($admin)->delete(route('organizer.events.destroy', $event));
        $deleteResponse->assertRedirect(route('organizer.events.index'));
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_required_event_creation_fields_are_validated(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post(route('organizer.events.store'), []);

        $response->assertSessionHasErrors(['title', 'start_date', 'end_date', 'status']);
    }

    public function test_event_creation_rejects_invalid_status(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post(route('organizer.events.store'), [
            'title' => 'Invalid Status Event',
            'start_date' => now()->addDays(2)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),
            'status' => 'bogus_status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_participant_cannot_delete_event(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($participant)->delete(route('organizer.events.destroy', $event));

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_draft_events_behave_correctly(): void
    {
        $draftEvent = Event::factory()->draft()->create(['title' => 'Unpublished Draft']);

        $this->assertFalse($draftEvent->isPublished());
        $this->assertFalse($draftEvent->isRegistrationOpen());

        $catalogResponse = $this->get(route('events.index'));
        $catalogResponse->assertDontSee('Unpublished Draft');

        $detailResponse = $this->get(route('events.show', $draftEvent));
        $detailResponse->assertStatus(403);
    }

    public function test_published_events_behave_correctly(): void
    {
        $publishedEvent = Event::factory()->published()->create(['title' => 'Open Conference']);

        $this->assertTrue($publishedEvent->isPublished());
        $this->assertTrue($publishedEvent->isRegistrationOpen());

        $catalogResponse = $this->get(route('events.index'));
        $catalogResponse->assertSee('Open Conference');

        $detailResponse = $this->get(route('events.show', $publishedEvent));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Open Conference');
    }

    public function test_cancelled_events_behave_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
        ]);

        $this->assertTrue($event->isRegistrationOpen());

        // Organizer updates status to cancelled
        $response = $this->actingAs($organizer)->put(route('organizer.events.update', $event), [
            'title' => $event->title,
            'start_date' => $event->start_date->format('Y-m-d H:i'),
            'end_date' => $event->end_date->format('Y-m-d H:i'),
            'status' => EventStatus::Cancelled->value,
        ]);

        $response->assertRedirect(route('organizer.events.index'));
        $this->assertFalse($event->fresh()->isRegistrationOpen());
        $this->assertEquals(EventStatus::Cancelled, $event->fresh()->status);
    }

    public function test_completed_events_behave_correctly(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Completed]);

        $this->assertFalse($event->isPublished());
        $this->assertFalse($event->isRegistrationOpen());
        $this->assertEquals(EventStatus::Completed, $event->status);
    }

    public function test_validation_cannot_be_bypassed_via_direct_post(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->postJson(route('organizer.events.store'), [
            'title' => '',
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date',
            'status' => 'hack',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'start_date', 'end_date', 'status']);
    }

    public function test_authorization_is_enforced_server_side_on_event_creation(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->post(route('organizer.events.store'), [
            'title' => 'Participant Event',
            'start_date' => now()->addDays(1)->format('Y-m-d H:i'),
            'end_date' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i'),
            'status' => EventStatus::Published->value,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', ['title' => 'Participant Event']);
    }
}
