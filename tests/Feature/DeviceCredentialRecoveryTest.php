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
