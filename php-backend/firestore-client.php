<?php
/**
 * Firestore REST API Client with OAuth2 Service Account authentication.
 */

require_once __DIR__ . '/config.php';

class FirestoreClient {
    private string $projectId;
    private string $databaseId;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct() {
        if (!AppConfig::load()) {
            throw new Exception(AppConfig::$error);
        }
        $this->projectId = AppConfig::getProjectId();
        $this->databaseId = AppConfig::getDatabaseId();
    }

    /**
     * Get (or refresh) an OAuth2 access token using the service account JWT flow.
     */
    private function getAccessToken(): string {
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        $clientEmail = AppConfig::getServiceAccountEmail();
        $privateKey = AppConfig::getPrivateKey();

        if (!$clientEmail || !$privateKey) {
            throw new Exception('Service account email or private key missing');
        }

        $now = time();
        $jwtHeader = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $jwtClaims = json_encode([
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtHeader));
        $base64Claims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtClaims));
        $signatureInput = $base64Header . '.' . $base64Claims;

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
            throw new Exception('Failed to sign JWT: ' . openssl_error_string());
        }

        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . '.' . $base64Signature;

        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("OAuth2 token request failed: HTTP {$httpCode} - {$response}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('OAuth2 response missing access_token: ' . $response);
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = $now + ($data['expires_in'] ?? 3600);
        return $this->accessToken;
    }

    /**
     * Build the Firestore REST API base URL.
     */
    private function getBaseUrl(): string {
        return "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->databaseId}";
    }

    /**
     * Make an authenticated HTTP request.
     */
    private function request(string $method, string $url, ?array $body = null): array {
        $token = $this->getAccessToken();
        $headers = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL error: {$error}");
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? $response;
            throw new Exception("Firestore API error: HTTP {$httpCode} - {$msg}");
        }

        return $decoded ?? [];
    }

    /**
     * Convert a plain PHP value to Firestore typed value format.
     */
    public static function toFirestoreValue($value): array {
        if ($value === null) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_string($value)) {
            // Try to detect ISO8601 timestamps for known timestamp fields
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                return ['timestampValue' => $value];
            }
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            // Handle server timestamp sentinel
            if (isset($value['timestampValue']) && $value['timestampValue'] === 'REQUEST_TIME') {
                return ['timestampValue' => gmdate('c')];
            }
            if (array_keys($value) === range(0, count($value) - 1)) {
                // Sequential array -> arrayValue
                return ['arrayValue' => ['values' => array_map([self::class, 'toFirestoreValue'], $value)]];
            }
            // Associative array -> mapValue
            $fields = [];
            foreach ($value as $k => $v) {
                $fields[$k] = self::toFirestoreValue($v);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['nullValue' => null];
    }

    /**
     * Convert Firestore typed value format to plain PHP value.
     */
    public static function fromFirestoreValue(array $value) {
        if (isset($value['nullValue'])) {
            return null;
        }
        if (isset($value['booleanValue'])) {
            return $value['booleanValue'];
        }
        if (isset($value['integerValue'])) {
            return (int) $value['integerValue'];
        }
        if (isset($value['doubleValue'])) {
            return $value['doubleValue'];
        }
        if (isset($value['stringValue'])) {
            return $value['stringValue'];
        }
        if (isset($value['timestampValue'])) {
            return $value['timestampValue'];
        }
        if (isset($value['arrayValue']['values'])) {
            return array_map([self::class, 'fromFirestoreValue'], $value['arrayValue']['values']);
        }
        if (isset($value['mapValue']['fields'])) {
            $result = [];
            foreach ($value['mapValue']['fields'] as $k => $v) {
                $result[$k] = self::fromFirestoreValue($v);
            }
            return $result;
        }
        return null;
    }

    /**
     * Convert plain document fields to Firestore document format.
     */
    public static function toFirestoreDocument(array $data): array {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = self::toFirestoreValue($value);
        }
        return ['fields' => $fields];
    }

    /**
     * Convert Firestore document to plain PHP array.
     */
    public static function fromFirestoreDocument(array $doc): array {
        $result = [];
        if (isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
                $result[$key] = self::fromFirestoreValue($value);
            }
        }
        // Extract document ID from name like projects/.../documents/tickets/abc123
        if (isset($doc['name'])) {
            $parts = explode('/', $doc['name']);
            $result['id'] = end($parts);
        }
        return $result;
    }

    /**
     * Convert Firestore server timestamp sentinel to REST API format.
     */
    public static function serverTimestamp(): array {
        return ['timestampValue' => 'REQUEST_TIME'];
    }

    /**
     * List documents in a collection. Returns array of plain documents.
     */
    public function listDocuments(string $collection, ?string $orderBy = null, int $pageSize = 1000): array {
        $url = $this->getBaseUrl() . "/documents/{$collection}?pageSize={$pageSize}";
        if ($orderBy) {
            $url .= '&orderBy=' . urlencode($orderBy);
        }
        $result = $this->request('GET', $url);
        $documents = [];
        if (isset($result['documents'])) {
            foreach ($result['documents'] as $doc) {
                $documents[] = self::fromFirestoreDocument($doc);
            }
        }
        return $documents;
    }

    /**
     * Run a structured query and return plain documents.
     */
    public function runQuery(array $structuredQuery): array {
        $url = $this->getBaseUrl() . "/documents:runQuery";
        $body = ['structuredQuery' => $structuredQuery];
        $result = $this->request('POST', $url, $body);
        $documents = [];
        foreach ($result as $item) {
            if (isset($item['document'])) {
                $documents[] = self::fromFirestoreDocument($item['document']);
            }
        }
        return $documents;
    }

    /**
     * Get a single document by collection and ID.
     */
    public function getDocument(string $collection, string $docId): ?array {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        try {
            $result = $this->request('GET', $url);
            return self::fromFirestoreDocument($result);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create a new document. If docId is empty, Firestore generates one.
     */
    public function createDocument(string $collection, array $data, string $docId = ''): array {
        $url = $this->getBaseUrl() . "/documents/{$collection}";
        if ($docId) {
            $url .= "?documentId=" . urlencode($docId);
        }
        $body = self::toFirestoreDocument($data);
        $result = $this->request('POST', $url, $body);
        return self::fromFirestoreDocument($result);
    }

    /**
     * Update/patch a document. Only updates specified fields.
     */
    public function updateDocument(string $collection, string $docId, array $data): void {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        $fieldPaths = array_keys($data);
        if (!empty($fieldPaths)) {
            $queryParams = [];
            foreach ($fieldPaths as $fp) {
                $queryParams[] = 'updateMask.fieldPaths=' . urlencode($fp);
            }
            $url .= '?' . implode('&', $queryParams);
        }
        $body = self::toFirestoreDocument($data);
        $this->request('PATCH', $url, $body);
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(string $collection, string $docId): void {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        $this->request('DELETE', $url);
    }
}
