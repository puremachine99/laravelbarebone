<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SYSTEM') }}</title>
    <script>
        if (localStorage.getItem('theme') === 'light' || (!localStorage.getItem('theme') && window.matchMedia(
                '(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
        }
    </script>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,300;0,400;0,500;1,300&family=IBM+Plex+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #111111;
            --surface-2: #161616;
            --surface-3: #202020;

            --text: #ffffff;
            --text-muted: #b5b5b5;
            --text-soft: #7a7a7a;

            --border: #2c2c2c;
            --border-strong: #444444;

            --black: var(--bg);
            --white: var(--text);

            --gray-900: var(--surface);
            --gray-800: var(--surface-2);
            --gray-700: var(--surface-3);

            --gray-600: var(--border-strong);
            --gray-500: var(--text-soft);
            --gray-400: var(--text-muted);
            --gray-300: var(--text-muted);

            --gray-200: var(--text);
            --gray-100: var(--text);

            --nav-bg: rgba(10, 10, 10, 0.92);
            --ring-color: rgba(255, 255, 255, 1);
            --ring-hover: rgba(255, 255, 255, 1);
        }

        html.light {
            --bg: #ffffff;
            --surface: #f7f7f7;
            --surface-2: #f0f0f0;
            --surface-3: #e8e8e8;

            --text: #0a0a0a;
            --text-muted: #4d4d4d;
            --text-soft: #8a8a8a;

            --border: #d9d9d9;
            --border-strong: #bcbcbc;

            --nav-bg: rgba(255, 255, 255, 0.95);
            --ring-color: rgba(10, 10, 10, 0.4);
            --ring-hover: rgba(10, 10, 10, 0.8);
        }

        /* ── LIGHT MODE OVERRIDES ── */
        html.light body {
            background: var(--bg);
        }

        html.light .btn-solid {
            background: var(--text);
            color: var(--bg);
        }

        html.light .btn-solid:hover {
            background: var(--surface-3);
        }

        html.light .btn-outline {
            border-color: var(--border-strong);
            color: var(--text);
        }

        html.light .btn-outline:hover {
            border-color: var(--text);
        }

        html.light nav.bg-on {
            background: rgba(255, 255, 255, 0.95);
            border-bottom-color: var(--border);
        }

        html.light .contact-item-ico svg {
            stroke: var(--text-muted);
        }

        html.light .photo-num-big {
            color: var(--surface-3);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 300;
            overflow-x: hidden;
        }

        /* ─── CURSOR ─────────────────────────────── */
        body {
            cursor: none;
        }

        #c-dot {
            position: fixed;
            width: 8px;
            height: 8px;
            background: var(--white);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            transform: translate(-50%, -50%);
        }

        #c-ring {
            position: fixed;
            width: 32px;
            height: 32px;
            border: 1.5px solid var(--ring-color);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s, border-color 0.3s, opacity 0.3s;
        }

        body.hovering #c-ring {
            width: 56px;
            height: 56px;
            border-color: var(--ring-hover);
        }

        /* ─── NAVBAR ─────────────────────────────── */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 64px;
        }

        nav.bg-on {
            background: var(--nav-bg);
            backdrop-filter: blur(16px);
            padding: 18px 64px;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s;
        }

        .theme-toggle-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: var(--gray-400);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s, transform 0.2s;
        }

        .theme-toggle-btn:hover {
            color: var(--white);
            transform: scale(1.1);
        }

        .theme-toggle-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
        }

        html.light .sun-icon {
            display: none;
        }

        html:not(.light) .moon-icon {
            display: none;
        }

        .nav-logo {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 1.25rem;
            letter-spacing: 0.15em;
            color: var(--white);
            text-decoration: none;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 48px;
        }

        .nav-links {
            display: flex;
            gap: 36px;
            list-style: none;
        }

        .nav-links a {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            color: var(--gray-300);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--white);
        }

        .nav-sys {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.6rem;
            color: var(--gray-500);
            letter-spacing: 0.15em;
        }

        /* ─── PARALLAX WRAPPER ───────────────────── */
        .parallax-layer {
            will-change: transform;
        }

        /* ─── ABSTRACT GEO ───────────────────────── */
        .geo {
            position: absolute;
            pointer-events: none;
            opacity: 0.06;
            will-change: transform;
        }

        .geo-circle {
            border-radius: 50%;
            border: 1px solid var(--white);
        }

        .geo-rect {
            border: 1px solid var(--white);
        }

        .geo-fill {
            background: var(--gray-700);
            opacity: 0.2;
        }

        .geo-line {
            width: 1px;
            background: var(--gray-400);
            opacity: 0.12;
        }

        /* ─── HERO ───────────────────────────────── */
        #hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            position: relative;
            overflow: hidden;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 180px 64px 100px;
            position: relative;
            z-index: 10;
        }

        .hero-right {
            position: relative;
            overflow: hidden;
        }

        .hero-img {
            position: absolute;
            inset: 0;
            background: var(--gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(100%) contrast(1.3) brightness(.9);
            opacity: 0.5;
        }

        .hero-img-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--gray-800) 0%, var(--gray-900) 50%, var(--gray-700) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .placeholder-cross {
            position: relative;
            width: 80px;
            height: 80px;
            opacity: 0.15;
        }

        .placeholder-cross::before,
        .placeholder-cross::after {
            content: '';
            position: absolute;
            background: var(--gray-400);
        }

        .placeholder-cross::before {
            width: 1px;
            height: 100%;
            left: 50%;
            top: 0;
        }

        .placeholder-cross::after {
            width: 100%;
            height: 1px;
            top: 50%;
            left: 0;
        }

        .hero-eyebrow {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.4em;
            color: var(--gray-500);
            text-transform: uppercase;
            margin-bottom: 28px;
            opacity: 0;
            animation: slideIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards;
        }

        .hero-h1 {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: clamp(4rem, 8vw, 8rem);
            line-height: 0.95;
            letter-spacing: -0.02em;
            color: var(--white);
            opacity: 0;
            animation: slideIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.35s forwards;
        }

        .hero-h1 .stroke-text {
            color: transparent;
            -webkit-text-stroke: 1px var(--border-strong);
        }

        .hero-sub {
            max-width: 400px;
            margin-top: 32px;
            font-size: 0.9rem;
            line-height: 1.9;
            color: var(--gray-400);
            opacity: 0;
            animation: slideIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            margin-top: 48px;
            opacity: 0;
            animation: slideIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.65s forwards;
        }

        .btn-solid {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 14px 32px;
            background: var(--white);
            color: var(--black);
            border: 1px solid var(--border);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-solid:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        .btn-outline {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 14px 32px;
            background: transparent;
            color: var(--white);
            border: 1px solid var(--border);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-outline:hover {
            border-color: var(--gray-400);
            transform: translateY(-2px);
        }

        .hero-counter {
            position: absolute;
            bottom: 48px;
            right: 64px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            opacity: 0;
            animation: fadeIn 0.8s ease 1s forwards;
        }

        .hero-counter-num {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 5rem;
            line-height: 1;
            color: var(--gray-800);
        }

        .hero-counter-label {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.25em;
            color: var(--gray-600);
            text-transform: uppercase;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ─── SUMMARY SECTION ───────────────────── */
        #summary {
            position: relative;
            overflow: hidden;
            padding: 140px 64px;
            background: transparent;
            border-top: 1px solid var(--border);
        }

        .summary-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: start;
        }

        .summary-header {}

        .section-tag {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.35em;
            color: var(--gray-600);
            text-transform: uppercase;
            margin-bottom: 20px;
            display: block;
        }

        .section-h2 {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: clamp(3rem, 6vw, 6rem);
            line-height: 0.95;
            letter-spacing: -0.02em;
            color: var(--white);
        }

        .section-h2 .dim {
            color: var(--gray-700);
        }

        .summary-desc {
            margin-top: 24px;
            font-size: 0.9rem;
            line-height: 1.9;
            color: var(--gray-500);
            max-width: 380px;
        }

        .summary-metrics {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .metric-row {
            background: var(--gray-900);
            border: 1px solid var(--border);
            padding: 28px 32px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 24px;
            transition: background 0.2s;
            position: relative;
            overflow: hidden;
        }

        .metric-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray-600);
            transition: background 0.2s;
        }

        .metric-row:hover {
            background: var(--gray-800);
        }

        .metric-row:hover::before {
            background: var(--white);
        }

        .metric-row-num {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 2.4rem;
            color: var(--white);
            line-height: 1;
        }

        .metric-row-info {}

        .metric-row-dept {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.2em;
            color: var(--gray-500);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .metric-row-desc {
            font-size: 0.82rem;
            color: var(--gray-400);
        }

        .metric-row-badge {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.1em;
            padding: 4px 10px;
            border: 1px solid var(--gray-700);
            color: var(--gray-500);
        }

        /* ─── DEPT SECTION ───────────────────────── */
        #departments {
            position: relative;
            overflow: hidden;
            background: transparent;
            border-top: 1px solid var(--border);
            padding: 0;
        }

        /* Finance — left heavy */
        .dept-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
            border-top: 1px solid var(--gray-800);
        }

        .dept-block.flip {
            direction: rtl;
        }

        .dept-block.flip>* {
            direction: ltr;
        }

        .dept-content {
            padding: 80px 64px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .dept-num {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 7rem;
            line-height: 1;
            color: var(--gray-800);
            position: absolute;
            top: 40px;
            right: 40px;
        }

        .dept-block.flip .dept-num {
            right: auto;
            left: 40px;
        }

        .dept-tag {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.3em;
            color: var(--gray-600);
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .dept-name {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: clamp(2.8rem, 4vw, 4.5rem);
            line-height: 0.95;
            letter-spacing: -0.02em;
            color: var(--white);
            margin-bottom: 20px;
        }

        .dept-desc {
            font-size: 0.88rem;
            line-height: 1.85;
            color: var(--gray-500);
            margin-bottom: 32px;
            max-width: 380px;
        }

        .dept-list {
            list-style: none;
            margin-bottom: 40px;
        }

        .dept-list li {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-800);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dept-list li::before {
            content: '—';
            color: var(--gray-700);
        }

        .dept-cta {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--white);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: gap 0.2s;
        }

        .dept-cta:hover {
            gap: 18px;
        }

        .dept-cta-line {
            width: 32px;
            height: 1px;
            background: var(--white);
        }

        .dept-visual {
            position: relative;
            overflow: hidden;
            background: var(--gray-900);
        }

        .dept-visual-inner {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dept-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(100%) contrast(1.05);
            opacity: 0.35;
        }

        /* Placeholder photo panels */
        .dept-photo-placeholder {
            width: 100%;
            height: 100%;
            background: var(--gray-800);
            position: relative;
        }

        .photo-label {
            position: absolute;
            bottom: 24px;
            left: 24px;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.2em;
            color: var(--gray-700);
            text-transform: uppercase;
        }

        .photo-geo-1 {
            position: absolute;
            top: 10%;
            right: 10%;
            width: 160px;
            height: 160px;
            border: 1px solid var(--gray-700);
            border-radius: 50%;
            opacity: 0.3;
        }

        .photo-geo-2 {
            position: absolute;
            bottom: 20%;
            left: 15%;
            width: 80px;
            height: 80px;
            border: 1px solid var(--gray-600);
            transform: rotate(45deg);
            opacity: 0.2;
        }

        .photo-geo-3 {
            position: absolute;
            top: 40%;
            left: 30%;
            width: 1px;
            height: 200px;
            background: var(--gray-700);
            opacity: 0.4;
        }

        .photo-num-big {
            position: absolute;
            bottom: -20px;
            right: -10px;
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 18rem;
            line-height: 1;
            color: var(--gray-900);
            opacity: 0.8;
            pointer-events: none;
            user-select: none;
        }

        /* ─── SERVICES ───────────────────────────── */
        #services {
            background: transparent;
            border-top: 1px solid var(--border);
            padding: 140px 64px;
            position: relative;
            overflow: hidden;
        }

        .services-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: end;
            margin-bottom: 80px;
        }

        .services-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }

        .services-header-right p {
            font-size: 0.88rem;
            line-height: 1.85;
            color: var(--gray-500);
            max-width: 360px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .svc-card {
            background: var(--gray-900);
            border: 1px solid var(--border);
            padding: 48px 40px;
            position: relative;
            overflow: hidden;
            transition: background 0.2s;
        }

        .svc-card:hover {
            background: var(--gray-800);
        }

        .svc-card:nth-child(odd) {
            margin-top: 0;
        }

        .svc-card:nth-child(2) {
            margin-top: 48px;
        }

        .svc-card:nth-child(4) {
            margin-top: -48px;
        }

        .svc-num {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 5rem;
            color: var(--gray-700);
            line-height: 1;
            margin-bottom: 24px;
            transition: color 0.2s;
        }

        .svc-card:hover .svc-num {
            color: var(--gray-600);
        }

        .svc-title {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 1.8rem;
            letter-spacing: -0.01em;
            color: var(--white);
            margin-bottom: 14px;
        }

        .svc-desc {
            font-size: 0.85rem;
            line-height: 1.85;
            color: var(--gray-500);
        }

        .svc-tag {
            display: inline-block;
            margin-top: 20px;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.2em;
            padding: 5px 12px;
            border: 1px solid var(--gray-700);
            color: var(--gray-600);
            text-transform: uppercase;
        }

        /* ─── CONTACT ────────────────────────────── */
        #contact {
            background: transparent;
            padding: 140px 64px 120px;
            position: relative;
            overflow: hidden;
            border-top: 1px solid var(--border);
        }

        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: start;
        }

        .contact-left {}

        .contact-big {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: clamp(3.5rem, 7vw, 8rem);
            line-height: 0.88;
            letter-spacing: -0.02em;
            color: var(--white);
        }

        .contact-big .outline {
            -webkit-text-stroke: 1px var(--gray-700);
            color: transparent;
        }

        .contact-note {
            margin-top: 32px;
            font-size: 0.88rem;
            line-height: 1.9;
            color: var(--gray-500);
            max-width: 380px;
        }

        .contact-cta-row {
            margin-top: 48px;
            display: flex;
            gap: 16px;
        }

        .contact-right {
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .contact-item {
            background: var(--gray-900);
            border: 1px solid var(--border);
            padding: 28px 32px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 20px;
            align-items: center;
            transition: background 0.2s;
        }

        .contact-item:hover {
            background: var(--gray-800);
        }

        .contact-item-ico {
            width: 40px;
            height: 40px;
            border: 1px solid var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .contact-item-ico svg {
            width: 18px;
            height: 18px;
            stroke: var(--gray-400);
            fill: none;
            stroke-width: 1.5;
        }

        .contact-item-label {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.2em;
            color: var(--gray-600);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .contact-item-val {
            font-size: 0.88rem;
            color: var(--gray-300);
        }

        /* ─── FOOTER ─────────────────────────────── */
        footer {
            background: transparent;
            border-top: 1px solid var(--border);
            padding: 28px 64px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
        }

        .footer-logo {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 0.2em;
            color: var(--gray-600);
        }

        .footer-center {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.15em;
            color: var(--gray-700);
            text-align: center;
        }

        .footer-right {
            text-align: right;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.15em;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .footer-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--white);
        }

        /* ─── REVEAL ─────────────────────────────── */
        .rv {
            opacity: 0;
            transform: translateY(36px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .rv.on {
            opacity: 1;
            transform: none;
        }

        .rv.d1 {
            transition-delay: 0.1s;
        }

        .rv.d2 {
            transition-delay: 0.2s;
        }

        .rv.d3 {
            transition-delay: 0.3s;
        }

        .rv.d4 {
            transition-delay: 0.4s;
        }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media (max-width: 900px) {
            #hero {
                grid-template-columns: 1fr;
            }

            .hero-right {
                height: 50vh;
            }

            .summary-layout,
            .dept-block,
            .services-header,
            .contact-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .dept-block.flip {
                direction: ltr;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .svc-card:nth-child(2),
            .svc-card:nth-child(4) {
                margin-top: 0;
            }

            nav,
            footer {
                padding-left: 28px;
                padding-right: 28px;
            }

            section,
            #departments .dept-content,
            #contact {
                padding-left: 28px;
                padding-right: 28px;
            }

            .hero-left {
                padding: 140px 28px 80px;
            }

            footer {
                grid-template-columns: 1fr;
                text-align: left;
                gap: 8px;
            }

            .footer-right {
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body>

    <div id="c-dot"></div>
    <div id="c-ring"></div>

    <!-- NAVBAR -->
    <nav id="nav">
        <a href="#hero" class="nav-logo">{{ config('app.name', 'SYSTEM') }}</a>
        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="#summary">Overview</a></li>
                <li><a href="#departments">Departments</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <button id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle theme">
                <svg class="sun-icon" viewBox="0 0 24 24" stroke-width="1.5">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="moon-icon" viewBox="0 0 24 24" stroke-width="1.5">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
            <span class="nav-sys">v1.0 — {{ date('Y') }}</span>
        </div>
    </nav>

    <!-- HERO -->
    <section id="hero">
        <!-- Abstract geo parallax bg -->
        <div class="geo geo-circle" style="width:600px;height:600px;top:-200px;left:-100px;" data-parallax="0.08"></div>
        <div class="geo geo-rect" style="width:200px;height:200px;bottom:15%;right:5%;transform:rotate(22deg);"
            data-parallax="0.14"></div>
        <div class="geo geo-line" style="height:300px;top:25%;left:48%;" data-parallax="0.06"></div>
        <div class="geo geo-circle" style="width:80px;height:80px;bottom:30%;left:40%;" data-parallax="0.18"></div>

        <div class="hero-left">
            <p class="hero-eyebrow">// Technology Dept. &nbsp;—&nbsp; Perusahaan "Smart Id"</p>
            <h1 class="hero-h1">
                SMARTID<br>
                <span class="stroke-text">TECH</span><br>
                PORTAL
            </h1>
            <p class="hero-sub">
                Leading Technology Platform for Education and Public Sector Innovation. SmartID adalah Perusahaan
                Platform Teknologi untuk Edukasi dan Sektor Publik.
            </p>
            <div class="hero-actions">
                <a href="/login" class="btn-solid">Masuk Portal</a>
                <a href="#departments" class="btn-outline">Lihat Modul</a>
            </div>
        </div>

        <div class="hero-right">
            <div class="hero-img">
                <div class="hero-img-placeholder">
                    <!-- Swap with: <img src="..." alt="Hero" class="dept-photo"> -->
                    <div class="placeholder-cross"></div>
                    <div
                        style="position:absolute;top:15%;left:10%;width:180px;height:180px;border:1px solid var(--gray-700);border-radius:50%;opacity:0.3;">
                    </div>
                    <div
                        style="position:absolute;bottom:20%;right:10%;width:120px;height:120px;border:1px solid var(--gray-700);transform:rotate(30deg);opacity:0.2;">
                    </div>
                    <div
                        style="position:absolute;top:45%;left:50%;width:1px;height:200px;background:var(--gray-700);transform:translateX(-50%);opacity:0.4;">
                    </div>
                    <span
                        style="position:absolute;bottom:20px;right:20px;font-family:'Space Mono',monospace;font-size:0.55rem;letter-spacing:0.25em;color:var(--gray-600);text-transform:uppercase;">Photo
                        Placeholder</span>
                </div>
            </div>
            <div class="hero-counter">
                <div class="hero-counter-num">3</div>
                <div class="hero-counter-label">Departments</div>
            </div>
        </div>
    </section>

    <!-- DASHBOARD SUMMARY -->
    <section id="summary">
        <div class="geo geo-circle" style="width:400px;height:400px;top:-100px;right:-80px;opacity:0.04;"
            data-parallax="0.05"></div>
        <div class="geo geo-rect" style="width:140px;height:140px;bottom:60px;left:40%;transform:rotate(15deg);"
            data-parallax="0.1"></div>

        <div class="summary-layout">
            <div class="summary-header rv">
                <span class="section-tag">// 01 — About Us</span>
                <h2 class="section-h2">ABOUT<br>SMART<br><span class="dim">ID</span></h2>
                <p class="summary-desc">Leading Technology Platform for Education and Public Sector Innovation. SmartID
                    adalah Perusahaan Platform Teknologi untuk Edukasi dan Sektor Publik.</p>
            </div>
            <div class="summary-metrics">
                <div class="metric-row rv d1">
                    <div class="metric-row-num">4.2T</div>
                    <div class="metric-row-info">
                        <div class="metric-row-dept">Finance</div>
                        <div class="metric-row-desc">Total anggaran dikelola sistem periode berjalan</div>
                    </div>
                    <div class="metric-row-badge">↑ 12.4%</div>
                </div>
                <div class="metric-row rv d2">
                    <div class="metric-row-num">1,847</div>
                    <div class="metric-row-info">
                        <div class="metric-row-dept">Sales</div>
                        <div class="metric-row-desc">Transaksi aktif terpantau secara real-time</div>
                    </div>
                    <div class="metric-row-badge">↑ 8.1%</div>
                </div>
                <div class="metric-row rv d3">
                    <div class="metric-row-num">348</div>
                    <div class="metric-row-info">
                        <div class="metric-row-dept">HR</div>
                        <div class="metric-row-desc">Personel terdaftar dalam sistem kepegawaian</div>
                    </div>
                    <div class="metric-row-badge">3 pending</div>
                </div>
                <div class="metric-row rv d4">
                    <div class="metric-row-num">OK</div>
                    <div class="metric-row-info">
                        <div class="metric-row-dept">System Health</div>
                        <div class="metric-row-desc">Semua modul beroperasi normal</div>
                    </div>
                    <div class="metric-row-badge">Nominal</div>
                </div>
            </div>
        </div>
    </section>

    <!-- DEPARTMENTS -->
    <section id="departments" style="padding:0;">

        <!-- FINANCE — content LEFT, photo RIGHT -->
        <div class="dept-block">
            <div class="dept-content rv">
                <div class="dept-num">01</div>
                <div class="dept-tag">Department</div>
                <div class="dept-name">Finance</div>
                <div class="dept-desc">Pengelolaan anggaran, pelaporan keuangan, dan audit internal berbasis data
                    real-time dengan enkripsi berlapis dan compliance penuh.</div>
                <ul class="dept-list">
                    <li>Manajemen Anggaran &amp; APBN</li>
                    <li>Pelaporan Keuangan Otomatis</li>
                    <li>Audit Trail &amp; Compliance</li>
                    <li>Forecasting &amp; Analytics</li>
                </ul>
                <a href="/login" class="dept-cta">
                    <span class="dept-cta-line"></span>
                    Akses Portal
                </a>
            </div>
            <div class="dept-visual rv d2">
                <div class="dept-visual-inner">
                    <div class="dept-photo-placeholder">
                        <!-- Swap: <img src="..." class="dept-photo" alt="Finance"> -->
                        <div class="photo-geo-1"></div>
                        <div class="photo-geo-2"></div>
                        <div class="photo-geo-3"></div>
                        <div class="photo-num-big">01</div>
                        <span class="photo-label">Finance / Photo</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SALES — photo LEFT, content RIGHT -->
        <div class="dept-block flip">
            <div class="dept-content rv">
                <div class="dept-num">02</div>
                <div class="dept-tag">Department</div>
                <div class="dept-name">Sales</div>
                <div class="dept-desc">Pemantauan pipeline penjualan, manajemen klien, dan analitik kinerja tim secara
                    terpusat. Visibilitas penuh atas target dan realisasi.</div>
                <ul class="dept-list">
                    <li>CRM &amp; Client Management</li>
                    <li>Sales Pipeline Tracking</li>
                    <li>Performance Dashboard</li>
                    <li>Target &amp; KPI Monitoring</li>
                </ul>
                <a href="/login" class="dept-cta">
                    <span class="dept-cta-line"></span>
                    Akses Portal
                </a>
            </div>
            <div class="dept-visual rv d2">
                <div class="dept-visual-inner">
                    <div class="dept-photo-placeholder" style="background:#1a1a1a;">
                        <div class="photo-geo-1" style="width:100px;height:100px;top:30%;left:20%;"></div>
                        <div class="photo-geo-2"
                            style="width:60px;height:60px;top:15%;right:20%;transform:rotate(15deg);"></div>
                        <div class="photo-geo-3" style="width:300px;height:1px;top:60%;left:0;background:#2a2a2a;">
                        </div>
                        <div class="photo-num-big">02</div>
                        <span class="photo-label">Sales / Photo</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- HR — content LEFT, photo RIGHT -->
        <div class="dept-block">
            <div class="dept-content rv">
                <div class="dept-num">03</div>
                <div class="dept-tag">Department</div>
                <div class="dept-name">Human<br>Resources</div>
                <div class="dept-desc">Administrasi kepegawaian, manajemen absensi, penggajian, dan pengembangan SDM
                    dalam satu platform terintegrasi.</div>
                <ul class="dept-list">
                    <li>Database Pegawai Terpusat</li>
                    <li>Payroll &amp; Kompensasi</li>
                    <li>Absensi &amp; Cuti Digital</li>
                    <li>Pelatihan &amp; Sertifikasi</li>
                </ul>
                <a href="/login" class="dept-cta">
                    <span class="dept-cta-line"></span>
                    Akses Portal
                </a>
            </div>
            <div class="dept-visual rv d2">
                <div class="dept-visual-inner">
                    <div class="dept-photo-placeholder" style="background:#161616;">
                        <div class="photo-geo-1"
                            style="width:220px;height:220px;bottom:-20px;right:-20px;top:auto;border-color:#2a2a2a;">
                        </div>
                        <div class="photo-geo-2" style="bottom:30%;right:25%;left:auto;border-color:#333;"></div>
                        <div class="photo-geo-3" style="height:1px;width:60%;top:35%;left:20%;background:#2a2a2a;">
                        </div>
                        <div class="photo-num-big">03</div>
                        <span class="photo-label">HR / Photo</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section id="services">
        <div class="geo geo-circle" style="width:500px;height:500px;bottom:-200px;left:-100px;opacity:0.04;"
            data-parallax="0.06"></div>
        <div class="services-header">
            <div class="rv">
                <span class="section-tag">// 03 — Core Capabilities</span>
                <h2 class="section-h2">FITUR<br><span class="dim">SISTEM</span></h2>
            </div>
            <div class="services-header-right rv d2">
                <p>Infrastruktur yang dirancang untuk keandalan, keamanan, dan efisiensi operasional skala pemerintahan
                    — aktif 24 jam sehari.</p>
            </div>
        </div>

        <div class="services-grid">
            <div class="svc-card rv d1">
                <div class="svc-num">01</div>
                <div class="svc-title">Keamanan Data Berlapis</div>
                <div class="svc-desc">Enkripsi end-to-end 256-bit dengan autentikasi multi-faktor. Setiap akses
                    tercatat dalam audit log yang tidak dapat dimodifikasi.</div>
                <span class="svc-tag">Security</span>
            </div>
            <div class="svc-card rv d2">
                <div class="svc-num">02</div>
                <div class="svc-title">Real-Time Analytics</div>
                <div class="svc-desc">Dashboard interaktif dengan pembaruan data langsung. Visualisasi KPI lintas
                    departemen untuk keputusan berbasis data.</div>
                <span class="svc-tag">Analytics</span>
            </div>
            <div class="svc-card rv d3">
                <div class="svc-num">03</div>
                <div class="svc-title">Integrasi Lintas Modul</div>
                <div class="svc-desc">Sinkronisasi data otomatis antara Finance, Sales, dan HR. Eliminasi redundansi
                    dan inkonsistensi data antar unit kerja.</div>
                <span class="svc-tag">Integration</span>
            </div>
            <div class="svc-card rv d4">
                <div class="svc-num">04</div>
                <div class="svc-title">Pelaporan Otomatis</div>
                <div class="svc-desc">Generate laporan periodik dalam format standar pemerintahan secara otomatis.
                    Ekspor ke PDF, Excel, dan format resmi lainnya.</div>
                <span class="svc-tag">Reporting</span>
            </div>
        </div>
    </section>

    <!-- CONTACT -->
    <section id="contact">
        <div class="geo geo-rect"
            style="width:300px;height:300px;top:-80px;right:10%;transform:rotate(10deg);opacity:0.04;"
            data-parallax="0.09"></div>
        <div class="geo geo-circle" style="width:120px;height:120px;bottom:15%;left:55%;opacity:0.05;"
            data-parallax="0.12"></div>

        <div class="contact-layout">
            <div class="contact-left rv">
                <span class="section-tag">// 04 — Support & Access</span>
                <div class="contact-big">
                    BUTUH<br>
                    <span class="outline">BANTUAN</span><br>?
                </div>
                <p class="contact-note">Hubungi tim IT support atau administrator sistem untuk akses, troubleshooting,
                    dan permintaan fitur baru.</p>
                <div class="contact-cta-row">
                    <a href="/login" class="btn-solid">Masuk Sekarang</a>
                </div>
            </div>

            <div class="contact-right rv d2">
                <div class="contact-item">
                    <div class="contact-item-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="contact-item-label">Alamat</div>
                        <div class="contact-item-val" style="font-size:0.8rem; line-height:1.4;">Kav Bukit Firdaus,
                            Jl. Tirto Agung No.1, Merjosari, Lowokwaru, Malang City, East Java 65144</div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-item-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="contact-item-label">Telepon</div>
                        <div class="contact-item-val">0851-5504-8860</div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-item-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="contact-item-label">Jam Kerja</div>
                        <div class="contact-item-val">Buka · Tutup pukul 16.00</div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-item-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h2a2.5 2.5 0 002.5-2.5V10a2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="contact-item-label">Provinsi</div>
                        <div class="contact-item-val">Jawa Timur</div>
                    </div>
                </div>
                <div
                    style="margin-top:20px; display:flex; gap:16px; font-family:'Space Mono',monospace; font-size:0.65rem; padding-left: 10px;">
                    <a href="#" style="color:var(--gray-500); text-decoration:none; transition: color 0.2s;"
                        onmouseover="this.style.color='var(--white)'"
                        onmouseout="this.style.color='var(--gray-500)'">Sarankan edit</a>
                    <span style="color:var(--gray-700);">·</span>
                    <a href="#" style="color:var(--gray-500); text-decoration:none; transition: color 0.2s;"
                        onmouseover="this.style.color='var(--white)'"
                        onmouseout="this.style.color='var(--gray-500)'">Pemilik bisnis ini?</a>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="footer-logo">SmartID</div>
        <div class="footer-center">© {{ date('Y') }} — Perusahaan "Smart Id"</div>
        <div class="footer-right">
            <div class="footer-dot"></div>
            (nopelwkwk devji-05)
        </div>
    </footer>

    <script>
        (function() {
            // ── Cursor
            const dot = document.getElementById('c-dot');
            const ring = document.getElementById('c-ring');
            let mx = 0,
                my = 0,
                rx = 0,
                ry = 0;
            document.addEventListener('mousemove', e => {
                mx = e.clientX;
                my = e.clientY;
            });
            (function tick() {
                rx += (mx - rx) * 0.14;
                ry += (my - ry) * 0.14;
                dot.style.cssText = `left:${mx}px;top:${my}px;`;
                ring.style.left = rx + 'px';
                ring.style.top = ry + 'px';
                requestAnimationFrame(tick);
            })();
            document.querySelectorAll('a,button,.metric-row,.svc-card,.dept-card,.contact-item').forEach(el => {
                el.addEventListener('mouseenter', () => document.body.classList.add('hovering'));
                el.addEventListener('mouseleave', () => document.body.classList.remove('hovering'));
            });

            // ── Navbar scroll
            const nav = document.getElementById('nav');
            window.addEventListener('scroll', () => nav.classList.toggle('bg-on', scrollY > 60), {
                passive: true
            });

            // ── Parallax geo elements
            const geos = document.querySelectorAll('[data-parallax]');
            window.addEventListener('scroll', () => {
                const sy = window.scrollY;
                geos.forEach(el => {
                    const speed = parseFloat(el.dataset.parallax);
                    el.style.transform = `translateY(${sy * speed}px)`;
                });
            }, {
                passive: true
            });

            // ── Scroll reveal
            const rvs = document.querySelectorAll('.rv');
            const io = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        e.target.classList.add('on');
                        io.unobserve(e.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            rvs.forEach(el => io.observe(el));

            // ── Theme Switcher
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const isLight = document.documentElement.classList.toggle('light');
                    localStorage.setItem('theme', isLight ? 'light' : 'dark');
                });
            }
        })();
    </script>
</body>

</html>
