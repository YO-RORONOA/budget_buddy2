<?php
// app/Http/Controllers/API/ExpenseController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Auth::user()->expenses()->with('tags')->get();
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date'
        ]);

        $expense = Auth::user()->expenses()->create([
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date
        ]);

        return response()->json([
            'message' => 'Expense created successfully',
            'expense' => $expense
        ], 201);
    }

    public function show($id)
    {
        $expense = Auth::user()->expenses()->with('tags')->findOrFail($id);
        return response()->json($expense);
    }

    public function update(Request $request, $id)
    {
        $expense = Auth::user()->expenses()->findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date'
        ]);

        $expense->update([
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date
        ]);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense
        ]);
    }

    public function destroy($id)
    {
        $expense = Auth::user()->expenses()->findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully'
        ]);
    }

    public function attachTags(Request $request, $id)
    {
        $expense = Auth::user()->expenses()->findOrFail($id);
        
        $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'exists:tags,id'
        ]);

        // Vérifier que les tags appartiennent à l'utilisateur
        $userTagIds = Auth::user()->tags()->whereIn('id', $request->tags)->pluck('id')->toArray();
        
        $expense->tags()->sync($userTagIds);

        return response()->json([
            'message' => 'Tags attached successfully',
            'expense' => $expense->load('tags')
        ]);
    }
}