<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::where('user_id', Auth::id());
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('read')) {
            $query->where('read', $request->read === 'true');
        }
        
        $alerts = $query->with(['budget', 'expense'])->orderBy('created_at', 'desc')->get();
        
        return response()->json($alerts);
    }

    public function markAsRead($id)
    {
        $alert = Alert::where('user_id', Auth::id())->findOrFail($id);
        $alert->update(['read' => true]);
        
        return response()->json([
            'message' => 'Alert marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        Alert::where('user_id', Auth::id())->update(['read' => true]);
        
        return response()->json([
            'message' => 'All alerts marked as read'
        ]);
    }
}