<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * MigrationController
 *
 * Exposes the LavaLust Migration library through routes.
 * CLI  -> always allowed  (php lava migration run)
 * Web  -> blocked unless ALLOW_WEB_MIGRATION=true, because /rollback-all
 *         and /refresh would wipe data on a public server.
 */
class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!IS_CLI && strtolower((string) getenv('ALLOW_WEB_MIGRATION')) !== 'true') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'Migration routes are disabled over HTTP. Use: php lava migration run',
            ]);
            exit;
        }

        if (!IS_CLI) {
            header('Content-Type: text/plain; charset=utf-8');
        }

        $this->call->library('migration');
    }

    public function create_migration($migration_class)
    {
        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->migration->refresh();
    }

    public function status()
    {
        $this->migration->status();
    }
}
