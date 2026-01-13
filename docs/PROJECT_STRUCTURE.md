# SafeShift EHR - Project Structure Documentation

> **Document Status**: Current as of January 2026  
> **Refactoring Status**: ✅ Complete - Client/Server separation implemented

---

## Overview

The SafeShift EHR project follows a clean separation between the **frontend** (client) and **backend** (server) applications. This structure enables independent development, testing, and deployment of each layer while maintaining clear boundaries and responsibilities.

---

## High-Level Architecture

```
project/
├── client/           # React frontend (SPA)
├── server/           # PHP backend (API + Services)
├── docker/           # Container configuration
├── docs/             # Shared documentation
├── Dockerfile        # Container build definition
├── docker-compose.yml
└── README.md
```

### Design Principles

1. **Separation of Concerns**: Frontend handles UI/UX; backend handles business logic and data
2. **API-First Design**: All communication happens via RESTful JSON APIs
3. **Independent Deployability**: Client and server can be deployed separately
4. **HIPAA Compliance**: Security controls implemented at both layers

---

## Client Directory (`client/`)

The frontend is a React Single Page Application (SPA) built with TypeScript and Vite.

```
client/
├── src/                    # Source code
│   ├── app/                # Application core
│   │   ├── components/     # Reusable UI components
│   │   ├── hooks/          # Custom React hooks
│   │   ├── services/       # API service layer
│   │   ├── types/          # TypeScript type definitions
│   │   └── utils/          # Utility functions
│   ├── pages/              # Page components (routes)
│   ├── features/           # Feature-specific modules
│   ├── styles/             # Global styles
│   ├── App.tsx             # Root application component
│   └── main.tsx            # Application entry point
│
├── index.html              # HTML template
├── package.json            # Node.js dependencies
├── package-lock.json       # Locked dependency versions
├── tsconfig.json           # TypeScript configuration
├── vite.config.ts          # Vite bundler configuration
├── tailwind.config.ts      # Tailwind CSS configuration
├── postcss.config.mjs      # PostCSS configuration
└── manifest.json           # PWA manifest
```

### Key Files

| File | Purpose |
|------|---------|
| `src/main.tsx` | Application bootstrap, renders root component |
| `src/App.tsx` | Root component with routing configuration |
| `src/app/services/api.ts` | Centralized API client for backend communication |
| `src/app/hooks/useAuth.ts` | Authentication state management |
| `src/app/hooks/useApi.ts` | API request hooks with error handling |
| `vite.config.ts` | Build configuration, dev server proxy settings |

### Development Commands

```bash
cd client
npm install          # Install dependencies
npm run dev          # Start development server (port 5173)
npm run build        # Production build to dist/
npm run preview      # Preview production build
npm run lint         # Run ESLint
npm run type-check   # TypeScript type checking
```

---

## Server Directory (`server/`)

The backend is a PHP application following MVVM (Model-View-ViewModel) architecture patterns.

```
server/
├── public/                 # Web-accessible entry points
│   ├── router.php          # Development server router
│   ├── index.php           # Production entry point
│   └── otp.php             # OTP verification endpoint
│
├── api/                    # API endpoints
│   ├── index.php           # API router
│   ├── v1/                 # Version 1 API
│   │   ├── index.php       # V1 router
│   │   ├── auth.php        # Authentication endpoints
│   │   ├── encounters.php  # Encounter CRUD
│   │   ├── patients.php    # Patient management
│   │   ├── dashboard.php   # Dashboard data
│   │   └── ...
│   ├── video/              # Video meeting endpoints
│   └── auth/               # Legacy auth endpoints
│
├── core/                   # Core infrastructure
│   ├── Services/           # Business logic services
│   ├── Middleware/         # Request middleware
│   └── Helpers/            # Utility classes
│
├── model/                  # Domain layer
│   ├── Entities/           # Domain entities (User, Patient, Encounter)
│   ├── Repositories/       # Data access layer
│   ├── Services/           # Domain services
│   ├── ValueObjects/       # Value objects (Email, SSN, UUID)
│   ├── Config/             # Configuration classes
│   └── Core/               # Core abstractions (Database, Session)
│
├── ViewModel/              # ViewModels for API responses
│   ├── Auth/               # Authentication ViewModels
│   ├── Core/               # Base ViewModel classes
│   ├── Patient/            # Patient ViewModels
│   ├── Encounter/          # Encounter ViewModels
│   └── ...
│
├── includes/               # Bootstrap and utilities
│   ├── bootstrap.php       # Application bootstrap
│   ├── config.php          # Configuration loader
│   ├── db.php              # Database connection
│   └── ...
│
├── database/               # Database management
│   ├── migrations/         # SQL migration files
│   └── seeds/              # Seed data scripts
│
├── tests/                  # PHPUnit tests
│   ├── Unit/               # Unit tests
│   ├── API/                # API integration tests
│   ├── Security/           # Security tests
│   └── bootstrap.php       # Test bootstrap
│
├── scripts/                # Utility scripts
│   ├── deploy.sh           # Deployment script
│   ├── healthcheck.php     # Health check endpoint
│   └── ...
│
├── logs/                   # Application logs (gitignored)
├── sessions/               # PHP session storage (gitignored)
├── cache/                  # Cache files (gitignored)
├── uploads/                # File uploads (gitignored)
├── backups/                # Database backups (gitignored)
│
├── composer.json           # PHP dependencies
├── composer.lock           # Locked dependency versions
├── phpunit.xml             # PHPUnit configuration
├── .env                    # Environment configuration (gitignored)
├── .env.example            # Environment template
└── .htaccess               # Apache configuration
```

### Key Files

| File | Purpose |
|------|---------|
| `public/router.php` | Development server entry point, handles routing |
| `public/index.php` | Production entry point for Apache |
| `api/v1/index.php` | API version 1 router |
| `includes/bootstrap.php` | Autoloading, error handling, session setup |
| `includes/config.php` | Environment and application configuration |
| `model/Core/Database.php` | PDO database abstraction |
| `model/Core/Session.php` | Session management |
| `phpunit.xml` | Test suite configuration |

### Development Commands

```bash
cd server

# Start development server
cd public && php -S localhost:8000 router.php

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite API

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Docker Directory (`docker/`)

Contains Docker-specific configuration files.

```
docker/
├── apache-vhost.conf       # Apache virtual host for production
└── apache_vhost_fix.conf   # Alternative Apache configuration
```

### Apache Virtual Host Configuration

The `apache-vhost.conf` configures Apache to:
- Serve the PHP API from `server/public/`
- Serve the built React app from `server/public/client/` (copied during Docker build)
- Route API requests to PHP backend
- Route all other requests to React SPA for client-side routing

---

## Documentation Directory (`docs/`)

Shared documentation for the entire project.

```
docs/
├── API.md                  # API endpoint documentation
├── ARCHITECTURE.md         # System architecture
├── DEPLOYMENT.md           # Deployment guide
├── HIPAA_COMPLIANCE.md     # HIPAA compliance details
├── PROJECT_STRUCTURE.md    # This document
├── REFACTORING_PLAN.md     # Migration plan (completed)
├── SECURITY.md             # Security documentation
├── TESTING.md              # Testing guide
├── guidelines/             # Development guidelines
│   └── Guidelines.md
└── screenshots/            # Application screenshots
```

---

## Data Flow Architecture

### Request Flow

```
┌─────────────────────────────────────────────────────────────┐
│                        Browser                               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   React SPA (client/)                        │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │ Components  │──│   Hooks      │──│  API Services    │   │
│  └─────────────┘  └──────────────┘  └──────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                         HTTP/JSON
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   PHP Backend (server/)                      │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │   Router    │──│  ViewModel   │──│     Model        │   │
│  │(public/*.php)│  │   Layer     │  │ (Repositories,   │   │
│  └─────────────┘  └──────────────┘  │  Entities)       │   │
│                                      └──────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      MySQL Database                          │
└─────────────────────────────────────────────────────────────┘
```

### MVVM Pattern in Backend

```
HTTP Request
      │
      ▼
┌──────────────┐
│    Router    │ ← public/router.php or api/v1/index.php
└──────────────┘
      │
      ▼
┌──────────────┐
│  ViewModel   │ ← Handles request validation, calls Model
└──────────────┘
      │
      ▼
┌──────────────┐
│    Model     │ ← Business logic, data access
│ (Repository) │
└──────────────┘
      │
      ▼
┌──────────────┐
│  ViewModel   │ ← Transforms data for response
└──────────────┘
      │
      ▼
JSON Response
```

---

## Environment Configuration

### Server Environment (`.env`)

```env
# Database
DB_HOST=localhost
DB_NAME=safeshift_ehr
DB_USER=app_user
DB_PASS=secure_password

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Security
ENCRYPTION_KEY=your-secret-key
SESSION_LIFETIME=3600

# AWS (for features like SES email)
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

### Client Environment (via Vite)

Environment variables prefixed with `VITE_` are available in the client:

```env
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=SafeShift EHR
```

Access in code: `import.meta.env.VITE_API_URL`

---

## Development Workflow

### 1. Backend Development

```bash
# Terminal 1: Start PHP server
cd server/public
php -S localhost:8000 router.php
```

### 2. Frontend Development

```bash
# Terminal 2: Start Vite dev server
cd client
npm run dev
```

### 3. Full Stack Testing

```bash
# Run backend tests
cd server && ./vendor/bin/phpunit

# Run frontend build check
cd client && npm run build
```

### 4. Docker Development

```bash
# Build and run everything
docker-compose up -d

# View logs
docker-compose logs -f app

# Rebuild after changes
docker-compose up -d --build
```

---

## Deployment Architecture

### Production Deployment (Docker)

```
┌─────────────────────────────────────────┐
│           Docker Container              │
│  ┌───────────────────────────────────┐  │
│  │           Apache                  │  │
│  │  ┌─────────────────────────────┐  │  │
│  │  │   /var/www/html/public/    │  │  │
│  │  │   (PHP + Static Assets)    │  │  │
│  │  └─────────────────────────────┘  │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│        MySQL Container/Service          │
└─────────────────────────────────────────┘
```

### URL Routing in Production

| URL Pattern | Handled By |
|-------------|------------|
| `/api/*` | PHP Backend (server/) |
| `/client/*` | React static files |
| `/*` (other) | React SPA (client-side routing) |

---

## Security Considerations

### File Access Control

Files outside `server/public/` are not web-accessible:
- `server/model/` - Protected
- `server/includes/` - Protected  
- `server/core/` - Protected
- `server/.env` - Protected

### Session Storage

Sessions stored in `server/sessions/` with:
- HTTPOnly cookies
- Secure flag (in production)
- SameSite=Strict

### Logs and Sensitive Data

Directories excluded from version control:
- `server/logs/`
- `server/sessions/`
- `server/cache/`
- `server/uploads/`
- `server/backups/`
- `server/.env`

---

## Related Documentation

- [`README.md`](../README.md) - Project overview and quick start
- [`ARCHITECTURE.md`](ARCHITECTURE.md) - Detailed architecture documentation
- [`DEPLOYMENT.md`](DEPLOYMENT.md) - Deployment procedures
- [`SECURITY.md`](SECURITY.md) - Security implementation details
- [`REFACTORING_PLAN.md`](REFACTORING_PLAN.md) - Migration history (completed)

---

*Last Updated: January 2026*
