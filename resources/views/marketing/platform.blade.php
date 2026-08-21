<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoachingDesk — Batches, attendance, fees & parent alerts</title>
    <meta name="description" content="Multi-tenant coaching management for competition classes: attendance alerts, fees, notes, enquiry CRM.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: Outfit, ui-sans-serif, system-ui, sans-serif; }
        html { scroll-behavior: smooth; }
        .fade-up { animation: fadeUp 0.75s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .fade-up-d1 { animation: fadeUp 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.08s both; }
        .fade-up-d2 { animation: fadeUp 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.16s both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-canvas text-ink antialiased">
    <header class="sticky top-0 z-40 border-b border-black/[0.06] bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('marketing.platform') }}" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-700 text-sm font-semibold text-white">C</span>
                <span class="text-lg font-semibold tracking-tight text-ink">CoachingDesk</span>
            </a>
            <nav class="flex items-center gap-5 text-sm">
                <a href="#solution" class="hidden text-slate-600 hover:text-brand-700 sm:inline">Solution</a>
                <a href="#benefits" class="hidden text-slate-600 hover:text-brand-700 sm:inline">Benefits</a>
                <a href="{{ route('login') }}" class="text-slate-600 hover:text-brand-700">Login</a>
                <a href="{{ route('register') }}" class="rounded-md bg-brand-700 px-3.5 py-2 text-white hover:bg-brand-800">Start pilot</a>
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero: brand + one headline + support + CTAs --}}
        <section class="relative overflow-hidden border-b border-black/[0.06] bg-white">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_70%_50%_at_10%_0%,rgba(12,74,110,0.14),transparent_55%),radial-gradient(ellipse_50%_40%_at_90%_10%,rgba(0,0,0,0.03),transparent_50%)]"></div>
            <div class="relative mx-auto max-w-6xl px-4 py-20 sm:py-24">
                <p class="fade-up text-sm font-medium uppercase tracking-[0.14em] text-brand-700">India-first coaching SaaS</p>
                <h1 class="fade-up-d1 mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-ink sm:text-5xl sm:leading-[1.1]">
                    Run competition batches, attendance, fees, and parent alerts from one place
                </h1>
                <p class="fade-up-d2 mt-5 max-w-2xl text-lg leading-relaxed text-slate-600">
                    Built for coaching owners who teach JEE/NEET and foundation classes in batches.
                    Multi-tenant from day one — onboard multiple coachings on one platform.
                </p>
                <div class="fade-up-d2 mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-md bg-brand-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-800">Open staff app</a>
                    <a href="{{ route('marketing.coaching', 'demo-coaching') }}" class="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-ink hover:bg-slate-50">View demo coaching page</a>
                </div>
            </div>
        </section>

        {{-- On this page --}}
        <div class="border-b border-black/[0.06] bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 text-sm text-slate-500">
                <span class="font-medium text-slate-800">On this page</span>
                <a href="#overview" class="hover:text-brand-700">Overview</a>
                <a href="#solution" class="hover:text-brand-700">Solution</a>
                <a href="#benefits" class="hover:text-brand-700">Benefits</a>
                <a href="#pilot" class="hover:text-brand-700">Pilot</a>
            </div>
        </div>

        <section id="overview" class="scroll-mt-24 border-b border-black/[0.06] bg-white">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
                <p class="text-sm font-medium uppercase tracking-[0.14em] text-brand-700">Overview</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-ink">Daily ops for coaching centres — not another LMS brochure.</h2>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
                    CoachingDesk helps owners and staff manage students, mark attendance, collect fees,
                    and keep parents informed — so the front desk spends less time chasing and more time teaching.
                </p>
            </div>
        </section>

        <section id="solution" class="scroll-mt-24 border-b border-black/[0.06] bg-canvas">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
                <p class="text-sm font-medium uppercase tracking-[0.14em] text-brand-700">Solution</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-ink">Everything your coaching desk runs on.</h2>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
                    Four pillars that cover the coaching life cycle — from enquiry to batch to fee receipt.
                </p>

                <ol class="mt-12 grid gap-8 sm:grid-cols-2">
                    @foreach ($solutions as $i => $item)
                        <li class="flex gap-5">
                            <span class="shrink-0 text-2xl font-semibold tabular-nums text-brand-600">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}.</span>
                            <div>
                                <h3 class="text-lg font-semibold text-ink">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="benefits" class="scroll-mt-24 border-b border-black/[0.06] bg-white">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
                <p class="text-sm font-medium uppercase tracking-[0.14em] text-brand-700">Benefits</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-ink">What changes for your centre.</h2>

                <div class="mt-12 grid gap-10 sm:grid-cols-3">
                    @foreach ($benefits as $item)
                        <div>
                            <h3 class="text-lg font-semibold text-ink">{{ $item['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="pilot" class="scroll-mt-24 bg-ink text-white">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
                <p class="text-sm font-medium uppercase tracking-[0.14em] text-brand-300">Ready when you are</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight">Start with the pilot desk.</h2>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-300">
                    Use the demo coaching credentials to explore staff and parent views, or open a fresh pilot account.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-md bg-white px-5 py-2.5 text-sm font-medium text-ink hover:bg-slate-100">Open staff app</a>
                    <a href="{{ route('register') }}" class="rounded-md border border-white/25 px-5 py-2.5 text-sm font-medium text-white hover:bg-white/10">Start pilot</a>
                </div>
                <p class="mt-8 text-sm text-slate-400">
                    Pilot credentials:
                    <span class="text-slate-200">owner@demo-coaching.test / password</span>
                    ·
                    <span class="text-slate-200">parent@demo-coaching.test / password</span>
                </p>
            </div>
        </section>
    </main>

    <footer class="border-t border-black/[0.06] bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-slate-500">
            <span>© {{ date('Y') }} CoachingDesk</span>
            <a href="{{ route('marketing.coaching', 'demo-coaching') }}" class="hover:text-brand-700">Demo coaching landing</a>
        </div>
    </footer>
</body>
</html>
