<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberRequest;
use App\Support\Audit;
use App\Support\RequestAccess;
use App\Support\RequestTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The inbox of requests members make from the portal. Each person sees only the types they handle (see
 * {@see RequestAccess}). A request to change details alters the member's record only when it is approved.
 */
class MemberRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $types = $this->handled($request);
        $status = $request->input('status', 'open');

        $requests = MemberRequest::query()
            ->with('member:id,member_number,full_name,mobile')
            ->whereIn('type', $types)
            ->when($request->input('type') && in_array($request->input('type'), $types, true), fn ($q) => $q->where('type', $request->input('type')))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', MemberRequest::OPEN))
            ->when(array_key_exists($status, MemberRequest::STATUSES), fn ($q) => $q->where('status', $status))
            ->orderByRaw("status in ('submitted','in_review') desc")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MemberRequest $r) => $this->row($r));

        return Inertia::render('requests/index', [
            'requests' => $requests,
            'filters' => ['status' => $status, 'type' => $request->input('type', '')],
            'types' => collect($types)->map(fn ($t) => ['key' => $t, 'label' => RequestTypes::label($t)])->values(),
            'statuses' => MemberRequest::STATUSES,
            'openCount' => MemberRequest::whereIn('type', $types)->whereIn('status', MemberRequest::OPEN)->count(),
        ]);
    }

    public function show(Request $request, MemberRequest $memberRequest): Response
    {
        $this->authorizeType($request, $memberRequest);
        $memberRequest->load('member:id,member_number,full_name,mobile,email', 'handler:id,first_name,last_name,username');

        $changes = collect($memberRequest->changes ?? [])->map(fn ($change, $field) => [
            'field' => $field,
            'label' => RequestTypes::changeable()[$field]['label'] ?? $field,
            'from' => $change['from'] ?? null,
            'to' => $change['to'] ?? null,
            // What the record says today: it may have changed since the member asked.
            'now' => $memberRequest->member->{$field} instanceof \DateTimeInterface ? $memberRequest->member->{$field}->format('Y-m-d') : $memberRequest->member->{$field},
        ])->values();

        return Inertia::render('requests/show', [
            'request' => [
                ...$this->row($memberRequest),
                'answers' => $memberRequest->answers(),
                'changes' => $changes,
                'member_note' => $memberRequest->member_note,
                'response' => $memberRequest->response,
                'handled_by' => $memberRequest->handler?->name,
                'handled_at' => $memberRequest->handled_at?->toDateTimeString(),
                'member_email' => $memberRequest->member->email,
            ],
        ]);
    }

    /** Marks a request as being looked at, so others can see somebody has picked it up. */
    public function review(Request $request, MemberRequest $memberRequest): RedirectResponse
    {
        $this->authorizeType($request, $memberRequest);

        if ($memberRequest->status === 'submitted') {
            $memberRequest->update(['status' => 'in_review', 'handled_by' => $request->user()->id]);
            Audit::record('request.review_started', "Started on {$memberRequest->reference()}", $memberRequest);
        }

        return back();
    }

    public function decide(Request $request, MemberRequest $memberRequest): RedirectResponse
    {
        $this->authorizeType($request, $memberRequest);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'declined'])],
            'response' => [$request->input('decision') === 'declined' ? 'required' : 'nullable', 'string', 'max:2000'],
        ], ['response.required' => 'Say why it is declined: the member will read this.']);

        if (! $memberRequest->isOpen()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That request has already been dealt with.']);

            return back();
        }

        DB::transaction(function () use ($request, $memberRequest, $data) {
            if ($data['decision'] === 'approved' && $memberRequest->type === RequestTypes::CHANGE_DETAILS) {
                $this->applyChanges($memberRequest);
            }

            $memberRequest->update([
                'status' => $data['decision'],
                'response' => filled($data['response'] ?? null) ? $data['response'] : null,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);
        });

        Audit::record("request.{$data['decision']}", ucfirst($data['decision'])." {$memberRequest->reference()} (".RequestTypes::label($memberRequest->type).')', $memberRequest, ['member_id' => $memberRequest->member_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['decision'] === 'approved' ? 'Approved.' : 'Declined.']);

        return to_route('requests.show', $memberRequest);
    }

    /** Writes the approved changes to the member's record, and leaves a note of them in the audit log. */
    private function applyChanges(MemberRequest $memberRequest): void
    {
        $member = Member::findOrFail($memberRequest->member_id);
        $allowed = RequestTypes::changeable();
        $values = collect($memberRequest->changes ?? [])->filter(fn ($c, $field) => isset($allowed[$field]))->map(fn ($c) => $c['to'] ?? null)->all();

        if ($values === []) {
            return;
        }

        $before = $member->only(array_keys($values));
        $member->update($values);

        Audit::record('member.updated_from_request', "Updated {$member->full_name} from {$memberRequest->reference()}", $member, ['from' => $before, 'to' => $values]);
    }

    /** @return list<string> */
    private function handled(Request $request): array
    {
        $types = RequestAccess::types($request->user());
        abort_if($types === [], 403);

        return $types;
    }

    private function authorizeType(Request $request, MemberRequest $memberRequest): void
    {
        abort_unless(RequestAccess::handles($request->user(), $memberRequest->type), 403);
    }

    /** @return array<string, mixed> */
    private function row(MemberRequest $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference(),
            'type' => $r->type,
            'type_label' => RequestTypes::label($r->type),
            'status' => $r->status,
            'member' => ['id' => $r->member->id, 'member_number' => $r->member->member_number, 'full_name' => $r->member->full_name, 'mobile' => $r->member->mobile],
            'submitted_at' => $r->created_at->toDateTimeString(),
            'summary' => collect($r->answers())->take(2)->map(fn ($a) => $a['value'])->implode(' · '),
        ];
    }
}
