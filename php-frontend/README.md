# PHP Frontend - Connect IT

A complete PHP conversion of the React/Firebase Connect IT application.

## Features

- **Session-based authentication** - No Firebase client SDK required
- **Server-rendered pages** - Fast initial load, SEO friendly
- **Firestore REST API** - Direct communication with Firestore backend
- **All React routes converted** - Same functionality, PHP implementation

## Pages Converted

| Page | File | Description |
|------|------|-------------|
| Login | `pages/login.php` | Email/password + demo login |
| Register | `pages/register.php` | User registration |
| Dashboard | `pages/dashboard.php` | Main dashboard with stats |
| Tickets | `pages/tickets.php` | Ticket list with filtering |
| Ticket Detail | `pages/ticket_detail.php` | Single ticket view |
| Users | `pages/users.php` | User management (admin) |
| SLA | `pages/sla.php` | SLA policies (admin) |
| Settings | `pages/settings.php` | System settings (admin) |
| Reports | `pages/reports.php` | Analytics and reports |
| Catalog | `pages/catalog.php` | Service catalog |
| Knowledge Base | `pages/knowledge_base.php` | KB articles |
| Approvals | `pages/approvals.php` | Approval workflow |
| Conversations | `pages/conversations.php` | Chat interface |
| History | `pages/history.php` | Activity log |
| Problem Mgmt | `pages/problem.php` | Problem management |
| Change Mgmt | `pages/change.php` | Change management |
| CMDB | `pages/cmdb.php` | Asset management |

## Setup

### 1. Service Account Key

Download a service account JSON key from Firebase Console:
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Project Settings → Service Accounts
3. Click "Generate new private key"
4. Save as `service-account.json` in the project root (next to `firebase-applet-config.json`)

### 2. Web Server

#### Apache
Point your document root to `php-frontend/` or create a virtual host:

```apache
<VirtualHost *:80>
    DocumentRoot "c:/Users/HP/OneDrive/Desktop/ai stu/php-frontend"
    ServerName connectit.local
    
    <Directory "c:/Users/HP/OneDrive/Desktop/ai stu/php-frontend">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### PHP Built-in Server (Development)
```bash
cd php-frontend
php -S localhost:8000
```

### 3. Access the Application

Navigate to: `http://localhost:8000/`

**Demo Login Options:**
- **Demo Admin** - Full administrator access
- **Demo Agent** - Support agent access
- **Demo User** - End user access

## Architecture

```
php-frontend/
├── index.php              # Main router (replaces React Router)
├── includes/
│   ├── config.php       # Configuration & session handling
│   ├── auth.php         # Session-based authentication
│   ├── firebase-api.php # Firestore REST API client
│   └── layout.php       # Base template with sidebar
├── pages/
│   ├── login.php        # Login page
│   ├── dashboard.php    # Dashboard
│   ├── tickets.php      # Tickets list
│   └── ...              # Other pages
└── assets/
    └── css/
        └── style.css    # Tailwind-like CSS
```

## Differences from React Version

| Feature | React | PHP |
|---------|-------|-----|
| Routing | React Router | PHP file-based routing |
| Auth | Firebase Auth | PHP Sessions |
| Data | Firebase Client SDK | Firestore REST API |
| Rendering | Client-side | Server-side |
| Real-time | onSnapshot | Polling (optional) |

## Security Notes

- Uses PHP sessions for authentication state
- CSRF tokens on forms
- Role-based access control
- Service account for Firestore access (server-side only)
