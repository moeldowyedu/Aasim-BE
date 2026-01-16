<?php

namespace Tests\Feature\Api;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_register_with_plan_and_billing_cycle()
    {
        // 1. Create a Subscription Plan
        $plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'type' => 'organization',
            'tier' => 'professional',
            'base_price' => 100,
            'final_price' => 100,
            'trial_days' => 30, // Custom trial duration
            'is_active' => true,
            'is_published' => true,
        ]);

        // 2. Prepare Payload
        $payload = [
            'fullName' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+1234567890',
            'country' => 'USA',
            'subdomain' => 'test-org-123',
            'organizationFullName' => 'Test Organization',
            'organizationShortName' => 'TestOrg',
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ];

        // 3. Send Request
        $response = $this->postJson('/api/v1/auth/register', $payload);

        // 4. Verification
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
            ]);

        // Verify User
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pending_verification', $user->status);

        // Verify Trial Duration (approximate check)
        // trial_ends_at should be roughly 30 days from now
        $this->assertTrue(
            $user->trial_ends_at->diffInDays(now()->addDays(30)) <= 1,
            'Trial end date should be 30 days from now'
        );

        // Verify Tenant
        $tenant = Tenant::where('plan_id', $plan->id)->first();
        $this->assertNotNull($tenant);
        $this->assertEquals($plan->id, $tenant->plan_id);
        $this->assertEquals('monthly', $tenant->billing_cycle);
        $this->assertEquals('test-org-123', $tenant->subdomain_preference);
    }

    public function test_registration_fails_with_invalid_plan()
    {
        $payload = [
            'fullName' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+1234567890',
            'country' => 'USA',
            'subdomain' => 'test-org-invalid',
            'organizationFullName' => 'Test Organization',
            'plan_id' => 'invalid-uuid', // Invalid
            'billing_cycle' => 'monthly',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }
}
