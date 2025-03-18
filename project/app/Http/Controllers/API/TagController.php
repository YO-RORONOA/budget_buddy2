<?php
// app/Http/Controllers/API/TagController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class TagController extends Controller
{
    public function index()
    {
        $tags = Auth::user()->tags;
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $tag = Auth::user()->tags()->create([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Tag created successfully',
            'tag' => $tag
        ], 201);
    }

    public function show($id)
    {
        $tag = Auth::user()->tags()->findOrFail($id);
        return response()->json($tag);
    }

    public function update(Request $request, $id)
    {
        $tag = Auth::user()->tags()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $tag->update([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Tag updated successfully',
            'tag' => $tag
        ]);
    }

    public function destroy($id)
    {
        $tag = Auth::user()->tags()->findOrFail($id);
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully'
        ]);
    }
}