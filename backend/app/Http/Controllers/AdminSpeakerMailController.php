<?php

namespace App\Http\Controllers;

use App\Mail\SpeakerBroadcast;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminSpeakerMailController extends Controller
{
    public function send(Request $request, AdminInviteController $names): JsonResponse
    {
        $validated = $request->validate([
            'subject_de' => ['required', 'string', 'max:200'],
            'subject_fr' => ['required', 'string', 'max:200'],
            'body_de'    => ['required', 'string', 'max:5000'],
            'body_fr'    => ['required', 'string', 'max:5000'],
        ]);

        $replyTo = AppSetting::get('event_manager_email', 'admin@example.com');
        $count = 0;

        User::where('role', User::ROLE_CONSULTANT)
            ->whereNotNull('email')
            ->with('consultantProfile')
            ->get()
            ->each(function (User $user) use ($validated, $names, $replyTo, &$count) {
                $profile = $user->consultantProfile;
                $language = $profile?->language === 'fr' ? 'fr' : 'de';

                $name = $profile && $profile->salutation && $profile->last_name
                    ? $names->nameForPlaceholder($profile->salutation, $profile->last_name, $language)
                    : $user->name;

                $body = str_replace('$NAME', $name, $validated['body_' . $language]);

                Mail::to($user->email)->send(
                    new SpeakerBroadcast($validated['subject_' . $language], $body, $language, $replyTo)
                );
                $count++;
            });

        return response()->json(['sent_count' => $count]);
    }
}
