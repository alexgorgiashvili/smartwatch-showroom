<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4', 'max:51200'],
        ]);

        $upload = $request->file('video');
        $path = $upload->store('videos/standalone', 'public');

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path),
            'path' => $path,
        ]);
    }
}

