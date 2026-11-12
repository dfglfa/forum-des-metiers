<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ConsultantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Validation\Rule;

class ConsultantProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('consultantProfile');

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'profile' => $user->consultantProfile,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        try {
            return $this->performUpdate($request);
        } catch (ValidationException $e) {
            Log::warning('Consultant profile validation failed', [
                'user_id' => $request->user()?->id,
                'errors' => $e->errors(),
                'input' => $request->except(['profile_picture', 'career_path', 'current_situation', 'why_this_career']),
            ]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('Consultant profile update failed: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e,
                'input' => $request->except(['profile_picture', 'career_path', 'current_situation', 'why_this_career']),
            ]);
            throw $e;
        }
    }

    private function performUpdate(Request $request): JsonResponse
    {
        $this->normalizeInput($request);

        $graduationYearMin = (int) AppSetting::get('graduation_year_min', 1990);
        $graduationYearMax = AppSetting::graduationYearMax();

        $validated = $request->validate([
            'first_name'       => ['nullable', 'string', 'max:100'],
            'last_name'        => ['nullable', 'string', 'max:100'],
            'phone'            => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-().\/ ]+$/'],
            'graduation_year'  => ['nullable', 'integer', "min:{$graduationYearMin}", "max:{$graduationYearMax}"],
            'serie'            => ['nullable', Rule::exists('series', 'name')],
            'language'         => ['nullable', Rule::in(AdminInviteController::LANGUAGES)],
            'linkedin_url'     => ['nullable', 'regex:#^https?://\S+$#iu', 'max:255'],
            'career_path'        => ['nullable', 'string'],
            'current_situation'  => ['nullable', 'string'],
            'why_this_career'    => ['nullable', 'string'],
            'consent_poster'     => ['nullable', 'boolean'],
            'consent_alumni_data'=> ['nullable', 'boolean'],
            'profile_picture'    => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $data = collect($validated)->except('profile_picture')->toArray();

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture_path'] = $request->file('profile_picture')
                ->store('profile-pictures', 'public');
        }

        $profile = ConsultantProfile::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return response()->json($profile->fresh());
    }

    /**
     * Tidy user input: collapse whitespace in phone numbers and make
     * LinkedIn URLs valid even when entered without a scheme.
     */
    private function normalizeInput(Request $request): void
    {
        if ($request->has('phone')) {
            $phone = preg_replace('/\s+/u', ' ', trim((string) $request->input('phone')));
            $request->merge(['phone' => $phone === '' ? null : $phone]);
        }

        if ($request->has('linkedin_url')) {
            $url = trim((string) $request->input('linkedin_url'));
            if ($url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
                $url = 'https://'.ltrim($url, '/');
            }
            $request->merge(['linkedin_url' => $url === '' ? null : $url]);
        }
    }
}
