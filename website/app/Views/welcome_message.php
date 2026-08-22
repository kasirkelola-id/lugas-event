<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUGAS - Layanan Kegiatan & Absensi Terpadu</title>
    <style>
        :root {
            --primary: #2563eb;
            --background: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --success: #10b981;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card {
            background: var(--surface);
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .hero h2 {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
        }

        .status-text {
            color: var(--text-main);
            margin-bottom: 3rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
            text-align: left;
        }

        .info-item {
            padding: 1.5rem;
            background: var(--background);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .description {
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 3rem auto;
            font-size: 1.125rem;
        }

        .cta-button {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .cta-button:hover {
            opacity: 0.9;
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        @media (max-width: 640px) {
            .card {
                padding: 2rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .hero h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <main class="card">
            <div class="hero">
                <h1>LUGAS</h1>
                <h2>Layanan Kegiatan &amp; Absensi Terpadu</h2>
            </div>

            <div>
                <div class="status-badge">
                    <div class="status-dot"></div>
                    Layanan Aktif
                </div>
                <p class="status-text">Platform LUGAS berjalan dengan normal.</p>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">API Status</div>
                    <div class="info-value">Online</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Version</div>
                    <div class="info-value">v1.0</div>
                </div>
            </div>

            <p class="description">
                LUGAS membantu pengelola dan anggota mengelola kegiatan, absensi, pengumuman, dan laporan dalam satu platform.
            </p>

            <!-- <a href="#" class="cta-button">Gunakan Aplikasi LUGAS</a> -->
        </main>
    </div>

    <footer>
        &copy; <?php echo date('Y'); ?> LUGAS
    </footer>
</body>
</html>
