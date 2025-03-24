<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\ExpenseShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function index($groupId)
    {
        $group = Group::findOrFail($groupId);

        // Vérifier si l'utilisateur est membre du groupe
        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $members = $group->members;

        $balances = [];
        $debts = [];

        foreach ($members as $member) {
            $totalPaid = ExpenseShare::whereHas('groupExpense', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            })->where('user_id', $member->id)
              ->sum('paid_amount');

            $totalShare = ExpenseShare::whereHas('groupExpense', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            })->where('user_id', $member->id)
              ->sum('share_amount');

            $balance = $totalPaid - $totalShare;

            $balances[] = [
                'user_id' => $member->id,
                'name' => $member->name,
                'total_paid' => round($totalPaid, 2),
                'total_share' => round($totalShare, 2),
                'balance' => round($balance, 2)
            ];
        }

        $creditors = array_filter($balances, function($b) { return $b['balance'] > 0; });
        $debtors = array_filter($balances, function($b) { return $b['balance'] < 0; });
        
        usort($creditors, function($a, $b) { return $b['balance'] <=> $a['balance']; });
        usort($debtors, function($a, $b) { return $a['balance'] <=> $b['balance']; });

        $transactions = [];
        
        while (!empty($creditors) && !empty($debtors)) {
            $creditor = reset($creditors);
            $debtor = reset($debtors);
            
            $amount = min(abs($debtor['balance']), abs($creditor['balance']));
            
            if ($amount > 0) {
                $transactions[] = [
                    'from' => $debtor,
                    'to' => $creditor,
                    'amount' => round($amount, 2)
                ];
            }
            
            $debtor['balance'] += $amount;
            $creditor['balance'] -= $amount;
            
            if (abs($debtor['balance']) < 0.01) {
                array_shift($debtors);
            }
            
            if (abs($creditor['balance']) < 0.01) {
                array_shift($creditors);
            }
        }

        return response()->json([
            'group' => $group->only('id', 'name', 'currency'),
            'balances' => $balances,
            'transactions' => $transactions
        ]);
    }
}