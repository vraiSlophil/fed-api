<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    /**
     * Afficher les statistiques globales des tâches pour l'utilisateur authentifié.
     */
    public function globalStats(): JsonResponse
    {
        $userId = Auth::id();
        $stats = $this->getTaskStats($userId);
        
        return ApiResponse::success($stats);
    }
    
    /**
     * Afficher les statistiques des tâches pour un thème spécifique.
     */
    public function themeStats(string $themeId): JsonResponse
    {
        $userId = Auth::id();
        
        // Vérifier que l'utilisateur a accès au thème
        $theme = Theme::where('theme_id', $themeId)
            ->where(function($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();
        
        $stats = $this->getTaskStats($userId, $themeId);
        $stats['theme'] = [
            'theme_id' => $theme->theme_id,
            'title' => $theme->title,
            'color' => $theme->color
        ];
        
        return ApiResponse::success($stats);
    }
    
    /**
     * Calcule les statistiques pour un utilisateur, éventuellement filtrées par thème.
     */
    private function getTaskStats(string $userId, ?string $themeId = null): array
    {
        $query = Task::where('user_id', $userId);
        
        // Si un thème est spécifié, filtrer les résultats par thème
        if ($themeId) {
            $query->where('theme_id', $themeId);
        }
        
        // Nombre total de tâches (archivées et non archivées)
        $totalTasks = (clone $query)->count();
        
        // Nombre de tâches non archivées
        $activeTasks = (clone $query)
            ->whereNull('archived_at')
            ->count();
            
        // Nombre de tâches archivées
        $archivedTasks = (clone $query)
            ->whereNotNull('archived_at')
            ->count();
            
        // Nombre de tâches par statut (non archivées)
        $todoTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'todo')
            ->count();
            
        $doingTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'doing')
            ->count();
            
        $doneTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'done')
            ->count();
            
        // Tâches récemment créées (7 derniers jours)
        $recentlyCreatedTasks = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
            
        // Tâches récemment validées (7 derniers jours)
        $recentlyCompletedTasks = (clone $query)
            ->whereNotNull('validated_at')
            ->where('validated_at', '>=', now()->subDays(7))
            ->count();
            
        // Taux de complétion (tâches terminées / total des tâches non archivées)
        $completionRate = $activeTasks > 0 ? round(($doneTasks / $activeTasks) * 100, 2) : 0;
        
        return [
            'total' => $totalTasks,
            'active' => $activeTasks,
            'archived' => $archivedTasks,
            'todo' => $todoTasks,
            'doing' => $doingTasks,
            'done' => $doneTasks,
            'recently_created' => $recentlyCreatedTasks,
            'recently_completed' => $recentlyCompletedTasks,
            'completion_rate' => $completionRate
        ];
    }
}
