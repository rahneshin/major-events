@extends('layouts.share')

@section('title', $data['name'].' — '.$data['event'])

@section('content')
    <header class="rounded-3xl bg-gradient-to-l from-pool-700 to-pool-500 p-6 text-white shadow-xl">
        <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold">{{ $data['status'] }}</span>
        <h1 class="mt-2 text-2xl font-bold">{{ $data['name'] }}</h1>
        <p class="mt-1 text-sm text-white/80">{{ $data['event'] }} · {{ $data['gender'] }} · {{ $data['participant'] }}</p>
    </header>

    @php($hasContent = $data['standings'] !== [] || $data['rounds'] !== [] || $data['heats'] !== [] || $data['final_ranking'] !== [])

    @unless ($hasContent)
        <div class="rounded-2xl border border-ink-100 bg-white px-6 py-12 text-center text-sm text-ink-500">{{ __('publicpage.not_started') }}</div>
    @endunless

    @if ($data['final_ranking'] !== [])
        <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold">{{ __('publicpage.final_ranking') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($data['final_ranking'] as $row)
                            <tr class="border-b border-ink-50">
                                <td class="w-12 py-2 tabular-nums font-bold">{{ fa_num($row['rank']) }}</td>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="text-xs text-ink-500">{{ $row['affiliation'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @foreach ($data['standings'] as $table)
        <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold">{{ __('publicpage.standings') }} · {{ $table['stage'] }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-100 text-xs text-ink-400">
                            <th class="py-2 text-start">{{ __('competition.col.rank') }}</th>
                            <th class="text-start">{{ __('competition.col.name') }}</th>
                            @foreach (['played', 'won', 'drawn', 'lost', 'for', 'against', 'diff', 'points'] as $col)
                                <th class="text-center">{{ __('competition.col.'.$col) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr class="border-b border-ink-50">
                                <td class="py-2 tabular-nums">{{ fa_num($row['rank']) }}@if ($row['tied'])<span title="{{ __('competition.tied') }}">=</span>@endif</td>
                                <td class="font-medium">
                                    {{ $row['name'] }}
                                    @if ($row['affiliation'])<span class="block text-[11px] font-normal text-ink-400">{{ $row['affiliation'] }}</span>@endif
                                </td>
                                @foreach (['played', 'won', 'drawn', 'lost', 'for', 'against', 'diff', 'points'] as $col)
                                    <td class="text-center tabular-nums {{ $col === 'points' ? 'font-bold' : '' }}">{{ fa_num($row[$col]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @foreach ($data['rounds'] as $stageBlock)
        <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold">{{ __('publicpage.schedule') }} · {{ $stageBlock['stage'] }}</h2>
            <div class="space-y-2">
                @foreach ($stageBlock['rounds'] as $round)
                    <details class="rounded-xl border border-ink-100" @if ($loop->last) open @endif>
                        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-ink-700">{{ __('publicpage.round', ['round' => fa_num($round['round'])]) }}</summary>
                        <ul class="divide-y divide-ink-50 text-sm">
                            @foreach ($round['matches'] as $m)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                    <span class="font-medium">{{ $m['a'] }} <span class="text-ink-300">—</span> {{ $m['b'] }}</span>
                                    <span class="text-xs text-ink-500">
                                        @if ($m['score_a'] !== null && $m['score_b'] !== null)
                                            <span class="font-bold tabular-nums text-ink-900">{{ fa_num($m['score_a']) }} : {{ fa_num($m['score_b']) }}</span>
                                        @elseif ($m['walkover'])
                                            {{ __('publicpage.walkover') }}@if ($m['winner']) · {{ $m['winner'] }}@endif
                                        @elseif ($m['status'])
                                            {{ $m['status'] }}
                                        @endif
                                        @if ($m['when']) · {{ fa_num($m['when']) }} @endif
                                        @if ($m['court']) · {{ $m['court'] }} @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    @foreach ($data['heats'] as $stageBlock)
        <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold">{{ __('publicpage.heats') }} · {{ $stageBlock['stage'] }}</h2>
            <div class="space-y-4">
                @foreach ($stageBlock['heats'] as $heat)
                    <div>
                        <p class="mb-1 text-xs font-semibold text-ink-600">
                            {{ $heat['name'] }}
                            @if ($heat['when']) · {{ fa_num($heat['when']) }} @endif
                            @unless ($heat['confirmed']) <span class="text-ink-400">· {{ __('publicpage.pending_heat') }}</span> @endunless
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-ink-100 text-xs text-ink-400">
                                        <th class="py-1.5 text-start">{{ __('ranked.col.lane') }}</th>
                                        <th class="text-start">{{ __('ranked.col.rank') }}</th>
                                        <th class="text-start">{{ __('ranked.col.name') }}</th>
                                        <th class="text-start">{{ __('ranked.col.value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($heat['rows'] as $row)
                                        <tr class="border-b border-ink-50">
                                            <td class="py-1.5 tabular-nums">{{ $row['lane'] !== null ? fa_num($row['lane']) : '—' }}</td>
                                            <td class="tabular-nums font-bold">{{ $row['rank'] !== null ? fa_num($row['rank']) : '—' }}</td>
                                            <td class="font-medium">
                                                {{ $row['name'] }}
                                                @if ($row['affiliation'])<span class="block text-[11px] font-normal text-ink-400">{{ $row['affiliation'] }}</span>@endif
                                            </td>
                                            <td class="tabular-nums">
                                                {{ $row['value'] !== null ? fa_num($row['value']) : ($row['status'] ?? '—') }}
                                                @if ($row['advanced']) <span class="ms-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">{{ __('publicpage.advanced') }}</span> @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
