<style type="text/css">
    body { font-family: sans-serif; font-size: 11px; margin: 0; padding: 0; }
    .header-table { width: 100%; margin-bottom: 4px; border-collapse: collapse; }
    .header-left { text-align: left; font-size: 14px; font-weight: bold; }
    .header-right { text-align: right; font-size: 12px; }
    .grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .grid-table > tbody > tr > td { vertical-align: top; border: 1px solid #000; padding: 0; }
    .cell-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .cell-table td { vertical-align: top; }
    .ed-title-row {
        background: #fff;
        padding: {{ \App\Services\Pdf\AttendancePdfLayout::TITLE_TOP_PAD_MM }}mm 4px 2px 4px;
        font-weight: bold;
        color: #000;
        font-size: 14px;
    }
    .ed-court-row { background: #fff; padding: 2px 4px 4px; font-style: italic; color: #666; font-size: 11px; border-bottom: 1px solid #000; }
    .color-swatch { width: 10px; height: 10px; border-collapse: collapse; display: inline-table; margin-right: 4px; vertical-align: middle; }
    .color-swatch td { width: 10px; height: 10px; padding: 0; font-size: 1px; line-height: 1px; }
    .participants-cell { padding: 2px 4px; color: #fff; vertical-align: top; }
    .participant-table { width: 100%; border-collapse: collapse; }
    .participant-row td {
        white-space: nowrap;
        overflow: hidden;
        font-size: 9px;
        line-height: 1.2;
        vertical-align: top;
        padding: 0;
    }
    .participant-gap td { height: {{ \App\Services\Pdf\AttendancePdfLayout::PARTICIPANT_GAP_MM }}mm; padding: 0; font-size: 1px; line-height: 1px; }
    .checkbox-cell { width: 16px; }
    .checkbox-box { width: 13px; height: 13px; border-collapse: collapse; }
    .checkbox-box td { width: 13px; height: 13px; border: 1px solid #fff; padding: 0; font-size: 1px; line-height: 1px; background: transparent; }
    .avatar-cell { width: 18px; }
    .avatar-img { width: 16px; height: 16px; vertical-align: top; }
    .name-cell { overflow: hidden; }
</style>

@foreach ($pages as $page)
    <table class="header-table">
        <tr>
            <td class="header-left">{{ $page['header_left'] }}</td>
            <td class="header-right">
                {{ $page['header_date'] }}<br>
                {{ $page['header_times'] }}
            </td>
        </tr>
    </table>

    <table class="grid-table">
        @foreach ($page['cell_rows'] as $row)
            <tr>
                @foreach ($row['cells'] as $cell)
                    <td style="width: {{ $page['column_width_percent'] }}%;">
                        <table class="cell-table">
                            <tr>
                                <td class="ed-title-row">
                                    <table class="color-swatch" style="background-color: {{ $cell['color'] }};">
                                        <tr>
                                            <td style="background-color: {{ $cell['color'] }};">&nbsp;</td>
                                        </tr>
                                    </table>
                                    {{ $cell['division_title'] }}
                                </td>
                            </tr>
                            <tr>
                                <td class="ed-court-row">{{ $cell['courts_label'] }}</td>
                            </tr>
                            <tr>
                                <td class="participants-cell" style="background-color: {{ $cell['color'] }};">
                                    <table class="participant-table">
                                        @foreach ($cell['participants'] as $participant)
                                            <tr class="participant-row" style="height: {{ \App\Services\Pdf\AttendancePdfLayout::PARTICIPANT_CONTENT_MM }}mm;">
                                                <td class="checkbox-cell">
                                                    <table class="checkbox-box">
                                                        <tr>
                                                            <td>&nbsp;</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td class="avatar-cell">
                                                    @if ($participant['avatar_src'] !== '')
                                                        <img class="avatar-img" src="{{ $participant['avatar_src'] }}" alt="" />
                                                    @endif
                                                </td>
                                                <td class="name-cell">{{ $participant['name'] }}</td>
                                            </tr>
                                            @if (! $loop->last)
                                                <tr class="participant-gap">
                                                    <td colspan="3">&nbsp;</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if (! $loop->last)
        <pagebreak />
    @endif
@endforeach
