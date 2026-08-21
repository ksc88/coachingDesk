<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }}</title>
    <meta name="description" content="{{ $landing['subheadline'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: {{ $tenant->primary_color ?: '#0c4a6e' }};
            --ink: #1d1d1f;
            --muted: #6e6e73;
            --hairline: rgba(0, 0, 0, 0.08);
            --surface: #ffffff;
            --canvas: #f5f5f7;
        }

        * { box-sizing: border-box; }

        body.landing {
            margin: 0;
            font-family: Outfit, ui-sans-serif, sans-serif;
            color: var(--ink);
            background: var(--canvas);
            -webkit-font-smoothing: antialiased;
        }

        .fade-up { animation: fadeUp 0.85s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .fade-up-d1 { animation: fadeUp 0.85s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both; }
        .fade-up-d2 { animation: fadeUp 0.85s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Default: clean light hero (no photo) */
        .hero {
            position: relative;
            overflow: hidden;
            min-height: min(78vh, 680px);
            display: flex;
            align-items: flex-end;
            background:
                radial-gradient(ellipse 80% 55% at 12% 18%, color-mix(in srgb, var(--brand) 16%, transparent), transparent 55%),
                radial-gradient(ellipse 60% 45% at 88% 8%, rgba(0,0,0,0.035), transparent 50%),
                linear-gradient(180deg, #ffffff 0%, var(--canvas) 100%);
            color: var(--ink);
        }

        .hero.has-photo {
            min-height: min(92vh, 820px);
            color: #fff;
            background: #1d1d1f;
        }

        .hero-media {
            display: none;
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            animation: heroZoom 14s ease-out both;
        }

        .hero.has-photo .hero-media {
            display: block;
            background-image:
                linear-gradient(180deg, rgba(0,0,0,0.22) 0%, rgba(0,0,0,0.5) 42%, rgba(0,0,0,0.78) 100%),
                var(--hero-image);
        }

        @keyframes heroZoom {
            from { transform: scale(1.06); }
            to { transform: scale(1.01); }
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding: 4.5rem 1.5rem 3.5rem;
        }

        .hero.has-photo .hero-inner { padding-top: 5.5rem; padding-bottom: 4rem; }

        .brand-mark {
            font-size: clamp(2.6rem, 7vw, 4.75rem);
            font-weight: 600;
            letter-spacing: -0.045em;
            line-height: 1.02;
            margin: 0;
        }

        .hero-line {
            margin: 1.25rem 0 0;
            max-width: 34rem;
            font-size: clamp(1.25rem, 2.6vw, 1.85rem);
            font-weight: 400;
            letter-spacing: -0.02em;
            line-height: 1.3;
            color: var(--muted);
        }

        .hero.has-photo .hero-line { color: rgba(255,255,255,0.88); }

        .hero-sub {
            margin: 1rem 0 0;
            max-width: 32rem;
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--muted);
        }

        .hero.has-photo .hero-sub { color: rgba(255,255,255,0.72); }

        .cta-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1.25rem;
            margin-top: 2rem;
        }

        .cta-primary {
            display: inline-flex;
            border-radius: 0.9rem;
            background: var(--ink);
            color: #fff;
            padding: 0.9rem 1.6rem;
            font-size: 0.925rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .hero.has-photo .cta-primary {
            background: #fff;
            color: var(--ink);
        }

        .cta-primary:hover {
            transform: translateY(-1px);
            background: #000;
        }

        .hero.has-photo .cta-primary:hover { background: #f5f5f7; }

        .cta-ghost {
            /* Keep Call readable even if accent is yellow / light */
            color: var(--ink);
            font-size: 0.925rem;
            font-weight: 500;
            text-decoration: none;
            border-bottom: 1px solid rgba(29, 29, 31, 0.25);
        }

        .hero.has-photo .cta-ghost {
            color: rgba(255,255,255,0.9);
            border-bottom-color: rgba(255,255,255,0.45);
        }

        .cta-ghost:hover { opacity: 0.75; }

        .top-call {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            z-index: 2;
            color: var(--ink);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
        }

        .hero.has-photo .top-call { color: rgba(255,255,255,0.85); }
        .top-call:hover { opacity: 0.7; }
        .hero.has-photo .top-call:hover { color: #fff; opacity: 1; }

        .page { max-width: 72rem; margin: 0 auto; padding: 3rem 1.5rem 1rem; }

        .layout { display: grid; gap: 2.5rem; }
        .layout.has-offers { align-items: start; }

        @media (min-width: 1024px) {
            .layout.has-offers { grid-template-columns: 1.35fr 0.9fr; gap: 3.5rem; }
            .enquire-panel.sticky { position: sticky; top: 1.25rem; }
        }

        .layout.enquire-only .enquire-wrap {
            max-width: 34rem;
            margin: 0 auto;
            width: 100%;
        }

        .section-title {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 1rem;
        }

        .soft-panel {
            background: var(--surface);
            border: 1px solid var(--hairline);
            border-radius: 1.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03), 0 16px 48px rgba(0,0,0,0.045);
        }

        .field {
            width: 100%;
            border-radius: 0.85rem;
            border: 1px solid var(--hairline);
            background: #fafafa;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .field:focus {
            outline: none;
            border-color: color-mix(in srgb, var(--brand) 50%, #ccc);
            background: #fff;
        }

        .offer-row { border-top: 1px solid var(--hairline); padding: 1.05rem 0; }
        .offer-row:first-child { border-top: 0; padding-top: 0.25rem; }
        .offer-row:last-child { padding-bottom: 0.25rem; }

        .site-footer {
            margin-top: 4rem;
            border-top: 1px solid var(--hairline);
            background: #fff;
        }

        .site-footer-inner {
            max-width: 72rem;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 3rem;
            display: grid;
            gap: 1.75rem;
        }

        @media (min-width: 768px) {
            .site-footer-inner { grid-template-columns: 1.4fr 1fr 1fr; }
        }

        .footer-brand { font-size: 1.15rem; font-weight: 600; letter-spacing: -0.02em; }
        .footer-muted { margin: 0.5rem 0 0; color: var(--muted); font-size: 0.9rem; line-height: 1.55; }
        .footer-link { color: var(--ink); text-decoration: none; font-size: 0.925rem; }
        .footer-link:hover { color: color-mix(in srgb, var(--brand) 35%, var(--ink)); }

        .btn-dark {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 0.9rem;
            background: var(--ink);
            color: #fff;
            padding: 0.95rem 1.25rem;
            font-size: 0.925rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-dark:hover { background: #000; }
        .label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .stack { display: grid; gap: 2.5rem; }
        .form-stack { margin-top: 1.5rem; display: grid; gap: 1rem; }
    </style>
</head>
<body class="landing">
    @php
        $hasOffers = $courses->isNotEmpty() || $batches->isNotEmpty();
        $hasHero = filled($landing['hero_url'] ?? null);
    @endphp

    <section class="hero {{ $hasHero ? 'has-photo' : '' }}" @if($hasHero) style="--hero-image: url('{{ $landing['hero_url'] }}')" @endif>
        <div class="hero-media" aria-hidden="true"></div>
        @if ($tenant->phone)
            <a class="top-call" href="tel:{{ $tenant->phone }}">{{ $tenant->phone }}</a>
        @endif

        <div class="hero-inner">
            <p class="fade-up brand-mark">{{ $tenant->name }}</p>
            <h1 class="fade-up-d1 hero-line">{{ $landing['headline'] }}</h1>
            <p class="fade-up-d2 hero-sub">{{ $landing['subheadline'] }}</p>
            <div class="fade-up-d2 cta-row">
                <a href="#enquire" class="cta-primary">Enquire now</a>
                @if ($tenant->phone)
                    <a href="tel:{{ $tenant->phone }}" class="cta-ghost">Call {{ $tenant->phone }}</a>
                @endif
            </div>
        </div>
    </section>

    <main class="page">
        <div class="layout {{ $hasOffers ? 'has-offers' : 'enquire-only' }}">
            @if ($hasOffers)
                <div class="stack">
                    @if ($courses->isNotEmpty())
                        <section>
                            <h2 class="section-title">Courses</h2>
                            <div class="soft-panel" style="padding:0.75rem 1.5rem;">
                                @foreach ($courses as $course)
                                    <div class="offer-row">
                                        <div style="font-size:1.1rem;font-weight:500;letter-spacing:-0.02em;">{{ $course->name }}</div>
                                        @if ($course->category?->name)
                                            <div style="margin-top:0.25rem;font-size:0.875rem;color:var(--muted);">{{ $course->category->name }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($batches->isNotEmpty())
                        <section>
                            <h2 class="section-title">Open batches</h2>
                            <div class="soft-panel" style="padding:0.75rem 1.5rem;">
                                @foreach ($batches as $batch)
                                    <div class="offer-row" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:0.5rem;align-items:baseline;">
                                        <div>
                                            <div style="font-size:1.1rem;font-weight:500;letter-spacing:-0.02em;">{{ $batch->name }}</div>
                                            <div style="margin-top:0.25rem;font-size:0.875rem;color:var(--muted);">
                                                {{ $batch->timing ?: 'Schedule on request' }}
                                                @if ($batch->course?->name) · {{ $batch->course->name }} @endif
                                            </div>
                                        </div>
                                        @if ((float) $batch->default_fee > 0)
                                            <div style="font-size:0.9rem;font-weight:500;">₹{{ number_format((float) $batch->default_fee, 0) }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @endif

            <div id="enquire" class="enquire-wrap">
                <div class="soft-panel enquire-panel {{ $hasOffers ? 'sticky' : '' }}" style="padding:1.75rem 1.75rem 2rem;">
                    <h2 style="margin:0;font-size:1.75rem;font-weight:600;letter-spacing:-0.03em;">Enquire</h2>
                    <p style="margin:0.5rem 0 0;font-size:0.925rem;line-height:1.55;color:var(--muted);">
                        Share a few details. We’ll follow up with the right course and batch.
                    </p>

                    @if (session('success'))
                        <div class="enquiry-success" role="status" aria-live="polite" style="margin-top:1.5rem;border-radius:1.1rem;padding:1.35rem 1.25rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;">
                            <div style="font-size:1.1rem;font-weight:600;">Enquiry sent</div>
                            <p style="margin:0.45rem 0 0;font-size:0.95rem;line-height:1.5;">{{ session('success') }}</p>
                            <a href="#enquire-form" style="display:inline-block;margin-top:1rem;font-size:0.875rem;font-weight:500;color:#065f46;">Send another enquiry</a>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div role="alert" style="margin-top:1.25rem;border-radius:1rem;padding:0.9rem 1rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:0.9rem;">
                            Please fix the highlighted fields below.
                        </div>
                    @endif

                    <form id="enquire-form" method="post" action="{{ route('marketing.enquiry', $tenant->slug) }}" class="form-stack" @if(session('success')) style="display:none;" @endif>
                        @csrf
                        <div>
                            <label class="label">Name</label>
                            <input name="name" required class="field" value="{{ old('name') }}" autocomplete="name" maxlength="120">
                            @error('name') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Phone</label>
                            <input name="phone" required class="field" value="{{ old('phone') }}" autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="10-digit mobile">
                            @error('phone') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Email <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                            <input type="email" name="email" class="field" value="{{ old('email') }}" autocomplete="email" maxlength="191">
                            @error('email') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                        </div>
                        @if ($batches->isNotEmpty())
                            <div>
                                <label class="label">Class / batch interest</label>
                                <select name="batch_id" class="field" required>
                                    <option value="">Select</option>
                                    @foreach ($batches as $batch)
                                        <option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>
                                            {{ $batch->name }}
                                            @if ($batch->timing) — {{ $batch->timing }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('batch_id') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        @if ($courses->count() > 1)
                            <div>
                                <label class="label">Course interest</label>
                                <select name="course_id" class="field">
                                    <option value="">Select</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>
                                    @endforeach
                                </select>
                                @error('course_id') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                            </div>
                        @elseif ($courses->count() === 1 && $batches->isEmpty())
                            <input type="hidden" name="course_id" value="{{ $courses->first()->id }}">
                        @endif
                        <div>
                            <label class="label">Notes <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                            <textarea name="notes" rows="3" class="field" maxlength="1000" placeholder="Level, preferred timing, goal…">{{ old('notes') }}</textarea>
                            @error('notes') <p style="margin:0.35rem 0 0;font-size:0.75rem;color:#dc2626;">{{ $message }}</p> @enderror
                        </div>
                        <label style="display:flex;align-items:flex-start;gap:0.75rem;font-size:0.875rem;color:var(--muted);">
                            <input type="checkbox" name="whatsapp_opt_in" value="1" style="margin-top:0.2rem;" @checked(old('whatsapp_opt_in'))>
                            <span>I agree to receive updates on WhatsApp / SMS</span>
                        </label>
                        <button type="submit" class="btn-dark">Submit enquiry</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div>
                <div class="footer-brand">{{ $tenant->name }}</div>
                <p class="footer-muted">{{ $landing['headline'] }}</p>
            </div>
            <div>
                <div class="section-title">Contact</div>
                @if ($tenant->phone)
                    <div><a class="footer-link" href="tel:{{ $tenant->phone }}">{{ $tenant->phone }}</a></div>
                @endif
                @if ($tenant->email)
                    <div style="margin-top:0.35rem;"><a class="footer-link" href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a></div>
                @endif
                @if ($tenant->address)
                    <p class="footer-muted">{{ $tenant->address }}</p>
                @endif
                @unless ($tenant->phone || $tenant->email || $tenant->address)
                    <p class="footer-muted">Add phone and address in Settings.</p>
                @endunless
            </div>
            <div>
                <div class="section-title">Visit</div>
                <a class="footer-link" href="#enquire">Enquire about a batch</a>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var target = document.getElementById('enquire');
            if (!target) return;

            var shouldFocus = {{ session('success') || $errors->any() ? 'true' : 'false' }}
                || window.location.hash === '#enquire'
                || window.location.hash === '#enquire-form';

            if (shouldFocus) {
                setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 50);
            }

            var again = document.querySelector('a[href="#enquire-form"]');
            var form = document.getElementById('enquire-form');
            if (again && form) {
                again.addEventListener('click', function (e) {
                    e.preventDefault();
                    form.style.display = '';
                    again.closest('.enquiry-success')?.remove();
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        })();
    </script>
</body>
</html>
