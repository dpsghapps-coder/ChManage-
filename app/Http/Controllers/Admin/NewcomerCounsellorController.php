<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\NewcomerCounsellor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The church members who counsel newcomers. A counsellor who has people cannot be removed, only made inactive. */
class NewcomerCounsellorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('newcomers/counsellors', [
            'counsellors' => NewcomerCounsellor::with('member:id,member_number,full_name,mobile,telephone')->withCount([
                'newcomers as people' => fn ($q) => $q->where('status', '!=', 'inactive')->where('stage', '!=', 'member'),
                'newcomers as total',
            ])->get()->map(fn (NewcomerCounsellor $c) => [
                'id' => $c->id,
                'member_id' => $c->member_id,
                'name' => $c->member->full_name,
                'member_number' => $c->member->member_number,
                'phone' => $c->member->mobile ?: $c->member->telephone,
                'is_active' => $c->is_active,
                'people' => $c->people,
                'total' => $c->total,
            ])->sortBy('name')->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id', 'unique:newcomer_counsellors,member_id'],
        ], ['member_id.unique' => 'That member is already a counsellor.', 'member_id.required' => 'Search for a member and pick them.']);

        $counsellor = NewcomerCounsellor::create($data);
        $name = Member::whereKey($data['member_id'])->value('full_name');

        Audit::record('newcomer.counsellor_added', "Added {$name} as a newcomers' counsellor", $counsellor);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} is now a counsellor."]);

        return back();
    }

    public function update(Request $request, NewcomerCounsellor $counsellor): RedirectResponse
    {
        $counsellor->update($request->validate(['is_active' => ['required', 'boolean']]));

        Audit::record('newcomer.counsellor_updated', 'Marked a counsellor '.($counsellor->is_active ? 'active' : 'inactive'), $counsellor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $counsellor->is_active ? 'Counsellor is active.' : 'Counsellor is inactive and no longer offered.']);

        return back();
    }

    public function destroy(NewcomerCounsellor $counsellor): RedirectResponse
    {
        if ($counsellor->newcomers()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This counsellor has people on record. Make them inactive instead.']);

            return back();
        }

        $counsellor->delete();

        Audit::record('newcomer.counsellor_removed', "Removed a newcomers' counsellor", null, ['member_id' => $counsellor->member_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Counsellor removed.']);

        return back();
    }
}
