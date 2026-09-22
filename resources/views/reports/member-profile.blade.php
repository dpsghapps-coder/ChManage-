<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Member Profile Report – {{ $report['number'] }}</title>
    <style>
        @page { margin: 58px 34px 46px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; margin: 0; }
        table { width: 100%; border-collapse: collapse; }

        .page-head { position: fixed; top: -40px; left: 0; right: 0; text-align: right; font-size: 8px; color: #999;
            border-bottom: 0.5px solid #e4e4e7; padding-bottom: 4px; }

        .head td { vertical-align: middle; }
        .head { text-align: center; border-bottom: 3px solid #5b21b6; padding-bottom: 8px; margin-bottom: 12px; }
        .head .logo { width: 46px; height: auto; }
        .head h1 { margin: 0; font-size: 16px; color: #5b21b6; }
        .head p { margin: 2px 0 0; font-size: 10px; color: #6d28d9; }
        .title { text-align: center; margin-bottom: 12px; }
        .title h2 { margin: 0; font-size: 13px; letter-spacing: 1px; }
        .title p { margin: 2px 0 0; font-size: 9px; color: #777; }
        .who td { vertical-align: middle; }
        .photo { width: 78px; height: 78px; border: 1px solid #ccc; }
        .photo.placeholder { display: block; box-sizing: border-box; line-height: 76px; text-align: center;
            font-size: 22px; font-weight: bold; color: #7c3aed; background: #ede9fe; }
        .name { font-size: 15px; font-weight: bold; }
        .muted { color: #666; }
        .block { margin-top: 12px; page-break-inside: avoid; }
        .block h3 { margin: 0 0 3px; padding: 4px 8px; font-size: 10px; letter-spacing: 0.5px; text-transform: uppercase; background: #ede9fe; color: #5b21b6; }
        .rows { table-layout: fixed; }
        .rows col.label { width: 17%; }
        .rows col.value { width: 33%; }
        .rows td { border: 1px solid #d4d4d8; padding: 4px 8px; word-wrap: break-word; }
        .rows td.label { font-weight: bold; background: #f7f7f8; }
        .grid th { border: 1px solid #d4d4d8; padding: 4px 8px; background: #f7f7f8; text-align: left;
            font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        .grid td { border: 1px solid #d4d4d8; padding: 4px 8px; }
        .signatures { margin-top: 42px; }
        .signatures td { width: 50%; text-align: center; font-size: 9px; color: #777; }
        .line { width: 190px; border-bottom: 1px solid #888; margin: 0 auto 4px; height: 26px; }
        .date { margin-top: 10px; }
        .foot { position: fixed; bottom: -34px; left: 0; right: 0; text-align: center; font-size: 8px; color: #999; }
        .foot .confidential { margin-bottom: 2px; }
        .foot .page-number:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
    <div class="page-head">{{ $report['name'] }} &nbsp;·&nbsp; {{ $report['number'] }}</div>

    <table class="head">
        <tr>
            @if ($report['logo'])
                <td style="width: 60px"><img class="logo" src="{{ $report['logo'] }}" alt=""></td>
            @endif
            <td>
                <h1>{{ $report['church']['name'] ?: 'Church' }}</h1>
                @if ($report['church']['congregation'])
                    <p>{{ $report['church']['congregation'] }}</p>
                @endif
                @if ($report['church']['presbytery'] || $report['church']['district'])
                    <p>{{ collect([$report['church']['presbytery'] ? $report['church']['presbytery'].' Presbytery' : null, $report['church']['district'] ? $report['church']['district'].' District' : null])->filter()->implode('  |  ') }}</p>
                @endif
            </td>
            @if ($report['logo'])
                <td style="width: 60px"></td>
            @endif
        </tr>
    </table>

    <div class="title">
        <h2>MEMBER PROFILE REPORT</h2>
        <p>Generated: {{ $report['generated'] }}@if ($generatedBy) by {{ $generatedBy }}@endif</p>
    </div>

    <table class="who">
        <tr>
            <td style="width: 90px">
                @if ($report['photo'])
                    <img class="photo" src="{{ $report['photo'] }}" alt="">
                @else
                    <div class="photo placeholder">{{ $report['initials'] }}</div>
                @endif
            </td>
            <td>
                <div class="name">{{ $report['name'] }}</div>
                <div class="muted">{{ $report['number'] }}@if ($report['status']) &nbsp;·&nbsp; {{ $report['status'] }}@endif</div>
            </td>
        </tr>
    </table>

    @foreach ($report['blocks'] as $block)
        <div class="block">
            <h3>{{ $block['title'] }}</h3>
            @if ($block['type'] === 'rows')
                <table class="rows">
                    <colgroup>
                        <col class="label"><col class="value"><col class="label"><col class="value">
                    </colgroup>
                    @foreach ($block['rows'] as $line)
                        <tr>
                            @foreach ($line as [$label, $value])
                                <td class="label">{{ $label }}</td>
                                <td @if (count($line) === 1) colspan="3" @endif>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @else
                <table class="grid">
                    <thead>
                        <tr>@foreach ($block['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($block['rows'] as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <table class="signatures">
        <tr>
            <td><div class="line"></div>Member Signature<div class="date">Date: ________________</div></td>
            <td><div class="line"></div>Authorised Signature<div class="date">Date: ________________</div></td>
        </tr>
    </table>

    <div class="foot">
        <div class="confidential">Confidential — for internal church use only</div>
        <div>{{ $report['church']['congregation'] ?: $report['church']['name'] }} · {{ $report['number'] }} · <span class="page-number"></span></div>
    </div>
</body>
</html>
