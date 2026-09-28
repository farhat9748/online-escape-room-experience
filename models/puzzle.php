<?php
namespace App\Models;
use PDO;
class Puzzle
{
    private static ?PDO $pdo = null;
    private static function pdo(): PDO { require_once __DIR__ . '/../config/db.php'; return self::$pdo ??= $GLOBALS['pdo']; }
    public static function findById(int $id, bool $includeAnswer = false): ?array { $cols = $includeAnswer ? '*' : 'id, room_id, title, puzzle_type, question_text, answer_case_sensitive, order_in_room, points, created_at'; $stmt = self::pdo()->prepare("SELECT $cols FROM puzzles WHERE id = :id"); $stmt->execute([':id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null; }
    public static function getNextUnsolvedForSession(int $sessionId): ?array { $stmt = self::pdo()->prepare('SELECT p.* FROM puzzles p JOIN puzzle_progress pp ON pp.puzzle_id = p.id WHERE pp.session_id = :session_id AND pp.is_solved = 0 ORDER BY p.order_in_room LIMIT 1'); $stmt->execute([':session_id' => $sessionId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null; }
    public static function verifyAnswer(int $id, string $answer): bool { $puzzle = self::findById($id, true); if (!$puzzle) return false; $expected = (string)($puzzle['answer_text'] ?? $puzzle['answer'] ?? ''); return (bool)($puzzle['answer_case_sensitive'] ? hash_equals($expected, $answer) : strcasecmp($expected, $answer) === 0); }
}
