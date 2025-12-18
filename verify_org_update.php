<?php

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Mock context if needed, or just rely on existing tenant context if running via artisan tinker or similar
// For this script, we'll try to simulate a request or just inspect the resource manually.

$tenant = Tenant::first();
if (!$tenant) {
    echo "No tenant found.\n";
    exit;
}

tenancy()->initialize($tenant);

$user = User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}
Auth::login($user);

// 1. Test Creation Validation
$validator = Illuminate\Support\Facades\Validator::make([
    'name' => 'Test Org',
    'short_name' => 'TO',
    'phone' => '1234567890',
], (new App\Http\Requests\StoreOrganizationRequest())->rules());

if ($validator->fails()) {
    echo "Store Validation Failed:\n";
    print_r($validator->errors()->all());
} else {
    echo "Store Validation Passed.\n";
}

// 2. Test Resource Output
$org = Organization::first();
if (!$org) {
    // Create one if none exists
    $org = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Org',
        'short_name' => 'TestShort',
        'phone' => '555-0199',
    ]);
} else {
    // Update existing one to ensure fields are populated
    $org->update([
        'short_name' => 'ExistingShort',
        'phone' => '555-0100'
    ]);
}

$resource = new App\Http\Resources\OrganizationResource($org);
$json = $resource->response()->getData(true);

echo "Resource Output:\n";
print_r($json);

if (isset($json['data']['short_name']) && isset($json['data']['phone'])) {
    echo "SUCCESS: short_name and phone are present.\n";
} else {
    echo "FAILURE: short_name or phone missing.\n";
}
