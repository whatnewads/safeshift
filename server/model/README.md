# Model Layer (`/model` or `/core/model`)

## Purpose

The **Model** layer is the **brain** of the application.

It owns:

- Business rules and domain logic
- Data structures and invariants
- Persistence (DB access, repositories)
- Integrations that are part of the core domain (e.g. EHR-specific external APIs)
- Security/encryption logic tied to how data is stored or transmitted
- Core authentication and authorization policies

The UI and ViewModel should depend on Models, not the other way around.

---

## PHP-Specific Context

Unlike reactive frameworks (React, Vue, WPF) that use data binding, PHP requires explicit coordination. Our Model layer provides:

- **Stateless operations** that work request-to-request
- **Domain services** that encapsulate complex business rules
- **Repositories** that abstract database operations
- **Entities** that represent domain objects with behavior

The Model should be **framework-agnostic** and testable without any web context.

---

## Files that belong here

### Domain Entities / Value Objects
- `Patient.php`
- `Encounter.php`
- `Visit.php`
- `Clinic.php`
- `User.php`
- `Role.php`
- `InjuryCase.php`
- `WorkRestriction.php`

### Repositories / Data Access Classes
- `PatientRepository.php`
- `EncounterRepository.php`
- `UserRepository.php`
- Any class that speaks SQL / PDO / DB driver

### Business Logic Services (Domain-Focused)
- `OschaCaseClassifier.php`
- `ReturnToWorkCalculator.php`
- `ExposureAssessment.php`
- `BillingRulesEngine.php`
- `ComplianceChecker.php`

### Validation & Domain Policies
- Input validators tied to **business rules** (not just UI formatting)
- `EncounterValidator.php`
- `WorkRestrictionPolicy.php`
- `DOTComplianceValidator.php`

### Security / Crypto Helpers Tied to Data
- Encryption/decryption for PHI at rest or in motion
- Key handling logic (where it must be domain-aware)
- `EncryptionService.php`
- `TokenGenerator.php`

### Integrations (Domain-Level, Not UI-Level)
- EHR-to-EHR interfaces
- Lab result importers
- Safety system data ingestors
- `LabResultImporter.php`
- `EHRIntegration.php`

### Shared Domain Exceptions & Interfaces
- `InvalidEncounterStateException.php`
- `PatientRepositoryInterface.php`
- `CaseNumberGeneratorInterface.php`

---

## These files SHOULD:

### 1. Enforce Core Rules and Invariants
```php
// ✅ CORRECT - Model enforces domain rules
class InjuryCase {
    public function markAsRecordable(): void {
        if (!$this->meetsOSHARecordabilityCriteria()) {
            throw new InvalidCaseStateException(
                "Case does not meet OSHA recordability criteria"
            );
        }
        $this->status = CaseStatus::RECORDABLE;
        $this->recordedAt = new DateTime();
    }
}
```

### 2. Own All Database Interaction
```php
// ✅ CORRECT - Repository handles all DB access
class PatientRepository {
    private PDO $pdo;
    
    public function findById(int $patientId): ?Patient {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM patients WHERE patient_id = :id"
        );
        $stmt->execute(['id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->mapRowToEntity($row) : null;
    }
}
```

### 3. Provide Clean API to ViewModel
```php
// ✅ CORRECT - Model exposes domain operations
class EncounterService {
    public function getOpenEncountersForPatient(int $patientId): array {
        // Implementation details hidden
    }
    
    public function createNewEncounter(
        int $patientId, 
        EncounterType $type,
        DateTime $visitDate
    ): Encounter {
        // Business rules applied here
    }
    
    public function isEncounterRecordable(Encounter $encounter): bool {
        // OSHA logic here
    }
}
```

### 4. Handle Domain-Oriented Transformations
- Normalize units, formats, and codes (ICD, CPT, OSHA categories, etc.)
- Aggregate data for reports (counts, trends, risk classifications)
- Convert between domain concepts (e.g., diagnosis codes → OSHA categories)

### 5. Implement Security Decisions Around Data
```php
// ✅ CORRECT - Model handles encryption of sensitive data
class PatientRepository {
    private EncryptionService $encryption;
    
    public function save(Patient $patient): void {
        $encryptedSSN = $this->encryption->encrypt($patient->getSSN());
        // Store encrypted data
    }
}
```

### 6. Core Authentication & Authorization
```php
// ✅ CORRECT - Model defines permission policies
class AuthorizationService {
    public function canUserEditEncounter(User $user, Encounter $encounter): bool {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
        
        if ($user->hasRole(Role::CLINICIAN)) {
            return $encounter->getClinicId() === $user->getClinicId();
        }
        
        return false;
    }
}
```

---

## These files SHOULD NEVER:

### ❌ Render HTML, Echo Templates, or Include View Files
```php
// ❌ WRONG - Model should never output HTML
class Patient {
    public function displayCard(): void {
        echo "<div class='patient-card'>";
        echo "<h3>{$this->name}</h3>";
        echo "</div>";
    }
}

// ✅ CORRECT - Model returns data, View renders it
class Patient {
    public function getFullName(): string {
        return trim("{$this->firstName} {$this->lastName}");
    }
}
```

### ❌ Output JSON Directly to the Client
```php
// ❌ WRONG - Model shouldn't handle response format
class PatientService {
    public function getPatientJson(int $id): void {
        $patient = $this->repository->findById($id);
        header('Content-Type: application/json');
        echo json_encode($patient);
    }
}

// ✅ CORRECT - Model returns data, controller/ViewModel handles format
class PatientService {
    public function getPatient(int $id): ?Patient {
        return $this->repository->findById($id);
    }
}
```

### ❌ Perform Routing or HTTP Redirects
```php
// ❌ WRONG - Model shouldn't redirect
class AuthService {
    public function login(string $username, string $password): void {
        if ($this->isValid($username, $password)) {
            header('Location: /dashboard');
            exit;
        }
    }
}

// ✅ CORRECT - Model returns result, caller handles redirect
class AuthService {
    public function authenticate(string $username, string $password): ?User {
        // Return User object if valid, null if not
    }
}
```

### ❌ Read from Request Globals
```php
// ❌ WRONG - Model shouldn't touch superglobals
class EncounterService {
    public function createFromPost(): Encounter {
        $patientId = $_POST['patient_id'];
        $date = $_POST['visit_date'];
        // ...
    }
}

// ✅ CORRECT - Accept sanitized parameters
class EncounterService {
    public function createEncounter(
        int $patientId,
        DateTime $visitDate,
        EncounterType $type
    ): Encounter {
        // ...
    }
}
```

### ❌ Know Anything About CSS, JS, or Specific Screens/Pages
```php
// ❌ WRONG - Model knows about UI details
class Patient {
    public function getBadgeColor(): string {
        return $this->hasOpenCases ? 'red' : 'green';
    }
}

// ✅ CORRECT - Model exposes domain state
class Patient {
    public function hasOpenCases(): bool {
        return count($this->openCases) > 0;
    }
}
```

### ❌ Depend on Directory Structure of `/view` or `/viewmodel`
```php
// ❌ WRONG - Model shouldn't include views
class ReportGenerator {
    public function generateReport(): void {
        $data = $this->getData();
        include __DIR__ . '/../view/reports/template.php';
    }
}

// ✅ CORRECT - Model returns data
class ReportGenerator {
    public function getReportData(): array {
        return [
            'summary' => $this->calculateSummary(),
            'details' => $this->getDetails()
        ];
    }
}
```

### ❌ Hard-Code UI Concerns
```php
// ❌ WRONG - Model shouldn't format for display
class Encounter {
    public function getDisplayDate(): string {
        return $this->visitDate->format('F j, Y'); // UI format
    }
}

// ✅ CORRECT - Model provides raw data
class Encounter {
    public function getVisitDate(): DateTime {
        return $this->visitDate;
    }
}
```

---

## Common Anti-Patterns to Avoid

### Anti-Pattern #1: Anemic Domain Model
```php
// ❌ WRONG - Just getters/setters, no behavior
class Patient {
    private string $name;
    private DateTime $dob;
    
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getDob(): DateTime { return $this->dob; }
    public function setDob(DateTime $dob): void { $this->dob = $dob; }
}

// ✅ CORRECT - Rich domain model with behavior
class Patient {
    private string $firstName;
    private string $lastName;
    private DateTime $dateOfBirth;
    
    public function getAge(): int {
        return $this->dateOfBirth->diff(new DateTime())->y;
    }
    
    public function isMinor(): bool {
        return $this->getAge() < 18;
    }
    
    public function getFullName(): string {
        return trim("{$this->firstName} {$this->lastName}");
    }
}
```

### Anti-Pattern #2: God Service Class
```php
// ❌ WRONG - One service doing everything
class PatientService {
    public function createPatient() { }
    public function updatePatient() { }
    public function deletePatient() { }
    public function searchPatients() { }
    public function generateReport() { }
    public function sendEmail() { }
    public function validateInsurance() { }
    // 50 more methods...
}

// ✅ CORRECT - Focused, single-responsibility services
class PatientRegistrationService { }
class PatientSearchService { }
class PatientReportingService { }
class InsuranceVerificationService { }
```

### Anti-Pattern #3: Leaking Repository Details
```php
// ❌ WRONG - Exposing PDO details
class PatientRepository {
    public function getConnection(): PDO {
        return $this->pdo; // Don't expose this!
    }
}

// ✅ CORRECT - Abstract interface
interface PatientRepositoryInterface {
    public function findById(int $id): ?Patient;
    public function save(Patient $patient): void;
    public function delete(int $id): void;
}
```

---

## Validation Strategy

### Domain Validation (Model)
Business rule validation that's always true:
```php
// ✅ In Model - Core business rule
class WorkRestriction {
    public function setLiftingLimit(int $pounds): void {
        if ($pounds < 0) {
            throw new InvalidArgumentException("Lifting limit cannot be negative");
        }
        if ($pounds > 500) {
            throw new InvalidArgumentException("Lifting limit exceeds maximum threshold");
        }
        $this->liftingLimit = $pounds;
    }
}
```

### Input Validation (ViewModel)
Format/structure validation for UI:
```php
// ✅ In ViewModel - Input format checking before passing to Model
class EncounterViewModel {
    public function validateInput(array $data): array {
        $errors = [];
        
        if (empty($data['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        }
        
        if (!empty($data['visit_date']) && !strtotime($data['visit_date'])) {
            $errors['visit_date'] = 'Invalid date format';
        }
        
        return $errors;
    }
}
```

**Key Principle:** If the validation is about **business rules**, it's in Model. If it's about **input format/structure**, it's in ViewModel.

---

## Quick Smell Tests

### Smell Test #1 – "Can I run this in a CLI?"
If you wrote a command-line script (cron job, worker process) and this code still makes complete sense → good.

If it breaks because it needs HTML, session variables, or the request context → you've leaked UI/web concerns into Model.

### Smell Test #2 – "Can I swap the database?"
If you could swap MySQL for PostgreSQL or even a file-based system by changing just the Repository implementation → good.

If business logic would need to change → you've leaked persistence details into domain logic.

### Smell Test #3 – "Can I test this without a web server?"
If you can write unit tests that instantiate these classes and test behavior without phpunit needing a server → good.

If tests need `$_POST`, `$_SESSION`, or HTTP mocking → you've coupled Model to web infrastructure.

### Smell Test #4 – "Do I see HTML, headers(), or includes?"
If yes → wrong layer. Move it out immediately.

---

## Relationship to ViewModel (MVVM Context)

```
View ──────> ViewModel ──────> Model
 │                                │
 └─ Renders data                  └─ Business logic & persistence
    provided by ViewModel            Returns domain objects
```

- **Model** exposes domain operations and data
- **ViewModel** calls into the Model to:
  - Fetch/update data
  - Apply domain rules
  - Then reshape results for the View
- **View** simply renders whatever the ViewModel hands it

**Dependency direction is one-way:** `View → ViewModel → Model`

Model should **never** call ViewModel or View. Model should **never** know they exist.

---

## Logging & Error Handling

### Model Should Log:
- ✅ Domain events (case classified as recordable)
- ✅ Data integrity issues (missing required relationships)
- ✅ Integration failures (external API errors)
- ✅ Security events (failed authorization checks)

### Model Should NOT Log:
- ❌ HTTP request details (IP addresses, user agents)
- ❌ View rendering issues
- ❌ Session management events

```php
// ✅ CORRECT - Domain event logging
class EncounterService {
    private LoggerInterface $logger;
    
    public function closeEncounter(Encounter $encounter): void {
        $encounter->close();
        $this->repository->save($encounter);
        
        $this->logger->info('Encounter closed', [
            'encounter_id' => $encounter->getId(),
            'patient_id' => $encounter->getPatientId(),
            'closed_by' => $encounter->getClosedBy(),
            'closure_reason' => $encounter->getClosureReason()
        ]);
    }
}
```

---

## Security Considerations

### Model SHOULD Handle:
- ✅ Data encryption/decryption
- ✅ Authorization policies ("Can this user edit this record?")
- ✅ Password hashing/verification
- ✅ Token generation and validation
- ✅ PHI access auditing

### Model SHOULD NOT Handle:
- ❌ CSRF token validation (infrastructure concern)
- ❌ Session management (infrastructure concern)
- ❌ Rate limiting (infrastructure concern)
- ❌ XSS prevention (View/output concern)

```php
// ✅ CORRECT - Model handles authorization policy
class EncounterAuthorizationService {
    public function canUserView(User $user, Encounter $encounter): bool {
        // Admin can view all
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
        
        // Clinician can view own clinic's encounters
        if ($user->hasRole(Role::CLINICIAN)) {
            return $encounter->getClinicId() === $user->getClinicId();
        }
        
        // Patient can view own encounters
        if ($user->hasRole(Role::PATIENT)) {
            return $encounter->getPatientId() === $user->getPatientId();
        }
        
        return false;
    }
}
```

---

## Summary

- **Model** = brains & rules (OSHA/DOT/business logic, persistence, encryption, integrations)
- **ViewModel** = coordinator & translator (request in → Model → UI-ready data out)
- **View** = dumb renderer (take data, show it)

If a Model class starts looking like a web controller or starts caring about HTML, CSS, or user sessions, it's time to rip that logic out and push it up to ViewModel or infrastructure layer.

**The Model should be the most stable, testable, and reusable part of your entire system.**