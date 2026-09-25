<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewcomerLesson;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The lessons of the newcomers' class, in teaching order. Lessons are added and reordered here; where each person stands is recorded on their own page. */
class NewcomerLessonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('newcomers/lessons', [
            'lessons' => NewcomerLesson::orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'description', 'is_active'])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $lesson = NewcomerLesson::create([...$data, 'sort_order' => (int) NewcomerLesson::max('sort_order') + 1]);

        Audit::record('newcomer.lesson_added', "Added the lesson {$lesson->title}", $lesson);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$lesson->title} added."]);

        return back();
    }

    public function update(Request $request, NewcomerLesson $lesson): RedirectResponse
    {
        $lesson->update([...$this->validated($request, $lesson), 'is_active' => $request->boolean('is_active', true)]);

        Audit::record('newcomer.lesson_updated', "Updated the lesson {$lesson->title}", $lesson);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$lesson->title} saved."]);

        return back();
    }

    /** Moves a lesson one place up or down, by swapping its order with its neighbour's. */
    public function move(Request $request, NewcomerLesson $lesson): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $all = NewcomerLesson::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $all->search(fn ($l) => $l->id === $lesson->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;

        if (isset($all[$j])) {
            $all->splice($i, 1, [$all[$j]]);
            $all->splice($j, 1, [$lesson]);
            $all->each(fn (NewcomerLesson $l, int $n) => $l->update(['sort_order' => $n + 1]));
        }

        return back();
    }

    public function destroy(NewcomerLesson $lesson): RedirectResponse
    {
        if (DB::table('newcomer_lesson_progress')->where('lesson_id', $lesson->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$lesson->title} is on people's class records. Mark it not in use instead."]);

            return back();
        }

        $lesson->delete();

        Audit::record('newcomer.lesson_removed', "Removed the lesson {$lesson->title}", null, ['lesson' => $lesson->title]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$lesson->title} removed."]);

        return back();
    }

    /** @return array{title: string, description: ?string} */
    private function validated(Request $request, ?NewcomerLesson $lesson = null): array
    {
        $request->merge(['title' => trim(preg_replace('/\s+/', ' ', (string) $request->input('title')))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200', Rule::unique('newcomer_lessons', 'title')->ignore($lesson)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], ['title.unique' => 'There is already a lesson with that title.']);

        return ['title' => $data['title'], 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null];
    }
}
