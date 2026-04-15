<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArSys\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NewsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $programId = null;

        if ($user->staff) {
            $programId = $user->staff->program_id;
        } elseif ($user->student) {
            $programId = $user->student->program_id;
        }

        $news = News::where('is_active', true)
            ->where(function ($q) use ($programId) {
                $q->where('program_id', $programId)
                  ->orWhereNull('program_id'); // global news
            })
            ->with('author')
            ->orderBy('created_at', 'DESC')
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'content' => $item->content,
                    'author_name' => $item->author ? trim($item->author->first_name . ' ' . $item->author->last_name) : 'Unknown',
                    'author_code' => $item->author?->code,
                    'created_at' => $item->created_at->format('Y-m-d H:i'),
                    'is_mine' => $user->staff && $item->author_id == $user->staff->id,
                ];
            });

        return response()->json(['data' => $news]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasRole('program') && !$user->hasRole('specialization')) {
            return response()->json(['message' => 'Unauthorized. Only kaprodi or KBK can create news.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $news = News::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'program_id' => $user->staff?->program_id,
            'author_id' => $user->staff->id,
        ]);

        return response()->json(['message' => 'News created successfully', 'data' => $news], 201);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $news = News::findOrFail($id);

        if (!$user->staff || $news->author_id !== $user->staff->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
        ]);

        $news->update($validated);

        return response()->json(['message' => 'News updated successfully', 'data' => $news]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $news = News::findOrFail($id);

        if (!$user->staff || $news->author_id !== $user->staff->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $news->delete();

        return response()->json(['message' => 'News deleted successfully']);
    }
}
