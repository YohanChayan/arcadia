<?php

use App\Models\Role;
use App\Models\User;

/*
 * A save in Settings is confirmed beside its button, through the session's `status` — the same in
 * the owner's editor shell and a reader's, neither of which the kit's toast reached.
 */

test('saving the profile says so', function () {
    $user = User::factory()->withRole(Role::ALIAS_READER)->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ana', 'email' => $user->email])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-updated');

    $this->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('status', 'profile-updated'));
});

test('changing the password says so', function () {
    $user = User::factory()->withRole(Role::ALIAS_READER)->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'password-updated');

    $this->get(route('security.edit'))->assertInertia(fn ($page) => $page->where('status', 'password-updated'));
});
