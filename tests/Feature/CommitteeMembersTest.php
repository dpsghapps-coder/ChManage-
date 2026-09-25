<?php

namespace Tests\Feature;

use App\Models\ChurchSetting;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Member;
use App\Support\CommitteeTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommitteeMembersTest extends TestCase
{
    use RefreshDatabase;

    private function committee(string $name = 'Committee on Finance'): Committee
    {
        return Committee::where('name', $name)->firstOrFail();
    }

    private function member(string $number = 'M1', string $name = 'Mensah Efua'): Member
    {
        return Member::create(['member_number' => $number, 'full_name' => $name, 'status' => 'active', 'sex' => 'female', 'mobile' => '0244000111']);
    }

    private function term(Committee $committee, ?Member $member, array $attributes = []): CommitteeMember
    {
        return CommitteeMember::create(['committee_id' => $committee->id, 'member_id' => $member?->id, 'name' => $member?->full_name ?? 'Guest', 'started_on' => today()->subYear(), ...$attributes]);
    }

    public function test_the_pages_follow_the_committee_permissions(): void
    {
        $committee = $this->committee();
        $term = $this->term($committee, $this->member());
        $viewer = $this->userWith(['committees.view']);

        $this->actingAs($this->userWith([]))->get(route('committees.overview'))->assertForbidden();
        $this->actingAs($viewer)->get(route('committees.overview'))->assertOk();
        $this->actingAs($viewer)->get(route('committees.show', $committee))->assertOk();
        $this->actingAs($viewer)->put(route('committees.rules', $committee), ['term_years' => 3])->assertForbidden();
        $this->actingAs($viewer)->post(route('committees.members.store', $committee), ['name' => 'X', 'started_on' => today()->toDateString()])->assertForbidden();
        $this->actingAs($viewer)->put(route('committees.terms.update', $term), ['started_on' => today()->toDateString()])->assertForbidden();
        $this->actingAs($viewer)->post(route('committees.terms.renew', $term))->assertForbidden();
        $this->actingAs($viewer)->put(route('committees.terms.end', $term))->assertForbidden();
        $this->actingAs($viewer)->delete(route('committees.terms.destroy', $term))->assertForbidden();
    }

    public function test_a_member_or_a_typed_name_is_added_and_the_end_comes_from_the_term_length(): void
    {
        $user = $this->userWith(['committees.view', 'committees.manage']);
        $committee = $this->committee();
        $member = $this->member();

        $this->actingAs($user)->put(route('committees.rules', $committee), ['term_years' => 3, 'max_terms' => 2])->assertSessionHasNoErrors();
        $this->assertSame([3, 2], [$committee->fresh()->term_years, $committee->fresh()->max_terms]);
        $this->actingAs($user)->put(route('committees.rules', $committee), ['term_years' => 11])->assertSessionHasErrors('term_years');

        $this->actingAs($user)->post(route('committees.members.store', $committee), ['member_id' => $member->id, 'position' => ' Committee   Chairperson ', 'started_on' => '2026-01-15'])->assertSessionHasNoErrors();
        $term = CommitteeMember::firstOrFail();
        $this->assertSame(['Mensah Efua', 'Committee Chairperson', '2029-01-15'], [$term->name, $term->position, $term->ends_on->toDateString()]);

        // The end can be given, a member cannot serve twice at once, and a name or a member is needed.
        $this->actingAs($user)->post(route('committees.members.store', $committee), ['name' => 'Rev. Guest', 'started_on' => '2026-01-15', 'ends_on' => '2026-12-31'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-31', CommitteeMember::where('name', 'Rev. Guest')->firstOrFail()->ends_on->toDateString());
        $this->actingAs($user)->post(route('committees.members.store', $committee), ['member_id' => $member->id, 'started_on' => today()->toDateString()])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('committees.members.store', $committee), ['started_on' => today()->toDateString()])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('committees.members.store', $committee), ['name' => 'X'])->assertSessionHasErrors('started_on');
        $this->actingAs($user)->post(route('committees.members.store', $committee), ['name' => 'X', 'started_on' => '2026-05-01', 'ends_on' => '2026-04-01'])->assertSessionHasErrors('ends_on');

        // A committee with no term length leaves the end empty.
        $harvest = $this->committee('Harvest Committee');
        $this->actingAs($user)->post(route('committees.members.store', $harvest), ['name' => 'Someone', 'started_on' => '2026-01-15'])->assertSessionHasNoErrors();
        $this->assertNull(CommitteeMember::where('committee_id', $harvest->id)->firstOrFail()->ends_on);
    }

    public function test_the_committee_page_separates_current_from_past_and_counts_terms(): void
    {
        $user = $this->userWith(['committees.view']);
        $committee = $this->committee();
        $committee->update(['max_terms' => 2]);
        $member = $this->member();
        $this->term($committee, $member, ['started_on' => today()->subYears(9), 'ends_on' => today()->subYears(6)]);
        $this->term($committee, $member, ['started_on' => today()->subYears(6), 'ends_on' => today()->subYears(3)]);
        $third = $this->term($committee, $member, ['started_on' => today()->subYears(3), 'ends_on' => today()->addDays(30)]);
        $this->term($committee, null, ['name' => 'No end', 'ends_on' => null]);

        $this->actingAs($user)->get(route('committees.show', $committee))->assertInertia(fn (Assert $page) => $page
            ->component('committees/members')->has('current', 2)->has('past', 2)
            ->where('current.0.id', $third->id)->where('current.0.term_number', 3)->where('current.0.over_limit', true)->where('current.0.ending', true)
            ->where('current.1.name', 'No end')->where('current.1.ends_on', null)->where('current.1.ending', false)
            ->where('past.0.term_number', 2)->where('past.0.over_limit', false)->where('warnDays', 90));
    }

    public function test_renewing_starts_the_next_term_and_warns_when_over_the_limit(): void
    {
        $user = $this->userWith(['committees.view', 'committees.manage']);
        $committee = $this->committee();
        $committee->update(['term_years' => 3, 'max_terms' => 2]);
        $member = $this->member();
        $first = $this->term($committee, $member, ['position' => 'Committee Member', 'started_on' => today()->subYears(3)->addDays(20), 'ends_on' => today()->addDays(20)]);

        // The next term starts the day this one ends, for the term length, in the same position.
        $this->actingAs($user)->post(route('committees.terms.renew', $first))->assertSessionHasNoErrors();
        $second = $first->successor()->firstOrFail();
        $this->assertSame([today()->addDays(20)->toDateString(), today()->addDays(20)->addYears(3)->toDateString(), 'Committee Member', $first->id],
            [$second->started_on->toDateString(), $second->ends_on->toDateString(), $second->position, $second->renewed_from_id]);
        $this->assertSame(2, CommitteeTerms::termNumber($second));
        $this->assertFalse(CommitteeTerms::overLimit($committee, $second));

        // Only once, and a third term is allowed but flagged.
        $this->actingAs($user)->post(route('committees.terms.renew', $first))->assertSessionHasNoErrors();
        $this->assertSame(1, CommitteeMember::where('renewed_from_id', $first->id)->count());
        $this->actingAs($user)->post(route('committees.terms.renew', $second))->assertSessionHasNoErrors();
        $third = $second->successor()->firstOrFail();
        $this->assertTrue(CommitteeTerms::overLimit($committee, $third));

        // A term that ended long ago is renewed from today, not from the old end date.
        $old = $this->term($committee, $this->member('M2', 'Owusu Kofi'), ['started_on' => today()->subYears(4), 'ends_on' => today()->subYear()]);
        $this->actingAs($user)->post(route('committees.terms.renew', $old));
        $this->assertSame(today()->toDateString(), $old->successor()->firstOrFail()->started_on->toDateString());
    }

    public function test_a_term_can_be_edited_ended_early_and_removed(): void
    {
        $user = $this->userWith(['committees.view', 'committees.manage']);
        $committee = $this->committee();
        $term = $this->term($committee, $this->member(), ['started_on' => '2026-01-01', 'ends_on' => today()->addYear()]);

        $this->actingAs($user)->put(route('committees.terms.update', $term), ['position' => 'Committee Secretary', 'started_on' => '2026-02-01', 'ends_on' => '2028-02-01'])->assertSessionHasNoErrors();
        $this->assertSame(['Committee Secretary', '2028-02-01'], [$term->fresh()->position, $term->fresh()->ends_on->toDateString()]);
        $this->actingAs($user)->put(route('committees.terms.update', $term), ['started_on' => '2026-02-01', 'ends_on' => '2026-01-01'])->assertSessionHasErrors('ends_on');

        $this->actingAs($user)->put(route('committees.terms.end', $term), ['end_note' => 'Moved away'])->assertSessionHasNoErrors();
        $this->assertSame([today()->toDateString(), 'Moved away'], [$term->fresh()->ends_on->toDateString(), $term->fresh()->end_note]);
        $this->actingAs($user)->put(route('committees.terms.end', $term), ['ends_on' => '2025-01-01'])->assertSessionHasErrors('ends_on');
        // Ending today leaves them serving until the end of the day; from tomorrow they are past.
        $this->assertTrue(CommitteeMember::current()->where('id', $term->id)->exists());

        $this->actingAs($user)->delete(route('committees.terms.destroy', $term));
        $this->assertNull($term->fresh());
    }

    public function test_terms_ending_soon_or_just_ended_and_not_renewed_are_flagged(): void
    {
        $user = $this->userWith(['committees.view', 'committees.manage']);
        $committee = $this->committee();
        $soon = $this->term($committee, $this->member('M1', 'Soon One'), ['ends_on' => today()->addDays(45)]);
        $ended = $this->term($committee, $this->member('M2', 'Ended One'), ['ends_on' => today()->subDays(10)]);
        $this->term($committee, $this->member('M3', 'Far Off'), ['ends_on' => today()->addDays(200)]);
        $this->term($committee, $this->member('M4', 'Long Gone'), ['ends_on' => today()->subDays(90)]);
        $this->term($committee, $this->member('M5', 'No End'), ['ends_on' => null]);
        $renewed = $this->term($committee, $this->member('M6', 'Renewed'), ['ends_on' => today()->addDays(10)]);
        $this->term($committee, $renewed->member, ['renewed_from_id' => $renewed->id, 'started_on' => today()->addDays(10), 'ends_on' => today()->addYears(3)]);

        $this->assertSame(['Ended One', 'Soon One'], CommitteeTerms::endingSoon()->pluck('name')->all());
        $this->actingAs($user)->get(route('committees.overview'))->assertInertia(fn (Assert $page) => $page->component('committees/overview')
            ->where('endingTotal', 2)->where('ending.0.name', 'Ended One')->where('ending.0.days_left', -10)->where('ending.1.days_left', 45)
            ->where('committees', fn ($rows) => collect($rows)->firstWhere('id', $committee->id)['ending'] === 2));

        // The menu badge counts them, for those who manage committees only.
        $this->actingAs($user)->get(route('committees.overview'))->assertInertia(fn (Assert $page) => $page->where('badges.committees', 2));
        $this->actingAs($this->userWith(['committees.view']))->get(route('committees.overview'))->assertInertia(fn (Assert $page) => $page->where('badges', []));

        // The warning period is a setting: a shorter one drops the "soon" term.
        ChurchSetting::put(ChurchSetting::TERM_WARNING_DAYS, '30');
        $this->assertSame(['Ended One'], CommitteeTerms::endingSoon()->pluck('name')->all());
    }

    public function test_sample_committee_members_are_created_and_purged_without_touching_real_ones(): void
    {
        foreach (range(1, 10) as $i) {
            Member::create(['member_number' => "M{$i}", 'full_name' => "Member {$i}", 'status' => 'active', 'sex' => 'male', 'date_of_birth' => today()->subYears(40)]);
        }
        $real = $this->term($this->committee('Session'), Member::first(), ['ends_on' => today()->addYear()]);

        $this->artisan('committees:sample')->assertSuccessful();
        $this->assertGreaterThan(30, CommitteeMember::where('is_sample', true)->count());
        $this->assertSame(0, CommitteeMember::where('is_sample', true)->whereColumn('ends_on', '<=', 'started_on')->count());
        $this->assertTrue(CommitteeMember::where('is_sample', true)->whereNotNull('renewed_from_id')->exists());
        $this->assertSame([3, 2], [$this->committee()->term_years, $this->committee()->max_terms]);

        $this->artisan('committees:sample', ['--purge' => true])->assertSuccessful();
        $this->assertSame(0, CommitteeMember::where('is_sample', true)->count());
        $this->assertNotNull($real->fresh());
    }

    public function test_the_members_page_shows_their_committee_terms_read_only(): void
    {
        $member = $this->member();
        $this->term($this->committee(), $member, ['position' => 'Committee Secretary', 'started_on' => '2020-01-01', 'ends_on' => '2023-01-01']);
        $this->term($this->committee('Session'), $member, ['position' => 'Committee Member', 'started_on' => '2024-01-01', 'ends_on' => today()->addYear()]);

        $terms = $this->actingAs($this->userWith(['members.view']))->getJson(route('members.related', $member))->assertOk()->json('related.committee_terms');
        $this->assertSame(['Session', true, 'Committee on Finance', false], [$terms[0]['committee'], $terms[0]['serving'], $terms[1]['committee'], $terms[1]['serving']]);
    }

    public function test_the_warning_period_is_set_on_church_settings(): void
    {
        $user = $this->userWith(['settings.manage']);
        $church = ['church_name' => 'PCG', 'presbytery' => 'Accra West', 'district' => 'Mamprobi', 'congregation' => 'Ebenezer'];

        $this->actingAs($user)->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page->where('settings.term_warning_days', 90));
        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'term_warning_days' => 3])->assertSessionHasErrors('term_warning_days');
        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'term_warning_days' => 120])->assertSessionHasNoErrors();
        $this->assertSame(120, ChurchSetting::termWarningDays());
        $this->actingAs($user)->put(route('admin.church.update'), $church)->assertSessionHasNoErrors();
        $this->assertSame(120, ChurchSetting::termWarningDays());
    }
}
