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
            ->assertSee('Download setup package')
            ->assertSee('mtcgs-biometric-device-setup')
            ->assertSee('ScannerSerialNumber')
            ->assertSee('windowsCredentialFile')
            ->assertSee($device->api_secret);

        $this->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertDontSee($device->api_secret);

        $this->actingAs($admin)
            ->get(route('admin.devices.edit', $device))
            ->assertOk()
            ->assertSee('name="branch_id" id="branch_id" class="form-select" required', false)
            ->assertSee('value="New Branch Address"', false)
            ->assertDontSee('San Nicolas, Iriga City, Camarines Sur')
            ->assertDontSee('13.433153')
            ->assertDontSee('123.411049');
    }

    public function test_deleting_a_device_clears_only_its_branch_fingerprint_templates(): void
    {
        $branch = Branch::create([
            'branch_code' => 'BR-DEVICE-RESET',
            'branch_name' => 'Device Reset Branch',
            'address' => 'Device Reset Address',
        ]);
        $otherBranch = Branch::create([
            'branch_code' => 'BR-DEVICE-RESET-OTHER',
            'branch_name' => 'Other Branch',
            'address' => 'Other Branch Address',
        ]);
        $admin = User::create([
            'name' => 'Device Reset Admin',
            'email' => 'device.reset.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'admin_type' => 'super_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_verified' => true,
            'id_verification_status' => 'approved',
        ]);
        $device = Device::create([
            'mac_address' => 'AA-BB-CC-DD-EE-32',
            'serial_number' => 'SERIAL-DEVICE-RESET',
            'api_secret' => str_repeat('device-reset-', 5),
            'device_name' => 'Device Reset Kiosk',
            'device_type' => 'biometric_scanner',
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $makeUser = function (string $suffix) use ($branch): User {
            return User::create([
                'name' => "Fingerprint {$suffix}",
                'email' => "fingerprint.{$suffix}@example.com",
                'password' => bcrypt('password'),
                'role' => 'employee',
                'branch_id' => $branch->id,
                'is_active' => true,
                'is_verified' => true,
                'id_verification_status' => 'approved',
            ]);
        };
        $now = now();

        DB::table('employee_profiles')->insert([
            'user_id' => $makeUser('employee')->id,
            'branch_id' => $branch->id,
            'employee_number' => 'RESET-EMPLOYEE',
            'first_name' => 'Employee',
            'last_name' => 'Fingerprint',
            'position' => 'Teacher',
            'date_hired' => $now->toDateString(),
            'fingerprint_template' => 'saved-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('admin_profiles')->insert([
            'user_id' => $makeUser('admin-profile')->id,
            'branch_id' => $branch->id,
            'employee_number' => 'RESET-ADMIN',
            'first_name' => 'Admin',
            'last_name' => 'Fingerprint',
            'fingerprint_template' => 'saved-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_profiles')->insert([
            'user_id' => $makeUser('finance')->id,
            'branch_id' => $branch->id,
            'employee_number' => 'RESET-FINANCE',
            'first_name' => 'Finance',
            'last_name' => 'Fingerprint',
            'fingerprint_template' => 'saved-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('branch_heads')->insert([
            'user_id' => $makeUser('branch-head')->id,
            'branch_id' => $branch->id,
            'employee_number' => 'RESET-BRANCH-HEAD',
            'first_name' => 'Branch',
            'last_name' => 'Head',
            'fingerprint_template' => 'saved-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('employees')->insert([
            'user_id' => $makeUser('legacy-employee')->id,
            'branch_id' => $branch->id,
            'employee_number' => 'RESET-LEGACY',
            'first_name' => 'Legacy',
            'last_name' => 'Employee',
            'fingerprint_template' => 'saved-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('employee_profiles')->insert([
            'user_id' => $makeUser('other-branch')->id,
            'branch_id' => $otherBranch->id,
            'employee_number' => 'OTHER-BRANCH-EMPLOYEE',
            'first_name' => 'Other',
            'last_name' => 'Branch',
            'position' => 'Teacher',
            'date_hired' => $now->toDateString(),
            'fingerprint_template' => 'keep-this-template',
            'is_fingerprint_registered' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.devices.destroy', $device))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('fingerprint_profiles_cleared', 5);

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
        foreach (['employee_profiles', 'admin_profiles', 'finance_profiles', 'branch_heads', 'employees'] as $table) {
            $this->assertDatabaseHas($table, [
                'branch_id' => $branch->id,
                'fingerprint_template' => null,
                'is_fingerprint_registered' => false,
            ]);
        }
        $this->assertDatabaseHas('employee_profiles', [
            'branch_id' => $otherBranch->id,
            'fingerprint_template' => 'keep-this-template',
            'is_fingerprint_registered' => true,
        ]);
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
