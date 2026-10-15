<?php

namespace App\Http\Controllers;

use App\Mail\SpeakerInvitation;
use App\Models\AppSetting;
use App\Models\ConsultantProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminInviteController extends Controller
{
    public const SALUTATIONS = [
        'Herr',
        'Frau',
        '(ohne)',
        'Herr Dr.',
        'Frau Dr.',
        'Dr.',
        'Herr Prof. Dr.',
        'Frau Prof. Dr.',
        'Prof. Dr.',
    ];

    public const LANGUAGES = ['de', 'fr'];

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salutation'      => ['required', 'string', Rule::in(self::SALUTATIONS)],
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email',
            'language'        => ['required', 'string', Rule::in(self::LANGUAGES)],
            'invitation_body' => 'required|string|max:3000',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json(['warning' => __('messages.already_invited')]);
        }

        $this->createAndInviteSpeaker(
            $validated['salutation'],
            $validated['first_name'],
            $validated['last_name'],
            $validated['email'],
            $validated['language'],
            $validated['invitation_body'],
        );

        return response()->json(['message' => __('messages.invitation_sent')]);
    }

    public function bulkInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'csv'                => ['required', 'file', 'mimes:csv,txt'],
            'invitation_body_de' => ['required', 'string', 'max:3000'],
            'invitation_body_fr' => ['required', 'string', 'max:3000'],
        ]);

        $invited = [];
        $skipped = [];

        foreach ($this->parseCsv($validated['csv']) as $row) {
            [$salutation, $firstName, $lastName, $email, $language] = $row;

            $rowValidator = Validator::make(
                ['salutation' => $salutation, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email],
                [
                    'salutation' => ['required', 'string', Rule::in(self::SALUTATIONS)],
                    'first_name' => ['required', 'string', 'max:100'],
                    'last_name'  => ['required', 'string', 'max:100'],
                    'email'      => ['required', 'email'],
                ]
            );

            if ($rowValidator->fails()) {
                $skipped[] = ['email' => $email, 'reason' => implode(' ', $rowValidator->errors()->all())];

                continue;
            }

            if (User::where('email', $email)->exists()) {
                $skipped[] = ['email' => $email, 'reason' => __('messages.already_invited')];

                continue;
            }

            $invitationBody = $language === 'fr' ? $validated['invitation_body_fr'] : $validated['invitation_body_de'];

            $this->createAndInviteSpeaker($salutation, $firstName, $lastName, $email, $language, $invitationBody);
            $invited[] = $email;
        }

        return response()->json([
            'invited_count' => count($invited),
            'invited'       => $invited,
            'skipped'       => $skipped,
        ]);
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];

        fgetcsv($handle); // skip header row (salutation, firstname, lastname, email, language)

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $language = strtolower(trim($row[4] ?? ''));

            $rows[] = [
                trim($row[0] ?? ''),
                trim($row[1] ?? ''),
                trim($row[2] ?? ''),
                trim($row[3] ?? ''),
                in_array($language, self::LANGUAGES, true) ? $language : 'de',
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** French equivalents of the (German-keyed) stored salutations, used for $NAME in French invitations. */
    public const SALUTATIONS_FR = [
        'Herr'           => 'Monsieur',
        'Frau'           => 'Madame',
        'Herr Dr.'       => 'Monsieur Dr',
        'Frau Dr.'       => 'Madame Dr',
        'Dr.'            => 'Dr',
        'Herr Prof. Dr.' => 'Monsieur Prof. Dr',
        'Frau Prof. Dr.' => 'Madame Prof. Dr',
        'Prof. Dr.'      => 'Prof. Dr',
    ];

    public function nameForPlaceholder(string $salutation, string $lastName, string $language = 'de'): string
    {
        if ($salutation === '(ohne)') {
            return $lastName;
        }

        if ($language === 'fr') {
            $salutation = self::SALUTATIONS_FR[$salutation] ?? $salutation;
        }

        return $salutation . ' ' . $lastName;
    }

    private function createAndInviteSpeaker(string $salutation, string $firstName, string $lastName, string $email, string $language, string $invitationBody): void
    {
        $user = User::create([
            'name'     => $firstName . ' ' . $lastName,
            'email'    => $email,
            'role'     => User::ROLE_CONSULTANT,
            'password' => Hash::make(Str::random(32)),
        ]);

        ConsultantProfile::create([
            'user_id'    => $user->id,
            'salutation' => $salutation,
            'language'   => $language,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        $token = Password::createToken($user);
        $link  = env('FRONTEND_URL', 'http://localhost:5173')
            . '/set-password?token=' . $token
            . '&email=' . urlencode($user->email)
            . '&lang=' . $language;

        $personalizedBody = str_replace('$NAME', $this->nameForPlaceholder($salutation, $lastName, $language), $invitationBody);
        $replyTo = AppSetting::get('event_manager_email', 'admin@example.com');

        Mail::to($user->email)->send(new SpeakerInvitation($firstName, $personalizedBody, $link, $language, $replyTo));
    }
}
