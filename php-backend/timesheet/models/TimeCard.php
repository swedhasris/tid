<?php
/**
 * TimeCard Model
 */

require_once __DIR__ . '/../Database.php';

class TimeCard {
    public static function getByTimesheet(int $timesheetId): array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare(
            "SELECT tc.*, t.name as task_name
             FROM time_cards tc
             LEFT JOIN tasks t ON tc.task_id = t.id
             WHERE tc.timesheet_id = ?
             ORDER BY tc.entry_date, tc.created_at"
        );
        $stmt->execute([$timesheetId]);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM time_cards WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $timesheetId, string $entryDate, ?int $taskId, float $hours, ?string $description = null): int {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("INSERT INTO time_cards (timesheet_id, entry_date, task_id, hours_worked, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$timesheetId, $entryDate, $taskId, $hours, $description]);
        $id = (int) $db->lastInsertId();
        Timesheet::updateTotalHours($timesheetId);
        return $id;
    }

    public static function update(int $id, ?int $taskId, float $hours, ?string $description = null): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("UPDATE time_cards SET task_id = ?, hours_worked = ?, description = ? WHERE id = ?");
        $stmt->execute([$taskId, $hours, $description, $id]);

        // Recalculate total hours for the parent timesheet
        $stmt2 = $db->prepare("SELECT timesheet_id FROM time_cards WHERE id = ?");
        $stmt2->execute([$id]);
        $row = $stmt2->fetch();
        if ($row) {
            Timesheet::updateTotalHours((int) $row['timesheet_id']);
        }
    }

    public static function delete(int $id): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT timesheet_id FROM time_cards WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $timesheetId = (int) $row['timesheet_id'];
            $stmt2 = $db->prepare("DELETE FROM time_cards WHERE id = ?");
            $stmt2->execute([$id]);
            Timesheet::updateTotalHours($timesheetId);
        }
    }

    public static function getDayTotal(int $timesheetId, string $entryDate): float {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT COALESCE(SUM(hours_worked), 0) as total FROM time_cards WHERE timesheet_id = ? AND entry_date = ?");
        $stmt->execute([$timesheetId, $entryDate]);
        $row = $stmt->fetch();
        return (float) ($row['total'] ?? 0);
    }

    public static function exists(int $timesheetId, string $entryDate, ?int $taskId, ?int $excludeId = null): bool {
        $db = TimesheetDB::getConnection();
        if ($excludeId) {
            $stmt = $db->prepare("SELECT id FROM time_cards WHERE timesheet_id = ? AND entry_date = ? AND task_id = ? AND id != ?");
            $stmt->execute([$timesheetId, $entryDate, $taskId, $excludeId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM time_cards WHERE timesheet_id = ? AND entry_date = ? AND task_id = ?");
            $stmt->execute([$timesheetId, $entryDate, $taskId]);
        }
        return (bool) $stmt->fetch();
    }
}
