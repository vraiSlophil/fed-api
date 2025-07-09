<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $status === 'success' ? 'Invitation acceptée' : ($status === 'error' ? 'Erreur' : 'Invitation refusée') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class=" min-h-screen flex flex-col bg-white dark:bg-[#1C1B22] text-black dark:text-white">
<div class="flex-grow flex items-center justify-center p-4 sm:p-6 md:p-8">
    <div class="p-6 sm:p-8">
    <div class="flex justify-center my-8">
        @if($status === 'success')
            <div class="flex items-center justify-center text-6xl text-green-500 dark:text-green-400 h-24 w-24 bg-green-500/30 rounded-full">✓</div>
        @elseif($status === 'error')
            <div class="flex items-center justify-center text-6xl text-red-500 dark:text-red-400 h-24 w-24 bg-red-500/30 rounded-full">✗</div>
        @else
            <div class="flex items-center justify-center text-6xl text-blue-500 dark:text-blue-400 h-24 w-24 bg-blue-500/30 rounded-full">ℹ</div>
        @endif
    </div>
    <p class="text-center text-lg mb-8">{!! e($message) !!}</p>

    @if(isset($frontendUrl))
        <div class="flex justify-center">
            <a href="{{ $frontendUrl }}" class="text-blue-500 hover:underline text-sm">
                Retourner à l'application
            </a>
        </div>
    @endif
    </div>
</div>
</body>
</html>
