<?php
namespace App\Models;
use PDO;
class Hint
{
    private static ?PDO $pdo = null;
    private static function pdo(): PDO { require_once __DIR__ . '/../config/db.php'; return self::$pdo ??= $GLOBALS['pdo']; }
    public static function getNextForSession(int $sessionId, int $puzzleId): ?array
    {
        $stmt = self::pdo()->prepare('SELECT h.* FROM hints h JOIN puzzle_progress pp ON pp.puzzle_id = h.puzzle_id WHERE pp.session_id = :session_id AND h.puzzle_id = :puzzle_id AND h.order_index > pp.hints_used_count ORDER BY h.order_index LIMIT 1');
        $stmt->execute([':session_id' => $sessionId, ':puzzle_id' => $puzzleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }
    public static function grantToSession(int $sessionId, int $puzzleId, int $hintId): bool
    {
        $stmt = self::pdo()->prepare('UPDATE puzzle_progress SET hints_used_count = hints_used_count + 1, penalty_points = penalty_points + (SELECT penalty_points FROM hints WHERE id = :hint_id) WHERE session_id = :session_id AND puzzle_id = :puzzle_id');
        return $stmt->execute([':hint_id' => $hintId, ':session_id' => $sessionId, ':puzzle_id' => $puzzleId]);
    }
}
