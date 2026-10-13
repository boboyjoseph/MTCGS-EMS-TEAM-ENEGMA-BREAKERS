<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_only_their_own_notification(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstUser->notify(new SystemNotification('First user', 'Private notice', 'info'));
        $secondUser->notify(new SystemNotification('Second user', 'Keep this notice', 'info'));

        $firstNotificationId = $firstUser->notifications()->firstOrFail()->id;
        $secondNotificationId = $secondUser->notifications()->firstOrFail()->id;

        $this->actingAs($firstUser)
            ->delete(route('notifications.destroy', $firstNotificationId))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $firstNotificationId]);

        $this->delete(route('notifications.destroy', $secondNotificationId))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $secondNotificationId]);
    }

    public function test_notification_actions_return_json_response_for_ajax_requests(): void
    {
        $user = User::factory()->create();
        $user->notify(new SystemNotification('Hourly update', 'Needs review', 'info', route('dashboard')));

        $notificationId = $user->notifications()->firstOrFail()->id;

        $this->actingAs($user)
            ->getJson(route('notifications.read', $notificationId))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification marked as read.');

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'All notifications marked as read.');

        $user->notify(new SystemNotification('New alert', 'Delete me', 'warning', route('dashboard')));
        $newNotificationId = $user->notifications()->latest()->firstOrFail()->id;

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy', $newNotificationId))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification deleted successfully.');
    }
}