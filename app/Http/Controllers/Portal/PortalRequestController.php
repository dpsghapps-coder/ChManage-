<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberRequest;
use App\Support\Audit;
use App\Support\RequestTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** A member's own requests: making one, and withdrawing one that nobody has started on. */
class PortalRequestController extends Controller
{
    public function create(string $type): Response
    {
        $types = RequestTypes::all();
        abort_unless(isset($types[$type]), 404);

        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return Inertia::render('portal/request', [
            'type' => $type,
            'definition' => $types[$type],
            'changeable' => collect(RequestTypes::changeable())->map(fn ($meta) => $meta['label'])->all(),
            'current' => RequestTypes::currentValues($member),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, RequestTypes::keys(), true), 404);

        /** @var Member $member */
        $member = Auth::guard('member')->user();

        $row = ['member_id' => $member->id, 'type' => $type, 'status' => 'submitted'];

        if ($type === RequestTypes::CHANGE_DETAILS) {
            $changeable = RequestTypes::changeable();
            $data = $request->validate([
                'values' => ['present', 'array'],
                ...collect($changeable)->mapWithKeys(fn ($meta, $field) => ["values.{$field}" => $meta['rules']])->all(),
                'note' => ['nullable', 'string', 'max:2000'],
            ]);

            $current = RequestTypes::currentValues($member);
            $changes = [];

            foreach ($changeable as $field => $meta) {
                if (! array_key_exists($field, $data['values'])) {
                    continue;
                }

                $to = filled($data['values'][$field]) ? trim((string) $data['values'][$field]) : null;
                $from = filled($current[$field] ?? null) ? (string) $current[$field] : null;

                if ($to !== $from) {
                    $changes[$field] = ['from' => $from, 'to' => $to];
                }
            }

            // Names and date of birth are not on the form: those need a word with the office, or the note below.
            if ($changes === [] && ! filled($data['note'] ?? null)) {
                throw ValidationException::withMessages(['values' => 'Change at least one detail, or say in the note what needs correcting.']);
            }

            $row += ['changes' => $changes, 'member_note' => $data['note'] ?? null];
        } else {
            $data = $request->validate(RequestTypes::rules($type));
            $row += ['details' => array_filter($data['details'] ?? [], fn ($v) => filled($v))];
        }

        $made = MemberRequest::create($row);

        Audit::record('portal.request_made', "{$member->full_name} made a request: ".RequestTypes::label($type), $made, ['type' => $type], null);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Your request was sent ({$made->reference()}). You will see the reply here."]);

        return to_route('portal.home', ['tab' => 'requests']);
    }

    public function cancel(MemberRequest $memberRequest): RedirectResponse
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        // Someone else's request is not found, rather than forbidden: its existence is not for this member to know.
        abort_unless($memberRequest->member_id === $member->id, 404);

        if ($memberRequest->status !== 'submitted') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That request is already being handled, so it can no longer be withdrawn.']);

            return to_route('portal.home', ['tab' => 'requests']);
        }

        $memberRequest->update(['status' => 'cancelled']);
        Audit::record('portal.request_withdrawn', "{$member->full_name} withdrew {$memberRequest->reference()}", $memberRequest, [], null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request withdrawn.']);

        return to_route('portal.home', ['tab' => 'requests']);
    }
}
