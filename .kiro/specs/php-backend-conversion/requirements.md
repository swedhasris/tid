# Requirements Document

## Introduction

This feature converts the existing Node.js/TypeScript/Express backend to a PHP backend that replicates all current functionality. The PHP backend must expose the same REST API endpoints, implement the same SLA escalation engine via a scheduled CLI script, connect to Firestore using the REST API (no official PHP Admin SDK exists), and serve the React SPA in production. Business logic for auto-assignment, priority notifications, and SLA breach/escalation must be preserved exactly.

## Glossary

- **PHP_Server**: The PHP application that handles all incoming HTTP requests and routes them to the appropriate handler.
- **Firestore_Client**: The PHP component responsible for communicating with Firestore via the Google Firestore REST API using a service account for authentication.
- **SLA_Engine**: The PHP CLI script that evaluates SLA deadlines on tickets and applies breach or at-risk status updates.
- **Ticket**: A Firestore document in the `tickets` collection representing a support request, containing fields such as `status`, `priority`, `assignedTo`, `assignmentGroup`, `category`, `createdAt`, `updatedAt`, `history`, `responseDeadline`, `resolutionDeadline`, `firstResponseAt`, `resolvedAt`, `responseSlaStatus`, and `resolutionSlaStatus`.
- **SLA_Status**: One of the string values `"On Track"`, `"At Risk"`, or `"Breached"` applied to `responseSlaStatus` or `resolutionSlaStatus` on a Ticket.
- **Auto-Assignment**: The server-side logic that derives `assignmentGroup` from `category` when the caller does not supply one.
- **Priority_Notification**: A history entry appended automatically when a ticket is created with priority `"1 - Critical"` or `"2 - High"`.
- **Service_Account**: A Google Cloud service account JSON key file used by the Firestore_Client to obtain OAuth 2.0 access tokens for Firestore REST API calls.
- **Cron_Job**: A system-level scheduled task (e.g., Linux crontab entry) that invokes the SLA_Engine CLI script every 15 minutes.

---

## Requirements

### Requirement 1: Firestore Connectivity

**User Story:** As a system operator, I want the PHP backend to connect to the correct Firestore database, so that all ticket data is read from and written to the right project and database instance.

#### Acceptance Criteria

1. THE Firestore_Client SHALL read `projectId` and `firestoreDatabaseId` from `firebase-applet-config.json` at startup.
2. THE Firestore_Client SHALL authenticate all Firestore REST API requests using a Google Cloud Service_Account credential.
3. WHEN a Firestore REST API request fails due to an authentication error, THE Firestore_Client SHALL log the error with the HTTP status code and response body.
4. IF the `firebase-applet-config.json` file is missing or malformed, THEN THE PHP_Server SHALL return HTTP 500 with a descriptive error message on all API requests.

---

### Requirement 2: Health Check Endpoint

**User Story:** As a system operator, I want a health check endpoint, so that monitoring tools can verify the PHP backend process is running.

#### Acceptance Criteria

1. WHEN a `GET /api/health` request is received, THE PHP_Server SHALL respond with HTTP 200 and a JSON body `{"status": "ok"}`.

---

### Requirement 3: Database Connectivity Test Endpoint

**User Story:** As a system operator, I want a database test endpoint, so that I can verify the Firestore connection is working from the running server.

#### Acceptance Criteria

1. WHEN a `GET /api/db-test` request is received, THE PHP_Server SHALL attempt to fetch at most 1 document from the `tickets` collection via the Firestore_Client.
2. WHEN the Firestore query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON body containing `status`, `project`, `database`, and `count` fields.
3. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body containing `status`, `error`, `project`, and `database` fields.

---

### Requirement 4: Fetch All Tickets

**User Story:** As a frontend client, I want to retrieve all tickets ordered by creation date descending, so that the most recent tickets appear first.

#### Acceptance Criteria

1. WHEN a `GET /api/tickets/all` request is received, THE PHP_Server SHALL query the `tickets` collection ordered by `createdAt` descending via the Firestore_Client.
2. WHEN the query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON array where each element includes the Firestore document `id` merged with all document fields.
3. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to fetch tickets"}`.

---

### Requirement 5: Fetch Open Tickets

**User Story:** As a frontend client, I want to retrieve all non-resolved and non-closed tickets, so that agents can see the active workload.

#### Acceptance Criteria

1. WHEN a `GET /api/tickets/open` request is received, THE PHP_Server SHALL query the `tickets` collection filtering out documents where `status` is `"Resolved"` or `"Closed"` via the Firestore_Client.
2. WHEN the query succeeds, THE PHP_Server SHALL sort the results by `createdAt` descending in application memory before responding.
3. WHEN the query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON array of matching Ticket objects including their document `id`.
4. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to fetch open tickets"}`.

---

### Requirement 6: Fetch Tickets Assigned to a User

**User Story:** As a frontend client, I want to retrieve all tickets assigned to a specific user, so that agents can view their personal queue.

#### Acceptance Criteria

1. WHEN a `GET /api/tickets/assigned/{userId}` request is received, THE PHP_Server SHALL query the `tickets` collection filtering by `assignedTo == {userId}` ordered by `createdAt` descending via the Firestore_Client.
2. WHEN the query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON array of matching Ticket objects including their document `id`.
3. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to fetch assigned tickets"}`.

---

### Requirement 7: Fetch Unassigned Tickets

**User Story:** As a frontend client, I want to retrieve all tickets with no assignee, so that dispatchers can identify and assign unowned work.

#### Acceptance Criteria

1. WHEN a `GET /api/tickets/unassigned` request is received, THE PHP_Server SHALL query the `tickets` collection filtering by `assignedTo == ""` ordered by `createdAt` descending via the Firestore_Client.
2. WHEN the query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON array of matching Ticket objects including their document `id`.
3. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to fetch unassigned tickets"}`.

---

### Requirement 8: Fetch Resolved Tickets

**User Story:** As a frontend client, I want to retrieve all resolved and closed tickets, so that agents can review completed work.

#### Acceptance Criteria

1. WHEN a `GET /api/tickets/resolved` request is received, THE PHP_Server SHALL query the `tickets` collection filtering by `status` in `["Resolved", "Closed"]` via the Firestore_Client.
2. WHEN the query succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON array of matching Ticket objects including their document `id`.
3. IF the Firestore query fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to fetch resolved tickets"}`.

---

### Requirement 9: Create Ticket with Auto-Assignment and Priority Notification

**User Story:** As a frontend client, I want to create a new ticket via the API, so that support requests are recorded in Firestore with correct assignment and priority metadata.

#### Acceptance Criteria

1. WHEN a `POST /api/tickets/create` request is received with a valid JSON body, THE PHP_Server SHALL apply Auto-Assignment logic to derive `assignmentGroup` if the request body does not include one.
2. THE PHP_Server SHALL map `category` to `assignmentGroup` using the following rules: `"Network"` → `"Network Team"`, `"Hardware"` → `"Hardware Support"`, `"Software"` → `"App Support"`, `"Database"` → `"DBA Team"`, any other value → `"Service Desk"`.
3. THE PHP_Server SHALL set `createdAt` and `updatedAt` to the Firestore server timestamp on the new document.
4. THE PHP_Server SHALL append an initial history entry `{"action": "Ticket Created via API", "timestamp": <ISO8601>, "user": <caller field or "System">}` to the `history` array.
5. WHEN the request body contains `priority` equal to `"1 - Critical"` or `"2 - High"`, THE PHP_Server SHALL append a Priority_Notification history entry `{"action": "Manager Notified (High Priority)", "timestamp": <ISO8601>, "user": "System Automation"}` to the `history` array.
6. WHEN the Firestore write succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON body containing the new document `id` merged with all stored fields.
7. IF the Firestore write fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to create ticket: <message>"}`.

---

### Requirement 10: Update Ticket

**User Story:** As a frontend client, I want to update an existing ticket by ID, so that ticket fields can be modified as work progresses.

#### Acceptance Criteria

1. WHEN a `PUT /api/tickets/{id}` request is received with a valid JSON body, THE PHP_Server SHALL merge the request body fields onto the existing Firestore document and set `updatedAt` to the Firestore server timestamp.
2. WHEN the Firestore update succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON body containing the document `id` and all submitted update fields.
3. IF the Firestore update fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to update ticket"}`.

---

### Requirement 11: Delete Ticket

**User Story:** As a frontend client, I want to delete a ticket by ID, so that erroneous or duplicate tickets can be removed.

#### Acceptance Criteria

1. WHEN a `DELETE /api/tickets/{id}` request is received, THE PHP_Server SHALL delete the corresponding document from the `tickets` collection via the Firestore_Client.
2. WHEN the Firestore delete succeeds, THE PHP_Server SHALL respond with HTTP 200 and a JSON body `{"message": "Ticket deleted successfully"}`.
3. IF the Firestore delete fails, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body `{"error": "Failed to delete ticket"}`.

---

### Requirement 12: Manual SLA Escalation Trigger

**User Story:** As a system operator, I want to manually trigger the SLA escalation check via an API endpoint, so that I can test or force-run the SLA engine without waiting for the scheduled cron.

#### Acceptance Criteria

1. WHEN a `POST /api/tickets/trigger-escalation` request is received, THE PHP_Server SHALL invoke the same SLA escalation logic used by the SLA_Engine.
2. WHEN the escalation logic completes, THE PHP_Server SHALL respond with HTTP 200 and a JSON body `{"message": "Escalation check triggered manually"}`.
3. IF the escalation logic throws an unhandled error, THEN THE PHP_Server SHALL respond with HTTP 500 and a JSON body containing the error message.

---

### Requirement 13: SLA Engine — Ticket Evaluation

**User Story:** As a system operator, I want the SLA engine to evaluate all active tickets against their deadlines, so that breached and at-risk tickets are flagged automatically.

#### Acceptance Criteria

1. WHEN the SLA_Engine runs, THE SLA_Engine SHALL fetch all documents from the `tickets` collection via the Firestore_Client.
2. WHEN evaluating a Ticket, THE SLA_Engine SHALL skip any Ticket whose `status` is `"Resolved"`, `"Closed"`, `"Canceled"`, `"On Hold"`, or `"Waiting for Customer"`.
3. WHEN a Ticket has a `responseDeadline`, no `firstResponseAt`, and `responseSlaStatus` is not `"Breached"` or `"Completed"`, THE SLA_Engine SHALL evaluate the response SLA.
4. WHEN the current time is past `responseDeadline`, THE SLA_Engine SHALL set `responseSlaStatus` to `"Breached"` and append a history entry `{"action": "Response SLA BREACHED", "timestamp": <ISO8601>, "user": "SLA Engine"}`.
5. WHEN the remaining time before `responseDeadline` is less than 20% of the total response window (calculated as `responseDeadline - createdAt`) and `responseSlaStatus` is not already `"At Risk"`, THE SLA_Engine SHALL set `responseSlaStatus` to `"At Risk"`.
6. WHEN a Ticket has a `resolutionDeadline`, no `resolvedAt`, and `resolutionSlaStatus` is not `"Breached"` or `"Completed"`, THE SLA_Engine SHALL evaluate the resolution SLA.
7. WHEN the current time is past `resolutionDeadline`, THE SLA_Engine SHALL set `resolutionSlaStatus` to `"Breached"`, set `priority` to `"1 - Critical"`, and append a history entry `{"action": "Resolution SLA BREACHED: Ticket escalated to Critical", "timestamp": <ISO8601>, "user": "SLA Engine"}`.
8. WHEN the remaining time before `resolutionDeadline` is less than 20% of the total resolution window (calculated as `resolutionDeadline - createdAt`) and `resolutionSlaStatus` is not already `"At Risk"`, THE SLA_Engine SHALL set `resolutionSlaStatus` to `"At Risk"`.
9. WHEN any SLA status field or priority is updated, THE SLA_Engine SHALL also set `updatedAt` to the current server timestamp on the Ticket document.
10. IF a Firestore operation fails during SLA evaluation, THEN THE SLA_Engine SHALL log the error with the ticket ID and continue processing remaining tickets.

---

### Requirement 14: SLA Engine — Scheduled Execution

**User Story:** As a system operator, I want the SLA engine to run automatically every 15 minutes, so that SLA breaches are detected and escalated without manual intervention.

#### Acceptance Criteria

1. THE SLA_Engine SHALL be implemented as a standalone PHP CLI script that can be invoked independently of the HTTP server.
2. THE Cron_Job SHALL invoke the SLA_Engine CLI script on a `*/15 * * * *` schedule.
3. WHEN the SLA_Engine CLI script starts, THE SLA_Engine SHALL log a message indicating the check has begun, including the current timestamp.
4. WHEN the SLA_Engine CLI script completes, THE SLA_Engine SHALL log the number of tickets fetched and the number of tickets updated.

---

### Requirement 15: Static Frontend Serving

**User Story:** As a system operator, I want the PHP backend to serve the pre-built React SPA in production, so that the frontend and backend can be deployed together on a single PHP host.

#### Acceptance Criteria

1. WHILE the PHP_Server is running in production mode, THE PHP_Server SHALL serve static files from the `dist/` directory for all non-API requests.
2. WHEN a request path does not match any API route and no matching static file exists in `dist/`, THE PHP_Server SHALL serve `dist/index.html` to support client-side routing.
3. WHEN a request path matches an existing file in `dist/`, THE PHP_Server SHALL serve that file with the appropriate `Content-Type` header.

---

### Requirement 16: JSON Request and Response Handling

**User Story:** As a frontend client, I want all API endpoints to accept and return JSON, so that the React frontend can communicate with the backend using a consistent data format.

#### Acceptance Criteria

1. THE PHP_Server SHALL parse the request body as JSON for all `POST` and `PUT` endpoints.
2. THE PHP_Server SHALL set the `Content-Type: application/json` response header on all API responses.
3. IF a `POST` or `PUT` request body is not valid JSON, THEN THE PHP_Server SHALL respond with HTTP 400 and a JSON body `{"error": "Invalid JSON body"}`.
