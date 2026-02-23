<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaController extends Controller
{
    /**
     * Stream a file from public storage after path and MIME validation.
     *
     * @param  Request  $request  Request used to inspect `Accept` header compatibility.
     * @param  string  $path  Storage path of the target file.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse Binary stream response for the resolved file.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException When the operation cannot be completed.
     */
    public function show(Request $request, string $path)
    {
        $path = $this->sanitizePath($path);

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        $filePath = $disk->path($path);
        $rootPath = realpath($disk->path(''));
        $resolvedFilePath = realpath($filePath);
        if ($rootPath === false || $resolvedFilePath === false) {
            throw new NotFoundHttpException;
        }

        $rootPrefix = rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (strncmp($resolvedFilePath, $rootPrefix, strlen($rootPrefix)) !== 0) {
            throw new NotFoundHttpException;
        }

        $mimeType = $this->determineMimeType($request, $filePath);

        return ApiResponse::media()
            ->path($filePath)
            ->mimeType($mimeType)
            ->filename(basename($path))
            ->build();
    }

    /**
     * Normalize a storage path and block path traversal segments.
     *
     * @param  string  $path  Storage path of the target file.
     * @return string Resolved file path string.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException When the operation cannot be completed.
     */
    private function sanitizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');

        $segments = explode('/', $path);
        $clean = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new NotFoundHttpException;
            }

            $clean[] = $segment;
        }

        return implode('/', $clean);
    }

    /**
     * Resolve the file MIME type while honoring compatible Accept headers.
     *
     * @param  Request  $request  Request used to inspect acceptable media types.
     * @param  string  $filePath  Storage path of the file to stream.
     * @return string MIME type that will be used for the streamed response.
     */
    private function determineMimeType(Request $request, string $filePath): string
    {
        $actualMimeType = mime_content_type($filePath);

        if (! $request->header('Accept')) {
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
