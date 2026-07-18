<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        .wrap { width: 100%; padding: 28px 0; }
        .mail { width: 100%; max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .head { background: #064e3b; color: #ffffff; padding: 24px 28px; }
        .brand { font-size: 21px; font-weight: 700; letter-spacing: 0; }
        .tag { margin-top: 6px; color: #d1fae5; font-size: 13px; }
        .body { padding: 28px; font-size: 15px; line-height: 1.65; color: #334155; }
        .body p { margin: 0 0 16px; }
        .details { width: 100%; margin: 18px 0 22px; border-collapse: collapse; border: 1px solid #e2e8f0; }
        .details th { width: 38%; background: #f8fafc; color: #0f172a; font-size: 13px; text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .details td { color: #334155; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .details tr:last-child th, .details tr:last-child td { border-bottom: 0; }
        .foot { padding: 18px 28px; background: #f8fafc; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; line-height: 1.5; }
        @media (max-width: 680px) {
            .wrap { padding: 0; }
            .mail { border-radius: 0; border-left: 0; border-right: 0; }
            .head, .body, .foot { padding-left: 18px; padding-right: 18px; }
            .details th, .details td { display: block; width: auto; border-bottom: 0; padding: 10px 12px; }
            .details tr { border-bottom: 1px solid #e2e8f0; }
            .details tr:last-child { border-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="mail">
            <div class="head">
                <div class="brand">AlignEx</div>
                <div class="tag">Secure examination management notification</div>
            </div>
            <div class="body">
                @if ($isHtml)
                    {!! $body !!}
                @else
                    {!! nl2br(e($body)) !!}
                @endif
            </div>
            <div class="foot">
                This message was sent by AlignEx. Please do not share access codes, reset links, or exam credentials with anyone.
            </div>
        </div>
    </div>
</body>
</html>
