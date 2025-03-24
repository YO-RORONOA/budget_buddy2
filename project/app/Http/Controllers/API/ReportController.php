<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function summary()
    {
        $userId = Auth::id();
        $now = Carbon::now();
        $currentMonth = $now->format('Y-m');
        $lastMonth = $now->copy()->subMonth()->format('Y-m');
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        
        $currentMonthExpenses = Expense::where('user_id', $userId)
                                     ->where('expense_date', 'like', $currentMonth . '%')
                                     ->sum('amount');
        
        $previousMonthExpenses = Expense::where('user_id', $userId)
                                      ->where('expense_date', 'like', $lastMonth . '%')
                                      ->sum('amount');
        
        $expensesByCategory = Expense::where('user_id', $userId)
                                    ->where('expense_date', 'like', $currentMonth . '%')
                                    ->select('category', DB::raw('SUM(amount) as total'))
                                    ->groupBy('category')
                                    ->orderBy('total', 'desc')
                                    ->get();
        
        $budgets = Budget::where('user_id', $userId)
                         ->where('start_date', '<=', $endOfMonth)
                         ->where('end_date', '>=', $startOfMonth)
                         ->get();
        
        foreach ($budgets as &$budget) {
            $spent = Expense::where('user_id', $userId)
                           ->where('category', $budget->category)
                           ->whereBetween('expense_date', [$budget->start_date, $budget->end_date])
                           ->sum('amount');
            
            $budget->spent = $spent;
            $budget->remaining = max(0, $budget->amount - $spent);
            $budget->percentage_used = $budget->amount > 0 ? min(100, ($spent / $budget->amount) * 100) : 0;
        }
        
        $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();
        $monthlyExpenses = [];
        
        for ($i = 0; $i < 6; $i++) {
            $date = $sixMonthsAgo->copy()->addMonths($i);
            $monthKey = $date->format('Y-m');
            
            $total = Expense::where('user_id', $userId)
                           ->where('expense_date', 'like', $monthKey . '%')
                           ->sum('amount');
            
            $monthlyExpenses[] = [
                'month' => $date->format('M Y'),
                'total' => $total
            ];
        }
        
        return response()->json([
            'current_month_total' => $currentMonthExpenses,
            'previous_month_total' => $previousMonthExpenses,
            'month_over_month_change' => $previousMonthExpenses > 0 
                ? (($currentMonthExpenses - $previousMonthExpenses) / $previousMonthExpenses) * 100 
                : null,
            'expenses_by_category' => $expensesByCategory,
            'budgets' => $budgets,
            'monthly_trend' => $monthlyExpenses
        ]);
    }
    
    public function custom(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start'
        ]);
        
        $userId = Auth::id();
        $startDate = $request->start;
        $endDate = $request->end;
        
        $totalExpenses = Expense::where('user_id', $userId)
                               ->whereBetween('expense_date', [$startDate, $endDate])
                               ->sum('amount');
        
        $expensesByCategory = Expense::where('user_id', $userId)
                                    ->whereBetween('expense_date', [$startDate, $endDate])
                                    ->select('category', DB::raw('SUM(amount) as total'))
                                    ->groupBy('category')
                                    ->orderBy('total', 'desc')
                                    ->get();
        
        // Dépenses par jour
        $expensesByDay = Expense::where('user_id', $userId)
                               ->whereBetween('expense_date', [$startDate, $endDate])
                               ->select('expense_date', DB::raw('SUM(amount) as total'))
                               ->groupBy('expense_date')
                               ->orderBy('expense_date')
                               ->get();
        
        // Top 10 des dépenses individuelles
        $topExpenses = Expense::where('user_id', $userId)
                             ->whereBetween('expense_date', [$startDate, $endDate])
                             ->orderBy('amount', 'desc')
                             ->limit(10)
                             ->get();
        
        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'total_expenses' => $totalExpenses,
            'expenses_by_category' => $expensesByCategory,
            'expenses_by_day' => $expensesByDay,
            'top_expenses' => $topExpenses
        ]);
    }
}