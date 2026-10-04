<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class UserModel extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';

    public function find_by_login(string $login)
    {
        return $this->db->table($this->table)
            ->grouped(function ($q) use ($login) {
                $q->where('email', $login)->or_where('username', $login);
            })
            ->get();
    }

    public function find_public(int $id)
    {
        return $this->db->table($this->table)
            ->select('id, username, email, role, created_at')
            ->where('id', $id)
            ->get();
    }

    public function exists_with(string $column, string $value): bool
    {
        if (!in_array($column, ['email', 'username'], true)) {
            return false;
        }
        return (bool) $this->db->table($this->table)->where($column, $value)->get();
    }

    public function create(array $data)
    {
        $this->db->table($this->table)->insert([
            'username'   => $data['username'],
            'email'      => $data['email'],
            'password'   => $data['password'],
            'role'       => $data['role'] ?? 'user',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->last_id();
    }
}
