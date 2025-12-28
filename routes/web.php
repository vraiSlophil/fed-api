<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

//require __DIR__.'/auth.php';
// Route de test pour générer des VRAIES erreurs HTTP
Route::get('/test-security', function (Request $request) {
    $results = [];
    
    // Simuler 15 requêtes 403 Forbidden depuis la même IP
    for ($i = 0; $i < 15; $i++) {
        Log::channel('single')->warning('Access Forbidden', [
            'status' => 403,
            'ip' => '10.0.0.50',
            'path' => '/admin',
            'method' => 'GET',
            'user_agent' => 'Mozilla/5.0'
        ]);
    }
    $results['403_generated'] = 15;
    
    // Simuler 25 requêtes 401 Unauthorized depuis la même IP
    for ($i = 0; $i < 25; $i++) {
        Log::channel('single')->warning('Unauthorized Access', [
            'status' => 401,
            'ip' => '10.0.0.51',
            'path' => '/api/users',
            'method' => 'GET',
            'user_agent' => 'PostmanRuntime/7.26.8'
        ]);
    }
    $results['401_generated'] = 25;
    
    // Simuler des tentatives de scan
    $scanPaths = ['/.env', '/wp-admin', '/phpmyadmin', '/.git/config', '/admin/login'];
    foreach ($scanPaths as $path) {
        Log::channel('single')->warning('Suspicious access attempt', [
            'status' => 404,
            'ip' => '203.0.113.42',
            'path' => $path,
            'method' => 'GET',
            'user_agent' => 'sqlmap/1.0'
        ]);
    }
    $results['scans_generated'] = count($scanPaths);
    
    // Simuler des erreurs serveur 500
    Log::channel('single')->emergency('Server Error', [
        'status' => 500,
        'ip' => '192.168.1.101',
        'path' => '/api/crash',
        'error' => 'Division by zero',
        'trace' => 'Stack trace here...'
    ]);
    
    Log::channel('single')->critical('Database Connection Failed', [
        'status' => 500,
        'ip' => '192.168.1.101',
        'error' => 'SQLSTATE[HY000] [2002] Connection refused'
    ]);
    $results['500_errors_generated'] = 2;
    
    return response()->json([
        'success' => true,
        'message' => 'Logs de sécurité générés',
        'summary' => $results
    ]);
});

// Routes qui retournent vraiment des codes HTTP d'erreur
Route::get('/trigger-403', function () {
    Log::warning('Real 403 Forbidden triggered', [
        'ip' => request()->ip(),
        'status' => 403,
        'path' => request()->path()
    ]);
    abort(403, 'Accès interdit - Test de sécurité');
});

Route::get('/trigger-401', function () {
    Log::warning('Real 401 Unauthorized triggered', [
        'ip' => request()->ip(),
        'status' => 401,
        'path' => request()->path()
    ]);
    abort(401, 'Non autorisé - Test de sécurité');
});

Route::get('/trigger-500', function () {
    Log::emergency('Real 500 Server Error triggered', [
        'ip' => request()->ip(),
        'status' => 500,
        'path' => request()->path()
    ]);
    abort(500, 'Erreur serveur - Test de sécurité');
});

