<?php
/**
 * SLA Engine - evaluates ticket deadlines and escalates breached/at-risk tickets.
 * Can be run as CLI or included by the web router for manual triggering.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firestore-client.php';

function runEscalation(): void {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $nowMs = (int) ($now->format('U') * 1000 + $now->format('u') / 1000);

    echo "[SLA Engine] Checking tickets...\n";

    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        echo "[SLA Engine] Fetched " . count($tickets) . " tickets.\n";

        $updatedCount = 0;

        foreach ($tickets as $ticket) {
            $status = $ticket['status'] ?? '';
            if (in_array($status, ['Resolved', 'Closed', 'Canceled', 'On Hold', 'Waiting for Customer'], true)) {
                continue;
            }

            $updates = [];
            $historyEntries = [];
            $ticketId = $ticket['id'] ?? '';
            if (!$ticketId) {
                continue;
            }

            $createdAtMs = timestampToMs($ticket['createdAt'] ?? null);

            // Response SLA Check
            $responseDeadline = $ticket['responseDeadline'] ?? null;
            $firstResponseAt = $ticket['firstResponseAt'] ?? null;
            $responseSlaStatus = $ticket['responseSlaStatus'] ?? '';

            if ($responseDeadline && !$firstResponseAt && $responseSlaStatus !== 'Breached' && $responseSlaStatus !== 'Completed') {
                $deadlineMs = timestampToMs($responseDeadline);
                $diff = $deadlineMs - $nowMs;
                $totalWindow = $deadlineMs - $createdAtMs;

                if ($diff <= 0) {
                    $updates['responseSlaStatus'] = 'Breached';
                    $historyEntries[] = [
                        'action' => 'Response SLA BREACHED',
                        'timestamp' => $now->format('c'),
                        'user' => 'SLA Engine',
                    ];
                } elseif ($totalWindow > 0 && $diff < $totalWindow * 0.2) {
                    if ($responseSlaStatus !== 'At Risk') {
                        $updates['responseSlaStatus'] = 'At Risk';
                    }
                }
            }

            // Resolution SLA Check
            $resolutionDeadline = $ticket['resolutionDeadline'] ?? null;
            $resolvedAt = $ticket['resolvedAt'] ?? null;
            $resolutionSlaStatus = $ticket['resolutionSlaStatus'] ?? '';

            if ($resolutionDeadline && !$resolvedAt && $resolutionSlaStatus !== 'Breached' && $resolutionSlaStatus !== 'Completed') {
                $deadlineMs = timestampToMs($resolutionDeadline);
                $diff = $deadlineMs - $nowMs;
                $totalWindow = $deadlineMs - $createdAtMs;

                if ($diff <= 0) {
                    $updates['resolutionSlaStatus'] = 'Breached';
                    $updates['priority'] = '1 - Critical';
                    $historyEntries[] = [
                        'action' => 'Resolution SLA BREACHED: Ticket escalated to Critical',
                        'timestamp' => $now->format('c'),
                        'user' => 'SLA Engine',
                    ];
                } elseif ($totalWindow > 0 && $diff < $totalWindow * 0.2) {
                    if ($resolutionSlaStatus !== 'At Risk') {
                        $updates['resolutionSlaStatus'] = 'At Risk';
                    }
                }
            }

            if (!empty($updates) || !empty($historyEntries)) {
                $existingHistory = $ticket['history'] ?? [];
                $updates['history'] = array_merge($existingHistory, $historyEntries);
                $updates['updatedAt'] = ['timestampValue' => 'REQUEST_TIME'];

                try {
                    $client->updateDocument('tickets', $ticketId, $updates);
                    $updatedCount++;
                } catch (Exception $e) {
                    echo "[SLA Engine] Error updating ticket {$ticketId}: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "[SLA Engine] Updated {$updatedCount} tickets.\n";
    } catch (Exception $e) {
        echo "[SLA Engine] Error: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function timestampToMs($value): int {
    if (!$value) return 0;
    if (is_string($value)) {
        $t = strtotime($value);
        return $t !== false ? $t * 1000 : 0;
    }
    if (is_array($value) && isset($value['seconds'])) {
        return (int) ($value['seconds'] * 1000 + ($value['nanos'] ?? 0) / 1000000);
    }
    return 0;
}

// CLI execution
if (PHP_SAPI === 'cli' && basename($argv[0]) === basename(__FILE__)) {
    try {
        runEscalation();
    } catch (Exception $e) {
        echo "[SLA Engine] Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
