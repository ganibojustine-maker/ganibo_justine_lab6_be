<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ApiController
 *
 * Base controller for JSON endpoints. All responses go through the
 * LavaLust Api library ($this->api->respond / respond_error).
 */
class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Raw JSON body. (Api::body() HTML-escapes every value, which would
     * corrupt passwords and names such as "Tom & Jerry". Output escaping is
     * done by React and SQL injection is prevented by prepared statements.)
     */
    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (is_array($data)) {
            return $data;
        }
        return $_POST ?: [];
    }

    protected function ok($data = null, string $message = 'OK', int $code = 200)
    {
        $this->api->respond([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function fail(string $message, int $code = 400, array $errors = [])
    {
        $payload = ['success' => false, 'error' => $message, 'status' => $code];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        $this->api->respond($payload, $code);
    }

    /** Requires a valid Bearer access token. Returns its payload. */
    protected function authenticate(): array
    {
        return $this->api->require_jwt();
    }
}
