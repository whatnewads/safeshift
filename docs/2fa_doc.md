# 2FA Verification Debugging Context Document

This document provides full context for debugging the 2FA verification issue in the SafeShift EHR application. Use this to onboard a new chat session.

---

## 1. Current Issue Summary

- **Login succeeds**: `pending_2fa` is correctly set in PHP session after login
- **OTP verification fails**: Despite successful login, OTP verification does not complete
- **Error**: `"2FA verification not expected at this stage"` (thrown by React AuthContext)

The core problem: After login sets `pending_2fa` in the session, the verify-2fa endpoint either isn't being called or the session state isn't being recognized properly.

---

## 2. System Architecture

### Backend (PHP)

| Component | Details |
|-----------|---------|
| **Session Cookie** | `SAFESHIFT_SESSION` (custom name, NOT `PHPSESSID`) |
| **Session Storage** | File-based in `/sessions` directory |
| **OTP Storage** | Database table `otp_codes` (NOT session) |
| **Session Data** | `pending_2fa` array stored in `$_SESSION` |

### Frontend (React + Vite)

| Component | Details |
|-----------|---------|
| **Dev Server** | `localhost:5173` |
| **PHP API** | `localhost:8000` |
| **Proxy** | Configured in `vite.config.ts` to forward `/api/*` to PHP |

---

## 3. Authentication Flow (Intended)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ STEP 1: Initial Check                                                        │
│ GET /api/v1/auth/session-status → Returns stage:'idle'                       │
├─────────────────────────────────────────────────────────────────────────────┤
│ STEP 2: Login                                                                │
│ POST /api/v1/auth/login → Returns stage:'otp', sets $_SESSION['pending_2fa'] │
├─────────────────────────────────────────────────────────────────────────────┤
│ STEP 3: Session Check (after login)                                          │
│ GET /api/v1/auth/session-status → Should return stage:'otp'                  │
├─────────────────────────────────────────────────────────────────────────────┤
│ STEP 4: OTP Verification                                                     │
│ POST /api/v1/auth/verify-2fa → Validates OTP, completes login               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Expected Session Data After Login

```php
$_SESSION['pending_2fa'] = [
    'user_id' => 123,
    'username' => 'tadmin',
    'user_role' => 'tadmin',
    'expires_at' => time() + 600  // 10 minutes
];
```

---

## 4. Key Files

### Backend Files

| File | Purpose |
|------|---------|
| [`includes/bootstrap.php`](includes/bootstrap.php) | Session configuration (cookie name, path, secure settings) |
| [`api/v1/auth.php`](api/v1/auth.php) | Auth endpoints (login, verify-2fa, session-status) |
| [`api/v1/index.php`](api/v1/index.php) | API routing |
| [`ViewModel/Auth/AuthViewModel.php`](ViewModel/Auth/AuthViewModel.php) | Auth business logic |
| [`core/Services/AuthService.php`](core/Services/AuthService.php) | OTP generation/verification |
| [`core/Repositories/OtpRepository.php`](core/Repositories/OtpRepository.php) | Database OTP operations |
| [`model/Core/Session.php`](model/Core/Session.php) | Session wrapper class |

### Frontend Files

| File | Purpose |
|------|---------|
| [`src/app/contexts/AuthContext.tsx`](src/app/contexts/AuthContext.tsx) | Auth state management, stage tracking |
| [`src/app/pages/auth/TwoFactor.tsx`](src/app/pages/auth/TwoFactor.tsx) | OTP entry page component |
| [`src/app/App.tsx`](src/app/App.tsx) | Route definitions |
| [`src/app/services/auth.service.ts`](src/app/services/auth.service.ts) | API calls to backend |
| [`vite.config.ts`](vite.config.ts) | Proxy configuration |

---

## 5. Issues Fixed So Far (14 Total)

| # | Issue | Fix Applied |
|---|-------|-------------|
| 1 | DB_HOST wrong | Changed `10.255.255.254` → `127.0.0.1` |
| 2 | phpMyAdmin password not updated | Synced passwords |
| 3 | Missing schema columns | Added `checksum`, `assigned_at` |
| 4 | Test user passwords not set | Created password hashes |
| 5 | Secure cookie on HTTP localhost | Set `secure: false` for dev |
| 6 | Session regeneration too aggressive | Limited regeneration calls |
| 7 | Vite proxy not forwarding cookies | Added `changeOrigin: true` |
| 8 | `session_name()` called after `session_start()` | Reordered calls |
| 9 | Premature `Session::regenerate(true)` in login | Removed early regeneration |
| 10 | React StrictMode duplicate session calls | Handled with refs |
| 11 | TwoFactor.tsx crash | Fixed `isLoading` vs `loading` property name |
| 12 | App.tsx routing | Fixed `requires2FA` vs `stage === 'otp'` check |
| 13 | Auth stage race condition | Added proper state synchronization |
| 14 | session-status not returning stage:'otp' | Fixed pending_2fa detection |

---

## 6. Current Log Analysis (Most Recent)

### Session Debug Log (`logs/session_debug.log`)

```
[2025-12-26 10:32:47] session-status: No cookie, NEW session created
[2025-12-26 10:33:09] login: Cookie sent, SAME session used
[2025-12-26 10:33:12] POST-LOGIN: SUCCESS, pending_2fa SET
```

### What's Missing From Logs

- ❌ NO `verify-2fa` request logged
- ❌ NO `VERIFY-2FA` entries at all
- ❓ Either the request isn't being made, or it's failing before reaching the API

---

## 7. The Problem

The React frontend is either:

1. **Not calling** the `verify-2fa` API endpoint at all
2. **Throwing error** `"stage not otp"` before making the API call
3. **Session-status** is overwriting the stage back to `'idle'` before verify runs

### Likely Culprit: AuthContext Stage Check

The error `"2FA verification not expected at this stage"` comes from AuthContext when:

```typescript
// In AuthContext.tsx verify2FA function
if (stage !== 'otp') {
    throw new Error('2FA verification not expected at this stage');
}
```

This means when `verify2FA()` is called, the `stage` state variable is NOT `'otp'`.

---

## 8. Diagnostic Logging Locations

| Log File | Contents |
|----------|----------|
| `logs/session_debug.log` | Session state per request (session ID, pending_2fa status) |
| `logs/otp.log` | Generated OTP codes with timestamps |
| Browser Console | `[TwoFactor]` and `[AuthContext]` prefixed logs |
| Browser Network Tab | API requests/responses |

### Adding Console Logs

Check these in browser console:
- `[AuthContext] stage:` - Current auth stage
- `[AuthContext] verify2FA called` - If verify function runs
- `[TwoFactor] handleSubmit` - If form submission triggers

---

## 9. Test Credentials

```
Username: tadmin
Password: TAdmin123!
OTP:      Check logs/otp.log for the current code
```

### Sample OTP Log Entry

```
[2025-12-26 10:33:12] Generated OTP for user_id=1: 482916
```

---

## 10. Commands

### Start PHP Server

```bash
cd c:\Users\wesyi\bckup\project
php -S localhost:8000 router.php
```

### Start Vite Dev Server

```bash
npm run dev
```

### Check OTP Code

```bash
type logs\otp.log
```

### Check Session Debug

```bash
type logs\session_debug.log
```

### Tail Logs (PowerShell)

```powershell
Get-Content logs\session_debug.log -Tail 20 -Wait
```

---

## 11. Next Debugging Steps

### Step 1: Verify TwoFactor Form Submission

Check if `TwoFactor.tsx` form submission actually calls `verify2FA()`:

```typescript
// In TwoFactor.tsx - look for handleSubmit
const handleSubmit = async (e) => {
    console.log('[TwoFactor] handleSubmit called');
    console.log('[TwoFactor] current stage:', stage);
    await verify2FA(otpCode);
};
```

### Step 2: Check React Stage State

Before clicking verify, check browser console for:
- What is the current `stage` value?
- Did it get set to `'otp'` after login?
- Is something resetting it to `'idle'`?

### Step 3: Check Browser Network Tab

1. Open DevTools → Network tab
2. Login with credentials
3. Watch for `session-status` response after login
4. Check if it returns `stage: 'otp'`
5. Enter OTP and click verify
6. Watch for `verify-2fa` request
   - If no request: Frontend issue
   - If request fails: Backend issue

### Step 4: Verify session-status Returns Correct Stage

After login, manually call:
```bash
curl -X GET http://localhost:8000/api/v1/auth/session-status -b "SAFESHIFT_SESSION=<session_id>"
```

Expected response:
```json
{
    "success": true,
    "data": {
        "stage": "otp",
        "pending_2fa": true
    }
}
```

---

## 12. Architecture Diagrams

### Session Flow

```
Browser                     Vite Proxy                    PHP Backend
   │                            │                              │
   │  GET /api/v1/auth/session-status                          │
   │ ─────────────────────────> │ ─────────────────────────>   │
   │                            │                              │
   │                            │  Set-Cookie: SAFESHIFT_SESSION
   │ <───────────────────────── │ <─────────────────────────   │
   │                            │                              │
   │  POST /api/v1/auth/login                                  │
   │  Cookie: SAFESHIFT_SESSION                                │
   │ ─────────────────────────> │ ─────────────────────────>   │
   │                            │                              │
   │                            │  $_SESSION['pending_2fa'] set │
   │  { stage: 'otp' }          │                              │
   │ <───────────────────────── │ <─────────────────────────   │
   │                            │                              │
   │  POST /api/v1/auth/verify-2fa  (THIS IS FAILING)          │
   │  Cookie: SAFESHIFT_SESSION                                │
   │ ─────────────────────────> │ ─────────────────────────>   │
```

### React State Machine

```
┌─────────┐     login()     ┌─────────┐    verify2FA()   ┌──────────────┐
│  idle   │ ──────────────> │   otp   │ ───────────────> │ authenticated│
└─────────┘                 └─────────┘                  └──────────────┘
     │                           │                              │
     │                           │ (stage !== 'otp')            │
     │                           │ ───> ERROR                   │
     │                           │                              │
     └───────────────────────────┴──────────────────────────────┘
                          (session expires or logout)
```

---

## 13. Common Pitfalls

1. **Cookie not sent**: Vite proxy may not forward cookies correctly
2. **Session regeneration**: Calling `session_regenerate_id()` creates new session
3. **React StrictMode**: Double-renders can cause duplicate API calls
4. **Stage race condition**: `session-status` called after login may return before stage updates in state
5. **CORS issues**: Credentials not included in fetch requests

---

## 14. Quick Fixes to Try

### If stage is resetting to 'idle':
- Check if `session-status` is being called after login and overwriting stage
- Add `if (stage === 'otp') return;` guard in session-status effect

### If verify-2fa isn't being called:
- Add console.log in TwoFactor handleSubmit
- Check if form onSubmit is wired correctly
- Check if button type="submit"

### If session isn't persisting:
- Check browser cookies for `SAFESHIFT_SESSION`
- Verify cookie domain and path
- Check if SameSite cookie attribute is set correctly

---

## 15. Related Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) - System architecture
- [`docs/API.md`](docs/API.md) - API documentation
- [`USER_CREDENTIALS.md`](USER_CREDENTIALS.md) - All test user credentials

---

*Document created: 2025-12-26*
*Last updated: 2025-12-26*
