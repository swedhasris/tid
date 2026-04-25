<?php
/**
 * Timesheet Database Connection (PDO)
 * Supports MySQL via environment variables, auto-falls back to SQLite.
 */

class TimesheetDB {
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $db   = getenv('DB_NAME') ?: 'timesheet_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            self::$driver = 'mysql';
        } catch (PDOException $e) {
            // Fallback to SQLite for zero-config local development
            $sqlitePath = __DIR__ . '/timesheet.db';
            try {
                self::$instance = new PDO("sqlite:{$sqlitePath}", null, null, $options);
                self::$driver = 'sqlite';
                self::initSqlite();
            } catch (PDOException $e2) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function getDriver(): string {
        // Ensure connection is established so driver is known
        if (self::$instance === null) {
            self::getConnection();
        }
        return self::$driver;
    }

    public static function sqlNow(): string {
        return self::getDriver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    public static function sqlYear(string $column): string {
        return self::getDriver() === 'sqlite'
            ? "strftime('%Y', {$column})"
            : "YEAR({$column})";
    }

    public static function sqlMonth(string $column): string {
        return self::getDriver() === 'sqlite'
            ? "strftime('%m', {$column})"
            : "MONTH({$column})";
    }

    private static function initSqlite(): void {
        $db = self::$instance;
        $db->exec("PRAGMA foreign_keys = ON;");

        $db->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS timesheets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            user_name TEXT,
            week_start DATE NOT NULL,
            status TEXT DEFAULT 'Draft' CHECK(status IN ('Draft','Submitted','Approved','Rejected')),
            total_hours REAL DEFAULT 0.00,
            admin_comment TEXT,
            submitted_at DATETIME,
            approved_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, week_start)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS time_cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timesheet_id INTEGER NOT NULL,
            entry_date DATE NOT NULL,
            task_id INTEGER,
            hours_worked REAL NOT NULL DEFAULT 0.00,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
            UNIQUE(timesheet_id, entry_date, task_id)
        )");

        // Seed default tasks if empty
        $stmt = $db->query("SELECT COUNT(*) FROM tasks");
        if ((int) $stmt->fetchColumn() === 0) {
            $db->exec("INSERT INTO tasks (name, description) VALUES
                ('General Support', 'General IT support and troubleshooting'),
                ('Ticket Resolution', 'Resolving assigned support tickets'),
                ('Project Work', 'Working on internal projects'),
                ('Meeting', 'Team or client meetings'),
                ('Training', 'Learning and development activities'),
                ('Documentation', 'Writing documentation and knowledge base articles'),
                ('System Maintenance', 'Server and infrastructure maintenance')
            ");
        }
    }
}
