<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimrsSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiSimrsSlideController extends Controller
{
    /** Daftar slide SIMRS yang aktif */
    public function index(): JsonResponse
    {
        $slides = SimrsSlide::active()->ordered()->get()->map(fn($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'href' => $s->href,
            'sort_order' => $s->sort_order,
            'image_url' => route('api.v1.simrs.slides.image', ['id' => $s->id]),
        ]);

        return response()->json([
            'success' => true,
            'data' => $slides,
            'total' => $slides->count(),
        ]);
    }

    /** Stream gambar slide ke klien */
    public function image(string $id): StreamedResponse|JsonResponse
    {
        $slide = SimrsSlide::active()->find($id);

        if (!$slide || !$slide->file_path) {
            return response()->json(['message' => 'Slide tidak ditemukan'], 404);
        }

        $disk = Storage::disk('simrs_slides');
        $fullPath = $disk->path($slide->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File tidak tersedia di server'], 500);
        }

        return response()->stream(function () use ($fullPath) {
            $stream = fopen($fullPath, 'rb');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $slide->mime_type,
            'Content-Length' => $slide->file_size,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
