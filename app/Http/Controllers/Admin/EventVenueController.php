<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventVenue;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The places events are held. Events store the venue as text, so renaming or removing one never changes an event
 * already saved; the event form also lets someone type a venue that is not listed.
 */
class EventVenueController extends Controller
{
    public function index(): Response
    {
        $used = Event::query()->whereNotNull('venue')->selectRaw('venue, count(*) as n')->groupBy('venue')->pluck('n', 'venue');

        return Inertia::render('events/venues', [
            'venues' => EventVenue::orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (EventVenue $v) => ['id' => $v->id, 'name' => $v->name, 'events' => (int) ($used[$v->name] ?? 0)])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = EventVenue::create([...$this->validated($request), 'sort_order' => (int) EventVenue::max('sort_order') + 1]);

        Audit::record('settings.venue_added', "Added the venue {$venue->name}", $venue);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$venue->name} added."]);

        return back();
    }

    public function update(Request $request, EventVenue $venue): RedirectResponse
    {
        $venue->update($this->validated($request, $venue));

        Audit::record('settings.venue_updated', "Renamed a venue to {$venue->name}", $venue);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$venue->name} saved."]);

        return back();
    }

    public function destroy(EventVenue $venue): RedirectResponse
    {
        $venue->delete();

        Audit::record('settings.venue_removed', "Removed the venue {$venue->name}", null, ['name' => $venue->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$venue->name} removed."]);

        return back();
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?EventVenue $venue = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('event_venues', 'name')->ignore($venue)],
        ], ['name.unique' => 'That venue is already on the list.']);
    }
}
