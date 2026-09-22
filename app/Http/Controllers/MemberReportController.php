<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\YoungMember;
use App\Support\Audit;
use App\Support\MemberReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The printable Member Profile Report, as a PDF that opens in the browser (add ?download=1 to save it instead). */
class MemberReportController extends Controller
{
    public function adult(Request $request, Member $member): Response
    {
        return $this->pdf($request, MemberReport::adult($member), $member->full_name, $member);
    }

    public function young(Request $request, YoungMember $youngMember): Response
    {
        return $this->pdf($request, MemberReport::young($youngMember), $youngMember->fullName(), $youngMember);
    }

    /** @param  array<string, mixed>  $report */
    private function pdf(Request $request, array $report, string $name, Member|YoungMember $subject): Response
    {
        $pdf = Pdf::loadView('reports.member-profile', ['report' => $report, 'generatedBy' => $request->user()?->name])
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);

        Audit::record('member.report', "Generated the profile report of {$name} ({$report['number']})", $subject);

        $file = 'Member_Profile_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['number']).'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }
}
