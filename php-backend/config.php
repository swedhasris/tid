<?php
/**
 * Configuration loader for the PHP backend.
 * Reads firebase-applet-config.json and service account credentials.
 */

class AppConfig {
    public static array $firebase = [];
    public static ?array $serviceAccount = null;
    public static bool $loaded = false;
    public static string $error = '';

    public static function load(): bool {
        if (self::$loaded) {
            return true;
        }

        $configPath = __DIR__ . '/../firebase-applet-config.json';
        if (!file_exists($configPath)) {
            self::$error = 'firebase-applet-config.json not found';
            return false;
        }

        $json = file_get_contents($configPath);
        $config = json_decode($json, true);
        if (!$config || !isset($config['projectId']) || !isset($config['firestoreDatabaseId'])) {
            self::$error = 'firebase-applet-config.json is malformed: missing projectId or firestoreDatabaseId';
            return false;
        }

        self::$firebase = $config;

        // Load service account from environment variable or default path
        $serviceAccountPath = getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: __DIR__ . '/../service-account.json';
        if (file_exists($serviceAccountPath)) {
            $saJson = file_get_contents($serviceAccountPath);
            self::$serviceAccount = json_decode($saJson, true);
            if (!self::$serviceAccount) {
                self::$error = 'Service account JSON is malformed';
                return false;
            }
        } else {
            self::$error = 'Service account JSON not found. Set GOOGLE_APPLICATION_CREDENTIALS or place service-account.json in project root.';
            return false;
        }

        self::$loaded = true;
        return true;
    }

    public static function getProjectId(): string {
        return self::$firebase['projectId'] ?? '';
    }

    public static function getDatabaseId(): string {
        return self::$firebase['firestoreDatabaseId'] ?? '(default)';
    }

    public static function getServiceAccountEmail(): string {
        return self::$serviceAccount['client_email'] ?? '';
    }

    public static function getPrivateKey(): string {
        return self::$serviceAccount['private_key'] ?? '';
    }
}
