<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Récupère et renvoie un fichier média.
     *
     * @param  string  $path  Le chemin relatif du fichier demandé
     * @return BinaryFileResponse|Response
     */
    public function show(Request $request, string $path)
    {
        // Nettoyer le chemin pour éviter la traversée de répertoire
        $path = $this->sanitizePath($path);

        // Vérifier si le fichier existe dans le stockage public
        if (! Storage::disk('public')->exists($path)) {
            return response('Fichier non trouvé', 404);
        }

        // Obtenir le chemin absolu du fichier
        $filePath = Storage::disk('public')->path($path);

        // Déterminer le type MIME
        $mimeType = $this->determineMimeType($request, $filePath);

        // Retourner le fichier avec le type MIME approprié
        return ApiResponse::media()
            ->path($filePath)
            ->mimeType($mimeType)
            ->filename(basename($path))
            ->build();
    }

    /**
     * Nettoie le chemin pour éviter les attaques de traversée de répertoire.
     */
    private function sanitizePath(string $path): string
    {
        // Supprimer les doubles points et les barres obliques multiples
        $path = str_replace('..', '', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');

        return $path;
    }

    /**
     * Détermine le type MIME à utiliser en fonction des en-têtes Accept.
     */
    private function determineMimeType(Request $request, string $filePath): string
    {
        // Obtenir le type MIME du fichier
        $actualMimeType = mime_content_type($filePath);

        // Si pas d'en-tête Accept, retourner le type MIME actuel
        if (! $request->header('Accept')) {
            return $actualMimeType;
        }

        // Extraire les types MIME acceptés
        $acceptHeader = $request->header('Accept');
        $acceptedTypes = explode(',', $acceptHeader);

        // Vérifier si le type MIME actuel est accepté
        foreach ($acceptedTypes as $type) {
            $type = trim($type);

            // Gérer les types génériques comme image/*
            if (strpos($type, '*') !== false) {
                $generalType = substr($type, 0, strpos($type, '/'));
                $actualGeneralType = substr($actualMimeType, 0, strpos($actualMimeType, '/'));

                if ($generalType === $actualGeneralType) {
                    return $actualMimeType;
                }
            }

            // Vérifier les types exacts (ignorer la qualité q=0.8)
            $cleanType = explode(';', $type)[0];
            if ($cleanType === $actualMimeType) {
                return $actualMimeType;
            }
        }

        // Si le type n'est pas accepté, retourner quand même le type actuel
        // Le navigateur décidera comment le traiter
        return $actualMimeType;
    }
}
