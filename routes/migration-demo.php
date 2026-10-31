<?php
global $app;

// Demo/debug-only routes showing the migration plugin features.
// Never registered unless APP_DEBUG=true, same convention as other demo routes.
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    
    // 1. Render migration dashboard
    $app->get('/migration-demo', function($req, $env) {
        try {
            $migrator = $env->migrator;
            $status = $migrator->getStatus();
            
            // Render the dashboard template view
            return View::render('migration_demo', [
                'title' => 'Database Migration Panel',
                'status' => $status,
                'driver' => strtolower($_ENV['DB_CONNECTION'] ?? 'mysql')
            ]);
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
