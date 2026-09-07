<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
        ]);

        $organizer = User::factory()->organizer()->create([
            'name' => 'Event Organizer',
            'email' => 'organizer@example.com',
        ]);

        $participant = User::factory()->participant()->create([
            'name' => 'Sample Participant',
            'email' => 'participant@example.com',
        ]);

        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Tech Summit 2026',
            'slug' => 'tech-summit-2026',
            'description' => 'The premier tech summit exploring AI, cloud architecture, and modern web development.',
            'location' => 'Grand Convention Center, Hall A',
            'start_date' => now()->addDays(14)->setTime(9, 0),
            'end_date' => now()->addDays(14)->setTime(17, 0),
        ]);

        $generalTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'description' => 'Full day access to keynotes and exhibition hall.',
            'price' => 50.00,
            'quota' => 200,
        ]);

        $vipTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Pass',
            'description' => 'Keynotes, VIP lounge, lunch, and networking dinner.',
            'price' => 150.00,
            'quota' => 50,
        ]);

        $registration = Registration::factory()->confirmed()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $generalTicket->id,
            'registration_code' => 'EVENT-REG-DEMO1234',
        ]);

        CheckIn::factory()->create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);
    }
}
