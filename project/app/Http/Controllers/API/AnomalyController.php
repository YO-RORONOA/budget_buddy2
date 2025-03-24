<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnomalyController extends Controller
{
    // Détecter et afficher les dépenses anormales
    public function index()
    {
        $userId = Auth::id();
        $anomalies = [];
        
        // 1. Détecter les augmentations soudaines par catégorie
        $categoryAnomalies = $this->detectCategoryAnomalies($userId);
        $anomalies = array_merge($anomalies, $categoryAnomalies);
        
        // 2. Détecter les transactions inhabituelles (montants exceptionnellement élevés)
        $unusualAmounts = $this->detectUnusualAmounts($userId);
        $anomalies = array_merge($anomalies, $unusualAmounts);
        
        // Charger les détails de chaque dépense anormale
        $anomalyIds = array_column($anomalies, 'expense_id');
        $expenses = Expense::whereIn('id', $anomalyIds)->get();
        
        // Enrichir les données d'anomalies avec les détails de la dépense
        foreach ($anomalies as &$anomaly) {
            $expense = $expenses->firstWhere('id', $anomaly['expense_id']);
            if ($expense) {
                $anomaly['expense'] = $expense;
            }
        }
        
        return response()->json($anomalies);
    }
    
    // Méthode privée pour détecter les anomalies par catégorie
    private function detectCategoryAnomalies($userId)
    {
        $anomalies = [];
        $now = Carbon::now();
        $currentMonth = $now->format('Y-m');
        $lastMonth = $now->copy()->subMonth()->format('Y-m');
        
        // Récupérer les dépenses par catégorie pour le mois en cours
        $currentMonthExpenses = Expense::where('user_id', $userId)
            ->where('expense_date', 'like', $currentMonth . '%')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();
        
        // Récupérer les dépenses par catégorie pour le mois précédent
        $lastMonthExpenses = Expense::where('user_id', $userId)
            ->where('expense_date', 'like', $lastMonth . '%')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();
        
        // Comparer les totaux
        foreach ($currentMonthExpenses as $current) {
            $last = $lastMonthExpenses->firstWhere('category', $current->category);
            
            if ($last && $last->total > 0) {
                $increasePercentage = (($current->total - $last->total) / $last->total) * 100;
                
                // Considérer comme anomalie si l'augmentation est supérieure à 50%
                if ($increasePercentage > 50) {
                    // Trouver la dernière dépense dans cette catégorie
                    $latestExpense = Expense::where('user_id', $userId)
                                            ->where('category', $current->category)
                                            ->where('expense_date', 'like', $currentMonth . '%')
                                            ->orderBy('created_at', 'desc')
                                            ->first();
                    
                    if ($latestExpense) {
                        $anomalies[] = [
                            'type' => 'category_increase',
                            'expense_id' => $latestExpense->id,
                            'category' => $current->category,
                            'current_month_total' => $current->total,
                            'last_month_total' => $last->total,
                            'increase_percentage' => round($increasePercentage, 2),
                            'message' => "Augmentation de " . round($increasePercentage, 2) . "% des dépenses dans la catégorie '{$current->category}'"
                        ];
                    }
                }
            }
        }
        
        return $anomalies;
    }
    
    // Méthode privée pour détecter les montants inhabituels
    private function detectUnusualAmounts($userId)
    {
        $anomalies = [];
        
        // Calculer la moyenne et l'écart-type des dépenses par catégorie
        $categoryStats = Expense::where('user_id', $userId)
            ->select('category', 
                DB::raw('AVG(amount) as avg_amount'), 
                DB::raw('STDDEV(amount) as stddev_amount'))
            ->groupBy('category')
            ->having('stddev_amount', '>', 0) // Éviter les catégories avec une seule dépense
            ->get();
        
        // Pour chaque catégorie, trouver les dépenses qui s'écartent significativement de la moyenne
        foreach ($categoryStats as $stat) {
            // Seuil : moyenne + 2 écarts-types
            $threshold = $stat->avg_amount + (2 * $stat->stddev_amount);
            
            // Trouver les dépenses qui dépassent ce seuil
            $unusualExpenses = Expense::where('user_id', $userId)
                                      ->where('category', $stat->category)
                                      ->where('amount', '>', $threshold)
                                      ->orderBy('amount', 'desc')
                                      ->get();
            
            foreach ($unusualExpenses as $expense) {
                // Z-score pour quantifier l'anomalie
                $zScore = ($expense->amount - $stat->avg_amount) / $stat->stddev_amount;
                
                $anomalies[] = [
                    'type' => 'unusual_amount',
                    'expense_id' => $expense->id,
                    'category' => $stat->category,
                    'amount' => $expense->amount,
                    'average' => $stat->avg_amount,
                    'z_score' => round($zScore, 2),
                    'message' => "Dépense exceptionnellement élevée : {$expense->amount} € (moyenne de la catégorie: " . round($stat->avg_amount, 2) . " €)"
                ];
            }
        }
        
        return $anomalies;
    }
}