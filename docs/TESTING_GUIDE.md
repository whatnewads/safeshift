# SafeShift EHR Testing Guide

This document provides comprehensive testing guidance for SafeShift EHR, covering backend PHP testing, frontend React testing, API testing, and security testing.

## Testing Strategy

### Test Pyramid

```
         /\
        /  \      E2E Tests (Cypress/Playwright)
       /----\     - Complete user flows
      /      \    - Critical paths only
     /--------\   Integration Tests
    /          \  - API endpoints with database
   /------------\ - Service interactions
  /              \
 /----------------\ Unit Tests
/                  \ - Individual functions
/__________________\ - Components in isolation
```

### Test Levels

1. **Unit Tests** - Individual functions/components in isolation
2. **Integration Tests** - API endpoints with database, service interactions
3. **End-to-End Tests** - Complete user flows through the UI
4. **Security Tests** - Vulnerability assessment, penetration testing

---

## Backend Testing (PHP)

### Setup

#### Install PHPUnit

```bash
# Install via Composer
composer require --dev phpunit/phpunit ^10.0

# Verify installation
./vendor/bin/phpunit --version
```

#### Project Structure

```
/tests/
├── bootstrap.php           # Test bootstrap file
├── .env.testing           # Test environment config
├── Unit/
│   ├── Services/
│   │   ├── RoleServiceTest.php
│   │   └── AuthorizationServiceTest.php
│   ├── Validators/
│   │   ├── PatientValidatorTest.php
│   │   └── EncounterValidatorTest.php
│   └── ValueObjects/
│       ├── EmailTest.php
│       └── SSNTest.php
├── Integration/
│   ├── Api/
│   │   ├── AuthEndpointsTest.php
│   │   ├── PatientEndpointsTest.php
│   │   └── EncounterEndpointsTest.php
│   └── Repositories/
│       └── PatientRepositoryTest.php
└── Fixtures/
    └── TestData.php        # Shared test data
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/Services/RoleServiceTest.php

# Run specific test method
./vendor/bin/phpunit --filter testToUiRoleMapsProviderCorrectly

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Run only unit tests
./vendor/bin/phpunit --testsuite Unit

# Run only integration tests
./vendor/bin/phpunit --testsuite Integration

# Verbose output
./vendor/bin/phpunit -v
```

### Unit Test Examples

#### Testing RoleService

```php
<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Model\Services\RoleService;

class RoleServiceTest extends TestCase
{
    /**
     * @test
     */
    public function toUiRoleMapsProviderCorrectly(): void
    {
        $this->assertEquals('provider', RoleService::toUiRole('pclinician'));
        $this->assertEquals('technician', RoleService::toUiRole('dclinician'));
        $this->assertEquals('registration', RoleService::toUiRole('1clinician'));
    }
    
    /**
     * @test
     */
    public function toUiRoleMapsAdminCorrectly(): void
    {
        $this->assertEquals('super-admin', RoleService::toUiRole('Admin'));
        $this->assertEquals('admin', RoleService::toUiRole('cadmin'));
        $this->assertEquals('admin', RoleService::toUiRole('tadmin'));
    }
    
    /**
     * @test
     */
    public function getPermissionsReturnsArrayForValidRole(): void
    {
        $permissions = RoleService::getPermissions('pclinician');
        
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('patient.view', $permissions);
        $this->assertContains('encounter.create', $permissions);
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsTrueForValidPermission(): void
    {
        $this->assertTrue(RoleService::hasPermission('pclinician', 'patient.view'));
        $this->assertTrue(RoleService::hasPermission('pclinician', 'encounter.sign'));
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsFalseForInvalidPermission(): void
    {
        $this->assertFalse(RoleService::hasPermission('QA', 'patient.delete'));
        $this->assertFalse(RoleService::hasPermission('1clinician', 'system.configure'));
    }
    
    /**
     * @test
     */
    public function adminHasWildcardPermission(): void
    {
        // Admin has '*' which should match any permission
        $this->assertTrue(RoleService::hasPermission('Admin', 'patient.delete'));
        $this->assertTrue(RoleService::hasPermission('Admin', 'system.configure'));
        $this->assertTrue(RoleService::hasPermission('Admin', 'any.permission.here'));
    }
    
    /**
     * @test
     */
    public function wildcardPermissionMatchesCategory(): void
    {
        // cadmin has 'patient.*'
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.view'));
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.create'));
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.delete'));
    }
    
    /**
     * @test
     */
    public function getDashboardRouteReturnsValidPath(): void
    {
        $route = RoleService::getDashboardRoute('pclinician');
        $this->assertEquals('/dashboard/provider', $route);
        
        $route = RoleService::getDashboardRoute('Admin');
        $this->assertEquals('/dashboard/super-admin', $route);
    }
    
    /**
     * @test
     */
    public function isAdminReturnsTrueForAdminRoles(): void
    {
        $this->assertTrue(RoleService::isAdmin('Admin'));
        $this->assertTrue(RoleService::isAdmin('cadmin'));
        $this->assertTrue(RoleService::isAdmin('tadmin'));
    }
    
    /**
     * @test
     */
    public function isAdminReturnsFalseForNonAdminRoles(): void
    {
        $this->assertFalse(RoleService::isAdmin('pclinician'));
        $this->assertFalse(RoleService::isAdmin('Manager'));
        $this->assertFalse(RoleService::isAdmin('QA'));
    }
    
    /**
     * @test
     */
    public function getRoleLevelReturnsCorrectHierarchy(): void
    {
        $this->assertGreaterThan(
            RoleService::getRoleLevel('pclinician'),
            RoleService::getRoleLevel('Admin')
        );
        
        $this->assertGreaterThan(
            RoleService::getRoleLevel('1clinician'),
            RoleService::getRoleLevel('Manager')
        );
    }
}
```

#### Testing AuthorizationService

```php
<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Model\Services\AuthorizationService;

class AuthorizationServiceTest extends TestCase
{
    private array $providerUser;
    private array $adminUser;
    private array $qaUser;
    
    protected function setUp(): void
    {
        $this->providerUser = [
            'user_id' => '123',
            'username' => 'provider1',
            'role' => 'pclinician',
            'clinic_id' => 'clinic_1'
        ];
        
        $this->adminUser = [
            'user_id' => '1',
            'username' => 'admin',
            'role' => 'Admin',
            'clinic_id' => null
        ];
        
        $this->qaUser = [
            'user_id' => '456',
            'username' => 'qa1',
            'role' => 'QA',
            'clinic_id' => 'clinic_1'
        ];
    }
    
    /**
     * @test
     */
    public function canReturnsTrueForValidPermission(): void
    {
        $this->assertTrue(AuthorizationService::can($this->providerUser, 'view', 'patient'));
        $this->assertTrue(AuthorizationService::can($this->providerUser, 'create', 'encounter'));
    }
    
    /**
     * @test
     */
    public function canReturnsFalseForInvalidPermission(): void
    {
        $this->assertFalse(AuthorizationService::can($this->qaUser, 'delete', 'patient'));
        $this->assertFalse(AuthorizationService::can($this->qaUser, 'create', 'patient'));
    }
    
    /**
     * @test
     */
    public function isSuperAdminReturnsTrueForAdmin(): void
    {
        $this->assertTrue(AuthorizationService::isSuperAdmin($this->adminUser));
    }
    
    /**
     * @test
     */
    public function isSuperAdminReturnsFalseForOtherRoles(): void
    {
        $this->assertFalse(AuthorizationService::isSuperAdmin($this->providerUser));
        $this->assertFalse(AuthorizationService::isSuperAdmin($this->qaUser));
    }
    
    /**
     * @test
     */
    public function canViewPatientReturnsCorrectlyBasedOnClinic(): void
    {
        // Same clinic
        $this->assertTrue(AuthorizationService::canViewPatient(
            $this->providerUser, 
            'patient_1', 
            'clinic_1'
        ));
        
        // Different clinic - should be denied
        $this->assertFalse(AuthorizationService::canViewPatient(
            $this->providerUser, 
            'patient_1', 
            'clinic_2'
        ));
        
        // Super admin can view any clinic
        $this->assertTrue(AuthorizationService::canViewPatient(
            $this->adminUser, 
            'patient_1', 
            'clinic_2'
        ));
    }
    
    /**
     * @test
     */
    public function requirePermissionThrowsForMissingPermission(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');
        
        AuthorizationService::requirePermission($this->qaUser, 'patient.delete');
    }
    
    /**
     * @test
     */
    public function filterByAccessFiltersToUserClinic(): void
    {
        $data = [
            ['id' => 1, 'clinic_id' => 'clinic_1'],
            ['id' => 2, 'clinic_id' => 'clinic_2'],
            ['id' => 3, 'clinic_id' => 'clinic_1'],
        ];
        
        $filtered = AuthorizationService::filterByAccess($this->providerUser, $data);
        
        $this->assertCount(2, $filtered);
        foreach ($filtered as $item) {
            $this->assertEquals('clinic_1', $item['clinic_id']);
        }
    }
    
    /**
     * @test
     */
    public function filterByAccessReturnsAllForSuperAdmin(): void
    {
        $data = [
            ['id' => 1, 'clinic_id' => 'clinic_1'],
            ['id' => 2, 'clinic_id' => 'clinic_2'],
            ['id' => 3, 'clinic_id' => 'clinic_3'],
        ];
        
        $filtered = AuthorizationService::filterByAccess($this->adminUser, $data);
        
        $this->assertCount(3, $filtered);
    }
}
```

### Integration Test Examples

```php
<?php
namespace Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

class AuthEndpointsTest extends TestCase
{
    private string $baseUrl = 'http://localhost/api/v1/auth';
    private ?string $sessionCookie = null;
    
    /**
     * @test
     */
    public function loginReturnsSuccessWithValidCredentials(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'TestPass123!'
        ]);
        
        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayHasKey('requires2FA', $response['body']['data']);
    }
    
    /**
     * @test
     */
    public function loginReturns401WithInvalidCredentials(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword'
        ]);
        
        $this->assertEquals(401, $response['status']);
        $this->assertFalse($response['body']['success']);
    }
    
    /**
     * @test
     */
    public function loginReturns422WithMissingFields(): void
    {
        $response = $this->post('/login', [
            'username' => 'testuser'
            // missing password
        ]);
        
        $this->assertEquals(422, $response['status']);
        $this->assertArrayHasKey('errors', $response['body']);
    }
    
    /**
     * @test
     */
    public function currentUserReturns401WhenNotAuthenticated(): void
    {
        $response = $this->get('/current-user');
        
        $this->assertEquals(401, $response['status']);
    }
    
    /**
     * @test
     */
    public function csrfTokenReturnsToken(): void
    {
        $response = $this->get('/csrf-token');
        
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('token', $response['body']['data']);
        $this->assertNotEmpty($response['body']['data']['token']);
    }
    
    /**
     * @test
     */
    public function rateLimitBlocksExcessiveAttempts(): void
    {
        // Make 6 login attempts (limit is 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', [
                'username' => 'ratelimit_test',
                'password' => 'wrongpassword'
            ]);
        }
        
        $this->assertEquals(429, $response['status']);
        $this->assertArrayHasKey('retryAfter', $response['body']);
    }
    
    // Helper methods
    private function post(string $endpoint, array $data): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_HEADER => true
        ]);
        
        if ($this->sessionCookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        }
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $body = substr($response, $headerSize);
        
        return [
            'status' => $status,
            'body' => json_decode($body, true)
        ];
    }
    
    private function get(string $endpoint): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);
        
        if ($this->sessionCookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        }
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $status,
            'body' => json_decode($response, true)
        ];
    }
}
```

---

## Frontend Testing (React)

### Setup

#### Install Dependencies

```bash
cd src

# Install test dependencies
npm install --save-dev vitest @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom

# Update package.json scripts
```

Add to `package.json`:
```json
{
  "scripts": {
    "test": "vitest",
    "test:ui": "vitest --ui",
    "test:coverage": "vitest --coverage",
    "test:watch": "vitest --watch"
  }
}
```

#### Configure Vitest

Create `vitest.config.ts`:
```typescript
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/setupTests.ts'],
    include: ['**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      reporter: ['text', 'html'],
      exclude: ['node_modules/', 'src/setupTests.ts']
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    }
  }
});
```

#### Create Setup File

Create `src/setupTests.ts`:
```typescript
import '@testing-library/jest-dom';
import { vi } from 'vitest';

// Mock fetch globally
global.fetch = vi.fn();

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});
```

### Test Structure

```
/src/
├── __tests__/
│   ├── components/
│   │   ├── ProtectedRoute.test.tsx
│   │   └── PatientList.test.tsx
│   ├── hooks/
│   │   ├── useAuth.test.ts
│   │   └── usePatients.test.ts
│   ├── services/
│   │   └── api.test.ts
│   └── contexts/
│       └── AuthContext.test.tsx
└── setupTests.ts
```

### Running Tests

```bash
# Run all tests
npm run test

# Run in watch mode
npm run test:watch

# Run with UI
npm run test:ui

# Run with coverage
npm run test:coverage

# Run specific file
npm run test -- ProtectedRoute.test.tsx
```

### Component Test Examples

#### Testing ProtectedRoute

```typescript
// src/__tests__/components/ProtectedRoute.test.tsx
import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '@/app/contexts/AuthContext';
import { ProtectedRoute } from '@/app/components/ProtectedRoute';

// Mock the auth service
vi.mock('@/app/services/auth.service', () => ({
  authService: {
    getSessionStatus: vi.fn(),
    getCurrentUser: vi.fn(),
    getCsrfToken: vi.fn()
  }
}));

import { authService } from '@/app/services/auth.service';

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    vi.mocked(authService.getSessionStatus).mockImplementation(
      () => new Promise(() => {}) // Never resolves
    );

    render(
      <MemoryRouter>
        <AuthProvider>
          <ProtectedRoute>
            <div>Protected Content</div>
          </ProtectedRoute>
        </AuthProvider>
      </MemoryRouter>
    );

    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  it('renders children when authenticated', async () => {
    vi.mocked(authService.getSessionStatus).mockResolvedValue({
      authenticated: true,
      user: {
        id: '1',
        username: 'testuser',
        role: 'pclinician',
        uiRole: 'provider'
      }
    });

    render(
      <MemoryRouter>
        <AuthProvider>
          <ProtectedRoute>
            <div>Protected Content</div>
          </ProtectedRoute>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Protected Content')).toBeInTheDocument();
    });
  });

  it('redirects to login when not authenticated', async () => {
    vi.mocked(authService.getSessionStatus).mockResolvedValue({
      authenticated: false
    });

    const { container } = render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <AuthProvider>
          <ProtectedRoute>
            <div>Protected Content</div>
          </ProtectedRoute>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
    });
  });

  it('restricts access based on required roles', async () => {
    vi.mocked(authService.getSessionStatus).mockResolvedValue({
      authenticated: true,
      user: {
        id: '1',
        username: 'qa_user',
        role: 'QA',
        uiRole: 'qa'
      }
    });

    render(
      <MemoryRouter>
        <AuthProvider>
          <ProtectedRoute requiredRoles={['admin', 'super-admin']}>
            <div>Admin Only Content</div>
          </ProtectedRoute>
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.queryByText('Admin Only Content')).not.toBeInTheDocument();
    });
  });
});
```

#### Testing useAuth Hook

```typescript
// src/__tests__/hooks/useAuth.test.ts
import { renderHook, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAuth, AuthProvider } from '@/app/contexts/AuthContext';
import { authService } from '@/app/services/auth.service';

vi.mock('@/app/services/auth.service');

describe('useAuth', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should return loading true initially', () => {
    vi.mocked(authService.getSessionStatus).mockImplementation(
      () => new Promise(() => {})
    );

    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.user).toBeNull();
  });

  it('should set user when authenticated', async () => {
    const mockUser = {
      id: '1',
      username: 'testuser',
      role: 'pclinician',
      uiRole: 'provider',
      permissions: ['patient.view', 'encounter.create']
    };

    vi.mocked(authService.getSessionStatus).mockResolvedValue({
      authenticated: true,
      user: mockUser
    });

    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.user).toEqual(mockUser);
      expect(result.current.isAuthenticated).toBe(true);
    });
  });

  it('should handle login correctly', async () => {
    vi.mocked(authService.login).mockResolvedValue({
      success: true,
      requires2FA: false,
      user: { id: '1', username: 'test' }
    });

    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });

    await act(async () => {
      await result.current.login('testuser', 'password123');
    });

    expect(authService.login).toHaveBeenCalledWith('testuser', 'password123');
  });

  it('should handle logout correctly', async () => {
    vi.mocked(authService.logout).mockResolvedValue({ success: true });

    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });

    await act(async () => {
      await result.current.logout();
    });

    expect(authService.logout).toHaveBeenCalled();
    expect(result.current.user).toBeNull();
  });

  it('hasPermission should check permissions correctly', async () => {
    const mockUser = {
      id: '1',
      permissions: ['patient.view', 'encounter.create']
    };

    vi.mocked(authService.getSessionStatus).mockResolvedValue({
      authenticated: true,
      user: mockUser
    });

    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );

    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.hasPermission('patient.view')).toBe(true);
      expect(result.current.hasPermission('patient.delete')).toBe(false);
    });
  });
});
```

---

## API Testing (Manual)

### Authentication Flow

```bash
# 1. Login
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "testuser", "password": "TestPass123!"}' \
  -c cookies.txt -b cookies.txt

# 2. Get CSRF Token
curl http://localhost/api/v1/auth/csrf-token \
  -b cookies.txt

# 3. Verify OTP (if 2FA enabled)
curl -X POST http://localhost/api/v1/auth/verify-2fa \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token-from-step-2>" \
  -d '{"code": "123456"}' \
  -b cookies.txt

# 4. Get Current User
curl http://localhost/api/v1/auth/current-user \
  -b cookies.txt

# 5. Check Session Status
curl http://localhost/api/v1/auth/session-status \
  -b cookies.txt

# 6. Logout
curl -X POST http://localhost/api/v1/auth/logout \
  -H "X-CSRF-Token: <token>" \
  -b cookies.txt
```

### Patient Endpoints

```bash
# List patients
curl http://localhost/api/v1/patients \
  -b cookies.txt

# Get single patient
curl http://localhost/api/v1/patients/123 \
  -b cookies.txt

# Create patient (requires CSRF)
curl -X POST http://localhost/api/v1/patients \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "dob": "1990-01-01",
    "gender": "M"
  }' \
  -b cookies.txt

# Search patients
curl "http://localhost/api/v1/patients?q=john&limit=10" \
  -b cookies.txt
```

### Encounter Endpoints

```bash
# List encounters
curl http://localhost/api/v1/encounters \
  -b cookies.txt

# Create encounter
curl -X POST http://localhost/api/v1/encounters \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -d '{
    "patient_id": "123",
    "encounter_type": "VISIT",
    "chief_complaint": "Annual checkup"
  }' \
  -b cookies.txt
```

---

## Security Testing

### CSRF Protection Test

```bash
# Test 1: POST without CSRF token - should return 403/419
curl -X POST http://localhost/api/v1/auth/logout \
  -b cookies.txt
# Expected: 403 Forbidden or 419 Invalid CSRF

# Test 2: POST with invalid token - should return 403/419
curl -X POST http://localhost/api/v1/auth/logout \
  -H "X-CSRF-Token: invalid_token" \
  -b cookies.txt
# Expected: 403 Forbidden

# Test 3: POST with valid token - should succeed
curl -X POST http://localhost/api/v1/auth/logout \
  -H "X-CSRF-Token: <valid_token>" \
  -b cookies.txt
# Expected: 200 OK
```

### Authentication Test

```bash
# Test 1: Access protected endpoint without session - should return 401
curl http://localhost/api/v1/patients
# Expected: 401 Unauthorized

# Test 2: Login with wrong password - should return 401
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "testuser", "password": "wrongpassword"}'
# Expected: 401 Unauthorized

# Test 3: Rate limit test - 6 attempts should block
for i in {1..6}; do
  curl -X POST http://localhost/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username": "test", "password": "wrong"}'
done
# Expected: 429 Too Many Requests on 6th attempt
```

### Authorization Test

```bash
# Test 1: Access admin endpoint as non-admin
# Login as provider first, then:
curl http://localhost/api/v1/admin/users \
  -b cookies.txt
# Expected: 403 Forbidden

# Test 2: Access patient from different clinic
curl http://localhost/api/v1/patients/other_clinic_patient_id \
  -b cookies.txt
# Expected: 403 Forbidden
```

### SQL Injection Test

```bash
# Test: Injection in search parameter - should be sanitized
curl "http://localhost/api/v1/patients?q='; DROP TABLE patients; --" \
  -b cookies.txt
# Expected: Empty results or search results, no SQL error

# Test: Injection in ID parameter
curl "http://localhost/api/v1/patients/1 OR 1=1" \
  -b cookies.txt
# Expected: 404 Not Found or 400 Bad Request
```

### XSS Prevention Test

```bash
# Test: Script injection in input - should be escaped
curl -X POST http://localhost/api/v1/patients \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -d '{"first_name": "<script>alert(1)</script>"}' \
  -b cookies.txt
# Expected: Input rejected or sanitized in response
```

---

## Test Data

### Test Users

Create test users with the provided [`create_test_user.php`](../create_test_user.php) script:

| Username | Role | Password | Purpose |
|----------|------|----------|---------|
| `admin` | Admin | `AdminPass123!` | Super admin testing |
| `provider1` | pclinician | `ProviderPass123!` | Clinical provider testing |
| `tech1` | dclinician | `TechPass123!` | DOT technician testing |
| `intake1` | 1clinician | `IntakePass123!` | Registration testing |
| `qa1` | QA | `QAPass123!` | Quality assurance testing |
| `manager1` | Manager | `ManagerPass123!` | Manager testing |

### Test Patients

```php
// Create via API or database seeder
$testPatients = [
    ['first_name' => 'John', 'last_name' => 'Doe', 'dob' => '1980-01-15'],
    ['first_name' => 'Jane', 'last_name' => 'Smith', 'dob' => '1992-06-20'],
    ['first_name' => 'Bob', 'last_name' => 'Johnson', 'dob' => '1975-11-30'],
];
```

---

## Continuous Integration

### GitHub Actions Configuration

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  php-tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: safeshift_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, pdo, pdo_mysql
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run unit tests
        run: ./vendor/bin/phpunit --testsuite Unit
      
      - name: Run integration tests
        run: ./vendor/bin/phpunit --testsuite Integration
        env:
          DB_HOST: 127.0.0.1
          DB_NAME: safeshift_test
          DB_USER: root
          DB_PASS: root
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml

  react-tests:
    runs-on: ubuntu-latest
    
    defaults:
      run:
        working-directory: ./src
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: src/package-lock.json
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run tests
        run: npm run test -- --coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./src/coverage/lcov.info

  security-scan:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Run PHP security checker
        uses: symfonycorp/security-checker-action@v5
      
      - name: Run npm audit
        run: npm audit --audit-level=high
        working-directory: ./src
```

---

## Code Coverage Requirements

### Minimum Coverage Targets

| Category | Target | Critical |
|----------|--------|----------|
| Overall | 70% | Yes |
| Services | 80% | Yes |
| Validators | 90% | Yes |
| API Endpoints | 75% | Yes |
| UI Components | 60% | No |
| Hooks | 70% | Yes |

### Coverage Reports

```bash
# PHP coverage
./vendor/bin/phpunit --coverage-html coverage/php

# React coverage
npm run test:coverage

# View reports
open coverage/php/index.html
open src/coverage/index.html
```

---

## Video Meeting Testing

This section covers testing for the WebRTC Video Meeting feature.

### Video Meeting Test Files

```
tests/
├── Unit/
│   ├── Entities/
│   │   └── VideoMeetingEntityTest.php      # Entity tests (8 test cases)
│   ├── Repositories/
│   │   └── VideoMeetingRepositoryTest.php  # Repository tests (12 test cases)
│   └── ViewModels/
│       └── VideoMeetingViewModelTest.php   # ViewModel tests (14 test cases)
├── API/
│   └── VideoMeetingApiTest.php             # API integration tests (10 test cases)
├── Security/
│   └── VideoMeetingSecurityTest.php        # Security tests (9 test cases)
└── Helpers/
    └── Factories/
        └── VideoMeetingFactory.php          # Test data factory
```

### Running Video Meeting Tests

```bash
# Run all video meeting tests
./vendor/bin/phpunit --filter VideoMeeting

# Run specific test suites
./vendor/bin/phpunit tests/Unit/Entities/VideoMeetingEntityTest.php
./vendor/bin/phpunit tests/Unit/Repositories/VideoMeetingRepositoryTest.php
./vendor/bin/phpunit tests/Unit/ViewModels/VideoMeetingViewModelTest.php
./vendor/bin/phpunit tests/API/VideoMeetingApiTest.php
./vendor/bin/phpunit tests/Security/VideoMeetingSecurityTest.php

# Run with coverage
./vendor/bin/phpunit --filter VideoMeeting --coverage-html coverage/video-meeting

# Run security tests only
./vendor/bin/phpunit --group security
```

### Video Meeting Test Cases

#### Entity Tests (`VideoMeetingEntityTest.php`)

| Test Method | Description |
|-------------|-------------|
| `testFromArray_MapsAllFields` | VideoMeeting::fromArray correctly maps all database fields |
| `testToArray_IncludesAllFields` | VideoMeeting::toArray returns complete array representation |
| `testToSafeArray_ExcludesToken` | VideoMeeting::toSafeArray excludes sensitive token |
| `testIsActive_ReturnsTrueWhenActive` | Active meeting detection |
| `testIsActive_ReturnsFalseWhenInactive` | Ended meeting detection |
| `testIsTokenExpired_ReturnsTrueWhenExpired` | Expired token detection |
| `testIsTokenExpired_ReturnsFalseWhenValid` | Valid token verification |
| `testGetId_ReturnsCorrectId` | ID getter works correctly |

#### Repository Tests (`VideoMeetingRepositoryTest.php`)

| Test Method | Description |
|-------------|-------------|
| `testCreate_ReturnsMeetingId` | Meeting creation returns new ID |
| `testFindByToken_ExistingToken_ReturnsMeeting` | Token lookup for existing meeting |
| `testFindByToken_NonExistingToken_ReturnsNull` | Token lookup for missing meeting |
| `testFindById_ReturnsCorrectMeeting` | ID-based meeting lookup |
| `testUpdateStatus_SetsIsActiveAndEndedAt` | Status update functionality |
| `testAddParticipant_ReturnsParticipantId` | Participant creation |
| `testGetParticipants_ActiveOnly_FiltersLeft` | Active participant filtering |
| `testAddChatMessage_ReturnsMessageId` | Chat message creation |
| `testGetChatMessages_OrderedByTime` | Message ordering |
| `testLogEvent_InsertsLog` | Event logging |
| `testIsTokenUnique_ReturnsTrueForNew` | Token uniqueness check |
| `testIsTokenUnique_ReturnsFalseForExisting` | Duplicate token detection |

#### ViewModel Tests (`VideoMeetingViewModelTest.php`)

| Test Method | Description |
|-------------|-------------|
| `testCreateMeeting_WithClinicianRole_Success` | Clinician can create meeting |
| `testCreateMeeting_WithoutClinicianRole_Fails` | Non-clinician blocked |
| `testGenerateSecureToken_ReturnsUnique64CharString` | Token format validation |
| `testValidateToken_ValidToken_ReturnsSuccess` | Valid token acceptance |
| `testValidateToken_ExpiredToken_ReturnsFalse` | Expired token rejection |
| `testValidateToken_InvalidToken_ReturnsFalse` | Invalid token rejection |
| `testJoinMeeting_ValidToken_CreatesParticipant` | Successful join flow |
| `testJoinMeeting_EmptyDisplayName_Fails` | Empty name validation |
| `testJoinMeeting_DisplayNameSanitized` | XSS prevention in name |
| `testLeaveMeeting_UpdatesLeftAt` | Leave meeting updates timestamp |
| `testEndMeeting_ByCreator_Success` | Creator can end meeting |
| `testEndMeeting_ByNonCreator_Fails` | Non-creator blocked |
| `testSendChatMessage_SanitizesInput` | Chat XSS prevention |
| `testGetParticipants_ReturnsActiveOnly` | Active participant list |

#### API Integration Tests (`VideoMeetingApiTest.php`)

| Test Method | Description |
|-------------|-------------|
| `testCreateMeetingEndpoint_Authenticated_Success` | Authenticated meeting creation |
| `testCreateMeetingEndpoint_Unauthenticated_Returns401` | Auth requirement |
| `testCreateMeetingEndpoint_NonClinician_Returns403` | RBAC enforcement |
| `testValidateTokenEndpoint_ValidToken_ReturnsSuccess` | Token validation endpoint |
| `testValidateTokenEndpoint_InvalidToken_ReturnsError` | Invalid token handling |
| `testJoinMeetingEndpoint_ValidRequest_Success` | Join flow success |
| `testJoinMeetingEndpoint_MissingDisplayName_Returns400` | Validation errors |
| `testChatMessageEndpoint_ValidMessage_Success` | Chat functionality |
| `testChatMessageEndpoint_XSS_Sanitized` | XSS prevention in chat |
| `testEndMeetingEndpoint_ByCreator_Success` | End meeting authorization |

#### Security Tests (`VideoMeetingSecurityTest.php`)

| Test Method | Description |
|-------------|-------------|
| `testToken_Is64Characters` | Token length validation |
| `testToken_IsCryptographicallyRandom` | Token randomness verification |
| `testTokenExpiration_After24Hours` | Token expiry behavior |
| `testSQLInjection_InDisplayName_Prevented` | SQL injection prevention |
| `testXSS_InChatMessage_Sanitized` | XSS sanitization in messages |
| `testXSS_InDisplayName_Sanitized` | XSS sanitization in names |
| `testRBAC_OnlyClinicianCanCreate` | Role-based access control |
| `testMeetingAccess_OnlyCreatorCanEnd` | Meeting ownership check |
| `testIPLogging_RecordsClientIP` | IP logging functionality |

### Video Meeting Test Factory Usage

```php
use Tests\Helpers\Factories\VideoMeetingFactory;

// Create a basic meeting
$meeting = VideoMeetingFactory::create();

// Create with overrides
$meeting = VideoMeetingFactory::create([
    'created_by' => 42,
    'is_active' => true,
]);

// Create expired meeting
$expiredMeeting = VideoMeetingFactory::createExpiredMeeting();

// Create ended meeting
$endedMeeting = VideoMeetingFactory::createEndedMeeting();

// Create meeting with participants
$result = VideoMeetingFactory::createWithParticipants(3);
// Returns: ['meeting' => VideoMeeting, 'participants' => [MeetingParticipant, ...]]

// Create secure token
$token = VideoMeetingFactory::createToken();
// Returns: 64-character hex string

// Create participant
$participant = VideoMeetingFactory::createParticipant([
    'meeting_id' => 100,
    'display_name' => 'Dr. Smith',
]);

// Create chat message
$message = VideoMeetingFactory::createChatMessage([
    'meeting_id' => 100,
    'participant_id' => 50,
    'message_text' => 'Hello!',
]);

// Create XSS payloads for testing
$xss = VideoMeetingFactory::createXSSPayload('script');
$xss = VideoMeetingFactory::createXSSPayload('img');
$xss = VideoMeetingFactory::createXSSPayload('event');

// Create SQL injection payloads for testing
$sql = VideoMeetingFactory::createSQLInjectionPayload('basic');
$sql = VideoMeetingFactory::createSQLInjectionPayload('union');
```

### Manual API Testing for Video Meetings

```bash
# 1. Create a meeting (requires clinician session)
curl -X POST http://localhost/api/video/meetings \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -b cookies.txt

# Expected response:
# {
#   "success": true,
#   "data": {
#     "meeting_id": 123,
#     "meeting_url": "http://localhost/video/join?token=...",
#     "token": "64-char-hex-token",
#     "expires_at": "2026-01-12T14:30:00Z"
#   }
# }

# 2. Validate token (no auth required)
curl "http://localhost/api/video/validate-token?token=<64-char-token>"

# Expected response:
# {
#   "success": true,
#   "data": {
#     "valid": true,
#     "meeting_id": 123,
#     "can_join": true
#   }
# }

# 3. Join meeting (no auth required)
curl -X POST http://localhost/api/video/join \
  -H "Content-Type: application/json" \
  -d '{
    "token": "<64-char-token>",
    "display_name": "John Patient"
  }'

# Expected response:
# {
#   "success": true,
#   "data": {
#     "participant_id": 456,
#     "meeting_id": 123,
#     "display_name": "John Patient"
#   }
# }

# 4. Send chat message
curl -X POST http://localhost/api/video/chat \
  -H "Content-Type: application/json" \
  -d '{
    "meeting_id": 123,
    "participant_id": 456,
    "message": "Hello, can you hear me?"
  }'

# 5. Register peer (for WebRTC)
curl -X POST http://localhost/api/video/peer/register \
  -H "Content-Type: application/json" \
  -d '{
    "meeting_id": 123,
    "participant_id": 456,
    "peer_id": "peer-abc123"
  }'

# 6. Send heartbeat (for peer discovery)
curl -X POST http://localhost/api/video/peer/heartbeat \
  -H "Content-Type: application/json" \
  -d '{"participant_id": 456}'

# 7. Leave meeting
curl -X POST http://localhost/api/video/leave \
  -H "Content-Type: application/json" \
  -d '{
    "meeting_id": 123,
    "participant_id": 456
  }'

# 8. End meeting (creator only)
curl -X POST http://localhost/api/video/meetings/123/end \
  -H "X-CSRF-Token: <token>" \
  -b cookies.txt
```

### Security Test Scenarios

#### Token Security Tests

```bash
# Test 1: Short token (should be rejected)
curl "http://localhost/api/video/validate-token?token=short"
# Expected: 400 Bad Request

# Test 2: Invalid hex characters
curl "http://localhost/api/video/validate-token?token=GHIJ$(printf 'a%.0s' {1..60})"
# Expected: 400 Bad Request

# Test 3: Expired token
# (Create meeting, wait 24+ hours, then validate)
# Expected: { "valid": false, "error": "Meeting link has expired" }
```

#### XSS Prevention Tests

```bash
# Test: XSS in display name
curl -X POST http://localhost/api/video/join \
  -H "Content-Type: application/json" \
  -d '{
    "token": "<valid-token>",
    "display_name": "<script>alert(1)</script>"
  }'
# Expected: Script tags sanitized in response

# Test: XSS in chat message
curl -X POST http://localhost/api/video/chat \
  -H "Content-Type: application/json" \
  -d '{
    "meeting_id": 123,
    "participant_id": 456,
    "message": "<img src=x onerror=alert(1)>"
  }'
# Expected: XSS payload sanitized
```

#### SQL Injection Tests

```bash
# Test: SQL injection in display name
curl -X POST http://localhost/api/video/join \
  -H "Content-Type: application/json" \
  -d '{
    "token": "<valid-token>",
    "display_name": "'\'' OR 1=1; DROP TABLE users;--"
  }'
# Expected: No SQL error, input is sanitized
```

#### Authorization Tests

```bash
# Test: Non-clinician creating meeting
# Login as QA user first
curl -X POST http://localhost/api/video/meetings \
  -H "X-CSRF-Token: <token>" \
  -b qa_cookies.txt
# Expected: 403 Forbidden

# Test: Non-creator ending meeting
curl -X POST http://localhost/api/video/meetings/123/end \
  -H "X-CSRF-Token: <token>" \
  -b different_user_cookies.txt
# Expected: 403 Forbidden
```

### Video Meeting Test Coverage Targets

| Component | Target | Critical |
|-----------|--------|----------|
| VideoMeetingViewModel | 85% | Yes |
| VideoMeetingRepository | 80% | Yes |
| Entity Classes | 90% | Yes |
| API Endpoints | 75% | Yes |
| Security Tests | 100% | Yes |

---

## Related Documentation

- [Security Documentation](./SECURITY.md)
- [HIPAA Compliance](./HIPAA_COMPLIANCE.md)
- [API Integration Guide](./INTEGRATION_GUIDE.md)
- [Video Meeting API Documentation](./VIDEO_MEETING_API.md)
