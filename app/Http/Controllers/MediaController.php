<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaController extends Controller
{
    public function show(Request $request, string $path)
    {
        $path = $this->sanitizePath($path);

        if (!Storage::disk('public')->exists($path)) {
            throw new NotFoundHttpException();
        }

        $filePath = Storage::disk('public')->path($path);
        $mimeType = $this->determineMimeType($request, $filePath);

        return ApiResponse::media()
            ->path($filePath)
            ->mimeType($mimeType)
            ->filename(basename($path))
            ->messageCode('common.ok')
            ->build();
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace('..', '', $path);
        $path = preg_replace('#/+#', '/', $path);
        return ltrim($path, '/');
    }

    private function determineMimeType(Request $request, string $filePath): string
    {
        $actualMimeType = mime_content_type($filePath);

        if (!$request->header('Accept')) {
            return $actualMimeType;
        }

        $acceptHeader = $request->header('Accept');
        $acceptedTypes = explode(',', $acceptHeader);

        foreach ($acceptedTypes as $type) {
            $type = trim($type);

            if (strpos($type, '*') !== false) {
                $generalType = substr($type, 0, strpos($type, '/'));
                $actualGeneralType = substr($actualMimeType, 0, strpos($actualMimeType, '/'));

                if ($generalType === $actualGeneralType) {
                    return $actualMimeType;
                }
            }

            $cleanType = explode(';', $type)[0];
            if ($cleanType === $actualMimeType) {
                return $actualMimeType;
            }
        }

        return $actualMimeType;
    }
}
