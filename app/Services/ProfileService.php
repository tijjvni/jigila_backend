<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function update(User $user, array $data): User
    {
        unset($data['current_password']);

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            if (app()->isLocal()) {
                $user->markEmailAsVerified();
            } else {
                $user->sendEmailVerificationNotification();
            }
        }

        return $user;
    }
}
