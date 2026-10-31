<?php
global $app;

// Demo/debug-only routes showing the migration plugin features.
// Never registered unless APP_DEBUG=true, same convention as other demo routes.
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {

    if (!function_exists('renderMigrationDashboardHtml')) {
        function renderMigrationDashboardHtml(array $status, string $driver): string {
            $total = count($status);
            $appliedCount = 0;
            $pendingCount = 0;
            foreach ($status as $s) {
                if (!empty($s['applied'])) {
                    $appliedCount++;
                } else {
                    $pendingCount++;
                }
            }

            $driverName = strtoupper($driver ?: 'MYSQL');

            $rowsHtml = '';
            if (empty($status)) {
                $rowsHtml = '<tr><td colspan="3" style="text-align:center; padding: 2.5rem; color: #94a3b8; font-style: italic;">No migration files found in database/migrations/</td></tr>';
            } else {
                foreach ($status as $s) {
                    $name = htmlspecialchars($s['migration'], ENT_QUOTES, 'UTF-8');
                    $appliedAt = htmlspecialchars($s['applied_at'] ?: '-', ENT_QUOTES, 'UTF-8');
                    $isApplied = !empty($s['applied']);
                    $batch = htmlspecialchars((string)($s['batch'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $badge = $isApplied 
                        ? '<span class="badge badge-success">Ran (Batch #' . $batch . ')</span>'
                        : '<span class="badge badge-pending">Pending</span>';

                    $rowsHtml .= "<tr>
                        <td class=\"col-name\">{$name}</td>
                        <td class=\"col-status\">{$badge}</td>
                        <td class=\"col-date\">{$appliedAt}</td>
                    </tr>";
                }
            }

            return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration Panel - Wisp</title>
    <style>
        :root {
            --bg: #0b0f19;
            --surface: #111827;
            --surface-card: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(99, 102, 241, 0.4);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #6366f1;
            --accent-gradient: linear-gradient(135deg, #6366f1, #a855f7);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 2.5rem 1.5rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }
        .title-group h1 {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .title-group p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .driver-badge {
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }
        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-value.primary { color: #818cf8; }
        .stat-value.success { color: var(--success); }
        .stat-value.warning { color: var(--warning); }
        
        .action-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .btn-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover:not(:disabled) {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.25);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.1);
        }
        .create-form {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .input-text {
            flex: 1;
            min-width: 250px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.65rem 1rem;
            color: white;
            font-size: 0.9rem;
        }
        .input-text:focus {
            outline: none;
            border-color: var(--accent);
        }
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: rgba(255, 255, 255, 0.02);
            padding: 1rem 1.25rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td {
            background: rgba(255, 255, 255, 0.015);
        }
        .col-name { font-family: monospace; font-size: 0.85rem; color: #e2e8f0; }
        .col-date { color: var(--text-secondary); font-size: 0.85rem; }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        #alert-box {
            display: none;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title-group">
                <h1>🌬️ Wisp Migration Panel</h1>
                <p>Database versioning, batch tracking & schema migrations</p>
            </div>
            <div class="driver-badge">Driver: {$driverName}</div>
        </div>

        <div id="alert-box"></div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Migrations</div>
                <div class="stat-value primary">{$total}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Applied (Ran)</div>
                <div class="stat-value success">{$appliedCount}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value warning">{$pendingCount}</div>
            </div>
        </div>

        <div class="action-card">
            <div class="action-header">
                <div class="action-title">Actions & Operations</div>
                <div class="btn-group">
                    <button id="btn-run" class="btn btn-primary" onclick="runMigrations()">⚡ Run Pending</button>
                    <button id="btn-rollback" class="btn btn-danger" onclick="rollbackMigrations()">↩️ Rollback Last Batch</button>
                    <button class="btn btn-secondary" onclick="window.location.reload()">🔄 Refresh</button>
                </div>
            </div>

            <form class="create-form" onsubmit="createMigration(event)">
                <input type="text" id="migration-name" class="input-text" placeholder="New migration name (e.g. create_users_table)" required>
                <button type="submit" id="btn-create" class="btn btn-secondary">➕ Create Migration</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Migration File Name</th>
                        <th>Status</th>
                        <th>Applied At</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showAlert(msg, isSuccess) {
            const box = document.getElementById('alert-box');
            box.className = isSuccess ? 'alert-success' : 'alert-error';
            box.textContent = msg;
            box.style.display = 'block';
        }

        async function runMigrations() {
            const btn = document.getElementById('btn-run');
            btn.disabled = true;
            btn.textContent = 'Running...';
            try {
                const res = await fetch('/migration-demo/run', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
                const json = await res.json();
                if (res.ok) {
                    showAlert(json.message || 'Migrations completed successfully.', true);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showAlert(json.message || 'Failed to run migrations.', false);
                    btn.disabled = false;
                    btn.textContent = '⚡ Run Pending';
                }
            } catch (err) {
                showAlert(err.message, false);
                btn.disabled = false;
                btn.textContent = '⚡ Run Pending';
            }
        }

        async function rollbackMigrations() {
            if (!confirm('Are you sure you want to rollback the last migration batch?')) {
                return;
            }
            const btn = document.getElementById('btn-rollback');
            btn.disabled = true;
            btn.textContent = 'Rolling back...';
            try {
                const res = await fetch('/migration-demo/rollback', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
                const json = await res.json();
                if (res.ok) {
                    showAlert(json.message || 'Rollback completed successfully.', true);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showAlert(json.message || 'Failed to rollback migrations.', false);
                    btn.disabled = false;
                    btn.textContent = '↩️ Rollback Last Batch';
                }
            } catch (err) {
                showAlert(err.message, false);
                btn.disabled = false;
                btn.textContent = '↩️ Rollback Last Batch';
            }
        }

        async function createMigration(e) {
            e.preventDefault();
            const input = document.getElementById('migration-name');
            const name = input.value.trim();
            if (!name) return;

            const btn = document.getElementById('btn-create');
            btn.disabled = true;
            btn.textContent = 'Creating...';
            try {
                const res = await fetch('/migration-demo/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const json = await res.json();
                if (res.ok) {
                    showAlert('Migration created: ' + (json.data ? json.data.filename : ''), true);
                    input.value = '';
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showAlert(json.message || 'Failed to create migration.', false);
                    btn.disabled = false;
                    btn.textContent = '➕ Create Migration';
                }
            } catch (err) {
                showAlert(err.message, false);
                btn.disabled = false;
                btn.textContent = '➕ Create Migration';
            }
        }
    </script>
</body>
</html>
HTML;
        }
    }

    // 1. Render migration dashboard (Zero dependency on wisp-mvc)
    $app->get('/migration-demo', function($req, $env) {
        try {
            $migrator = $env->migrator;
            $status = $migrator->getStatus();
            $driver = strtolower($_ENV['DB_CONNECTION'] ?? 'mysql');

            // If JSON requested via header or query param
            $accept = $req->header('Accept') ?? '';
            if (strpos($accept, 'application/json') !== false || ($req->query['format'] ?? '') === 'json') {
                return Response::json([
                    'status' => 'success',
                    'driver' => $driver,
                    'migrations' => $status
                ]);
            }
            
            // Return standalone HTML dashboard (Pure Wisp Core - No View/wisp-mvc required)
            return Response::html(renderMigrationDashboardHtml($status, $driver));
        } catch (\Exception $e) {
            return Response::text("Error loading migrations: " . $e->getMessage(), 500);
        }
    });

    // 2. API Endpoint to run pending migrations
    $app->post('/migration-demo/run', function($req, $env) {
        try {
            $migrator = $env->migrator;
            $executed = $migrator->runPending();
            return Response::json([
                'status' => 'success',
                'message' => count($executed) > 0 
                    ? 'Successfully executed ' . count($executed) . ' migrations.' 
                    : 'No pending migrations to run.',
                'data' => $executed
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    // 3. API Endpoint to rollback the last batch
    $app->post('/migration-demo/rollback', function($req, $env) {
        try {
            $migrator = $env->migrator;
            $rolled = $migrator->rollbackLastBatch();
            return Response::json([
                'status' => 'success',
                'message' => count($rolled) > 0 
                    ? 'Successfully rolled back ' . count($rolled) . ' migrations.' 
                    : 'No migrations to rollback.',
                'data' => $rolled
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    // 4. API Endpoint to create a new migration stub
    $app->post('/migration-demo/create', function($req, $env) {
        try {
            $data = $req->json();
            $name = $data['name'] ?? null;
            if (!$name || trim($name) === '') {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Migration name is required.'
                ], 400);
            }

            $migrator = $env->migrator;
            $filepath = $migrator->createMigrationFile($name);
            return Response::json([
                'status' => 'success',
                'message' => 'Migration skeleton created successfully.',
                'data' => [
                    'filepath' => $filepath,
                    'filename' => basename($filepath)
                ]
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });
}
