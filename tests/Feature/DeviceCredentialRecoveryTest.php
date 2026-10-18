<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceCredentialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_device_for_a_new_branch_shows_its_unique_credential_once(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-NEW-DEVICE',
            'branch_name' => 'New Branch',
            'address' => 'New Branch Address',
        ]);
        $admin = User::create([
            'name' => 'New Branch Device Admin',
            'email' => 'new.branch.device.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.devices.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:81',
            'serial_number' => 'NEW-BRANCH-SCANNER-01',
            'laptop_mac_address' => 'NEW-BRANCH-DEVICE-01',
            'device_name' => 'New Branch Scanner',
            'device_type' => 'biometric_scanner',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $device = Device::where('serial_number', 'NEW-BRANCH-SCANNER-01')->firstOrFail();
        $this->assertSame($branch->id, $device->branch_id);
        $this->assertSame('AABBCCDDEE81', Device::normalizeMacAddress($device->mac_address));
        $this->assertTrue((bool) $device->api_secret);

        $response->assertRedirect(route('admin.devices.show', $device))
            ->assertSessionHas('new_device_credential', $device->api_secret);

        $this->actingAs($admin)
            ->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertSee('Save this device credential now.')
            ->assertSee($device->api_secret);

        $this->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertDontSee($device->api_secret);
    }

    public function test_device_details_can_recover_when_stored_credential_cannot_be_decrypted(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-CREDENTIAL-RECOVERY',
            'branch_name' => 'Credential Recovery Branch',
            'address' => 'Credential Recovery Address',
        ]);
        $admin = User::create([
            'name' => 'Credential Recovery Admin',
            'email' => 'credential.recovery.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-76',
            'serial_number' => 'SERIAL-CREDENTIAL-RECOVERY',
            'api_secret' => str_repeat('valid-secret-', 5),
            'device_name' => 'Credential Recovery Scanner',
            'device_type' => 'biometric_scanner',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        DB::table('devices')
            ->where('id', $device->id)
            ->update(['api_secret' => 'unreadable-legacy-ciphertext']);

        $this->actingAs($admin)
            ->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertSee('Cannot be decrypted')
            ->assertSee('Generate a new credential')
            ->assertSee('Rotate Credential');

        $rotation = $this->postJson(route('admin.devices.credential.rotate', $device))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['credential']);

        $newCredential = $rotation->json('credential');
        $this->assertSame($newCredential, $device->fresh()->api_secret);

        $this->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertSee('Provisioned')
            ->assertDontSee('Cannot be decrypted');
    }
}
