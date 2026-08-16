<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class StudentTagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Tag::withCount('topics')
                ->with(['topics:id,title,tag_id,consultant_id', 'topics.consultant:id,name'])
                ->orderBy('name')
                ->get()
        );
    }
}
