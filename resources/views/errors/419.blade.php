<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión expirada — XINERGIA</title>
    <meta http-equiv="refresh" content="0;url=/signin">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
        }

        /* Background glow blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.12;
            pointer-events: none;
        }
        .blob-1 {
            width: 520px; height: 520px;
            background: #465fff;
            top: -120px; left: -120px;
        }
        .blob-2 {
            width: 400px; height: 400px;
            background: #7c3aed;
            bottom: -100px; right: -100px;
        }

        .card {
            position: relative;
            background: rgba(255,255,255,0.035);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04) inset;
        }

        .icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(70,95,255,0.2), rgba(124,58,237,0.2));
            border: 1px solid rgba(70,95,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.75rem;
        }
        .icon-wrap svg {
            width: 34px;
            height: 34px;
            color: #818cf8;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 1.25rem;
        }
        .badge-dot {
            width: 6px; height: 6px;
            background: #f87171;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.75); }
        }

        h1 {
            font-size: 1.625rem;
            font-weight: 800;
            color: #f1f5f9;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        p {
            font-size: 0.875rem;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #465fff, #3b47d9);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
            box-shadow: 0 8px 24px rgba(70,95,255,0.35);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(70,95,255,0.45);
            opacity: 0.95;
        }
        .btn svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        .divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.06);
            margin: 1.75rem 0 1.25rem;
        }

        .brand {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(148,163,184,0.5);
        }
        .brand span {
            color: rgba(148,163,184,0.8);
        }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="card">
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>

        <div class="badge">
            <span class="badge-dot"></span>
            Error 419
        </div>

        <h1>Página expirada</h1>

        <p>Tu sesión ha expirado o el token de seguridad ya no es válido. Esto ocurre cuando llevas mucho tiempo inactivo o al recargar una página protegida.</p>

        <a href="/signin" class="btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Ir al inicio de sesión
        </a>

        <hr class="divider">

        <p class="brand">XINERGIA <span>PRO</span></p>
    </div>
</body>
</html>
