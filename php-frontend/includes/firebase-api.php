<?php
/**
 * Firebase Firestore REST API Client for PHP Frontend
 * Works with API key (no service account required)
 */

require_once __DIR__ . '/config.php';

class FirebaseAPI {
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;
    private bool $useServiceAccount = false;

    public function __construct() {
        global $serviceAccount;
        // Use service account if available, otherwise fall back to API key
        if ($serviceAccount && !empty($serviceAccount['client_email'])) {
            $this->useServiceAccount = true;
        }
    }

    /**
     * Get OAuth2 access token using service account (if available)
     */
    private function getAccessToken(): ?string {
        if (!$this->useServiceAccount) return null;

        global $serviceAccount;
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        $clientEmail = $serviceAccount['client_email'] ?? '';
        $privateKey  = $serviceAccount['private_key']  ?? '';
        if (!$clientEmail || !$privateKey) return null;

        $now = time();
        $jwtHeader = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $jwtClaims = json_encode([
            'iss'   => $clientEmail,
            'sub'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]);

        $b64h = str_replace(['+','/','='], ['-','_',''], base64_encode($jwtHeader));
        $b64c = str_replace(['+','/','='], ['-','_',''], base64_encode($jwtClaims));
        $sig  = '';
        if (!openssl_sign("$b64h.$b64c", $sig, $privateKey, 'SHA256')) return null;
        $b64s = str_replace(['+','/','='], ['-','_',''], base64_encode($sig));
        $jwt  = "$b64h.$b64c.$b64s";

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        if (!isset($data['access_token'])) return null;

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = $now + ($data['expires_in'] ?? 3600);
        return $this->accessToken;
    }

    /**
     * Build URL — append API key if no service account
     */
    private function buildUrl(string $path): string {
        $base = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/" . FIREBASE_DATABASE_ID;
        $url  = $base . $path;
        if (!$this->useServiceAccount) {
            $sep  = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . 'key=' . urlencode(FIREBASE_API_KEY);
        }
        return $url;
    }

    /**
     * Make request to Firestore
     */
    private function request(string $method, string $url, ?array $body = null): array {
        $headers = ['Content-Type: application/json'];
        $token   = $this->getAccessToken();
        if ($token) $headers[] = "Authorization: Bearer {$token}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
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
        $url = $this->buildUrl("/documents/{$collection}?pageSize={$limit}");
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
        $url = $this->buildUrl("/documents/{$collection}/{$docId}");
        $result = $this->request('GET', $url);
        if (isset($result['error'])) return null;
        return self::fromFirestoreDocument($result);
    }

    /**
     * Create a document
     */
    public function createDocument(string $collection, array $data, ?string $docId = null): array {
        $path = "/documents/{$collection}";
        if ($docId) $path .= "?documentId=" . urlencode($docId);
        $url = $this->buildUrl($path);
        $body = self::toFirestoreDocument($data);
        $result = $this->request('POST', $url, $body);
        return self::fromFirestoreDocument($result);
    }

    /**
     * Update a document
     */
    public function updateDocument(string $collection, string $docId, array $data): void {
        $fieldPaths = array_keys($data);
        $params = [];
        foreach ($fieldPaths as $fp) {
            $params[] = 'updateMask.fieldPaths=' . urlencode($fp);
        }
        $path = "/documents/{$collection}/{$docId}" . (!empty($params) ? '?' . implode('&', $params) : '');
        $url  = $this->buildUrl($path);
        $body = self::toFirestoreDocument($data);
        $this->request('PATCH', $url, $body);
    }

    /**
     * Delete a document
     */
    public function deleteDocument(string $collection, string $docId): void {
        $url = $this->buildUrl("/documents/{$collection}/{$docId}");
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

        $url  = $this->buildUrl("/documents:runQuery");
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
