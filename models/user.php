<?php
// src/models/User.php

namespace App\Models;

use PDO;
use PDOException;

class User
{
    /** @var PDO */
    private static ?PDO $pdo = null;

    /**
     * Get PDO instance (lazy-loaded).
     */
    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            // Assumes database.php exposes get_pdo() or global $pdo
            require_once __DIR__ . '/../config/db.php';
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                self::$pdo = $GLOBALS['pdo'];
            } else {
                // Fallback if you prefer a function
                self::$pdo = \App\Config\get_pdo();
            }
        }
        return self::$pdo;
    }

    /**
     * Find user by ID.
     *
     * @param int $id
     * @return array|null Associative row or null if not found.
     */
    public static function findById(int $id): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, username, email, created_at
            FROM users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find user by username or email (for login).
     *
     * @param string $usernameOrEmail
     * @return array|null Row with id, username, email, password_hash or null.
     */
    public static function findByIdentity(string $usernameOrEmail): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, username, email, password_hash
            FROM users
            WHERE username = :identifier OR email = :identifier
            LIMIT 1
        ");
        $stmt->execute([':identifier' => $usernameOrEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find user by email (for uniqueness checks).
     *
     * @param string $email
     * @param int|null $excludeId Exclude this user ID (for updates).
     * @return array|null
     */
    public static function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = "SELECT id, username, email FROM users WHERE email = :email";
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find user by username (for uniqueness checks).
     *
     * @param string $username
     * @param int|null $excludeId
     * @return array|null
     */
    public static function findByUsername(string $username, ?int $excludeId = null): ?array
    {
        $sql = "SELECT id, username, email FROM users WHERE username = :username";
        $params = [':username' => $username];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new user.
     *
     * @param string $username
     * @param string $email
     * @param string $plainPassword
     * @return int New user ID.
     * @throws PDOException
     */
    public static function create(string $username, string $email, string $plainPassword): int
    {
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = self::pdo()->prepare("
            INSERT INTO users (username, email, password_hash, created_at)
            VALUES (:username, :email, :password_hash, NOW())
        ");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);

        return (int)self::pdo()->lastInsertId();
    }

    /**
     * Update username and email for a user.
     *
     * @param int $id
     * @param string $username
     * @param string $email
     * @return void
     * @throws PDOException
     */
    public static function updateProfile(int $id, string $username, string $email): void
    {
        $stmt = self::pdo()->prepare("
            UPDATE users
            SET username = :username, email = :email
            WHERE id = :id
        ");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':id' => $id,
        ]);
    }

    /**
     * Update password for a user.
     *
     * @param int $id
     * @param string $plainPassword
     * @return void
     * @throws PDOException
     */
    public static function updatePassword(int $id, string $plainPassword): void
    {
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = self::pdo()->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE id = :id
        ");
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $id,
        ]);
    }

    /**
     * Get user's game stats (games played, completed, best score).
     *
     * @param int $userId
     * @return array{games_played:int,games_completed:int,best_score:int|null}
     */
    public static function getStats(int $userId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                COUNT(*) AS games_played,
                SUM(CASE WHEN gs.status = 'completed' THEN 1 ELSE 0 END) AS games_completed,
                MAX(gs.total_score) AS best_score
            FROM game_sessions gs
            JOIN teams t ON t.id = gs.team_id
            JOIN team_members tm ON tm.team_id = t.id
            WHERE tm.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'games_played' => (int)($row['games_played'] ?? 0),
            'games_completed' => (int)($row['games_completed'] ?? 0),
            'best_score' => $row['best_score'] !== null ? (int)$row['best_score'] : null,
        ];
    }
}