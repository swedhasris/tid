<?php
/**
 * Firebase Firestore REST API Client for PHP Frontend
 */

require_once __DIR__ . '/config.php';

class FirebaseAPI {
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;
    private array $serviceAccount;

    public function __construct() {
        global $serviceAccount;
        if (!$serviceAccount) {
            throw new Exception('Service account not configured');
        }
        $this->serviceAccount = $serviceAccount;
    }

    /**
     * Get OAuth2 access token using service account
     */
    private function getAccessToken(): string {
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        $clientEmail = $this->serviceAccount['client_email'] ?? '';
        $privateKey = $this->serviceAccount['private_key'] ?? '';

        if (!$clientEmail || !$privateKey) {
            throw new Exception('Service account credentials missing');
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
            throw new Exception('Failed to sign JWT');
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

        if ($httpCode !== 200) {
            throw new Exception("OAuth failed: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('No access token in response');
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = $now + ($data['expires_in'] ?? 3600);
        return $this->accessToken;
    }

    /**
     * Make authenticated request to Firestore
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
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * Get Firestore base URL
     */
    private function getBaseUrl(): string {
        return "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/" . FIREBASE_DATABASE_ID;
    }

    /**
     * Convert Firestore value to PHP value
     */
    private static function fromFirestoreValue(array $value) {
        if (isset($value['nullValue'])) return null;
        if (isset($value['booleanValue'])) return $value['booleanValue'];
        if (isset($value['integerValue'])) return (int) $value['integerValue'];
        if (isset($value['doubleValue'])) return $value['doubleValue'];
        if (isset($value['stringValue'])) return $value['stringValue'];
        if (isset($value['timestampValue'])) return $value['timestampValue'];
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
     * Convert PHP value to Firestore value
     */
    private static function toFirestoreValue($value): array {
        if ($value === null) return ['nullValue' => null];
        if (is_bool($value)) return ['booleanValue' => $value];
        if (is_int($value)) return ['integerValue' => (string) $value];
        if (is_float($value)) return ['doubleValue' => $value];
        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                return ['timestampValue' => $value];
            }
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                return ['arrayValue' => ['values' => array_map([self::class, 'toFirestoreValue'], $value)]];
            }
            $fields = [];
            foreach ($value as $k => $v) {
                $fields[$k] = self::toFirestoreValue($v);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['nullValue' => null];
    }

    /**
     * Convert Firestore document to PHP array
     */
    private static function fromFirestoreDocument(array $doc): array {
        $result = [];
        if (isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
                $result[$key] = self::fromFirestoreValue($value);
            }
        }
        if (isset($doc['name'])) {
            $parts = explode('/', $doc['name']);
            $result['id'] = end($parts);
        }
        if (isset($doc['createTime'])) $result['_createTime'] = $doc['createTime'];
        if (isset($doc['updateTime'])) $result['_updateTime'] = $doc['updateTime'];
        return $result;
    }

    /**
     * Convert PHP array to Firestore document
     */
    private static function toFirestoreDocument(array $data): array {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = self::toFirestoreValue($value);
        }
        return ['fields' => $fields];
    }

    /**
     * List documents in a collection
     */
    public function listDocuments(string $collection, int $limit = 1000): array {
        $url = $this->getBaseUrl() . "/documents/{$collection}?pageSize={$limit}";
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
     * Get a single document
     */
    public function getDocument(string $collection, string $docId): ?array {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        $result = $this->request('GET', $url);
        if (isset($result['error'])) return null;
        return self::fromFirestoreDocument($result);
    }

    /**
     * Create a document
     */
    public function createDocument(string $collection, array $data, ?string $docId = null): array {
        $url = $this->getBaseUrl() . "/documents/{$collection}";
        if ($docId) {
            $url .= "?documentId=" . urlencode($docId);
        }
        $body = self::toFirestoreDocument($data);
        $result = $this->request('POST', $url, $body);
        return self::fromFirestoreDocument($result);
    }

    /**
     * Update a document
     */
    public function updateDocument(string $collection, string $docId, array $data): void {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        $fieldPaths = array_keys($data);
        if (!empty($fieldPaths)) {
            $params = [];
            foreach ($fieldPaths as $fp) {
                $params[] = 'updateMask.fieldPaths=' . urlencode($fp);
            }
            $url .= '?' . implode('&', $params);
        }
        $body = self::toFirestoreDocument($data);
        $this->request('PATCH', $url, $body);
    }

    /**
     * Delete a document
     */
    public function deleteDocument(string $collection, string $docId): void {
        $url = $this->getBaseUrl() . "/documents/{$collection}/{$docId}";
        $this->request('DELETE', $url);
    }

    /**
     * Query documents with filter
     */
    public function query(string $collection, array $filters = [], ?string $orderBy = null, string $direction = 'DESC'): array {
        $structuredQuery = [
            'from' => [['collectionId' => $collection]],
        ];

        // Add filters
        if (!empty($filters)) {
            $filterArray = [];
            foreach ($filters as $field => $value) {
                $filterArray[] = [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $field],
                        'op' => 'EQUAL',
                        'value' => self::toFirestoreValue($value)
                    ]
                ];
            }
            if (count($filterArray) === 1) {
                $structuredQuery['where'] = $filterArray[0];
            } else {
                $structuredQuery['where'] = [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => $filterArray
                    ]
                ];
            }
        }

        // Add ordering
        if ($orderBy) {
            $structuredQuery['orderBy'] = [
                [
                    'field' => ['fieldPath' => $orderBy],
                    'direction' => $direction
                ]
            ];
        }

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
}
