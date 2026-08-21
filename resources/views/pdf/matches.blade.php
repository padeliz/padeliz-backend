<link href="https://fonts.googleapis.com/css?family=Open%20Sans" rel="stylesheet">

<style type="text/css">
    table { width: 100%; }
    table .column { width: 50%; vertical-align: top; font-size: 40px; }
    tr { width: 100%; }
    td { color: black; overflow: hidden; text-overflow: ellipsis; }
</style>

<div style="text-align: center; width: 100%;">
    <table cellspacing="0" style="text-align: center; width: 100%;" autosize="1">
        <tr>
            <td colspan="2" style="padding-bottom: {{ $margin_top }}px;">
                <table cellspacing="0" style="width: 100%;" autosize="1">
                    <tr>
                        <td style="width: 30%; text-align: left;">
                            @if ($organizer_image)
                                <img style="max-width: 100%; width: 400px; max-height: 100px;" src="{{ $organizer_image }}" />
                            @endif
                            @if ($show_edition_label)
                                <span style="font-size: 60px;">&nbsp;</span>
                                <b style="font-family: sans; font-size: 60px; color: #a9d78b;">
                                    @if (is_numeric($edition_label))#@endif<span style="color: #7ab857;">{{ $edition_label }}</span>
                                </b>
                            @endif
                        </td>
                        <td style="width: 36%; text-align: left; padding-left: 180px;">
                            <h1 style="font-size: 60px; background-color: {{ $color }}; color: white;">
                                {{ $title }}
                            </h1>
                            @if ($include_scores)
                                <b style="font-size: 24px;">
                                    {{ $court_name }} • {{ $points_per_match_print }} points per match
                                </b>
                            @endif
                        </td>
                        <td style="width: 34%; text-align: right;">
                            @if ($club_host_image)
                                <img style="max-width: 100%; max-height: 80px;" src="{{ $club_host_image }}" />
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="column" style="width: 50%; border-right: 1px solid gray;">
                @if ($include_final)
                    <img height="180px" src="{{ $stretching_image }}" />
                    <br>
                    <span style="font-size: 18px;">{{ $stretch_time }} - we start with stretching</span>
                @endif

                @foreach ($matches as $m => $match)
                    <table @if (!in_array($m, $matches_without_margin_top, true)) style="margin-top: {{ $margin_top }}px;" @endif>
                        <tr>
                            <td style="width: 21%; text-align: right; padding-right: 17px;">
                                <div style="font-size: {{ $match['side_a'][0]['name_font_size'] }}px;">{!! $match['side_a'][0]['html_short_name'] !!}</div>
                                <div style="font-size: {{ $match['side_a'][1]['name_font_size'] }}px;">{!! $match['side_a'][1]['html_short_name'] !!}</div>
                            </td>
                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: left;">
                                    <img width="195px" src="{{ $match['team_a_image'] }}" />
                                </div>
                            </td>

                            @if ($include_scores)
                                <td style="width: 9%; vertical-align: bottom;"><hr></td>
                                <td style="width: 4%;">
                                    <span style="font-size: 15px;">{{ $match['starts_at'] }}</span>
                                    <hr>
                                </td>
                                <td style="width: 9%; vertical-align: bottom;"><hr></td>
                            @else
                                <td style="width: 22%;">
                                    <span style="font-size: 30px;">{{ $match['starts_at'] }}</span>
                                </td>
                            @endif

                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: right;">
                                    <img width="195px" src="{{ $match['team_b_image'] }}" />
                                </div>
                            </td>
                            <td style="width: 21%; text-align: left; padding-left: 17px;">
                                <div style="font-size: {{ $match['side_b'][0]['name_font_size'] }}px;">{!! $match['side_b'][0]['html_short_name'] !!}</div>
                                <div style="font-size: {{ $match['side_b'][1]['name_font_size'] }}px;">{!! $match['side_b'][1]['html_short_name'] !!}</div>
                            </td>
                        </tr>
                    </table>

                    @if ($m == $match_break_column)
            </td>
            <td class="column" style="width: 50%; border-left: 1px solid gray;">
                    @endif
                @endforeach

                @if ($include_final)
                    <table style="margin-top: {{ $margin_top }}px;">
                        <tr>
                            <td style="width: 21%; text-align: right; padding-right: 17px;">
                                <div style="font-size: 40px; overflow: hidden;">___________</div>
                                <br><br><br>
                                <div style="font-size: 40px; overflow: hidden;">___________</div>
                            </td>
                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: left;">
                                    <img width="195px" src="{{ $finalist_image }}" />
                                </div>
                            </td>
                            @if ($include_scores)
                                <td style="width: 9%; vertical-align: bottom;"><hr></td>
                                <td style="width: 4%;">
                                    <span style="font-size: 15px;">{{ $final_time }}</span>
                                    <hr>
                                </td>
                                <td style="width: 9%; vertical-align: bottom;"><hr></td>
                            @else
                                <td style="width: 22%;">
                                    <span style="font-size: 30px;">{{ $final_time }}</span>
                                </td>
                            @endif
                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: right;">
                                    <img width="195px" src="{{ $finalist_image }}" />
                                </div>
                            </td>
                            <td style="width: 21%; text-align: left; padding-left: 17px;">
                                <div style="font-size: 40px; overflow: hidden;">___________</div>
                                <br><br><br>
                                <div style="font-size: 40px; overflow: hidden;">___________</div>
                            </td>
                        </tr>
                    </table>
                    <span style="font-size: 18px;">final: total of {{ $final_points_target }} points played</span>
                @endif

                @if ($has_demonstrative_match)
                    <table style="margin-top: {{ $margin_top }}px;">
                        <tr>
                            <td style="width: 21%; text-align: center; padding-right: 17px;">
                                <div style="font-size: 30px;">
                                    experienced
                                    <div style="font-size: 10px;">&nbsp;</div>
                                    padel players
                                </div>
                            </td>
                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: left;">
                                    <img width="195px" src="{{ $special_guest_image }}" />
                                </div>
                            </td>
                            <td style="width: 6%;"></td>
                            <td style="width: 10%;">
                                <span style="font-size: 22px;">{{ $demo_time }}</span>
                                <hr>
                                <span style="font-size: 16px; color: grey;">also {{ $final_points_target }} points to play</span>
                            </td>
                            <td style="width: 6%;"></td>
                            <td style="width: 18%;">
                                <div style="width: 100%; max-width: 100%; text-align: right;">
                                    <img width="195px" src="{{ $best_teams_image }}" />
                                </div>
                            </td>
                            <td style="width: 21%; text-align: center; padding-left: 17px;">
                                <div style="font-size: 40px; overflow: hidden;">1st place only</div>
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
