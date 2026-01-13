# SafeShift EHR - Occupational Health Management System

A full-stack HIPAA-compliant Electronic Health Record (EHR) system designed for occupational health clinics. Built with a React/TypeScript frontend and PHP/MySQL backend following MVVM architecture patterns.

## 🏗️ Project Structure

```
project/
├── client/                 # React/TypeScript frontend application
│   ├── src/                # React source code
│   │   ├── app/            # Application core (hooks, services, components)
│   │   ├── pages/          # Page components
│   │   └── features/       # Feature modules
│   ├── index.html          # Frontend entry point
│   ├── package.json        # Node.js dependencies
│   ├── tsconfig.json       # TypeScript configuration
│   ├── vite.config.ts      # Vite bundler configuration
│   ├── tailwind.config.ts  # Tailwind CSS configuration
│   └── postcss.config.mjs  # PostCSS configuration
│
├── server/                 # PHP backend application
│   ├── public/             # Web entry points
│   │   ├── router.php      # Development server entry point
│   │   ├── index.php       # Production entry point
│   │   └── otp.php         # OTP handling
│   ├── api/                # API endpoints
│   │   ├── v1/             # Versioned API (recommended)
│   │   ├── video/          # Video meeting endpoints
│   │   └── auth/           # Authentication endpoints
│   ├── core/               # Core services and infrastructure
│   ├── model/              # Domain layer (Entities, Repositories, ValueObjects)
│   ├── ViewModel/          # ViewModels for MVVM pattern
│   ├── includes/           # Bootstrap, configuration, utilities
│   ├── database/           # Migrations and seed scripts
│   ├── scripts/            # Utility and maintenance scripts
│   ├── tests/              # PHPUnit tests
│   ├── logs/               # Application logs
│   ├── sessions/           # PHP session storage
│   ├── cache/              # Cache files
│   ├── uploads/            # File uploads
│   └── backups/            # Database backups
│
├── docker/                 # Docker configuration
│   └── apache-vhost.conf   # Apache virtual host configuration
│
├── docs/                   # Documentation
│   ├── guidelines/         # Development guidelines
│   └── screenshots/        # Application screenshots
│
├── Dockerfile              # Container build definition
├── docker-compose.yml      # Docker orchestration
├── .gitignore              # Git ignore rules
└── README.md               # This file
```

---

## 🚀 Getting Started

### Prerequisites

- **PHP 8.1+** with extensions: pdo, pdo_mysql, gd
- **MySQL 8.0+**
- **Node.js 18+** and npm
- **Composer** (PHP dependency manager)

### Option 1: Local Development

#### 1. Start the Backend Server

```bash
# Navigate to server public directory
cd server/public

# Start PHP built-in development server
php -S localhost:8000 router.php
```

The backend API will be available at `http://localhost:8000`

#### 2. Start the Frontend Development Server

```bash
# Navigate to client directory
cd client

# Install dependencies (first time only)
npm install

# Start Vite development server
npm run dev
```

The frontend will be available at `http://localhost:5173` with hot module replacement.

#### 3. Configure Environment

Create a `.env` file in the `server/` directory based on `.env.example`:

```bash
cp server/.env.example server/.env
```

Edit `server/.env` with your database credentials and other configuration.

### Option 2: Docker Deployment

#### Quick Start with Docker

```bash
# Build and start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

The application will be available at:
- **Frontend + API**: `http://localhost`
- **Database**: `localhost:3306`

#### Docker Environment Variables

Create a `.env` file at project root for Docker:

```env
DB_ROOT_PASSWORD=your_root_password
DB_NAME=safeshift_ehr
DB_USER=app_user
DB_PASS=your_password
```

---

## 📡 API Reference

### Base URL

| Environment | Base URL |
|-------------|----------|
| Development | `http://localhost:8000/api/v1` |
| Docker | `http://localhost/api/v1` |
| Production | `https://your-domain.com/api/v1` |

### Key Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/login` | POST | User authentication |
| `/api/v1/auth/logout` | POST | End user session |
| `/api/v1/auth/session` | GET | Validate session |
| `/api/v1/auth/csrf` | GET | Get CSRF token |
| `/api/v1/encounters` | GET/POST | List/create encounters |
| `/api/v1/encounters/{id}` | GET/PUT | Get/update encounter |
| `/api/v1/patients` | GET | List patients |
| `/api/v1/dashboard` | GET | Dashboard statistics |
| `/api/v1/notifications` | GET | User notifications |

See [`docs/API.md`](docs/API.md) for complete API documentation.

---

## 🧪 Testing

### Backend Tests (PHPUnit)

```bash
cd server

# Install test dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite API
```

### Frontend Tests

```bash
cd client

# Type checking
npm run type-check

# Lint check
npm run lint

# Build verification
npm run build
```

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [`docs/PROJECT_STRUCTURE.md`](docs/PROJECT_STRUCTURE.md) | Detailed project structure explanation |
| [`docs/API.md`](docs/API.md) | Complete API documentation |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | System architecture overview |
| [`docs/SECURITY.md`](docs/SECURITY.md) | Security implementation details |
| [`docs/HIPAA_COMPLIANCE.md`](docs/HIPAA_COMPLIANCE.md) | HIPAA compliance documentation |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Deployment guide |
| [`docs/TESTING.md`](docs/TESTING.md) | Testing strategy and guides |

---

## 🔒 Security Features

- **HIPAA Compliance**: PHI encryption, audit logging, access controls
- **Authentication**: Session-based auth with optional 2FA
- **CSRF Protection**: Token-based protection for state-changing requests
- **Input Validation**: Server-side validation with sanitization
- **Secure Sessions**: HTTPOnly cookies, secure flags, session regeneration
- **Role-Based Access Control (RBAC)**: Granular permissions system

---

## 🏛️ Architecture

### Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | React 18, TypeScript, Vite, Tailwind CSS |
| Backend | PHP 8.4, MVVM Architecture |
| Database | MySQL 8.0 |
| Web Server | Apache (production), PHP built-in (development) |
| Container | Docker, Docker Compose |

### MVVM Pattern (Backend)

```
HTTP Request → Router → ViewModel → Model → Database
                              ↓
HTTP Response ← View ← ViewModel (data transformation)
```

- **Model**: Business logic, entities, repositories, data persistence
- **ViewModel**: Request/response coordination, data transformation
- **View**: JSON API responses (no HTML rendering)

---

## 👥 Development Team

For development guidelines, see [`docs/guidelines/Guidelines.md`](docs/guidelines/Guidelines.md).

---

## 📄 License

Proprietary - All rights reserved.

---

**Version:** 2.0.0  
**Last Updated:** January 2026  
**Refactored Structure:** Client/Server separation completed
