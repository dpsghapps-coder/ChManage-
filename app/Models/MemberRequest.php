<?php

namespace App\Models;

use App\Support\RequestTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One request a member made from the portal. See {@see RequestTypes} for what each type asks. */
class MemberRequest extends Model
{
    public const STATUSES = ['submitted' => 'Submitted', 'in_review' => 'In review', 'approved' => 'Approved', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];

    /** Still waiting for someone to act on it. */
    public const OPEN = ['submitted', 'in_review'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'changes' => 'array', 'handled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** "REQ-000012". */
    public function reference(): string
    {
        return 'REQ-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** The answers as label => value, for showing to the member and to the person handling it. @return list<array{label: string, value: string}> */
    public function answers(): array
    {
        if ($this->type === RequestTypes::CHANGE_DETAILS) {
            $labels = RequestTypes::changeable();

            return collect($this->changes ?? [])->map(fn ($change, $field) => [
                'label' => $labels[$field]['label'] ?? $field,
                'value' => (filled($change['from'] ?? null) ? $change['from'] : 'nothing').' → '.(filled($change['to'] ?? null) ? $change['to'] : 'nothing'),
            ])->values()->all();
        }

        $fields = collect(RequestTypes::all()[$this->type]['fields'] ?? [])->keyBy('name');

        return collect($this->details ?? [])->filter(fn ($v) => filled($v))->map(fn ($value, $name) => [
            'label' => $fields[$name]['label'] ?? $name,
            'value' => (string) $value,
        ])->values()->all();
    }
}
