<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RecurringExpense;
use App\Models\Expense;
use App\Models\Budget;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RecurringExpenseController extends Controller
{
    public function index()
    {
        $recurringExpenses = Auth::user()->recurringExpenses()->orderBy('title')->get();
        return response()->json($recurringExpenses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'active' => 'nullable|boolean'
        ]);

        $recurringExpense = RecurringExpense::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $request->amount,
            'category' => $request->category,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'active' => $request->active ?? true
        ]);
        
        $now = Carbon::now();
        if (Carbon::parse($request->start_date)->lte($now)) {
            $this->generateExpenseInstance($recurringExpense);
        }

        return response()->json([
            'message' => 'Recurring expense created successfully',
            'recurringExpense' => $recurringExpense
        ], 201);
    }

    public function destroy($id)
    {
        $recurringExpense = RecurringExpense::where('user_id', Auth::id())->findOrFail($id);
        $recurringExpense->delete();

        return response()->json([
            'message' => 'Recurring expense deleted successfully'
        ]);
    }

    private function generateExpenseInstance(RecurringExpense $recurringExpense)
    {
        $expense = Expense::create([
            'user_id' => $recurringExpense->user_id,
            'title' => $recurringExpense->title,
            'description' => $recurringExpense->description,
            'amount' => $recurringExpense->amount,
            'category' => $recurringExpense->category,
            'expense_date' => Carbon::now()->toDateString(),
            'recurring_expense_id' => $recurringExpense->id
        ]);
        
        $recurringExpense->update([
            'last_generated' => Carbon::now()->toDateString()
        ]);
        
        $this->checkBudgetExceeded($expense);
        
        return $expense;
    }

    private function checkBudgetExceeded(Expense $expense)
    {
        $budget = Budget::where('user_id', $expense->user_id)
                        ->where('category', $expense->category)
                        ->where('start_date', '<=', $expense->expense_date)
                        ->where('end_date', '>=', $expense->expense_date)
                        ->first();
        
        if ($budget) {
            $totalSpent = Expense::where('user_id', $expense->user_id)
                                 ->where('category', $expense->category)
                                 ->whereBetween('expense_date', [$budget->start_date, $budget->end_date])
                                 ->sum('amount');
            
            $percentageUsed = ($totalSpent / $budget->amount) * 100;
            
            if ($percentageUsed >= $budget->warning_threshold) {
                Alert::create([
                    'user_id' => $expense->user_id,
                    'type' => 'budget',
                    'message' => "Votre budget pour la catégorie '{$budget->category}' a atteint {$percentageUsed}% du montant alloué.",
                    'budget_id' => $budget->id,
                    'expense_id' => $expense->id,
                    'read' => false
                ]);
            }
        }
    }
}