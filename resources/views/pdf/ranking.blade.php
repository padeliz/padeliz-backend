<link href="https://fonts.googleapis.com/css?family=Open%20Sans" rel="stylesheet">

<style type="text/css">
    table { width: 100%; }
    table .column { vertical-align: top; font-size: 40px; }
    tr { width: 100%; }
    td { overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
</style>

<table cellspacing="0" style="table-layout: fixed; width: 100%;" autosize="1">
    <tr>
        <td colspan="2" style="text-align: left; vertical-align: bottom;">
            <div>
                @if ($organizer_image)
                    <img style="max-width: 100%; width: 280px; max-height: 80px;" src="{{ $organizer_image }}" />
                @endif
                @if ($show_event_label)
                    <span style="font-size: 40px;">&nbsp;</span>
                    <b style="font-family: sans; font-size: 40px; color: {{ $edition_label_muted ?? '#a9d78b' }};">
                        @if (is_numeric($edition_label))#@endif<span style="color: {{ $edition_label_accent ?? '#7ab857' }};">{{ $edition_label }}</span>
                    </b>
                @endif
            </div>
        </td>
        <td colspan="2" style="text-align: left; padding-left: 50px;">
            <h1 style="font-size: 40px; text-align: left; background-color: {{ $color }}; color: white;">
                {{ $title }}
            </h1>
            @if ($include_scores)
                <b style="font-size: 18px;">
                    {{ $court_label }} • {{ $points_per_match_print }} points per match
                </b>
            @endif
        </td>
        <td colspan="2" style="text-align: right; vertical-align: bottom;">
            @if ($club_host_image)
                <img style="max-width: 100%; max-height: 55px;" src="{{ $club_host_image }}" />
            @endif
        </td>
    </tr>
</table>

<table cellspacing="0" style="width: 100%; margin-top: {{ $margin_top }}px;" autosize="1">
    @if ($show_column_headers)
        <thead style="border-spacing: 0; padding: 0;">
            <tr>
                <td></td>
                <td></td>
                <td></td>
                @for ($i = 1; $i < max($matches_rows); $i++)
                    <td></td>
                    <td></td>
                @endfor
                <td></td>
                <td style="text-align: center; vertical-align: bottom;">points won</td>
                <td></td>
                @for ($i = 0; $i < $player_matches_count; $i++)
                    <td></td>
                @endfor
                <td></td>
                <td style="text-align: center;">matches won</td>
            </tr>
        </thead>
    @endif
    <tbody style="border-collapse: separate; border-spacing: 0 {{ $margin_top }}px;">
        @foreach ($players as $player)
            @foreach ($matches_rows as $mr => $matchRow)
                <tr>
                    @if ($mr == 0)
                        <td rowspan="{{ $nr_of_rows }}" style="width: 170px; text-align: right; padding-right: 17px; align-items: center;">
                            <br>
                            <div style="white-space: nowrap; font-size: {{ $player['name_font_size'] }}px;">
                                {!! $player['html_short_name'] !!}
                                <br>
                            </div>
                            @if ($show_player_ids ?? true)
                                <small style="color: #999999;">#{{ $player['id'] }}</small>
                            @endif
                        </td>
                        <td rowspan="{{ $nr_of_rows }}" style="width: 155px; align-items: center;">
                            <img width="{{ $sizing['img-width'] }}" src="{{ $player['avatar_path'] }}" alt="{{ $player['short_name'] }}" />
                        </td>
                    @endif

                    <td style="padding: {{ $sizing['points-slot-padding'] }}; width: 4.5%; vertical-align: bottom;">
                        <hr style="color: #b9b9b9; border-color: #b9b9b9;">
                    </td>
                    @for ($i = 1; $i < $matchRow; $i++)
                        <td style="padding: 0px; font-size: 45px; color: #b9b9b9; text-align: center; height: {{ $sizing['img-width'] }};">+</td>
                        <td style="padding: {{ $sizing['points-slot-padding'] }}; width: 4.5%; vertical-align: bottom;">
                            <hr style="color: #b9b9b9; border-color: #b9b9b9;">
                        </td>
                    @endfor
                    <td style="font-size: 50px; color: #d9d9d9;">
                        @if (($mr + 1) == $nr_of_rows)=@endif
                    </td>
                    <td style="padding: {{ $sizing['total-points-padding'] }}; font-size: {{ $sizing['total-points-font-size'] }}; font-weight: 200; color: #d9d9d9;">
                        @if (($mr + 1) == $nr_of_rows)▢@endif
                    </td>
                    <td style="width: 3.5%;"></td>

                    @for ($i = 0; $i < $player_matches_count; $i++)
                        @if (($mr + 1) == $nr_of_rows)
                            <td style="font-size: {{ $sizing['match-slot-font-size'] }}; color: #b9b9b9; padding: 6px 10px 0 10px;">
                                │
                                <div style="font-size: 20px;">&nbsp;</div>
                                │
                            </td>
                        @endif
                    @endfor
                    <td style="font-size: 50px; color: #d9d9d9;">
                        @if (($mr + 1) == $nr_of_rows)=@endif
                    </td>
                    <td style="padding: {{ $sizing['total-matches-padding'] }}; font-size: {{ $sizing['total-matches-font-size'] }}; font-weight: 200; color: #d9d9d9;">
                        @if (($mr + 1) == $nr_of_rows)□@endif
                    </td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
