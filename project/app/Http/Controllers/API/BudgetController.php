<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BudgetController extends Controller
{
    // Lister tous les budgets
    public function index()
    {
        $budgets = Auth::user()->budgets;
        
        // Pour chaque budget, calculer le montant déjà dépensé
        foreach ($budgets as &$budget) {
            $spent = Expense::where('user_id', Auth::id())
                            ->where('category', $budget->category)
                            ->whereBetween('expense_date', [$budget->start_date, $budget->end_date])
                            ->sum('amount');
            
            $budget->spent = $spent;
            $budget->remaining = max(0, $budget->amount - $spent);
            $budget->percentage_used = $budget->amount > 0 ? min(100, ($spent / $budget->amount) * 100) : 0;
        }
        
        return response()->json($budgets);
    }

    // Créer un nouveau budget
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'warning_threshold' => 'nullable|numeric|min:1|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'recurring' => 'nullable|boolean'
        ]);

        $budget = Budget::create([
            'user_id' => Auth::id(),
            'category' => $request->category,
            'amount' => $request->amount,
            'warning_threshold' => $request->warning_threshold ?? 80,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'recurring' => $request->recurring ?? false
        ]);

        return response()->json([
            'message' => 'Budget created successfully',
            'budget' => $budget
        ], 201);
    }

    // Mettre à jour un budget
    public function update(Request $request, $id)
    {
        $budget = Budget::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'warning_threshold' => 'nullable|numeric|min:1|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'recurring' => 'nullable|boolean'
        ]);

        $budget->update([
            'category' => $request->category,
            'amount' => $request->amount,
            'warning_threshold' => $request->warning_threshold ?? $budget->warning_threshold,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'recurring' => $request->recurring ?? $budget->recurring
        ]);

        return response()->json([
            'message' => 'Budget updated successfully',
            'budget' => $budget
        ]);
    }

    // Supprimer un budget
    public function destroy($id)
    {
        $budget = Budget::where('user_id', Auth::id())->findOrFail($id);
        $budget->delete();

        return response()->json([
            'message' => 'Budget deleted successfully'
        ]);
    }
}