<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupExpense;
use App\Models\ExpenseShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupExpenseController extends Controller
{
    // Lister les dépenses d'un groupe
    public function index($groupId)
    {
        $group = Group::findOrFail($groupId);

        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $expenses = $group->expenses()->with('shares.user')->get();
        
        return response()->json($expenses);
    }

    public function store(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'split_type' => 'required|in:equal,custom',
            'paid_by' => 'required|array',
            'paid_by.*.user_id' => 'required|exists:users,id',
            'paid_by.*.amount' => 'required|numeric|min:0',
            'shares' => 'required_if:split_type,custom|array',
            'shares.*.user_id' => 'required_if:split_type,custom|exists:users,id',
            'shares.*.percentage' => 'required_if:split_type,custom|numeric|min:0|max:100'
        ]);

        $totalPaid = array_sum(array_column($request->paid_by, 'amount'));
        if (abs($totalPaid - $request->amount) > 0.01) {
            return response()->json([
                'message' => 'Total amount paid does not match the expense amount'
            ], 422);
        }

        $payerIds = array_column($request->paid_by, 'user_id');
        foreach ($payerIds as $payerId) {
            if (!$group->members->contains($payerId)) {
                return response()->json([
                    'message' => 'All payers must be members of the group'
                ], 422);
            }
        }

        if ($request->split_type === 'custom') {
            $totalPercentage = array_sum(array_column($request->shares, 'percentage'));
            if (abs($totalPercentage - 100) > 0.01) {
                return response()->json([
                    'message' => 'Total percentage must be 100%'
                ], 422);
            }

            $shareUserIds = array_column($request->shares, 'user_id');
            foreach ($shareUserIds as $userId) {
                if (!$group->members->contains($userId)) {
                    return response()->json([
                        'message' => 'All users with shares must be members of the group'
                    ], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            $expense = GroupExpense::create([
                'group_id' => $groupId,
                'title' => $request->title,
                'description' => $request->description,
                'amount' => $request->amount,
                'expense_date' => $request->expense_date,
                'split_type' => $request->split_type
            ]);

            // Traiter les paiements
            foreach ($request->paid_by as $payment) {
                ExpenseShare::create([
                    'group_expense_id' => $expense->id,
                    'user_id' => $payment['user_id'],
                    'paid_amount' => $payment['amount'],
                    'share_amount' => 0, // Sera calculé ci-dessous
                    'percentage' => null
                ]);
            }

            $groupMembers = $group->members;

            if ($request->split_type === 'equal') {
                $shareAmount = $request->amount / $groupMembers->count();
                
                foreach ($groupMembers as $member) {
                    $share = $expense->shares()->where('user_id', $member->id)->first();
                    
                    if ($share) {
                        $share->update([
                            'share_amount' => $shareAmount
                        ]);
                    } else {
                        ExpenseShare::create([
                            'group_expense_id' => $expense->id,
                            'user_id' => $member->id,
                            'paid_amount' => 0,
                            'share_amount' => $shareAmount,
                            'percentage' => null
                        ]);
                    }
                }
            } else {
                foreach ($request->shares as $shareData) {
                    $shareAmount = ($shareData['percentage'] / 100) * $request->amount;
                    
                    $share = $expense->shares()->where('user_id', $shareData['user_id'])->first();
                    
                    if ($share) {
                        $share->update([
                            'share_amount' => $shareAmount,
                            'percentage' => $shareData['percentage']
                        ]);
                    } else {
                        ExpenseShare::create([
                            'group_expense_id' => $expense->id,
                            'user_id' => $shareData['user_id'],
                            'paid_amount' => 0,
                            'share_amount' => $shareAmount,
                            'percentage' => $shareData['percentage']
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Expense added successfully',
                'expense' => $expense->load('shares.user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while adding the expense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($groupId, $expenseId)
    {
        $group = Group::findOrFail($groupId);
        
        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $expense = GroupExpense::where('group_id', $groupId)
                              ->where('id', $expenseId)
                              ->firstOrFail();
        
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully'
        ]);
    }
}