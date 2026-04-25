<?php
/**
 * Timesheet Model
 */

require_once __DIR__ . '/../Database.php';

class Timesheet {
    public static function getOrCreate(string $userId, string $userName, string $weekStart): array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM timesheets WHERE user_id = ? AND week_start = ?");
        $stmt->execute([$userId, $weekStart]);
        $row = $stmt->fetch();

        if ($row) {
            return $row;
        }

        $stmt = $db->prepare("INSERT INTO timesheets (user_id, user_name, week_start, status, total_hours) VALUES (?, ?, ?, 'Draft', 0.00)");
        $stmt->execute([$userId, $userName, $weekStart]);
        $id = (int) $db->lastInsertId();

        return [
            'id' => $id,
            'user_id' => $userId,
            'user_name' => $userName,
            'week_start' => $weekStart,
            'status' => 'Draft',
            'total_hours' => '0.00',
            'admin_comment' => null,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    public static function getById(int $id): ?array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM timesheets WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByUserAndWeek(string $userId, string $weekStart): ?array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM timesheets WHERE user_id = ? AND week_start = ?");
        $stmt->execute([$userId, $weekStart]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAllByUser(string $userId): array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM timesheets WHERE user_id = ? ORDER BY week_start DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getAllForAdmin(?string $status = null): array {
        $db = TimesheetDB::getConnection();
        if ($status) {
            $stmt = $db->prepare("SELECT * FROM timesheets WHERE status = ? ORDER BY week_start DESC, created_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query("SELECT * FROM timesheets ORDER BY week_start DESC, created_at DESC");
        }
        return $stmt->fetchAll();
    }

    public static function updateTotalHours(int $id): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("UPDATE timesheets t SET total_hours = (SELECT COALESCE(SUM(hours_worked), 0) FROM time_cards WHERE timesheet_id = ?) WHERE t.id = ?");
        $stmt->execute([$id, $id]);
    }

    public static function submit(int $id): void {
        $db = TimesheetDB::getConnection();
        $now = TimesheetDB::sqlNow();
        $stmt = $db->prepare("UPDATE timesheets SET status = 'Submitted', submitted_at = {$now} WHERE id = ? AND status = 'Draft'");
        $stmt->execute([$id]);
    }

    public static function approve(int $id, ?string $comment = null): void {
        $db = TimesheetDB::getConnection();
        $now = TimesheetDB::sqlNow();
        $stmt = $db->prepare("UPDATE timesheets SET status = 'Approved', approved_at = {$now}, admin_comment = ? WHERE id = ? AND status = 'Submitted'");
        $stmt->execute([$comment, $id]);
    }

    public static function reject(int $id, string $comment): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("UPDATE timesheets SET status = 'Rejected', admin_comment = ? WHERE id = ? AND status = 'Submitted'");
        $stmt->execute([$comment, $id]);
    }

    public static function getWeeklyHoursByUser(string $userId, string $weekStart): array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare(
            "SELECT tc.entry_date, SUM(tc.hours_worked) as day_total
             FROM timesheets t
             JOIN time_cards tc ON t.id = tc.timesheet_id
             WHERE t.user_id = ? AND t.week_start = ?
             GROUP BY tc.entry_date"
        );
        $stmt->execute([$userId, $weekStart]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['entry_date']] = (float) $row['day_total'];
        }
        return $results;
    }

    public static function getMonthlyReport(string $userId, int $year, int $month): array {
        $db = TimesheetDB::getConnection();
        $yearSql = TimesheetDB::sqlYear('t.week_start');
        $monthSql = TimesheetDB::sqlMonth('t.week_start');
        $stmt = $db->prepare(
            "SELECT t.week_start, t.status, t.total_hours
             FROM timesheets t
             WHERE t.user_id = ? AND {$yearSql} = ? AND {$monthSql} = ?
             ORDER BY t.week_start"
        );
        $stmt->execute([$userId, $year, $month]);
        return $stmt->fetchAll();
    }

    public static function getAllUsersMonthlyReport(int $year, int $month): array {
        $db = TimesheetDB::getConnection();
        $yearSql = TimesheetDB::sqlYear('week_start');
        $monthSql = TimesheetDB::sqlMonth('week_start');
        $stmt = $db->prepare(
            "SELECT user_id, user_name, SUM(total_hours) as total_hours, COUNT(id) as week_count
             FROM timesheets
             WHERE {$yearSql} = ? AND {$monthSql} = ?
             GROUP BY user_id, user_name
             ORDER BY total_hours DESC"
        );
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll();
    }
}
