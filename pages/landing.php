<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LouVentory — Inventory Management Made Simple</title>
    <meta name="description" content="Track your stock, manage loans, and know exactly where everything is. LouVentory is the clean, simple inventory system built for teams.">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --dark:    #0F1117;
            --dark-2:  #161B27;
            --dark-3:  #1F2937;
            --blue:    #378ADD;
            --blue-d:  #2a6db5;
            --blue-gl: rgba(55,138,221,0.12);
            --text-h:  #FFFFFF;
            --text-b:  #9CA3AF;
            --text-m:  #6B7280;
            --border:  #1F2937;
        }

        html { font-size: 16px; scroll-behavior: smooth; }
        body { background: var(--dark); color: var(--text-b); font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }

        .container { max-width: 1100px; margin: 0 auto; padding: 0 2rem; }

        /* ── NAV ─────────────────────────────────────────────────────────── */
        nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(15,17,23,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            display: flex; align-items: center; justify-content: space-between;
            height: 64px;
        }
        .nav-logo { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
        .logo-lou { color: #fff; }
        .logo-ventory { color: var(--blue); }

        .nav-cta {
            background: var(--blue);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .nav-cta:hover { background: var(--blue-d); }

        /* ── HERO ────────────────────────────────────────────────────────── */
        .hero {
            padding: 7rem 0 5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -200px; left: 50%; transform: translateX(-50%);
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(55,138,221,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--blue-gl);
            border: 1px solid rgba(55,138,221,0.3);
            color: var(--blue);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.3rem 0.9rem;
            border-radius: 999px;
            margin-bottom: 1.75rem;
        }
        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
            color: var(--text-h);
            margin-bottom: 1.5rem;
            max-width: 800px;
            margin-left: auto; margin-right: auto;
        }
        .hero h1 em { color: var(--blue); font-style: normal; }
        .hero-sub {
            font-size: 1.125rem;
            color: var(--text-b);
            max-width: 560px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }
        .hero-actions {
            display: flex; align-items: center; justify-content: center; gap: 1rem;
            flex-wrap: wrap;
        }
        .btn-hero-primary {
            background: var(--blue);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            padding: 0.85rem 2rem;
            border-radius: 8px;
            transition: background 0.15s, transform 0.15s;
            display: inline-block;
        }
        .btn-hero-primary:hover { background: var(--blue-d); transform: translateY(-1px); }
        .btn-hero-ghost {
            color: var(--text-b);
            font-size: 1rem;
            font-weight: 500;
            padding: 0.85rem 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            transition: border-color 0.15s, color 0.15s;
        }
        .btn-hero-ghost:hover { border-color: #4B5563; color: #fff; }

        /* ── SOCIAL PROOF BAR ────────────────────────────────────────────── */
        .proof-bar {
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2.5rem;
            flex-wrap: wrap;
        }
        .proof-item {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.8rem; font-weight: 600; color: var(--text-m);
        }
        .proof-item svg { width: 16px; height: 16px; color: var(--blue); }

        /* ── MOCK UI ─────────────────────────────────────────────────────── */
        .mock-wrap {
            padding: 4rem 0 0;
            text-align: center;
        }
        .mock-browser {
            display: inline-block;
            background: var(--dark-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
            max-width: 860px;
            width: 100%;
            text-align: left;
        }
        .mock-bar {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--dark-3);
            border-bottom: 1px solid #111827;
        }
        .mock-dot { width: 10px; height: 10px; border-radius: 50%; }
        .mock-dot-r { background: #EF4444; }
        .mock-dot-y { background: #F59E0B; }
        .mock-dot-g { background: #10B981; }
        .mock-url {
            flex: 1; background: var(--dark); border-radius: 4px;
            font-size: 0.72rem; color: var(--text-m); padding: 0.3rem 0.75rem;
            font-family: monospace;
        }
        .mock-body { display: flex; min-height: 340px; }
        .mock-sidebar {
            width: 180px; flex-shrink: 0;
            background: #0A0C12;
            border-right: 1px solid #111827;
            padding: 1rem 0.75rem;
        }
        .mock-sidebar-brand { font-size: 0.95rem; font-weight: 800; margin-bottom: 1.25rem; padding: 0 0.25rem; }
        .mock-nav-item {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.45rem 0.6rem; border-radius: 5px;
            font-size: 0.72rem; font-weight: 500; color: #4B5563;
            margin-bottom: 2px;
        }
        .mock-nav-item.active { background: #1F2937; color: #E5E7EB; }
        .mock-nav-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .mock-main { flex: 1; padding: 1.25rem; }
        .mock-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .mock-title { font-size: 0.9rem; font-weight: 700; color: #E5E7EB; }
        .mock-btn { background: var(--blue); color: #fff; font-size: 0.65rem; font-weight: 600; padding: 0.3rem 0.7rem; border-radius: 4px; }
        .mock-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 0.5rem; margin-bottom: 1rem; }
        .mock-stat { background: var(--dark-2); border: 1px solid var(--border); border-radius: 6px; padding: 0.6rem 0.75rem; }
        .mock-stat-val { font-size: 1rem; font-weight: 700; color: #E5E7EB; }
        .mock-stat-lbl { font-size: 0.6rem; color: #6B7280; }
        .mock-table { background: var(--dark-2); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
        .mock-th { display: flex; padding: 0.4rem 0.75rem; background: #1a1f2e; gap: 1rem; }
        .mock-th span { font-size: 0.58rem; font-weight: 700; color: #6B7280; text-transform: uppercase; flex: 1; }
        .mock-tr { display: flex; padding: 0.45rem 0.75rem; border-top: 1px solid #111827; gap: 1rem; align-items: center; }
        .mock-tr span { font-size: 0.65rem; color: #9CA3AF; flex: 1; }
        .mock-pill { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 0.55rem; font-weight: 700; }
        .mock-pill-g { background: #EAF3DE; color: #3B6D11; }
        .mock-pill-y { background: #FAEEDA; color: #854F0B; }
        .mock-pill-r { background: #FAECE7; color: #993C1D; }

        /* ── FEATURES ────────────────────────────────────────────────────── */
        .section { padding: 5rem 0; }
        .section-label {
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--blue); margin-bottom: 0.75rem;
        }
        .section-title {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 800; letter-spacing: -1px;
            color: var(--text-h); line-height: 1.15;
            margin-bottom: 1rem; max-width: 560px;
        }
        .section-sub { font-size: 1rem; color: var(--text-b); max-width: 480px; line-height: 1.7; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-top: 3rem;
        }
        .feature-card {
            background: var(--dark-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            transition: border-color 0.2s, transform 0.2s;
        }
        .feature-card:hover { border-color: rgba(55,138,221,0.4); transform: translateY(-2px); }
        .feature-icon {
            width: 40px; height: 40px;
            background: var(--blue-gl);
            border: 1px solid rgba(55,138,221,0.2);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
            color: var(--blue);
        }
        .feature-icon svg { width: 20px; height: 20px; }
        .feature-title { font-size: 0.925rem; font-weight: 700; color: var(--text-h); margin-bottom: 0.4rem; }
        .feature-desc { font-size: 0.825rem; color: var(--text-b); line-height: 1.6; }

        /* ── HOW IT WORKS ────────────────────────────────────────────────── */
        .how-section { padding: 5rem 0; background: var(--dark-2); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 3rem; position: relative; }
        .steps::before {
            content: '';
            position: absolute; top: 20px; left: calc(16.6% + 0.5rem); right: calc(16.6% + 0.5rem);
            height: 1px; background: var(--border);
        }
        .step { text-align: center; }
        .step-num {
            width: 40px; height: 40px;
            background: var(--blue);
            color: #fff;
            font-size: 0.875rem; font-weight: 800;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            position: relative; z-index: 1;
        }
        .step-title { font-size: 0.925rem; font-weight: 700; color: var(--text-h); margin-bottom: 0.4rem; }
        .step-desc { font-size: 0.8rem; color: var(--text-b); line-height: 1.6; }

        /* ── WHO IT'S FOR ────────────────────────────────────────────────── */
        .for-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 3rem; }
        .for-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            display: flex; gap: 0.75rem; align-items: flex-start;
        }
        .for-emoji { font-size: 1.5rem; flex-shrink: 0; line-height: 1; }
        .for-title { font-size: 0.875rem; font-weight: 700; color: var(--text-h); margin-bottom: 0.25rem; }
        .for-desc { font-size: 0.78rem; color: var(--text-b); line-height: 1.5; }

        /* ── CTA ─────────────────────────────────────────────────────────── */
        .cta-section { padding: 6rem 0; text-align: center; }
        .cta-box {
            background: linear-gradient(135deg, var(--blue-gl) 0%, transparent 60%);
            border: 1px solid rgba(55,138,221,0.25);
            border-radius: 16px;
            padding: 4rem 2rem;
            max-width: 700px;
            margin: 0 auto;
        }
        .cta-title { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; color: var(--text-h); letter-spacing: -1px; margin-bottom: 1rem; line-height: 1.2; }
        .cta-sub { font-size: 1rem; color: var(--text-b); margin-bottom: 2rem; }

        /* ── FOOTER ──────────────────────────────────────────────────────── */
        footer {
            border-top: 1px solid var(--border);
            padding: 2rem 0;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        footer .foot-logo { font-size: 1.1rem; font-weight: 800; }
        footer p { font-size: 0.75rem; color: var(--text-m); }

        /* ── RESPONSIVE ──────────────────────────────────────────────────── */
        @media (max-width: 768px) {
            .container { padding: 0 1.25rem; }

            .hero { padding: 4rem 0 2.5rem; }
            .hero-sub { font-size: 1rem; }

            /* hide mock sidebar to save space */
            .mock-sidebar { display: none; }
            .mock-stats { grid-template-columns: 1fr 1fr; }
            .mock-wrap { padding: 3rem 0 0; }

            .features-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
            .steps { grid-template-columns: 1fr; gap: 2rem; }
            .steps::before { display: none; }
            .for-grid { grid-template-columns: 1fr 1fr; }

            .section { padding: 3.5rem 0; }
            .how-section { padding: 3.5rem 0; }

            .cta-box { padding: 3rem 1.5rem; }

            .proof-bar { gap: 1rem; padding-left: 1.25rem; padding-right: 1.25rem; }
        }

        @media (max-width: 480px) {
            .hero { padding: 3rem 0 2rem; }
            .hero-actions { flex-direction: column; align-items: stretch; }
            .btn-hero-primary, .btn-hero-ghost { text-align: center; }

            .features-grid { grid-template-columns: 1fr; }
            .for-grid { grid-template-columns: 1fr; }

            .proof-bar { flex-direction: column; align-items: flex-start; gap: 0.6rem; }

            .cta-box { padding: 2.5rem 1.25rem; }

            footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="container nav-inner">
        <div class="nav-logo">
            <span class="logo-lou">Lou</span><span class="logo-ventory">Ventory</span>
        </div>
        <a href="/login" class="nav-cta">Sign in</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <div class="hero-eyebrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
            Inventory Management
        </div>
        <h1>Stop losing track of <em>what you own.</em></h1>
        <p class="hero-sub">
            LouVentory gives your team a clean, fast dashboard to track stock, loan equipment, and get alerts — without the spreadsheet chaos.
        </p>
        <div class="hero-actions">
            <a href="/login" class="btn-hero-primary">Get started free →</a>
            <a href="#features" class="btn-hero-ghost">See what's included</a>
        </div>
    </div>
</section>

<!-- SOCIAL PROOF BAR -->
<div class="proof-bar">
    <div class="proof-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        No spreadsheets
    </div>
    <div class="proof-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Barcode scanner ready
    </div>
    <div class="proof-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Overdue email alerts
    </div>
    <div class="proof-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Up and running in minutes
    </div>
</div>

<!-- MOCK UI -->
<div class="mock-wrap">
    <div class="container">
        <div class="mock-browser">
            <div class="mock-bar">
                <div class="mock-dot mock-dot-r"></div>
                <div class="mock-dot mock-dot-y"></div>
                <div class="mock-dot mock-dot-g"></div>
                <div class="mock-url">louventory.uk/dashboard</div>
            </div>
            <div class="mock-body">
                <div class="mock-sidebar">
                    <div class="mock-sidebar-brand">
                        <span style="color:#fff">Lou</span><span style="color:#378ADD">Ventory</span>
                    </div>
                    <div class="mock-nav-item active"><div class="mock-nav-dot"></div> Dashboard</div>
                    <div class="mock-nav-item"><div class="mock-nav-dot"></div> Items</div>
                    <div class="mock-nav-item"><div class="mock-nav-dot"></div> Check Out</div>
                    <div class="mock-nav-item"><div class="mock-nav-dot"></div> Active Loans</div>
                    <div class="mock-nav-item"><div class="mock-nav-dot"></div> Borrowers</div>
                    <div class="mock-nav-item"><div class="mock-nav-dot"></div> History</div>
                </div>
                <div class="mock-main">
                    <div class="mock-topbar">
                        <div class="mock-title">Dashboard</div>
                        <div class="mock-btn">+ Add Item</div>
                    </div>
                    <div class="mock-stats">
                        <div class="mock-stat"><div class="mock-stat-val" style="color:#378ADD">48</div><div class="mock-stat-lbl">Total Items</div></div>
                        <div class="mock-stat"><div class="mock-stat-val" style="color:#854F0B">7</div><div class="mock-stat-lbl">On Loan</div></div>
                        <div class="mock-stat"><div class="mock-stat-val" style="color:#993C1D">3</div><div class="mock-stat-lbl">Low Stock</div></div>
                        <div class="mock-stat"><div class="mock-stat-val" style="color:#3B6D11">12</div><div class="mock-stat-lbl">Borrowers</div></div>
                    </div>
                    <div class="mock-table">
                        <div class="mock-th">
                            <span>Item</span><span>Category</span><span>Qty</span><span>Status</span>
                        </div>
                        <div class="mock-tr">
                            <span style="color:#E5E7EB;font-weight:600">Sony A7 III</span>
                            <span>Cameras</span><span>2</span>
                            <span><div class="mock-pill mock-pill-y">On Loan</div></span>
                        </div>
                        <div class="mock-tr">
                            <span style="color:#E5E7EB;font-weight:600">DJI Mavic 3</span>
                            <span>Drones</span><span>1</span>
                            <span><div class="mock-pill mock-pill-g">Available</div></span>
                        </div>
                        <div class="mock-tr">
                            <span style="color:#E5E7EB;font-weight:600">Canon 70-200mm</span>
                            <span>Lenses</span><span>0</span>
                            <span><div class="mock-pill mock-pill-r">Overdue</div></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FEATURES -->
<section class="section" id="features">
    <div class="container">
        <div class="section-label">Features</div>
        <h2 class="section-title">Everything you need. Nothing you don't.</h2>
        <p class="section-sub">A focused toolset that covers the full lifecycle of your inventory — from adding items to chasing overdue returns.</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <div class="feature-title">Item Tracking</div>
                <div class="feature-desc">Add items with photos, barcodes, serial numbers, and categories. Know your stock levels at a glance.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="feature-title">Loan Management</div>
                <div class="feature-desc">Check items out to borrowers in seconds. Set due dates, add notes, and mark returns with one click.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="feature-title">Low Stock Alerts</div>
                <div class="feature-desc">Set a threshold per item. LouVentory flags anything running low so you're never caught short.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="feature-title">Barcode Scanner Support</div>
                <div class="feature-desc">Plug in any USB or Bluetooth barcode scanner. LouVentory detects the scan automatically — no button press needed.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div class="feature-title">Overdue Email Alerts</div>
                <div class="feature-desc">Borrowers get an automatic email reminder when something is overdue. No chasing required.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 1 .5 4M3 21v-4h4"/></svg>
                </div>
                <div class="feature-title">Full Audit History</div>
                <div class="feature-desc">Every action is logged — checkouts, returns, edits, additions. A complete paper trail, always.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="feature-title">Borrower Profiles</div>
                <div class="feature-desc">Store contact details for every borrower and see their full loan history — past and present — in one place.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
                <div class="feature-title">Categories & Colours</div>
                <div class="feature-desc">Organise items into colour-coded categories so your team can find and filter what they need instantly.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <div class="feature-title">Photo Uploads</div>
                <div class="feature-desc">Attach a photo to every item so there's never any confusion about what something is or what condition it's in.</div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section">
    <div class="container">
        <div style="text-align:center">
            <div class="section-label">How it works</div>
            <h2 class="section-title" style="margin:0 auto 0.5rem; text-align:center">Up and running in minutes</h2>
            <p class="section-sub" style="margin: 0 auto; text-align:center">No installation. No IT department. Just sign in and start tracking.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-title">Get your workspace</div>
                <div class="step-desc">Sign up and get your own private inventory workspace — completely isolated from everyone else.</div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-title">Add your items</div>
                <div class="step-desc">Add items with photos, barcodes, and categories. Use a barcode scanner or type manually — your call.</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-title">Start tracking</div>
                <div class="step-desc">Check items in and out, get overdue alerts, and always know exactly what you have and where it is.</div>
            </div>
        </div>
    </div>
</section>

<!-- WHO IT'S FOR -->
<section class="section">
    <div class="container">
        <div class="section-label">Who it's for</div>
        <h2 class="section-title">Built for anyone who lends things out</h2>

        <div class="for-grid">
            <div class="for-card">
                <div class="for-emoji">🎬</div>
                <div>
                    <div class="for-title">Film & Photo Studios</div>
                    <div class="for-desc">Track cameras, lenses, lighting, and grip equipment across shoots and crew.</div>
                </div>
            </div>
            <div class="for-card">
                <div class="for-emoji">🏫</div>
                <div>
                    <div class="for-title">Schools & Universities</div>
                    <div class="for-desc">Manage IT equipment, lab gear, and AV kit loans to students and staff.</div>
                </div>
            </div>
            <div class="for-card">
                <div class="for-emoji">🔧</div>
                <div>
                    <div class="for-title">Trades & Contractors</div>
                    <div class="for-desc">Know which tools are on which site and who signed them out last.</div>
                </div>
            </div>
            <div class="for-card">
                <div class="for-emoji">🎤</div>
                <div>
                    <div class="for-title">Event Companies</div>
                    <div class="for-desc">Track AV equipment, staging gear, and props across multiple events and venues.</div>
                </div>
            </div>
            <div class="for-card">
                <div class="for-emoji">⚽</div>
                <div>
                    <div class="for-title">Sports Clubs</div>
                    <div class="for-desc">Issue kit and equipment to members and get it back before the next season.</div>
                </div>
            </div>
            <div class="for-card">
                <div class="for-emoji">🏥</div>
                <div>
                    <div class="for-title">Healthcare & Charities</div>
                    <div class="for-desc">Manage equipment loans to patients, volunteers, or partner organisations.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-box">
            <h2 class="cta-title">Ready to get organised?</h2>
            <p class="cta-sub">Sign in to your workspace and start tracking in minutes.</p>
            <a href="/login" class="btn-hero-primary">Sign in to your workspace →</a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<div class="container">
    <footer>
        <div class="foot-logo">
            <span class="logo-lou">Lou</span><span class="logo-ventory">Ventory</span>
        </div>
        <p>&copy; <?= date('Y') ?> LouVentory. All rights reserved.</p>
    </footer>
</div>

</body>
</html>
