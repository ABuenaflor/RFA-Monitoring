<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,
                 initial-scale=1.0"
    >

    <title>
        RFA Monitoring Report
    </title>


    <style>

        /*
        |--------------------------------------------------------------------------
        | EXACT 8 × 13 LANDSCAPE PAPER
        |--------------------------------------------------------------------------
        |
        | 13 inches wide
        | 8 inches high
        |
        | This is long bond / folio size,
        | NOT US Legal 8.5 × 14.
        |
        */

        @page {
            size: 13in 8in;
            margin: 0.22in;
        }


        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #111827;

            background: #ffffff;
        }


        body {
            font-size: 7px;
        }


        /*
        |--------------------------------------------------------------------------
        | REPORT HEADER
        |--------------------------------------------------------------------------
        */

        .report-title {
            margin: 0;

            text-align: center;

            font-size: 16px;
            font-weight: 700;

            letter-spacing: 0.04em;
        }


        .report-subtitle {
            margin-top: 3px;

            text-align: center;

            font-size: 9px;
            font-weight: 700;
        }


        .report-meta {
            margin-top: 4px;

            text-align: center;

            font-size: 7px;
            color: #475569;
        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary-table {
            width: 100%;

            margin-top: 8px;

            border-collapse: collapse;
        }


        .summary-table td {
            border: 1px solid #94a3b8;

            padding: 3px 4px;

            text-align: center;
        }


        .summary-label {
            display: block;

            margin-bottom: 2px;

            font-size: 6px;
            font-weight: 700;

            text-transform: uppercase;

            color: #475569;
        }


        .summary-value {
            font-size: 9px;
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | DETAIL TABLE
        |--------------------------------------------------------------------------
        */

        .report-table {
            width: 100%;

            margin-top: 8px;

            border-collapse: collapse;

            table-layout: fixed;
        }


        .report-table th,
        .report-table td {
            border: 1px solid #334155;

            padding: 2px;

            vertical-align: top;

            line-height: 1.15;

            overflow-wrap: anywhere;
        }


        /*
        |--------------------------------------------------------------------------
        | GROUP HEADERS
        |--------------------------------------------------------------------------
        */

        .group-case {
            background: #dbeafe;
        }


        .group-process {
            background: #e0e7ff;
        }


        .group-conference {
            background: #ede9fe;
        }


        .group-disposition {
            background: #dcfce7;
        }


        .group-benefit {
            background: #fef3c7;
        }


        .group-heading {
            text-align: center;

            font-size: 6px;
            font-weight: 700;

            text-transform: uppercase;
        }


        .column-heading {
            background: #f8fafc;

            text-align: center;

            font-size: 5.5px;
            font-weight: 700;

            text-transform: uppercase;
        }


        /*
        |--------------------------------------------------------------------------
        | COLUMN WIDTHS
        |--------------------------------------------------------------------------
        */

        .w-docket {
            width: 5%;
        }

        .w-party {
            width: 7%;
        }

        .w-date {
            width: 4.5%;
        }

        .w-status {
            width: 5%;
        }

        .w-name {
            width: 6%;
        }

        .w-pct {
            width: 6%;
        }

        .w-benefit {
            width: 6%;
        }


        /*
        |--------------------------------------------------------------------------
        | TEXT HELPERS
        |--------------------------------------------------------------------------
        */

        .center {
            text-align: center;
        }


        .right {
            text-align: right;
        }


        .nowrap {
            white-space: nowrap;
        }


        .muted {
            color: #64748b;
        }


        .strong {
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | PCT EMPHASIS
        |--------------------------------------------------------------------------
        */

        .pct-beyond {
            font-weight: 700;
            color: #991b1b;
        }


        .pct-within {
            font-weight: 700;
            color: #166534;
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT BEHAVIOR
        |--------------------------------------------------------------------------
        */

        thead {
            display: table-header-group;
        }


        tfoot {
            display: table-footer-group;
        }


        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }


        .no-print {
            margin-bottom: 8px;

            text-align: right;
        }


        .print-button {
            border: 0;

            border-radius: 4px;

            background: #0f172a;

            padding: 7px 12px;

            color: #ffffff;

            font-size: 12px;

            cursor: pointer;
        }


        @media print {

            .no-print {
                display: none !important;
            }

            html,
            body {
                width: 13in;
            }

        }

    </style>

</head>


<body>


    {{-- ========================================================= --}}
    {{-- PRINT CONTROL --}}
    {{-- ========================================================= --}}

    <div class="no-print">

        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            Print Report
        </button>

    </div>


    {{-- ========================================================= --}}
    {{-- REPORT TITLE --}}
    {{-- ========================================================= --}}

    @php
        $settings = app(\App\Services\SettingsService::class);
    @endphp


    <h1 class="report-title">
        {{ $settings->get(\App\Support\SystemSettings::ORGANIZATION_NAME) }}
        {{ now()->format('Y') }}
    </h1>


    <div class="report-subtitle">

        {{ $settings->get(\App\Support\SystemSettings::ORGANIZATION_UNIT) }}

        @if ($selectedSeado !== '')

            —
            {{ $selectedSeado }}

        @endif

    </div>


    <div class="report-meta">

        As of
        {{
            now()->format(
                'F d, Y h:i A'
            )
        }}

        &nbsp; | &nbsp;

        Date Filed:
        {{
            request('date_from')
            ?: 'Beginning'
        }}

        to

        {{
            request('date_to')
            ?: 'Present'
        }}

    </div>


    {{-- ========================================================= --}}
    {{-- SUMMARY ROW --}}
    {{-- ========================================================= --}}

    <table class="summary-table">

        <tr>

            <td>
                <span class="summary-label">
                    Total RFAs
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $summary['total']
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Pending
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $summary['pending']
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Ongoing
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $summary['ongoing']
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Disposed
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $summary['disposed']
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Disposed Within PCT
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $dispositionPctSummary[
                                'disposed_within'
                            ]
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Disposed Beyond PCT
                </span>

                <span class="summary-value">
                    {{
                        number_format(
                            $dispositionPctSummary[
                                'disposed_beyond'
                            ]
                        )
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    PCT Compliance
                </span>

                <span class="summary-value">
                    {{
                        $dispositionPctSummary[
                            'compliance_rate'
                        ] !== null
                            ? $dispositionPctSummary[
                                'compliance_rate'
                            ].'%'
                            : '—'
                    }}
                </span>
            </td>


            <td>
                <span class="summary-label">
                    Monetary Benefit
                </span>

                <span class="summary-value">
                    ₱{{
                        number_format(
                            $summary[
                                'monetary_benefit'
                            ],
                            2
                        )
                    }}
                </span>
            </td>

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- CONFERENCE SUMMARY --}}
    {{-- ========================================================= --}}

    <table class="summary-table">

        <tr>

            @foreach (
                [
                    'No Conference' =>
                        $conferenceSummary[
                            'no_conference'
                        ],

                    'Reached 1st' =>
                        $conferenceSummary[
                            'first_conference'
                        ],

                    '1st Only' =>
                        $conferenceSummary[
                            'first_only'
                        ],

                    'Reached 2nd' =>
                        $conferenceSummary[
                            'second_conference'
                        ],

                    'Disposed After 1st' =>
                        $conferenceSummary[
                            'disposed_after_first'
                        ],

                    'Disposed After 2nd' =>
                        $conferenceSummary[
                            'disposed_after_second'
                        ],

                    'Conference Issues' =>
                        $conferenceSummary[
                            'data_issues'
                        ],
                ]
                as $label => $value
            )

                <td>
                    <span class="summary-label">
                        {{ $label }}
                    </span>

                    <span class="summary-value">
                        {{
                            number_format(
                                $value
                            )
                        }}
                    </span>
                </td>

            @endforeach

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- EXCEL-STYLE DETAIL TABLE --}}
    {{-- ========================================================= --}}

    <table class="report-table">

        <thead>

            {{-- GROUPED HEADER --}}

            <tr>

                <th
                    colspan="6"
                    class="
                        group-heading
                        group-case
                    "
                >
                    Case Information
                </th>

                <th
                    colspan="6"
                    class="
                        group-heading
                        group-process
                    "
                >
                    Processing / PCT
                </th>

                <th
                    colspan="3"
                    class="
                        group-heading
                        group-conference
                    "
                >
                    SEADO / Conference
                </th>

                <th
                    colspan="4"
                    class="
                        group-heading
                        group-disposition
                    "
                >
                    Disposition
                </th>

                <th
                    colspan="2"
                    class="
                        group-heading
                        group-benefit
                    "
                >
                    Benefits
                </th>

            </tr>


            {{-- COLUMN HEADER --}}

            <tr>

                <th class="column-heading">
                    Docket
                </th>

                <th class="column-heading">
                    Requesting
                </th>

                <th class="column-heading">
                    Responding
                </th>

                <th class="column-heading">
                    Date Filed
                </th>

                <th class="column-heading">
                    Source Status
                </th>

                <th class="column-heading">
                    Monitoring
                </th>


                <th class="column-heading">
                    Interviewer
                </th>

                <th class="column-heading">
                    Assigned
                </th>

                <th class="column-heading">
                    Interview
                </th>

                <th class="column-heading">
                    PCT 1
                </th>

                <th class="column-heading">
                    PCT 2
                </th>

                <th class="column-heading">
                    Processing Days
                </th>


                <th class="column-heading">
                    SEADO
                </th>

                <th class="column-heading">
                    1st Conference
                </th>

                <th class="column-heading">
                    2nd Conference
                </th>


                <th class="column-heading">
                    Official / Source
                </th>

                <th class="column-heading">
                    Date Disposed
                </th>

                <th class="column-heading">
                    30-Day PCT
                </th>

                <th class="column-heading">
                    Deadline
                </th>


                <th class="column-heading">
                    Workers
                </th>

                <th class="column-heading">
                    Monetary Benefit
                </th>

            </tr>

        </thead>


        <tbody>

            @forelse (
                $rfas
                as $rfa
            )

                @php

                    $pct =
                        $recordPct[
                            $rfa->id
                        ];

                    $pctOne =
                        $pct[
                            'stage_one'
                        ];

                    $pctTwo =
                        $pct[
                            'stage_two'
                        ];

                    $dispositionPct =
                        $pct[
                            'disposition_pct'
                        ];

                    $totalProcessing =
                        $pct[
                            'total_processing'
                        ];

                @endphp


                <tr>

                    <td>
                        <span class="strong">
                            {{
                                $rfa->docket_no
                                ?: '—'
                            }}
                        </span>

                        <br>

                        <span class="muted">
                            {{
                                $rfa->reference_no
                            }}
                        </span>
                    </td>


                    <td>
                        {{
                            $rfa->requesting_party
                            ?: '—'
                        }}
                    </td>


                    <td>
                        {{
                            $rfa->responding_party
                            ?: '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_filed
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->source_case_status
                            ?: '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            \Illuminate\Support\Str::headline(
                                $rfa
                                    ->monitoring_bucket
                                ?? ''
                            )
                            ?: '—'
                        }}
                    </td>


                    <td>
                        {{
                            $rfa
                                ->interviewer_name
                            ?: '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_assigned_interviewer
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_interview
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $pctOne[
                                'classification_label'
                            ]
                            ?? '—'
                        }}

                        @if (
                            $pctOne['days']
                            !== null
                        )

                            <br>

                            {{
                                $pctOne['days']
                            }}
                            d

                        @endif
                    </td>


                    <td class="center">
                        {{
                            $pctTwo[
                                'classification_label'
                            ]
                            ?? '—'
                        }}

                        @if (
                            $pctTwo['days']
                            !== null
                        )

                            <br>

                            {{
                                $pctTwo['days']
                            }}
                            d

                        @endif
                    </td>


                    <td class="center">
                        {{
                            $totalProcessing[
                                'days'
                            ]
                            ?? '—'
                        }}
                    </td>


                    <td>
                        {{
                            $rfa->seado_name
                            ?: '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_initial_conference
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_second_conference
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td>

                        @if (
                            $rfa
                                ->disposition_status
                        )

                            <span class="strong">
                                {{
                                    \Illuminate\Support\Str::headline(
                                        $rfa
                                            ->disposition_status
                                    )
                                }}
                            </span>

                        @else

                            <span class="muted">
                                No official
                            </span>

                        @endif


                        @if (
                            $rfa
                                ->disposition_mode
                        )

                            <br>

                            <span class="muted">
                                Source:
                                {{
                                    $rfa
                                        ->disposition_mode
                                }}
                            </span>

                        @endif

                    </td>


                    <td class="center">
                        {{
                            $rfa
                                ->date_disposed
                                ?->format('m/d/Y')
                            ?? '—'
                        }}
                    </td>


                    <td
                        class="
                            center

                            {{
                                (
                                    $dispositionPct[
                                        'status_key'
                                    ]
                                    ?? null
                                )
                                ===
                                'disposed_beyond'

                                ||
                                (
                                    $dispositionPct[
                                        'status_key'
                                    ]
                                    ?? null
                                )
                                ===
                                'active_beyond'

                                    ? 'pct-beyond'
                                    : 'pct-within'
                            }}
                        "
                    >
                        {{
                            $dispositionPct[
                                'status_label'
                            ]
                            ?? '—'
                        }}

                        @if (
                            $dispositionPct[
                                'days'
                            ]
                            !== null
                        )

                            <br>

                            {{
                                $dispositionPct[
                                    'days'
                                ]
                            }}
                            d

                        @endif
                    </td>


                    <td class="center">

                        {{
                            $dispositionPct[
                                'deadline'
                            ]
                                ?->format(
                                    'm/d/Y'
                                )
                            ?? '—'
                        }}


                        @if (
                            (
                                $dispositionPct[
                                    'overdue_days'
                                ]
                                ?? 0
                            ) > 0
                        )

                            <br>

                            {{
                                $dispositionPct[
                                    'overdue_days'
                                ]
                            }}
                            overdue

                        @elseif (
                            $dispositionPct[
                                'state'
                            ]
                            === 'active'
                        )

                            <br>

                            {{
                                $dispositionPct[
                                    'remaining_days'
                                ]
                            }}
                            remaining

                        @endif

                    </td>


                    <td class="center">
                        {{
                            number_format(
                                $rfa
                                    ->workers_involved
                                ?? 0
                            )
                        }}

                        /

                        {{
                            number_format(
                                $rfa
                                    ->workers_benefited
                                ?? 0
                            )
                        }}
                    </td>


                    <td class="right">
                        @if (
                            $rfa
                                ->monetary_benefit
                            !== null
                        )

                            ₱{{
                                number_format(
                                    (float)
                                    $rfa
                                        ->monetary_benefit,
                                    2
                                )
                            }}

                        @else

                            —

                        @endif
                    </td>

                </tr>


            @empty

                <tr>

                    <td
                        colspan="21"
                        class="center"
                        style="
                            padding: 20px;
                        "
                    >
                        No records match the
                        selected report filters.
                    </td>

                </tr>

            @endforelse

        </tbody>

    </table>


    {{-- ========================================================= --}}
    {{-- FOOTER NOTE --}}
    {{-- ========================================================= --}}

    @php
        $footerNote = $settings->get(
            \App\Support\SystemSettings::REPORT_FOOTER_NOTE
        );
    @endphp

    @if ($footerNote !== '')

        <div class="report-meta" style="margin-top: 6px;">
            {{ $footerNote }}
        </div>

    @endif


</body>

</html>
