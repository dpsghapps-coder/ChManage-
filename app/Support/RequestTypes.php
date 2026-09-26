<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventVenue;
use App\Models\Member;
use App\Rules\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * The requests a member can make from the portal, and the questions each one asks. The portal form is drawn from
 * {@see self::all()} and the answers are checked against the same list, so the two can never disagree.
 * Kinds: text, textarea, select, date, time, number.
 */
class RequestTypes
{
    public const CHANGE_DETAILS = 'change_details';

    /** Member fields a member may ask to change, with how each is checked. @return array<string, array{label: string, rules: array<int, mixed>}> */
    public static function changeable(): array
    {
        return [
            'title' => ['label' => 'Title', 'rules' => ['nullable', 'string', 'max:20']],
            'mobile' => ['label' => 'Mobile', 'rules' => ['nullable', new PhoneNumber]],
            'telephone' => ['label' => 'Telephone', 'rules' => ['nullable', new PhoneNumber]],
            'email' => ['label' => 'Email', 'rules' => ['nullable', 'email', 'max:150']],
            'residence' => ['label' => 'Residence', 'rules' => ['nullable', 'string', 'max:150']],
            'hometown' => ['label' => 'Hometown', 'rules' => ['nullable', 'string', 'max:150']],
            'marital_status' => ['label' => 'Marital status', 'rules' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])]],
            'marriage_type' => ['label' => 'Marriage type', 'rules' => ['nullable', 'string', 'max:50']],
            'marriage_date' => ['label' => 'Marriage date', 'rules' => ['nullable', 'date', 'before_or_equal:today']],
            'marriage_church' => ['label' => 'Married at', 'rules' => ['nullable', 'string', 'max:150']],
            'spouse_name' => ['label' => 'Spouse', 'rules' => ['nullable', 'string', 'max:150']],
            'previous_congregation' => ['label' => 'Previous congregation', 'rules' => ['nullable', 'string', 'max:150']],
        ];
    }

    /** @return array<string, array{label: string, description: string, fields: list<array<string, mixed>>}> */
    public static function all(): array
    {
        $events = Event::query()->where('status', 'scheduled')->whereDate('starts_on', '>=', today())->orderBy('starts_on')->limit(40)->get(['title', 'starts_on'])
            ->map(fn (Event $e) => $e->title.' ('.$e->starts_on->format('j M Y').')')->all();
        $venues = EventVenue::orderBy('sort_order')->orderBy('name')->pluck('name')->all();

        return [
            self::CHANGE_DETAILS => [
                'label' => 'Change my details',
                'description' => 'Ask for your contact or family details to be corrected. The church office checks it before your record changes.',
                'fields' => [],
            ],
            'transfer' => [
                'label' => 'Transfer to another congregation',
                'description' => 'Apply for a transfer letter to another congregation.',
                'fields' => [
                    ['name' => 'destination', 'label' => 'Congregation you are moving to', 'kind' => 'text', 'required' => true],
                    ['name' => 'place', 'label' => 'Town or city', 'kind' => 'text'],
                    ['name' => 'move_date', 'label' => 'When are you moving?', 'kind' => 'date'],
                    ['name' => 'reason', 'label' => 'Reason for the transfer', 'kind' => 'textarea', 'required' => true],
                ],
            ],
            'issue' => [
                'label' => 'Report an issue',
                'description' => 'Tell the church about a problem, an error in your record, or something that needs attention.',
                'fields' => [
                    ['name' => 'category', 'label' => 'What is it about?', 'kind' => 'select', 'required' => true, 'options' => ['An error in my record', 'Something at church', 'A pastoral concern', 'The portal or website', 'Something else']],
                    ['name' => 'subject', 'label' => 'Subject', 'kind' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Tell us more', 'kind' => 'textarea', 'required' => true],
                ],
            ],
            'discipleship' => [
                'label' => 'Discipleship course',
                'description' => 'Register for a discipleship or Bible study course.',
                'fields' => [
                    ['name' => 'course', 'label' => 'Which course?', 'kind' => 'text', 'required' => true],
                    ['name' => 'note', 'label' => 'Anything we should know?', 'kind' => 'textarea'],
                ],
            ],
            'baptism' => [
                'label' => 'Baptism',
                'description' => 'Ask for baptism for yourself, your child or someone else.',
                'fields' => [
                    ['name' => 'candidate', 'label' => 'Who is it for?', 'kind' => 'select', 'required' => true, 'options' => ['Myself', 'My child', 'Someone else']],
                    ['name' => 'candidate_name', 'label' => 'Name of the person', 'kind' => 'text', 'required' => true],
                    ['name' => 'candidate_dob', 'label' => 'Date of birth', 'kind' => 'date'],
                    ['name' => 'preferred_date', 'label' => 'Preferred date', 'kind' => 'date'],
                    ['name' => 'note', 'label' => 'Anything else', 'kind' => 'textarea'],
                ],
            ],
            'naming' => [
                'label' => 'Naming ceremony',
                'description' => 'Ask for a naming ceremony for a new baby.',
                'fields' => [
                    ['name' => 'child_name', 'label' => "Baby's name", 'kind' => 'text', 'required' => true],
                    ['name' => 'child_dob', 'label' => 'Date of birth', 'kind' => 'date', 'required' => true],
                    ['name' => 'father_name', 'label' => "Father's name", 'kind' => 'text'],
                    ['name' => 'mother_name', 'label' => "Mother's name", 'kind' => 'text'],
                    ['name' => 'preferred_date', 'label' => 'Preferred date', 'kind' => 'date'],
                    ['name' => 'note', 'label' => 'Anything else', 'kind' => 'textarea'],
                ],
            ],
            'marriage' => [
                'label' => 'Marriage',
                'description' => 'Ask to be married in the church or to have a marriage blessed.',
                'fields' => [
                    ['name' => 'partner_name', 'label' => "Your partner's name", 'kind' => 'text', 'required' => true],
                    ['name' => 'partner_congregation', 'label' => "Partner's congregation", 'kind' => 'text'],
                    ['name' => 'kind', 'label' => 'What are you asking for?', 'kind' => 'select', 'required' => true, 'options' => ['A church wedding', 'The blessing of a customary marriage']],
                    ['name' => 'intended_date', 'label' => 'Intended date', 'kind' => 'date'],
                    ['name' => 'note', 'label' => 'Anything else', 'kind' => 'textarea'],
                ],
            ],
            'bus' => [
                'label' => 'Church bus',
                'description' => 'Book seats on the church bus for an event.',
                'fields' => [
                    ['name' => 'event', 'label' => 'Which event?', 'kind' => 'select', 'required' => true, 'options' => [...$events, 'Another trip (say which below)']],
                    ['name' => 'seats', 'label' => 'How many seats?', 'kind' => 'number', 'required' => true],
                    ['name' => 'pickup', 'label' => 'Pick-up place', 'kind' => 'text', 'required' => true],
                    ['name' => 'note', 'label' => 'Anything else', 'kind' => 'textarea'],
                ],
            ],
            'facility' => [
                'label' => 'Use a church facility',
                'description' => 'Ask to use a chapel, the compound or another church space.',
                'fields' => [
                    ['name' => 'facility', 'label' => 'Which facility?', 'kind' => 'select', 'required' => true, 'options' => $venues],
                    ['name' => 'date', 'label' => 'Date', 'kind' => 'date', 'required' => true],
                    ['name' => 'starts_at', 'label' => 'From', 'kind' => 'time', 'required' => true],
                    ['name' => 'ends_at', 'label' => 'To', 'kind' => 'time'],
                    ['name' => 'purpose', 'label' => 'What is it for?', 'kind' => 'text', 'required' => true],
                    ['name' => 'expected', 'label' => 'How many people?', 'kind' => 'number'],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }

    /** Validation rules for the answers to one type, keyed `details.<field>`. @return array<string, array<int, mixed>> */
    public static function rules(string $type): array
    {
        $rules = [];

        foreach (self::all()[$type]['fields'] as $field) {
            $rule = [$field['required'] ?? false ? 'required' : 'nullable'];
            array_push($rule, ...match ($field['kind']) {
                'select' => [Rule::in($field['options'])],
                'date' => ['date'],
                'time' => ['date_format:H:i'],
                'number' => ['integer', 'min:1', 'max:1000'],
                'textarea' => ['string', 'max:2000'],
                default => ['string', 'max:200'],
            });
            $rules["details.{$field['name']}"] = $rule;
        }

        return $rules;
    }

    /** What the member has now for each field they may ask to change. @return array<string, mixed> */
    public static function currentValues(Member $member): array
    {
        return collect(self::changeable())->map(function ($meta, $field) use ($member) {
            $value = $member->{$field};

            return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        })->all();
    }
}
