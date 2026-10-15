<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    private const READER_BOUND_SCANNER_SERIALS = ['J620E23640', 'J320E01822'];

    private function readerIdRules(?string $serialNumber): array
    {
        return [
            Rule::requiredIf(fn () => in_array(
                Device::normalizeSerialNumber($serialNumber),
                self::READER_BOUND_SCANNER_SERIALS,
                true
            )),
            'nullable',
            'string',
            'max:100',
        ];
    }

    private function authorizeDeviceManagement(): void
    {
        $user = Auth::user();
        
        // Only super admins can manage biometric devices
        if (!$user || $user->role !== 'admin' || $user->admin_type !== 'super_admin') {
            abort(403, 'Only super administrators can manage biometric devices.');
        }
    }

    public function index()
    {
        $this->authorizeDeviceManagement();

        $devices = Device::with('branch')->orderBy('device_name')->get();
        $branches = Branch::orderBy('branch_name')->get();

        return view('admin.devices.index', compact('devices', 'branches'));
    }

    public function statusData()
    {
        $this->authorizeDeviceManagement();

        return response()->json([
            'success' => true,
            'devices' => Device::query()
                ->orderBy('device_name')
                ->get(['id', 'status', 'last_used_at', 'updated_at'])
                ->map(fn (Device $device) => [
                    'id' => $device->id,
                    'status' => $device->status,
                    'last_used_at' => $device->last_used_at?->format('M d, Y h:i A') ?? 'Never',
                    'updated_at' => $device->updated_at?->toIso8601String(),
                ])
                ->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function create()
    {
        $this->authorizeDeviceManagement();

        $branches = Branch::orderBy('branch_name')->get();

        return view('admin.devices.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $this->authorizeDeviceManagement();

        $request->validate([
            'mac_address' => ['required', 'string', 'max:20', 'regex:/^(?:[A-Fa-f0-9]{2}[:-]?){5}[A-Fa-f0-9]{2}$/', 'unique:devices,mac_address'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:devices,serial_number'],
            'reader_id' => $this->readerIdRules($request->input('serial_number')),
            'wifi_mac_address' => ['nullable', 'string', 'max:20', 'regex:/^(?:[A-Fa-f0-9]{2}[:-]?){5}[A-Fa-f0-9]{2}$/'],
            'laptop_mac_address' => ['required', 'string', 'max:100', 'unique:devices,laptop_mac_address'],
            'allowed_mac_addresses' => ['nullable', 'array'],
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|in:biometric_scanner,kiosk,computer',
            'branch_id' => 'required|exists:branches,id',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'nullable|integer|min:1|max:10000',
            'location_check_enabled' => 'sometimes|boolean',
            'status' => 'required|in:active,inactive,maintenance',
            'notes' => 'nullable|string',
        ]);

        $allowedMacs = $request->input('allowed_mac_addresses', []);
        $normalizedAllowedMacs = [];
        foreach ((array) $allowedMacs as $mac) {
            $normalized = Device::normalizeMacAddress($mac);
            if ($normalized !== '') {
                $normalizedAllowedMacs[] = $normalized;
            }
        }
        $normalizedAllowedMacs = array_values(array_unique($normalizedAllowedMacs));

        $credential = Str::random(64);
        $device = Device::create([
            'mac_address' => Device::normalizeMacAddress($request->mac_address),
            'serial_number' => Device::normalizeSerialNumber($request->serial_number),
            'reader_id' => $request->filled('reader_id') ? trim($request->reader_id) : null,
            'api_secret' => $credential,
            'wifi_mac_address' => Device::normalizeMacAddress($request->mac_address),
            'laptop_mac_address' => $request->filled('laptop_mac_address') ? trim($request->laptop_mac_address) : null,
            'allowed_mac_addresses' => $normalizedAllowedMacs ? json_encode($normalizedAllowedMacs) : null,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type,
            'branch_id' => $request->branch_id,
            'location' => $request->location,
            'address' => $request->address,
            'latitude' => $request->filled('latitude') ? $request->latitude : null,
            'longitude' => $request->filled('longitude') ? $request->longitude : null,
            'radius_meters' => $request->input('radius_meters', 100),
            'location_check_enabled' => $request->boolean('location_check_enabled'),
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Device registered successfully.',
                'device' => $device->load('branch'),
            ], 201);
        }

        return redirect()->route('admin.devices.show', $device)
            ->with('success', 'Device registered successfully.')
            ->with('new_device_credential', $credential);
    }

    public function edit(Device $device)
    {
        $this->authorizeDeviceManagement();

        $branches = Branch::orderBy('branch_name')->get();

        return view('admin.devices.edit', compact('device', 'branches'));
    }

    public function show(Device $device)
    {
        $this->authorizeDeviceManagement();

        $device->load('branch');

        $credentialStatus = 'not_provisioned';
        if (filled($device->getRawOriginal('api_secret'))) {
            try {
                $credentialStatus = filled($device->api_secret)
                    ? 'provisioned'
                    : 'unreadable';
            } catch (DecryptException) {
                $credentialStatus = 'unreadable';
            }
        }

        return view('admin.devices.show', compact('device', 'credentialStatus'));
    }

    public function rotateCredential(Device $device)
    {
        $this->authorizeDeviceManagement();

        $credential = Str::random(64);
        DB::table('devices')
            ->where('id', $device->getKey())
            ->update([
                'api_secret' => Crypt::encryptString($credential),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Device credential rotated. The previous credential is no longer valid.',
            'credential' => $credential,
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $this->authorizeDeviceManagement();

        $request->validate([
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|in:biometric_scanner,kiosk,computer',
            'branch_id' => ['required', 'exists:branches,id'],
            'mac_address' => ['required', 'string', 'max:20', 'regex:/^(?:[A-Fa-f0-9]{2}[:-]?){5}[A-Fa-f0-9]{2}$/', Rule::unique('devices', 'mac_address')->ignore($device->id)],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('devices', 'serial_number')->ignore($device->id)],
            'reader_id' => $this->readerIdRules($request->input('serial_number', $device->serial_number)),
            'wifi_mac_address' => ['nullable', 'string', 'max:20', 'regex:/^(?:[A-Fa-f0-9]{2}[:-]?){5}[A-Fa-f0-9]{2}$/'],
            'laptop_mac_address' => ['required', 'string', 'max:100', Rule::unique('devices', 'laptop_mac_address')->ignore($device->id)],
            'allowed_mac_addresses' => ['nullable', 'array'],
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'nullable|integer|min:1|max:10000',
            'location_check_enabled' => 'sometimes|boolean',
            'status' => 'required|in:active,inactive,maintenance',
            'notes' => 'nullable|string',
        ]);

        $allowedMacs = $request->input('allowed_mac_addresses', []);
        $normalizedAllowedMacs = [];
        foreach ((array) $allowedMacs as $mac) {
            $normalized = Device::normalizeMacAddress($mac);
            if ($normalized !== '') {
                $normalizedAllowedMacs[] = $normalized;
            }
        }
        $normalizedAllowedMacs = array_values(array_unique($normalizedAllowedMacs));

        $device->update([
            'mac_address' => Device::normalizeMacAddress($request->mac_address),
            'serial_number' => $request->filled('serial_number') ? Device::normalizeSerialNumber($request->serial_number) : $device->serial_number,
            'reader_id' => $request->filled('reader_id') ? trim($request->reader_id) : null,
            'wifi_mac_address' => Device::normalizeMacAddress($request->mac_address),
            'laptop_mac_address' => $request->filled('laptop_mac_address') ? trim($request->laptop_mac_address) : $device->laptop_mac_address,
            'allowed_mac_addresses' => $normalizedAllowedMacs ? json_encode($normalizedAllowedMacs) : null,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type,
            'branch_id' => $request->branch_id,
            'location' => $request->location,
            'address' => $request->address,
            'latitude' => $request->filled('latitude') ? $request->latitude : null,
            'longitude' => $request->filled('longitude') ? $request->longitude : null,
            'radius_meters' => $request->input('radius_meters', $device->radius_meters ?? 100),
            'location_check_enabled' => $request->boolean('location_check_enabled'),
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Device updated successfully.',
                'device' => $device->fresh()->load('branch'),
            ]);
        }

        return redirect()->route('admin.devices.index')->with('success', 'Device updated successfully.');
    }

    public function branchDetails(Request $request, Branch $branch)
    {
        $this->authorizeDeviceManagement();

        return response()->json([
            'success' => true,
            'branch' => $branch->only(['id', 'branch_code', 'branch_name', 'address']),
        ]);
    }

    public function updateStatus(Request $request, Device $device)
    {
        $this->authorizeDeviceManagement();

        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,maintenance'],
        ]);

        $device->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Device status updated.',
                'device' => $device->fresh()->load('branch'),
            ]);
        }

        return back()->with('success', 'Device status updated.');
    }

    public function destroy(Request $request, Device $device)
    {
        $this->authorizeDeviceManagement();

        $device->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Device removed successfully.',
            ]);
        }

        return redirect()->route('admin.devices.index')->with('success', 'Device removed successfully.');
    }
}
