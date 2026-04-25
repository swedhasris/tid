<?php
/**
 * Task Model
 */

require_once __DIR__ . '/../Database.php';

class Task {
    public static function getAll(): array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->query("SELECT * FROM tasks WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $description = null): int {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("INSERT INTO tasks (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, ?string $description = null): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("UPDATE tasks SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
    }

    public static function delete(int $id): void {
        $db = TimesheetDB::getConnection();
        $stmt = $db->prepare("UPDATE tasks SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }
}
