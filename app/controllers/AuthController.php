<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'controllers/ApiController.php';

class AuthController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('UserModel');
    }

    /** POST /api/auth/register */
    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 10, 60);

        $in       = $this->input();
        $username = trim((string) ($in['username'] ?? ''));
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Password must be 8-72 characters.';
        }
        if ($errors) {
            $this->fail('Validation failed', 422, $errors);
        }

        if ($this->UserModel->exists_with('email', $email)) {
            $this->fail('Email is already registered', 409, ['email' => 'Email is already registered.']);
        }
        if ($this->UserModel->exists_with('username', $username)) {
            $this->fail('Username is already taken', 409, ['username' => 'Username is already taken.']);
        }

        $id = $this->UserModel->create([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role'     => 'user',
        ]);

        $this->ok($this->UserModel->find_public($id), 'Account created', 201);
    }

    /** POST /api/auth/login  (email or username + password) */
    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 10, 60);

        $in       = $this->input();
        $login    = trim((string) ($in['email'] ?? $in['username'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->fail('Email and password are required', 422, [
                'email'    => $login === '' ? 'Email is required.' : null,
                'password' => $password === '' ? 'Password is required.' : null,
            ]);
        }

        $user = $this->UserModel->find_by_login(strtolower($login)) ?: $this->UserModel->find_by_login($login);

        // Always run a hash check to keep response time similar for unknown users.
        $hash  = $user['password'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $valid = password_verify($password, $hash);

        if (!$user || !$valid || (int) $user['is_active'] !== 1) {
            $this->fail('Invalid credentials', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => (int) $user['id'],
            'role'   => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $this->ok([
            'user'   => $this->UserModel->find_public((int) $user['id']),
            'tokens' => $tokens,
        ], 'Login successful');
    }

    /** POST /api/auth/refresh  { refresh_token } */
    public function refresh()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('refresh_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 30, 60);

        $token = (string) ($this->input()['refresh_token'] ?? '');
        if ($token === '') {
            $this->fail('refresh_token is required', 422);
        }
        $this->api->refresh_access_token($token); // responds & exits
    }

    /** POST /api/auth/logout  { refresh_token } */
    public function logout()
    {
        $this->api->require_method('POST');
        $this->authenticate();

        $token = (string) ($this->input()['refresh_token'] ?? '');
        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        }
        $this->ok(null, 'Logged out');
    }

    /** GET /api/auth/me */
    public function me()
    {
        $this->api->require_method('GET');
        $payload = $this->authenticate();
        $this->ok($this->UserModel->find_public((int) $payload['sub']));
    }
}
