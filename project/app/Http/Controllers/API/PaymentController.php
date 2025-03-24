<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function store(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id|different:from_user_id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        if (!$group->members->contains($request->from_user_id) || !$group->members->contains($request->to_user_id)) {
            return response()->json([
                'message' => 'Both users must be members of the group'
            ], 422);
        }

        if (Auth::id() != $request->from_user_id && Auth::id() != $request->to_user_id) {
            return response()->json([
                'message' => 'You can only record payments that involve you'
            ], 403);
        }

        $payment = Payment::create([
            'group_id' => $groupId,
            'from_user_id' => $request->from_user_id,
            'to_user_id' => $request->to_user_id,
            'amount' => $request->amount,
            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => $payment->load(['fromUser', 'toUser'])
        ], 201);
    }

    // Voir l'historique des paiements
    public function history($groupId)
    {
        $group = Group::findOrFail($groupId);

        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        $payments = $group->payments()->with(['fromUser', 'toUser'])->get();

        return response()->json($payments);
    }
}