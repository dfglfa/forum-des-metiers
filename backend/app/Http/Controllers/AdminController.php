<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function students(): JsonResponse
    {
        return response()->json(
            User::where('role', User::ROLE_STUDENT)->get()
        );
    }

    public function tags(): JsonResponse
    {
        return response()->json(
            Tag::orderBy('name')->get()
        );
    }

    public function consultants(): JsonResponse
    {
        return response()->json(
            User::where('role', User::ROLE_CONSULTANT)
                ->with(['consultantProfile', 'topics.tag'])
                ->orderByRaw('email_verified_at IS NOT NULL')
                ->orderBy('created_at')
                ->get()
        );
    }

    public function consultant(int $id): JsonResponse
    {
        $consultant = User::where('role', User::ROLE_CONSULTANT)
            ->with(['consultantProfile', 'topics.tag'])
            ->findOrFail($id);

        return response()->json($consultant);
    }

    public function topics(): JsonResponse
    {
        return response()->json(
            Topic::with(['tag', 'consultant.consultantProfile'])->get()
        );
    }

    public function destroyConsultant(int $id): JsonResponse
    {
        $consultant = User::where('role', User::ROLE_CONSULTANT)
            ->with('consultantProfile')
            ->findOrFail($id);

        DB::transaction(function () use ($consultant) {
            $profile = $consultant->consultantProfile;

            if ($profile?->profile_picture_path) {
                Storage::disk('public')->delete($profile->profile_picture_path);
            }
            if ($profile?->cv_path) {
                Storage::disk('public')->delete($profile->cv_path);
            }

            // consultant_profiles, topics, time_slots and student_schedules all have
            // cascadeOnDelete foreign keys back to users.id, so this one delete removes
            // the whole dependency chain (profile, talk/topic, slots, student bookings).
            $consultant->delete();
        });

        return response()->json(['message' => __('messages.consultant_deleted')]);
    }
}
