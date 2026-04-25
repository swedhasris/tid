# PHP Backend

This PHP backend replicates the functionality of the original Node.js/Express server (`server.ts`). It connects to Firestore via the Google Firestore REST API, exposes the same REST endpoints, and includes a CLI-based SLA escalation engine.

## Requirements

- PHP 8.1 or higher
- cURL extension enabled
- OpenSSL extension enabled
- Apache with `mod_rewrite` (or any web server that supports URL rewriting)

## Setup

### 1. Service Account Key

Download a Google Cloud service account JSON key with the **Cloud Datastore User** role (or broader Firestore access) and place it in one of these locations:

- **Recommended:** Set the environment variable `GOOGLE_APPLICATION_CREDENTIALS` to the absolute path of the service account file.
- **Alternative:** Place the file at `service-account.json` in the project root (next to `firebase-applet-config.json`).

### 2. Web Server Configuration

#### Apache

The `php-backend/.htaccess` file is already configured to route all non-file/directory requests to `index.php`. Ensure `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
```

Set the document root to the `php-backend/` directory, or place the `php-backend/` folder inside your web root and access it accordingly.

#### PHP Built-in Server (Development Only)

```bash
cd php-backend
php -S localhost:8000 index.php
```

### 3. Build the Frontend

Before serving in production, build the React frontend so the static files exist in `dist/`:

```bash
npm run build
```

The PHP backend serves files from `../dist/` relative to itself.

### 4. SLA Engine Cron Job

Schedule the SLA engine to run every 15 minutes using your system's cron:

```bash
crontab -e
```

Add this line:

```cron
*/15 * * * * /usr/bin/php /absolute/path/to/project/php-backend/scripts/sla-engine.php >> /var/log/sla-engine.log 2>&1
```

Or run manually:

```bash
php php-backend/scripts/sla-engine.php
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/db-test` | Firestore connectivity test |
| GET | `/api/tickets/all` | List all tickets |
| GET | `/api/tickets/open` | List open tickets |
| GET | `/api/tickets/assigned/{userId}` | List tickets assigned to user |
| GET | `/api/tickets/unassigned` | List unassigned tickets |
| GET | `/api/tickets/resolved` | List resolved/closed tickets |
| POST | `/api/tickets/create` | Create a new ticket |
| PUT | `/api/tickets/{id}` | Update a ticket |
| DELETE | `/api/tickets/{id}` | Delete a ticket |
| POST | `/api/tickets/trigger-escalation` | Manually run SLA escalation |

## File Structure

```
php-backend/
├── index.php              # Main router and API endpoints
├── config.php             # Configuration loader
├── firestore-client.php   # Firestore REST API client with OAuth2
├── sla-engine.php         # SLA escalation logic (shared by web and CLI)
├── scripts/
│   └── sla-engine.php     # CLI entry point for cron jobs
└── .htaccess              # Apache rewrite rules
```

## Differences from Node.js Backend

- **Authentication:** Uses OAuth2 service-account JWT flow instead of Firebase Admin SDK.
- **Database:** Communicates with Firestore via REST API instead of the native Node.js client.
- **Server Timestamps:** PHP generates UTC ISO8601 timestamps at request time rather than using Firestore's server-timestamp sentinel (behavior is equivalent for clients).
- **Cron:** Implemented as a standalone CLI script invoked by the system crontab instead of `node-cron`.
