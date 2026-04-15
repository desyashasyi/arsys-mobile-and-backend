<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; color: #374151; }
        .container { max-width: 580px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); }
        .header { background: #7c3aed; padding: 24px 28px; }
        .header h1 { color: #fff; margin: 0; font-size: 18px; letter-spacing: .3px; }
        .header p { color: #ddd6fe; margin: 5px 0 0; font-size: 13px; }
        .body { padding: 28px; }
        .greeting { font-size: 15px; margin: 0 0 18px; }
        .event-box { background: #f5f3ff; border-left: 4px solid #7c3aed; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; }
        .event-box .label { font-size: 11px; font-weight: 700; color: #7c3aed; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
        .event-box .event-code { font-size: 16px; font-weight: 700; color: #4c1d95; }
        .event-box .meta { font-size: 12px; color: #6b7280; margin-top: 6px; line-height: 1.6; }
        .section-title { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 8px; }
        .participants { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 22px; }
        .participant-row { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
        .participant-row:last-child { border-bottom: none; }
        .participant-row .nim { font-size: 11px; color: #7c3aed; font-weight: 700; margin-bottom: 2px; }
        .participant-row .student-name { font-size: 14px; font-weight: 600; color: #111827; }
        .participant-row .program { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .participant-row .title { font-size: 12px; color: #4b5563; margin-top: 4px; font-style: italic; }
        .participant-row .room { display: inline-block; margin-top: 5px; font-size: 11px; background: #ede9fe; color: #6d28d9; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
        .note { font-size: 13px; color: #6b7280; line-height: 1.7; margin-bottom: 0; }
        .footer { background: #f9fafb; padding: 16px 28px; font-size: 12px; color: #9ca3af; text-align: center; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Score Submission Reminder</h1>
            <p>{{ $defenseType }} &mdash; {{ config('app.name') }}</p>
        </div>

        <div class="body">
            <p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>

            <p style="font-size:14px; margin:0 0 18px; line-height:1.7;">
                This is a reminder regarding the <strong>{{ $defenseType }}</strong> examination event
                organized by the <strong>{{ $organizerProgram }}</strong> study program.
                You have pending score(s) that have not yet been submitted.
            </p>

            <div class="event-box">
                <div class="label">Event</div>
                <div class="event-code">{{ strtoupper($eventLabel) }}</div>
                <div class="meta">
                    📅 {{ $eventDate }}<br>
                    🎓 {{ $organizerProgram }}
                </div>
            </div>

            <p class="section-title">Participant(s) Awaiting Your Score</p>
            <div class="participants">
                @foreach($participants as $p)
                    <div class="participant-row">
                        <div class="nim">{{ $p['nim'] }}</div>
                        <div class="student-name">{{ $p['name'] }}</div>
                        @if(!empty($p['program']))
                            <div class="program">{{ $p['program'] }}</div>
                        @endif
                        @if(!empty($p['title']))
                            <div class="title">"{{ $p['title'] }}"</div>
                        @endif
                        @if(!empty($p['room']))
                            <span class="room">📍 {{ $p['room'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="note">
                Please log in to the system and submit your score(s) at your earliest convenience.
                If you believe you have received this message in error, please disregard it.
            </p>

            <div style="margin-top: 28px; padding-top: 18px; border-top: 1px solid #e5e7eb;">
                <p style="font-size:14px; margin:0 0 4px;">Regards,</p>
                @if($kaprodiName)
                    <p style="font-size:14px; font-weight:700; margin:0;">{{ $kaprodiName }}</p>
                    @if($kaprodiNip)
                        <p style="font-size:12px; color:#6b7280; margin:2px 0 0;">NIP. {{ $kaprodiNip }}</p>
                    @endif
                    <p style="font-size:12px; color:#7c3aed; margin:2px 0 0;">Head of Study Program &mdash; {{ $organizerProgram }}</p>
                @else
                    <p style="font-size:14px; font-weight:700; margin:0;">Head of Study Program</p>
                    <p style="font-size:12px; color:#7c3aed; margin:2px 0 0;">{{ $organizerProgram }}</p>
                @endif
            </div>
        </div>

        <div class="footer">
            ArSys &mdash; This is an automated reminder. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
