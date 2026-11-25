<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_times_are_required_and_saved_for_calendar_display(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);
        $date = today()->addDays(2)->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.calendar.store'), [
                'title' => 'School Program',
                'description' => 'Annual program',
                'event_date' => $date,
                'event_type' => 'activity',
                'start_time' => '09:00',
                'end_time' => '11:30',
            ])
            ->assertRedirect(route('calendar.index'));

        $event = CalendarEvent::query()->where('title', 'School Program')->firstOrFail();
        $this->assertSame('09:00', substr($event->start_time, 0, 5));
        $this->assertSame('11:30', substr($event->end_time, 0, 5));

        $this->actingAs($admin)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('9:00 AM - 11:30 AM');

        $this->actingAs($admin)
            ->get(route('calendar.calendar'))
            ->assertOk()
            ->assertSee($date.'T09:00')
            ->assertSee($date.'T11:30');
    }

    public function test_activity_time_range_must_be_valid_and_end_after_start(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.calendar.create'))
            ->post(route('admin.calendar.store'), [
                'title' => 'School Program',
                'event_date' => today()->addDays(2)->toDateString(),
                'event_type' => 'activity',
                'start_time' => '11:30',
                'end_time' => '09:00',
            ])
            ->assertRedirect(route('admin.calendar.create'))
            ->assertSessionHasErrors(['end_time']);

        $this->assertDatabaseMissing('calendar_events', ['title' => 'School Program']);
    }

    public function test_holiday_can_remain_an_all_day_event(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.calendar.store'), [
                'title' => 'School Holiday',
                'event_date' => today()->addDays(2)->toDateString(),
                'event_type' => 'holiday',
            ])
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'School Holiday',
            'start_time' => null,
            'end_time' => null,
        ]);
    }
}
