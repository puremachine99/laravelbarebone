<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SYSTEM') }} — Maintenance</title>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,300;0,400;0,500;1,300&family=IBM+Plex+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #0d2a4f;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='111' viewBox='0 0 64 111'%3E%3Cpath fill='none' stroke='%23FFC226' stroke-opacity='0.18' stroke-width='1' d='M32 0 L64 18.5 L64 55.5 L32 74 L0 55.5 L0 18.5 Z M32 37 L64 55.5 L64 92.5 L32 111 L0 92.5 L0 55.5 Z'/%3E%3C/svg%3E");
            background-repeat: repeat;
            font-family: 'IBM Plex Sans', sans-serif;
            color: #ffffff;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .glow {
            position: absolute;
            top: -6rem;
            right: -6rem;
            width: 24rem;
            height: 24rem;
            border-radius: 9999px;
            background: rgba(251, 191, 36, 0.1);
            filter: blur(64px);
            pointer-events: none;
        }

        .fade {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, #0d2a4f, transparent 40%, rgba(13, 42, 79, 0.5));
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            text-align: center;
            max-width: 420px;
        }

        .logo {
            width: 88px;
            height: 88px;
            border-radius: 20px;
            object-fit: contain;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .badge {
            display: inline-block;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.35);
            border-radius: 999px;
            padding: 4px 14px;
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
        }

        h1 span {
            color: #fbbf24;
        }

        p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .illustration {
            margin-top: 8px;
            max-width: 320px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }

        .retry {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
        }

        .footer {
            position: absolute;
            bottom: 24px;
            z-index: 10;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body>
    <div class="glow"></div>
    <div class="fade"></div>

    <div class="content">
        <img src="{{ asset('smartid.png') }}" alt="SmartID" class="logo">

        <span class="badge">Maintenance</span>

        <img src="{{ asset(rawurlencode('dev maintenance.jpeg')) }}" alt="Maintenance" class="illustration">

        {{-- Edit judul & pesan di bawah ini sesuai kebutuhan --}}
        <h1>SmartID <span>ERP</span></h1>
        <p>{{ config('app.name', 'Sistem') }} sedang dalam proses pemeliharaan terjadwal untuk peningkatan layanan.</p>
        <p>Mohon maaf atas ketidaknyamanannya. Silakan coba lagi beberapa saat lagi.</p>

        @if (! empty($retryAfter))
            <div class="retry">Perkiraan kembali normal dalam {{ $retryAfter }} detik.</div>
        @endif
    </div>

    <p class="footer">SmartID ERP v{{ config('app.version', '1.0.0') }} &middot; &copy; {{ now()->year }} SmartID</p>
</body>

</html>
