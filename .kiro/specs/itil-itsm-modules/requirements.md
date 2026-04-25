# Requirements Document

## Introduction

This feature enhances the existing React + TypeScript + Firebase (Firestore) ITSM platform (Connect) by replacing stub implementations and adding full ITIL-aligned functionality across three core modules: Incident Management, Problem Management, and Change Management. The system already has a working `tickets` collection, Firebase Auth, an Express.js backend (`server.ts`), and a Tailwind/shadcn/ui design system. All new functionality MUST extend the existing codebase without removing or breaking any current features.

The three modules share cross-cutting concerns: role-based access (user / agent / admin / super_admin), Firestore as the sole database, in-app notifications, audit history arrays, and a consistent ServiceNow-inspired UI using the existing `sn-card`, `sn-green`, `sn-dark`, `sn-sidebar`, and `text-dim` design tokens.

---

## Glossary

- **System**: The Connect ITSM React + TypeScript SPA backed by Firebase Firestore and an Express.js server.
- **Incident_Module**: The enhanced `/tickets` page and `/tickets/:id` detail page managing ITIL incident records in the `tickets` Firestore collection.
- **Problem_Module**: The full-featured `/problem` page and `/problem/:id` detail page managing ITIL problem records in the `problems` Firestore collection.
- **Change_Module**: The full-featured `/change` page and `/change/:id` detail page managing ITIL change records in the `changes` Firestore collection.
- **Firestore**: Google Cloud Firestore, the sole database. No SQL or MySQL is used.
- **Auth_Context**: The existing `AuthContext.tsx` providing `user`, `profile`, and `profile.role` to all components.
- **Role**: One of `user`, `agent`, `admin`, or `super_admin` stored in the `users` Firestore collection.
- **SLA_Engine**: The existing cron-based escalation logic in `server.ts` that monitors the `tickets` collection every 15 minutes.
- **AI_Endpoint**: The existing `/api/ai/chat` Express endpoint that calls Gemini 2.0 Flash.
- **Notification_Bell**: The existing notification icon in `AppNavbar.tsx` that currently has no wired data source.
- **Incident**: An ITIL incident record stored in the `tickets` Firestore collection with an auto-generated `INC#######` number.
- **Problem**: An ITIL problem record stored in the `problems` Firestore collection with an auto-generated `PRB#######` number.
- **Change_Request**: An ITIL change request record stored in the `changes` Firestore collection with an auto-generated `CHG#######` number.
- **Timeline**: An ordered array of history entries (`{ action, timestamp, user }`) stored directly on each document.
- **Known_Error**: A Problem record whose `status` is `"Known Error"`, indicating a documented workaround exists.
- **Approval_Workflow**: The two-step change approval process: agent submits → admin/super_admin approves or rejects.
- **Cross_Module_Link**: A reference stored as an array field on a document (e.g., `linkedIncidentIds` on a Problem, `linkedProblemId` on an Incident).
- **Recharts**: The existing charting library dependency used in the Reports page.
- **Pretty_Printer**: A utility function that serializes a Firestore document object into a human-readable display string for audit logs and detail views.

---

## Requirements

### Requirement 1: Incident Record Data Model Enhancement

**User Story:** As an agent, I want every incident to carry a full ITIL field set, so that I can manage incidents according to ITIL best practices without switching tools.

#### Acceptance Criteria

1. THE Incident_Module SHALL store each incident in the `tickets` Firestore collection with the following fields: `number` (string, format `INC` followed by 7 digits), `title` (string), `description` (string), `category` (string), `subcategory` (string), `priority` (one of `"1 - Critical"`, `"2 - High"`, `"3 - Moderate"`, `"4 - Low"`), `status` (one of `"New"`, `"Assigned"`, `"In Progress"`, `"On Hold"`, `"Resolved"`, `"Closed"`), `assignedTo` (string, Firestore user UID or empty), `assignmentGroup` (string), `caller` (string), `impact` (string), `urgency` (string), `createdBy` (string, Firestore user UID), `createdAt` (Firestore server timestamp), `updatedAt` (Firestore server timestamp), `responseDeadline` (ISO string), `resolutionDeadline` (ISO string), `responseSlaStatus` (string), `resolutionSlaStatus` (string), `history` (array of `{ action, timestamp, user }`), and `problemId` (optional string reference to a Problem document ID).
2. WHEN a new incident is created, THE Incident_Module SHALL auto-generate a unique `number` in the format `INC` followed by exactly 7 random digits (e.g., `INC0042731`).
3. WHEN a new incident is created, THE Incident_Module SHALL derive `priority` from the matrix: impact `1-High` + urgency `1-High` = `"1 - Critical"`, sum ≤ 2 = `"1 - Critical"`, sum = 3 = `"2 - High"`, sum = 4 = `"3 - Moderate"`, sum ≥ 5 = `"4 - Low"`.
4. WHEN a new incident is created, THE Incident_Module SHALL append an initial history entry `{ action: "Ticket Created", timestamp: <ISO string>, user: <caller name or email> }` to the `history` array.
5. THE Incident_Module SHALL preserve all existing fields already present on `tickets` documents (e.g., `channel`, `service`, `cmdbItem`, `serviceOffering`) so that no data is lost.

---

### Requirement 2: Incident SLA Tracking and Escalation

**User Story:** As an agent, I want SLA timers to track response and resolution deadlines per incident, so that I can prioritize work and avoid breaches.

#### Acceptance Criteria

1. WHEN a new incident is created, THE Incident_Module SHALL calculate `responseDeadline` and `resolutionDeadline` by looking up the matching active `sla_policies` document for the incident's `priority` and `category`, falling back to 4-hour response and 24-hour resolution if no policy matches.
2. WHILE an incident's `status` is `"On Hold"`, THE SLA_Engine SHALL pause SLA countdown by recording `onHoldStart` and accumulating elapsed pause time in `totalPausedTime` (milliseconds).
3. WHEN an incident's `status` transitions from `"On Hold"` to any active status, THE Incident_Module SHALL add the pause duration to `totalPausedTime` and extend both `responseDeadline` and `resolutionDeadline` by the same duration.
4. WHEN the SLA_Engine cron runs and a `resolutionDeadline` has passed for an unresolved incident, THE SLA_Engine SHALL set `resolutionSlaStatus` to `"Breached"`, escalate `priority` to `"1 - Critical"`, and append a history entry `{ action: "Resolution SLA BREACHED: Ticket escalated to Critical", timestamp, user: "SLA Engine" }`.
5. WHEN the SLA_Engine cron runs and a `responseDeadline` has passed for an incident without a `firstResponseAt` value, THE SLA_Engine SHALL set `responseSlaStatus` to `"Breached"` and append a history entry.
6. WHEN an agent posts the first comment on an incident, THE Incident_Module SHALL set `firstResponseAt` to the current ISO timestamp and set `responseSlaStatus` to `"Completed"`.
7. WHEN an incident's `status` is set to `"Resolved"` or `"Closed"`, THE Incident_Module SHALL set `resolvedAt` to the current ISO timestamp and set `resolutionSlaStatus` to `"Completed"`.

---

### Requirement 3: AI-Assisted Incident Priority Suggestion

**User Story:** As an agent creating an incident, I want the system to suggest a priority and category based on the incident description, so that I can triage faster and more consistently.

#### Acceptance Criteria

1. WHEN an agent clicks the AI Assist button on the incident creation form, THE Incident_Module SHALL send the incident `title` and `description` text to the `/api/ai/chat` endpoint with a prompt requesting priority and category classification.
2. WHEN the AI_Endpoint returns a valid classification response, THE Incident_Module SHALL pre-fill the `category`, `impact`, and `urgency` fields in the creation form with the suggested values without overwriting any fields the agent has already manually edited.
3. IF the AI_Endpoint returns an error or times out after 10 seconds, THEN THE Incident_Module SHALL display an inline error message "AI suggestion unavailable. Please set fields manually." and leave all form fields unchanged.
4. THE Incident_Module SHALL display a visual indicator (spinner) while the AI request is in flight so the agent knows the system is processing.

---

### Requirement 4: Incident Detail View and Timeline

**User Story:** As an agent, I want a full incident detail page with an activity timeline, so that I can see the complete history of an incident and collaborate with my team.

#### Acceptance Criteria

1. WHEN an agent navigates to `/tickets/:id`, THE Incident_Module SHALL display all incident fields in an editable form matching the existing ServiceNow-style two-column layout used in `TicketDetail.tsx`.
2. THE Incident_Module SHALL display a chronological activity timeline on the detail page showing all `history` array entries and all subcollection `comments` documents, ordered by timestamp ascending.
3. WHEN an agent submits a comment on the detail page, THE Incident_Module SHALL write the comment to the `tickets/:id/comments` subcollection and append a `{ action: "Comment Added", timestamp, user }` entry to the `history` array in the same Firestore write batch.
4. WHEN an agent updates any editable field and clicks Update, THE Incident_Module SHALL append one history entry per changed field in the format `{ action: "Field <fieldName> updated from <oldValue> to <newValue>", timestamp, user }`.
5. THE Incident_Module SHALL display the `problemId` field as a clickable link to `/problem/:id` when a linked problem exists, and as an empty field with a "Link to Problem" button when no problem is linked.
6. WHEN an agent clicks "Link to Problem" on the incident detail page, THE Incident_Module SHALL display a search dialog listing open Problem records by `number` and `title`, and upon selection SHALL set `problemId` on the incident and append the incident's `number` to `linkedIncidentIds` on the selected Problem document.

---

### Requirement 5: Problem Record Data Model

**User Story:** As an agent, I want to create and manage problem records in Firestore, so that I can track root causes of recurring incidents with persistent, real data instead of mock data.

#### Acceptance Criteria

1. THE Problem_Module SHALL store each problem in a `problems` Firestore collection with the following fields: `number` (string, format `PRB` followed by 7 digits), `title` (string), `description` (string), `linkedIncidentIds` (array of `tickets` document IDs), `rootCauseAnalysis` (string), `status` (one of `"Open"`, `"Investigating"`, `"Known Error"`, `"Resolved"`), `workaround` (string), `permanentFix` (string), `assignedTo` (string, Firestore user UID or empty), `assignmentGroup` (string), `createdBy` (string, Firestore user UID), `createdAt` (Firestore server timestamp), `updatedAt` (Firestore server timestamp), `history` (array of `{ action, timestamp, user }`), and `changeIds` (optional array of `changes` document IDs).
2. WHEN a new problem is created, THE Problem_Module SHALL auto-generate a unique `number` in the format `PRB` followed by exactly 7 random digits.
3. WHEN a new problem is created, THE Problem_Module SHALL append an initial history entry `{ action: "Problem Created", timestamp, user }` to the `history` array.
4. THE Problem_Module SHALL replace the existing mock-data implementation in `ProblemManagement.tsx` with live Firestore reads using `onSnapshot` so that the list updates in real time without a page refresh.
5. FOR ALL problem documents written to Firestore, THE Pretty_Printer SHALL be able to serialize the document's fields into a human-readable string suitable for display in the timeline and audit log.

---

### Requirement 6: Problem Management List View

**User Story:** As an agent, I want a searchable, filterable list of all problems, so that I can quickly find and manage problem records.

#### Acceptance Criteria

1. WHEN an agent navigates to `/problem`, THE Problem_Module SHALL display a list of all problem records from the `problems` Firestore collection, ordered by `createdAt` descending.
2. THE Problem_Module SHALL display summary cards at the top of the list showing: count of problems with `status` not equal to `"Resolved"` (Open Problems), total count of all `linkedIncidentIds` across all open problems (Linked Incidents), and the average age in days of open problems (Avg. Age).
3. WHEN an agent types in the search input on the `/problem` page, THE Problem_Module SHALL filter the displayed list in real time to show only problems whose `number`, `title`, or `assignmentGroup` contains the search string (case-insensitive).
4. THE Problem_Module SHALL display each problem row with: `number`, `title`, `status` badge, `assignedTo` name (resolved from `users` collection), count of `linkedIncidentIds`, and `createdAt` formatted as a locale date string.
5. WHEN an agent clicks a problem row, THE Problem_Module SHALL navigate to `/problem/:id`.

---

### Requirement 7: Problem Detail View

**User Story:** As an agent, I want a full problem detail page, so that I can document root cause analysis, link incidents, and track resolution progress.

#### Acceptance Criteria

1. WHEN an agent navigates to `/problem/:id`, THE Problem_Module SHALL display all problem fields in an editable form using the same two-column ServiceNow-style layout as `TicketDetail.tsx`.
2. THE Problem_Module SHALL display a "Linked Incidents" section on the detail page listing all incidents whose IDs appear in `linkedIncidentIds`, showing each incident's `number`, `title`, `priority`, and `status` as clickable links to `/tickets/:id`.
3. WHEN an agent clicks "Add Incident" in the Linked Incidents section, THE Problem_Module SHALL display a search dialog listing open incidents by `number` and `title`, and upon selection SHALL append the incident's document ID to `linkedIncidentIds` on the problem and set `problemId` on the incident document.
4. WHEN an agent updates any field and clicks Update, THE Problem_Module SHALL write the changes to Firestore and append one history entry per changed field to the `history` array.
5. THE Problem_Module SHALL display a chronological activity timeline showing all `history` array entries ordered by timestamp ascending.
6. WHEN a problem's `status` is set to `"Known Error"`, THE Problem_Module SHALL require the `workaround` field to be non-empty before saving, displaying an inline validation error "Workaround is required for Known Error status" if it is empty.

---

### Requirement 8: Known Error Database View

**User Story:** As an agent, I want a filtered view of all Known Error problems, so that I can quickly find documented workarounds when handling related incidents.

#### Acceptance Criteria

1. THE Problem_Module SHALL provide a "Known Errors" filter tab or button on the `/problem` list page that, when activated, filters the list to show only problems with `status` equal to `"Known Error"`.
2. WHEN the Known Errors filter is active, THE Problem_Module SHALL display the `workaround` field value as a preview excerpt (up to 120 characters) in each list row.
3. WHEN an agent clicks a Known Error row, THE Problem_Module SHALL navigate to `/problem/:id` where the full `workaround` and `permanentFix` fields are displayed.

---

### Requirement 9: Change Request Data Model

**User Story:** As an agent, I want to create and manage change requests in Firestore, so that I can track system changes with persistent, real data instead of mock data.

#### Acceptance Criteria

1. THE Change_Module SHALL store each change request in a `changes` Firestore collection with the following fields: `number` (string, format `CHG` followed by 7 digits), `title` (string), `description` (string), `changeType` (one of `"Standard"`, `"Normal"`, `"Emergency"`), `riskLevel` (one of `"Low"`, `"Medium"`, `"High"`), `approvalStatus` (one of `"Draft"`, `"Pending Approval"`, `"Approved"`, `"Rejected"`), `scheduledDate` (ISO string), `implementedDate` (optional ISO string), `rollbackPlan` (string), `impactAnalysis` (string), `requestedBy` (string, Firestore user UID), `assignedTo` (string, Firestore user UID or empty), `createdAt` (Firestore server timestamp), `updatedAt` (Firestore server timestamp), `history` (array of `{ action, timestamp, user }`), and `linkedProblemIds` (optional array of `problems` document IDs).
2. WHEN a new change request is created, THE Change_Module SHALL auto-generate a unique `number` in the format `CHG` followed by exactly 7 random digits.
3. WHEN a new change request is created, THE Change_Module SHALL set `approvalStatus` to `"Draft"` and append an initial history entry `{ action: "Change Request Created", timestamp, user }`.
4. THE Change_Module SHALL replace the existing mock-data implementation in `ChangeManagement.tsx` with live Firestore reads using `onSnapshot` so that the list updates in real time.
5. FOR ALL change documents written to Firestore, THE Pretty_Printer SHALL be able to serialize the document's fields into a human-readable string suitable for display in the timeline and audit log.

---

### Requirement 10: Change Management List View

**User Story:** As an agent, I want a searchable list of all change requests with status summary cards, so that I can monitor the change pipeline at a glance.

#### Acceptance Criteria

1. WHEN an agent navigates to `/change`, THE Change_Module SHALL display a list of all change records from the `changes` Firestore collection, ordered by `createdAt` descending.
2. THE Change_Module SHALL display four summary cards at the top showing counts of changes with `approvalStatus` equal to `"Draft"`, `"Pending Approval"`, `"Approved"`, and `"Rejected"` respectively, derived from live Firestore data.
3. WHEN an agent types in the search input on the `/change` page, THE Change_Module SHALL filter the displayed list in real time to show only changes whose `number`, `title`, or `changeType` contains the search string (case-insensitive).
4. THE Change_Module SHALL display each change row with: `number`, `title`, `changeType`, `approvalStatus` badge, `riskLevel` badge, and `scheduledDate` formatted as a locale date string.
5. WHEN an agent clicks a change row, THE Change_Module SHALL navigate to `/change/:id`.

---

### Requirement 11: Change Detail View and Approval Workflow

**User Story:** As an agent and admin, I want a change detail page with a multi-step approval workflow, so that changes are reviewed and authorized before implementation.

#### Acceptance Criteria

1. WHEN an agent navigates to `/change/:id`, THE Change_Module SHALL display all change fields in an editable form using the same two-column ServiceNow-style layout as `TicketDetail.tsx`.
2. WHEN an agent clicks "Submit for Approval" on a change with `approvalStatus` equal to `"Draft"`, THE Change_Module SHALL set `approvalStatus` to `"Pending Approval"` and append a history entry `{ action: "Submitted for Approval", timestamp, user }`.
3. WHEN a user with `profile.role` equal to `"admin"` or `"super_admin"` clicks "Approve" on a change with `approvalStatus` equal to `"Pending Approval"`, THE Change_Module SHALL set `approvalStatus` to `"Approved"` and append a history entry `{ action: "Change Approved by <userName>", timestamp, user }`.
4. WHEN a user with `profile.role` equal to `"admin"` or `"super_admin"` clicks "Reject" on a change with `approvalStatus` equal to `"Pending Approval"`, THE Change_Module SHALL set `approvalStatus` to `"Rejected"` and append a history entry `{ action: "Change Rejected by <userName>", timestamp, user }`.
5. IF a user with `profile.role` equal to `"user"` or `"agent"` attempts to approve or reject a change, THEN THE Change_Module SHALL not render the Approve and Reject buttons for that user.
6. THE Change_Module SHALL display a chronological activity timeline showing all `history` array entries ordered by timestamp ascending.
7. WHEN an agent updates any field and clicks Update, THE Change_Module SHALL write the changes to Firestore and append one history entry per changed field to the `history` array.

---

### Requirement 12: Change Calendar View

**User Story:** As an agent, I want a calendar view of scheduled changes, so that I can identify scheduling conflicts and plan maintenance windows.

#### Acceptance Criteria

1. THE Change_Module SHALL provide a "Calendar" toggle or tab on the `/change` page that switches the list view to a monthly calendar grid.
2. WHEN the calendar view is active, THE Change_Module SHALL render each change with a `scheduledDate` as a colored event block on the corresponding calendar day, using the `riskLevel` to determine color: `"Low"` = green, `"Medium"` = orange, `"High"` = red.
3. WHEN an agent clicks a calendar event block, THE Change_Module SHALL navigate to `/change/:id` for that change.
4. THE Change_Module SHALL display navigation controls (previous month, next month, current month label) on the calendar view.

---

### Requirement 13: Cross-Module Linking — Incidents to Problems

**User Story:** As an agent, I want to link incidents to a problem record, so that I can track which incidents share a common root cause.

#### Acceptance Criteria

1. THE System SHALL store the incident-to-problem relationship by setting `problemId` (string, Problem document ID) on the incident document and storing the incident document ID in the `linkedIncidentIds` array on the problem document.
2. WHEN an agent links an incident to a problem from the incident detail page, THE System SHALL update both documents in a single Firestore batch write: set `problemId` on the incident and append the incident ID to `linkedIncidentIds` on the problem.
3. WHEN an agent unlinks an incident from a problem, THE System SHALL clear `problemId` on the incident and remove the incident ID from `linkedIncidentIds` on the problem in a single Firestore batch write.
4. THE Incident_Module SHALL display the linked problem's `number` and `title` as a clickable link on the incident detail page when `problemId` is set.
5. THE Problem_Module SHALL display the count of `linkedIncidentIds` in the problem list view and the full linked incident list on the problem detail page.

---

### Requirement 14: Cross-Module Linking — Problems to Changes

**User Story:** As an agent, I want to link problems to change requests, so that I can track which changes were initiated to resolve a known problem.

#### Acceptance Criteria

1. THE System SHALL store the problem-to-change relationship by storing the change document ID in the `changeIds` array on the problem document and storing the problem document ID in the `linkedProblemIds` array on the change document.
2. WHEN an agent links a problem to a change from the problem detail page, THE System SHALL update both documents in a single Firestore batch write.
3. WHEN an agent unlinks a problem from a change, THE System SHALL remove the IDs from both documents in a single Firestore batch write.
4. THE Problem_Module SHALL display linked change records (`number`, `title`, `approvalStatus`) as clickable links on the problem detail page.
5. THE Change_Module SHALL display linked problem records (`number`, `title`, `status`) as clickable links on the change detail page.

---

### Requirement 15: New Routes and Navigation

**User Story:** As a user, I want dedicated routes for problem and change detail pages, so that I can navigate directly to any record via URL.

#### Acceptance Criteria

1. THE System SHALL register the route `/problem/:id` in `App.tsx` as a protected route rendering a new `ProblemDetail` component.
2. THE System SHALL register the route `/change/:id` in `App.tsx` as a protected route rendering a new `ChangeDetail` component.
3. THE System SHALL preserve all existing routes (`/tickets`, `/tickets/:id`, `/problem`, `/change`, `/history`, `/sla`, `/approvals`, `/users`, `/reports`, `/catalog`, `/cmdb`, `/conversations`, `/kb`, `/settings`) without modification.
4. THE Sidebar SHALL add a "Create Problem" link under the "Problem & Change" section navigating to `/problem?action=new`.
5. THE Sidebar SHALL add a "Create Change" link under the "Problem & Change" section navigating to `/change?action=new`.

---

### Requirement 16: Enhanced Dashboard and Reports

**User Story:** As an admin, I want the Reports page to show live ITIL metrics across all three modules, so that I can monitor service health and compliance.

#### Acceptance Criteria

1. WHEN an admin navigates to `/reports`, THE System SHALL display the following live counts derived from Firestore: total incidents (all `tickets` documents), open incidents (`status` not in `["Resolved", "Closed"]`), total problems (all `problems` documents), open problems (`status` not equal to `"Resolved"`), total changes (all `changes` documents), and pending changes (`approvalStatus` equal to `"Pending Approval"`).
2. THE System SHALL display an SLA compliance percentage on the Reports page calculated as: (count of `tickets` where `resolutionSlaStatus` equals `"Completed"`) / (count of all resolved `tickets`) × 100, rounded to one decimal place.
3. THE System SHALL render a bar chart using Recharts on the Reports page showing incident counts grouped by `category` for the current calendar month.
4. THE System SHALL render a pie chart using Recharts on the Reports page showing the distribution of open incidents by `priority`.
5. THE System SHALL render a line chart using Recharts on the Reports page showing the count of new incidents created per day over the last 30 days.
6. WHEN there are no records in a chart dataset, THE System SHALL display a "No data available" placeholder instead of an empty or broken chart.

---

### Requirement 17: In-App Notifications

**User Story:** As an agent or admin, I want in-app notifications for key ITSM events, so that I can respond promptly without manually polling each module.

#### Acceptance Criteria

1. THE System SHALL store in-app notifications in a `notifications` Firestore collection with fields: `userId` (string, recipient UID), `message` (string), `type` (one of `"incident_assigned"`, `"change_approval_update"`, `"sla_breach"`), `relatedId` (string, document ID of the related record), `relatedModule` (one of `"incident"`, `"problem"`, `"change"`), `read` (boolean, default `false`), and `createdAt` (Firestore server timestamp).
2. WHEN an incident's `assignedTo` field is updated to a non-empty user UID, THE System SHALL create a notification document for that user with `type: "incident_assigned"` and `message: "Incident <number> has been assigned to you"`.
3. WHEN a change request's `approvalStatus` changes to `"Approved"` or `"Rejected"`, THE System SHALL create a notification document for the change's `requestedBy` user with `type: "change_approval_update"` and `message: "Change <number> has been <Approved/Rejected>"`.
4. WHEN the SLA_Engine sets `resolutionSlaStatus` to `"Breached"` on an incident, THE System SHALL create a notification document for the incident's `assignedTo` user (if set) with `type: "sla_breach"` and `message: "SLA breached on Incident <number>"`.
5. THE Notification_Bell in `AppNavbar.tsx` SHALL display a badge showing the count of unread notifications for the current user, derived from a live `onSnapshot` query on the `notifications` collection filtered by `userId` and `read: false`.
6. WHEN a user clicks the Notification_Bell, THE System SHALL display a dropdown panel listing the 10 most recent notifications for that user, ordered by `createdAt` descending, showing `message` and a relative timestamp.
7. WHEN a user clicks a notification in the dropdown, THE System SHALL mark it as `read: true` in Firestore and navigate to the related record's detail page (`/tickets/:id`, `/problem/:id`, or `/change/:id`).

---

### Requirement 18: Email Alerts via Server API

**User Story:** As an admin, I want email alerts sent for critical ITSM events, so that stakeholders are notified even when they are not logged into the system.

#### Acceptance Criteria

1. THE System SHALL add a `POST /api/notifications/email` endpoint to `server.ts` that accepts `{ to: string, subject: string, body: string }` and sends an email using a configured email provider (e.g., nodemailer with SMTP credentials from environment variables).
2. WHEN the SLA_Engine detects a resolution SLA breach, THE System SHALL call `POST /api/notifications/email` with the incident's `assignedTo` user's email address, subject `"SLA Breach: Incident <number>"`, and a body containing the incident number, title, and breach timestamp.
3. WHEN a change request's `approvalStatus` changes to `"Approved"` or `"Rejected"`, THE System SHALL call `POST /api/notifications/email` with the `requestedBy` user's email address, subject `"Change Request <number> <Approved/Rejected>"`, and a body containing the change number, title, and approver name.
4. IF the email provider is not configured (missing SMTP environment variables), THEN THE System SHALL log a warning `"[Email] SMTP not configured, skipping email notification"` and return a `200` response with `{ status: "skipped", reason: "smtp_not_configured" }` rather than returning an error.

---

### Requirement 19: Role-Based Access Control

**User Story:** As a system administrator, I want role-based access enforced across all three modules, so that users can only perform actions appropriate to their role.

#### Acceptance Criteria

1. WHILE a user's `profile.role` is `"user"`, THE System SHALL allow that user to create new incidents via the `/tickets` page and view only incidents where `createdBy` equals their own UID.
2. WHILE a user's `profile.role` is `"user"`, THE System SHALL not render the Create Problem, Create Change, Approve, or Reject buttons.
3. WHILE a user's `profile.role` is `"agent"`, THE System SHALL allow that user to create, view, and update all incidents, create and manage problems, and view (but not approve or reject) change requests.
4. WHILE a user's `profile.role` is `"admin"` or `"super_admin"`, THE System SHALL allow full create, read, update, and delete access to all incidents, problems, and change requests, including the ability to approve and reject change requests.
5. IF a user with `profile.role` equal to `"user"` navigates directly to `/problem` or `/change`, THEN THE System SHALL display a read-only view of those pages without create or edit controls.

---

### Requirement 20: Input Validation

**User Story:** As a developer, I want all ITSM forms to validate required fields before writing to Firestore, so that the database does not contain incomplete or malformed records.

#### Acceptance Criteria

1. WHEN an agent submits the incident creation form with an empty `caller` or `title` field, THE Incident_Module SHALL display an inline validation error next to the empty field and SHALL NOT write to Firestore.
2. WHEN an agent submits the problem creation form with an empty `title` field, THE Problem_Module SHALL display an inline validation error and SHALL NOT write to Firestore.
3. WHEN an agent submits the change creation form with an empty `title`, `changeType`, or `scheduledDate` field, THE Change_Module SHALL display an inline validation error and SHALL NOT write to Firestore.
4. WHEN an agent submits a change creation form where `scheduledDate` is a date in the past, THE Change_Module SHALL display an inline validation error "Scheduled date must be in the future" and SHALL NOT write to Firestore.
5. THE System SHALL sanitize all string inputs by trimming leading and trailing whitespace before writing to Firestore.

---

### Requirement 21: Firestore Security Rules

**User Story:** As a security-conscious administrator, I want Firestore security rules to enforce role-based access at the database level, so that unauthorized writes are rejected even if the frontend is bypassed.

#### Acceptance Criteria

1. THE System SHALL update `firestore.rules` so that documents in the `problems` collection can only be created or updated by authenticated users whose `users/{uid}.role` is `"agent"`, `"admin"`, or `"super_admin"`.
2. THE System SHALL update `firestore.rules` so that documents in the `changes` collection can only be created by authenticated users whose `users/{uid}.role` is `"agent"`, `"admin"`, or `"super_admin"`, and the `approvalStatus` field can only be set to `"Approved"` or `"Rejected"` by users whose role is `"admin"` or `"super_admin"`.
3. THE System SHALL update `firestore.rules` so that documents in the `notifications` collection can only be read by the authenticated user whose UID matches the document's `userId` field.
4. THE System SHALL update `firestore.rules` so that documents in the `tickets` collection can be read by the authenticated user who created them (`createdBy == request.auth.uid`) or by any user whose role is `"agent"`, `"admin"`, or `"super_admin"`.
5. THE System SHALL update `firestore.rules` so that all existing rules for the `tickets`, `users`, and `sla_policies` collections remain at least as permissive as they currently are, ensuring no existing functionality is broken.

---

### Requirement 22: Preserve Existing Functionality

**User Story:** As a developer, I want all existing pages and features to continue working after the ITIL modules are added, so that no regressions are introduced.

#### Acceptance Criteria

1. THE System SHALL preserve the existing `Tickets.tsx` component and its `/tickets` route, including all existing fields, SLA timers, column filters, and the create ticket modal.
2. THE System SHALL preserve the existing `TicketDetail.tsx` component and its `/tickets/:id` route, including the activity timeline, comment system, SLA pause/resume logic, and Related Records tab.
3. THE System SHALL preserve all existing Express.js API endpoints in `server.ts` (`/api/tickets/*`, `/api/ai/chat`, `/api/health`, `/api/db-test`) without modification.
4. THE System SHALL preserve the existing `Sidebar.tsx` navigation structure, including all existing section labels, icons, and links, and SHALL only add new items to existing sections or append new items.
5. THE System SHALL preserve the existing `AuthContext.tsx` demo login, `demoLogin` function, and role-based routing logic in `App.tsx` without modification.
