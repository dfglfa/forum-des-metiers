<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'ldap_consultants' => AppSetting::getBool('ldap_consultants'),
            'ldap_students' => AppSetting::getBool('ldap_students', true),
            'current_phase' => AppSetting::currentPhase(),
            'max_tag_choices' => (int) AppSetting::get('max_tag_choices', 6),
            'assigned_tags_count' => (int) AppSetting::get('assigned_tags_count', 4),
            'event_manager_email' => AppSetting::get('event_manager_email', 'admin@example.com'),
            'event_title' => [
                'de' => AppSetting::get('event_title_de', 'Forum der Berufe'),
                'fr' => AppSetting::get('event_title_fr', 'Forum des métiers'),
            ],
            'event_datetime' => AppSetting::get('event_datetime'),
            'event_location' => AppSetting::get('event_location'),
            'selection_phase_start' => AppSetting::get('selection_phase_start'),
            'conference_phase_start' => AppSetting::get('conference_phase_start'),
            'event_logo_url' => ($path = AppSetting::get('event_logo_path')) ? '/storage/' . $path : null,
            'event_favicon_url' => ($faviconPath = AppSetting::get('event_favicon_path'))
                ? '/storage/' . $faviconPath . '?v=' . (Storage::disk('public')->exists($faviconPath) ? Storage::disk('public')->lastModified($faviconPath) : 0)
                : null,
            'graduation_year_range' => [
                'min' => (int) AppSetting::get('graduation_year_min', 1990),
                'max' => AppSetting::graduationYearMax(),
            ],
        ]);
    }
}
