<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LouVentory — Inventory Management</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body { background: #0F1117; margin: 0; }

        .landing {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Nav ── */
        .landing-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 3rem;
            border-bottom: 1px solid #1F2937;
        }
        .landing-logo {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .landing-nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav-link-ghost {
            color: #9CA3AF;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }
        .nav-link-ghost:hover { background: #1F2937; color: #E5E7EB; text-decoration: none; }

        /* ── Hero ── */
        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 5rem 2rem 4rem;
        }
        .hero-badge {
            display: inline-block;
            background: #378ADD22;
            color: #378ADD;
            border: 1px solid #378ADD44;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 0.9rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: 3.75rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
            color: #FFFFFF;
            margin-bottom: 1.25rem;
            max-width: 720px;
        }
        .hero h1 .accent { color: #378ADD; }
        .hero-sub {
            font-size: 1.125rem;
            color: #9CA3AF;
            max-width: 520px;
            line-height: 1.65;
            margin-bottom: 3rem;
        }

        /* ── Login cards ── */
        .login-cards {
            display: flex;
            gap: 1.25rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }
        .login-card {
            background: #161B27;
            border: 1px solid #1F2937;
            border-radius: 12px;
            padding: 2rem;
            width: 280px;
            text-align: left;
            text-decoration: none;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
            display: block;
        }
        .login-card:hover {
            border-color: #378ADD;
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(55, 138, 221, 0.15);
            text-decoration: none;
        }
        .login-card-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }
        .login-card-icon svg { width: 22px; height: 22px; }
        .icon-blue  { background: #378ADD22; color: #378ADD; }
        .icon-purple{ background: #7C3AED22; color: #A78BFA; }
        .login-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 0.4rem;
        }
        .login-card-desc {
            font-size: 0.8rem;
            color: #6B7280;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        .login-card-action {
            font-size: 0.8rem;
            font-weight: 600;
            color: #378ADD;
        }
        .login-card-action-purple { color: #A78BFA; }
        .login-card:hover .login-card-action { text-decoration: underline; }

        /* ── Features ── */
        .features {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 4rem;
            padding: 0 2rem;
        }
        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6B7280;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .feature-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #378ADD;
            flex-shrink: 0;
        }

        /* ── Footer ── */
        .landing-footer {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid #1F2937;
            color: #374151;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
<div class="landing">

    <nav class="landing-nav">
        <div class="landing-logo">
            <span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span>
        </div>
        <div class="landing-nav-links">
            <a href="/login" class="nav-link-ghost">Customer Login</a>
            <a href="/admin/" class="btn btn-primary" style="font-size:0.8rem; padding:0.45rem 0.9rem;">Admin</a>
        </div>
    </nav>

    <div class="hero">
        <div class="hero-badge">Inventory Management</div>
        <h1>Know what you have.<br><span class="accent">Know where it is.</span></h1>
        <p class="hero-sub">
            LouVentory helps you track stock, manage loans, and stay on top of your inventory — all in one clean, simple dashboard.
        </p>

        <div class="login-cards">
            <!-- Customer login -->
            <a href="/login" class="login-card">
                <div class="login-card-icon icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="login-card-title">Customer Login</div>
                <div class="login-card-desc">Access your workspace to manage your inventory, track loans, and view activity.</div>
                <div class="login-card-action">Sign in to your workspace →</div>
            </a>

            <!-- Admin login -->
            <a href="/admin/" class="login-card">
                <div class="login-card-icon icon-purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="login-card-title">Admin Panel</div>
                <div class="login-card-desc">Manage workspaces, create tenants, and oversee the platform.</div>
                <div class="login-card-action login-card-action-purple">Sign in as superadmin →</div>
            </a>
        </div>

        <div class="features">
            <div class="feature"><div class="feature-dot"></div> Track items & stock levels</div>
            <div class="feature"><div class="feature-dot"></div> Manage loans & borrowers</div>
            <div class="feature"><div class="feature-dot"></div> Barcode scanner support</div>
            <div class="feature"><div class="feature-dot"></div> Activity log & history</div>
            <div class="feature"><div class="feature-dot"></div> Photo uploads</div>
            <div class="feature"><div class="feature-dot"></div> Overdue alerts</div>
        </div>
    </div>

    <div class="landing-footer">
        &copy; <?= date('Y') ?> LouVentory. All rights reserved.
    </div>

</div>
</body>
</html>
