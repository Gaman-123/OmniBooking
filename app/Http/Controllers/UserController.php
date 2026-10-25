<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController
{
    /**
     * GET /api/users
     * List all users (passwords excluded).
     */
    public function list(): array
    {
        $users = User::all(100);
        return [
            'status' => 'success',
            'count'  => count($users),
            'users'  => $users,
        ];
    }

    /**
     * GET /api/users/{id}
     * Get a single user by ID.
     */
    public function show(int $id): array
    {
        $user = User::find($id);

        if (!$user) {
            http_response_code(404);
            return ['status' => 'error', 'message' => "User #{$id} not found."];
        }

        return [
            'status' => 'success',
            'user'   => $user,
        ];
    }

    /**
     * POST /api/users
     * Create a new user.
     *
     * Required JSON body:
     *   { "name": "...", "email": "...", "password": "..." }
     * Optional:
     *   { "phone": "...", "role": "guest|staff|admin" }
     */
    public function create(): array
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // ── Validation ──────────────────────────────────────────────────────
        $errors = [];

        if (empty($body['name'])) {
            $errors[] = "'name' is required.";
        }

        if (empty($body['email'])) {
            $errors[] = "'email' is required.";
        } elseif (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "'email' must be a valid email address.";
        } elseif (User::emailExists($body['email'])) {
            $errors[] = "Email '{$body['email']}' is already registered.";
        }

        if (empty($body['password'])) {
            $errors[] = "'password' is required.";
        }

        $allowedRoles = ['guest', 'staff', 'admin'];
        $role = $body['role'] ?? 'guest';
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = "'role' must be one of: " . implode(', ', $allowedRoles) . ".";
        }

        if (!empty($errors)) {
            http_response_code(422);
            return ['status' => 'error', 'errors' => $errors];
        }

        // ── Create ──────────────────────────────────────────────────────────
        $userId = User::create([
            'name'     => trim($body['name']),
            'email'    => trim($body['email']),
            'password' => $body['password'],    // stored as-is, no hashing
            'phone'    => $body['phone'] ?? null,
            'role'     => $role,
        ]);

        $user = User::find($userId);

        http_response_code(201);
        return [
            'status'  => 'success',
            'message' => "User '{$user['name']}' created successfully.",
            'user'    => $user,
        ];
    }

    /**
     * PUT /api/users/{id}
     * Update an existing user's profile fields.
     *
     * Accepted fields: name, phone, avatar_url, role, is_active, password
     */
    public function update(int $id): array
    {
        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            return ['status' => 'error', 'message' => "User #{$id} not found."];
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['name', 'phone', 'avatar_url', 'role', 'is_active', 'password'];
        $updates = array_intersect_key($body, array_flip($allowed));

        if (empty($updates)) {
            http_response_code(422);
            return [
                'status'  => 'error',
                'message' => 'No valid fields provided. Accepted: ' . implode(', ', $allowed),
            ];
        }

        User::update($id, $updates);

        return [
            'status'  => 'success',
            'message' => "User #{$id} updated.",
            'user'    => User::find($id),
        ];
    }

    /**
     * DELETE /api/users/{id}
     * Soft-deactivate a user (preserves booking history).
     */
    public function deactivate(int $id): array
    {
        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            return ['status' => 'error', 'message' => "User #{$id} not found."];
        }

        User::deactivate($id);

        return [
            'status'  => 'success',
            'message' => "User '{$user['name']}' (#{$id}) has been deactivated.",
        ];
    }

    /**
     * POST /api/users/login
     * Authenticate with email + plain-text password.
     */
    public function login(): array
    {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $body['email']    ?? '';
        $pass  = $body['password'] ?? '';

        if (!$email || !$pass) {
            http_response_code(422);
            return ['status' => 'error', 'message' => "'email' and 'password' are required."];
        }

        $user = User::authenticate($email, $pass);

        if (!$user) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'Invalid email or password.'];
        }

        return [
            'status'  => 'success',
            'message' => "Welcome back, {$user['name']}!",
            'user'    => $user,
        ];
    }

    /**
     * GET /api/users/{id}/bookings
     * Get all bookings for a specific user.
     */
    public function bookings(int $id): array
    {
        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            return ['status' => 'error', 'message' => "User #{$id} not found."];
        }

        $bookings = User::bookings($id);

        return [
            'status'   => 'success',
            'user'     => $user['name'],
            'count'    => count($bookings),
            'bookings' => $bookings,
        ];
    }
}
