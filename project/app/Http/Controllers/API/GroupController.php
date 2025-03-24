<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    // Lister les groupes de l'utilisateur
    public function index()
    {
        $groups = Auth::user()->groups()->with('creator')->get();
        return response()->json($groups);
    }

    // Créer un groupe
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id'
        ]);

        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'currency' => $request->currency ?? 'EUR',
            'creator_id' => Auth::id()
        ]);

        // Ajouter le créateur comme membre
        $group->members()->attach(Auth::id());

        // Ajouter d'autres membres si spécifiés
        if ($request->has('members')) {
            $members = array_diff($request->members, [Auth::id()]);
            if (!empty($members)) {
                $group->members()->attach($members);
            }
        }

        return response()->json([
            'message' => 'Group created successfully',
            'group' => $group->load('members', 'creator')
        ], 201);
    }

    // Voir les détails d'un groupe
    public function show($id)
    {
        $group = Group::with(['members', 'expenses.shares.user', 'creator'])
                     ->findOrFail($id);
        
        // Vérifier si l'utilisateur est membre du groupe
        if (!$group->members->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not a member of this group'
            ], 403);
        }

        return response()->json($group);
    }

    // Supprimer un groupe
    public function destroy($id)
    {
        $group = Group::findOrFail($id);

        // Vérifier si l'utilisateur est le créateur du groupe
        if ($group->creator_id !== Auth::id()) {
            return response()->json([
                'message' => 'Only the creator can delete this group'
            ], 403);
        }

        // TODO: Vérifier qu'il n'y a pas de soldes restants
        // Cette partie serait implémentée après la création de la logique de calcul des soldes

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully'
        ]);
    }
}