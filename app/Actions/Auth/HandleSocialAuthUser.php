<?php

namespace App\Actions\Auth;

use App\Models\AuthProvider;
use App\Models\User;
use App\Models\UserAuthProvider;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class HandleSocialAuthUser
{
    /**
     * Handle OAuth user authentication with comprehensive scenario coverage:
     *
     * Scenarios:
     * 1. Existing OAuth connection → Update tokens and user info
     * 2. New OAuth for existing user (by email) → Link OAuth to existing user
     * 3. New user with email → Create user + OAuth provider
     * 4. New user without email → Create user with NULL email + OAuth provider
     * 5. Multiple OAuth providers per user → Allow linking
     */
    public function handle(SocialiteUser $socialUser, AuthProvider $authProvider): User
    {
        $oauthUserId = $socialUser->getId();
        $userEmail = $socialUser->getEmail();

        // Scenario 1: Check if this OAuth provider connection already exists
        $userAuthProvider = UserAuthProvider::with('user')
            ->forProvider($authProvider->id, $oauthUserId)
            ->first();

        if ($userAuthProvider) {
            // logger('@existingOAuthConnection', [
            //     'provider' => $authProvider->alias,
            //     'oauth_user_id' => $oauthUserId,
            //     'user_id' => $userAuthProvider->user_id,
            // ]);

            return $this->updateExistingOAuthUser($socialUser, $userAuthProvider);
        }

        // Scenario 2 & 3: Check if user exists by email (if email provided)
        if ($userEmail) {
            $existingUser = User::where('email', $userEmail)->first();

            if ($existingUser) {
                // logger('@linkingOAuthToExistingUser', [
                //     'provider' => $authProvider->alias,
                //     'user_id' => $existingUser->id,
                //     'email' => $userEmail,
                // ]);

                return $this->linkOAuthToExistingUser($socialUser, $authProvider, $existingUser);
            }
        }

        // Scenario 4 & 5: Create new user (with or without email)
        // logger('@creatingNewUserWithOAuth', [
        //     'provider' => $authProvider->alias,
        //     'has_email' => !empty($userEmail),
        //     'email' => $userEmail,
        // ]);

        return $this->createNewUser($socialUser, $authProvider);
    }

    /**
     * Update existing OAuth user - refresh tokens and user info
     */
    protected function updateExistingOAuthUser(SocialiteUser $socialUser, UserAuthProvider $userAuthProvider): User
    {
        $user = $userAuthProvider->user;

        // Update user info if OAuth provides better data
        $updateData = [
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $user->name,
        ];

        // Only update email if OAuth provides one and user doesn't have one
        if ($socialUser->getEmail() && ! $user->email) {
            $updateData['email'] = $socialUser->getEmail();
            $updateData['email_verified_at'] = now();
        }

        $user->update($updateData);

        // Update OAuth provider tokens and info
        $userAuthProvider->update([
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $this->calculateExpiresAt($socialUser),
            'avatar' => $socialUser->getAvatar(),
            'nickname' => $socialUser->getNickname(),
            'scopes' => $socialUser->approvedScopes ?? [],
        ]);

        // logger('@updatedExistingOAuthUser', [
        //     'user_id' => $user->id,
        //     'provider_id' => $userAuthProvider->provider_id,
        // ]);

        return $user;
    }

    /**
     * Link OAuth provider to existing user (found by email)
     */
    protected function linkOAuthToExistingUser(SocialiteUser $socialUser, AuthProvider $authProvider, User $existingUser): User
    {
        $oauthUserId = $socialUser->getId();

        // Check if this provider is already linked (shouldn't happen, but safety check)
        $existingLink = UserAuthProvider::where('user_id', $existingUser->id)
            ->where('provider_id', $authProvider->id)
            ->first();

        if ($existingLink) {
            // Update existing link
            $existingLink->update([
                'provider_id_from_oauth' => $oauthUserId,
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => $this->calculateExpiresAt($socialUser),
                'avatar' => $socialUser->getAvatar(),
                'nickname' => $socialUser->getNickname(),
                'scopes' => $socialUser->approvedScopes ?? [],
            ]);
        } else {
            // Create new OAuth link
            $existingUser->authProviders()->create([
                'provider_id' => $authProvider->id,
                'provider_id_from_oauth' => $oauthUserId,
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => $this->calculateExpiresAt($socialUser),
                'avatar' => $socialUser->getAvatar(),
                'nickname' => $socialUser->getNickname(),
                'scopes' => $socialUser->approvedScopes ?? [],
            ]);
        }

        // Update user info if better data available
        $existingUser->update([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $existingUser->name,
            'email_verified_at' => $existingUser->email_verified_at ?? now(),
        ]);

        // logger('@linkedOAuthToExistingUser', [
        //     'user_id' => $existingUser->id,
        //     'provider' => $authProvider->alias,
        // ]);

        return $existingUser;
    }

    /**
     * Create new user with OAuth provider
     * Handles both cases: with email and without email
     */
    protected function createNewUser(SocialiteUser $socialUser, AuthProvider $authProvider): User
    {
        $oauthUserId = $socialUser->getId();
        $userEmail = $socialUser->getEmail();

        // Generate name from available data
        $name = $socialUser->getName()
            ?? $socialUser->getNickname()
            ?? 'User_'.Str::random(8);

        // Create user with or without email
        $userData = [
            'name' => $name,
            'email' => $userEmail, // Can be NULL
            'password' => bcrypt(Str::random(32)), // Random password for OAuth users
        ];

        // Only set email_verified_at if email is provided
        if ($userEmail) {
            $userData['email_verified_at'] = now();
        }

        $user = User::create($userData);

        // Create OAuth provider link
        $user->authProviders()->create([
            'provider_id' => $authProvider->id,
            'provider_id_from_oauth' => $oauthUserId,
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $this->calculateExpiresAt($socialUser),
            'avatar' => $socialUser->getAvatar(),
            'nickname' => $socialUser->getNickname(),
            'scopes' => $socialUser->approvedScopes ?? [],
        ]);

        // logger('@createdNewUser', [
        //     'user_id' => $user->id,
        //     'provider' => $authProvider->alias,
        //     'has_email' => !empty($userEmail),
        // ]);

        return $user;
    }

    /**
     * Calculate token expiration timestamp
     */
    protected function calculateExpiresAt(SocialiteUser $socialUser): ?string
    {
        return $socialUser->expiresIn
            ? now()->addSeconds($socialUser->expiresIn)
            : null;
    }
}
