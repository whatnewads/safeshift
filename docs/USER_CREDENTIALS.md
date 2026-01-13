# SafeShift EHR - User Credentials

Generated on: 2025-11-07 10:03:28

## Test User Accounts

These are the test accounts with known passwords for development/testing:

| Username | Password | Email | Role | Status |
|----------|----------|-------|------|--------|
| tadmin_user | TAdmin123! | tadmin_user@test.safeshift.ai | tadmin | Created New |
| cadmin_user | CAdmin123! | cadmin_user@test.safeshift.ai | cadmin | Created New |
| pclinician_user | PClinician123! | pclinician_user@test.safeshift.ai | pclinician | Created New |
| dclinician_user | DClinician123! | dclinician_user@test.safeshift.ai | dclinician | Created New |
| 1clinician_user | 1Clinician123! | 1clinician_user@test.safeshift.ai | 1clinician | Created New |
| custom_user | Custom123! | custom_user@test.safeshift.ai | custom | Created New |

## All Existing Users in Database

| Username | Email | Status | MFA Enabled | Roles |
|----------|-------|--------|-------------|-------|
| 1clinician | 1clinician@safeshift.local | active | No | 1clinician |
| 1clinician_user | 1clinician_user@test.safeshift.ai | active | No | 1clinician |
| cadmin_user | cadmin_user@test.safeshift.ai | active | No | No Role |
| custom_user | custom_user@test.safeshift.ai | active | No | No Role |
| dclinician | dclinician@safeshift.local | active | No | dclinician |
| dclinician_user | dclinician_user@test.safeshift.ai | active | No | dclinician |
| pclinician | pclinician@safeshift.local | active | No | pclinician |
| pclinician_user | pclinician_user@test.safeshift.ai | active | No | pclinician |
| tadmin | tadmin@safeshift.local | active | No | tadmin |
| tadmin_user | tadmin_user@test.safeshift.ai | active | No | tadmin |

## Password Policy

- Minimum 8 characters
- Must contain uppercase and lowercase letters
- Must contain numbers
- Must contain special characters

## Notes

- All passwords are hashed using bcrypt in the database
- MFA is disabled for test accounts to simplify testing
- These credentials are for development/testing only
