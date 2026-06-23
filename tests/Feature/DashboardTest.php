<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('saas admins are redirected from dashboard to the saas area', function () {
    $admin = User::factory()->create(['is_saas_admin' => true]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('saas.dashboard'));
});

test('company admins are redirected from dashboard to the admin area', function () {
    $user = companyAdmin();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});
