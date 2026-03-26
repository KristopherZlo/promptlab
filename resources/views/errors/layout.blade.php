<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Something went wrong') - Evala</title>
        <style>
            :root {
                color-scheme: dark;
                --canvas: #11161a;
                --canvas-strong: #182025;
                --surface: rgba(24, 32, 37, 0.94);
                --surface-soft: rgba(255, 255, 255, 0.03);
                --line: rgba(255, 255, 255, 0.08);
                --line-strong: rgba(0, 107, 66, 0.32);
                --ink: #f5f7f8;
                --muted: #a7b2b9;
                --accent: #006b42;
                --accent-soft: rgba(0, 107, 66, 0.16);
                --shadow: 0 18px 40px rgba(0, 0, 0, 0.28);
            }

            @media (prefers-color-scheme: light) {
                :root {
                    color-scheme: light;
                    --canvas: #f2f5f2;
                    --canvas-strong: #e7ece7;
                    --surface: rgba(255, 255, 255, 0.96);
                    --surface-soft: rgba(0, 107, 66, 0.05);
                    --line: rgba(17, 24, 39, 0.1);
                    --line-strong: rgba(0, 107, 66, 0.18);
                    --ink: #16201c;
                    --muted: #5b6a62;
                    --accent: #006b42;
                    --accent-soft: rgba(0, 107, 66, 0.08);
                    --shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
                }
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top left, var(--accent-soft), transparent 32%),
                    linear-gradient(180deg, var(--canvas-strong), var(--canvas));
                color: var(--ink);
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            .shell {
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            .panel {
                width: min(100%, 920px);
                display: grid;
                gap: 0;
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: var(--shadow);
            }

            @media (min-width: 860px) {
                .panel {
                    grid-template-columns: 320px minmax(0, 1fr);
                }
            }

            .rail {
                padding: 32px 28px;
                background: linear-gradient(180deg, var(--accent-soft), transparent 38%);
                border-right: 1px solid var(--line);
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .brand img {
                width: 34px;
                height: 34px;
                flex: 0 0 auto;
            }

            .brand-name {
                font-size: 1rem;
                font-weight: 600;
            }

            .brand-copy {
                margin-top: 4px;
                font-size: 0.92rem;
                line-height: 1.55;
                color: var(--muted);
            }

            .rail-block {
                margin-top: 28px;
                padding: 18px;
                border: 1px solid var(--line);
                border-radius: 12px;
                background: var(--surface-soft);
            }

            .rail-label {
                font-size: 0.84rem;
                font-weight: 600;
                color: var(--muted);
            }

            .rail-value {
                margin-top: 8px;
                font-size: 0.98rem;
                line-height: 1.6;
            }

            .content {
                padding: 32px 28px;
            }

            .status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 34px;
                padding: 0 12px;
                border: 1px solid var(--line-strong);
                border-radius: 999px;
                background: var(--accent-soft);
                color: var(--accent);
                font-size: 0.85rem;
                font-weight: 600;
            }

            h1 {
                margin: 20px 0 0;
                font-size: clamp(2rem, 4vw, 3rem);
                line-height: 1.05;
                letter-spacing: -0.04em;
            }

            .message {
                margin-top: 16px;
                max-width: 42rem;
                font-size: 1rem;
                line-height: 1.7;
                color: var(--muted);
            }

            .hint {
                margin-top: 18px;
                padding: 16px 18px;
                border: 1px solid var(--line);
                border-radius: 12px;
                background: var(--surface-soft);
                color: var(--ink);
                line-height: 1.65;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 24px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 0 16px;
                border-radius: 10px;
                border: 1px solid var(--line);
                font-size: 0.95rem;
                font-weight: 600;
            }

            .button-primary {
                border-color: var(--accent);
                background: var(--accent);
                color: #f5fffa;
            }

            .button-secondary {
                background: transparent;
                color: var(--ink);
            }

            .meta {
                margin-top: 24px;
                font-size: 0.85rem;
                color: var(--muted);
            }

            @media (max-width: 859px) {
                .rail {
                    border-right: 0;
                    border-bottom: 1px solid var(--line);
                }
            }
        </style>
    </head>
    <body>
        @php
            $primaryUrl = auth()->check()
                ? route(app(\App\Services\WorkspaceJourneyService::class)->landingRouteName())
                : route('login');
            $primaryLabel = auth()->check() ? 'Return to workspace' : 'Go to sign in';
            $statusCode = (int) trim($__env->yieldContent('status', isset($status) ? (string) $status : '500'));
        @endphp

        <div class="shell">
            <main class="panel" role="main">
                <section class="rail" aria-label="Product context">
                    <div class="brand">
                        <img src="{{ asset('images/evala-logo-colored.svg') }}" alt="">
                        <div>
                            <div class="brand-name">Evala</div>
                            <div class="brand-copy">Internal prompt operations workspace</div>
                        </div>
                    </div>

                    <div class="rail-block">
                        <div class="rail-label">What happened</div>
                        <div class="rail-value">@yield('rail', 'The requested page could not be served in the current state.')</div>
                    </div>

                    <div class="rail-block">
                        <div class="rail-label">Status</div>
                        <div class="rail-value">HTTP {{ $statusCode }}</div>
                    </div>
                </section>

                <section class="content">
                    <div class="status">HTTP {{ $statusCode }}</div>
                    <h1>@yield('heading', 'Something went wrong')</h1>
                    <p class="message">@yield('message', 'Evala could not finish this request.')</p>

                    <div class="hint">
                        @yield('hint', 'Try the workspace entry point again. If the problem persists, inspect the deployment or application logs.')
                    </div>

                    <div class="actions">
                        <a href="{{ $primaryUrl }}" class="button button-primary">{{ $primaryLabel }}</a>
                        <a href="{{ url('/') }}" class="button button-secondary">Open home route</a>
                    </div>

                    <div class="meta">
                        Evala error surface. No default Laravel exception template is shown here.
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
