<?php
// src/models/Team.php

namespace App\Models;

use PDO;

class Team
{
    /** @var PDO */
    private static ?PDO $pdo = null;

    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/../config/db.php';
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                self::$pdo = $GLOBALS['pdo'];
            } else {
                self::$pdo = \App\Config\get_pdo();
            }
        }
        return self::$pdo;
    }

    /**
     * Find team by ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT id, name, room_id, created_at
            FROM teams
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new team for a room.
     *
     * @param string $name
     * @param int $roomId
     * @return int New team ID.
     */
    public static function create(string $name, int $roomId): int
    {
        $stmt = self::pdo()->prepare("
            INSERT INTO teams (name, room_id, created_at)
            VALUES (:name, :room_id, NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':room_id' => $roomId
        ]);
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * Add a user to a team.
     *
     * @param int $teamId
     * @param int $userId
     * @return void
     * @throws \PDOException
     */
    public static function addMember(int $teamId, int $userId): void
    {
        $stmt = self::pdo()->prepare("
            INSERT INTO team_members (team_id, user_id, joined_at)
            VALUES (:team_id, :user_id, NOW())
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $userId
        ]);
    }

    /**
     * Remove a user from a team.
     */
    public static function removeMember(int $teamId, int $userId): void
    {
        $stmt = self::pdo()->prepare("
            DELETE FROM team_members
            WHERE team_id = :team_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $userId
        ]);
    }

    /**
     * Get members of a team.
     *
     * @param int $teamId
     * @return array[]
     */
    public static function getMembers(int $teamId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT u.id, u.username, u.email, tm.joined_at
            FROM team_members tm
            JOIN users u ON u.id = tm.user_id
            WHERE tm.team_id = :team_id
            ORDER BY tm.joined_at
        ");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get teams for a room with member counts.
     *
     * @param int $roomId
     * @return array[]
     */
    public static function getTeamsForRoom(int $roomId): array
    {
        $stmt = self::pdo()->prepare("
            SELECT
                t.id AS team_id,
                t.name AS team_name,
                t.room_id,
                COUNT(tm.user_id) AS member_count
            FROM teams t
            LEFT JOIN team_members tm ON tm.team_id = t.id
            WHERE t.room_id = :room_id
            GROUP BY t.id, t.name, t.room_id
            ORDER BY t.id DESC
        ");
        $stmt->execute([':room_id' => $roomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user is already in a team for a given room.
     *
     * @param int $userId
     * @param int $roomId
     * @return array|null Team info if found, else null.
     */
    public static function getUserTeamForRoom(int $userId, int $roomId): ?array
    {
        $stmt = self::pdo()->prepare("
            SELECT t.id AS team_id, t.name AS team_name, t.room_id
            FROM teams t
            JOIN team_members tm ON tm.team_id = t.id
            WHERE t.room_id = :room_id AND tm.user_id = :user_id
        ");
        $stmt->execute([
            ':room_id' => $roomId,
            ':user_id' => $userId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a user is a member of a specific team.
     */
    public static function isMember(int $teamId, int $userId): bool
    {
        $stmt = self::pdo()->prepare("
            SELECT id FROM team_members
            WHERE team_id = :team_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $userId
        ]);
        return (bool)$stmt->fetch();
    }
}