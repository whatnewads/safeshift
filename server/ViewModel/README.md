# ViewModel Layer (`/viewmodel`)

## Purpose

The **ViewModel** layer sits between **View** and **Model**.

It is the **orchestrator for a specific screen or workflow**, not a dumping ground for business logic.

Think of each ViewModel as:  
> "Everything this page needs, in a form that's safe and ready to render."

It talks to Models, shapes data, and handles UI-facing flow, but it does **not** become a second Model.

---

## PHP-Specific Context

Unlike reactive frameworks (React, Vue, Angular, WPF) that use **two-way data binding**, PHP ViewModels work differently:

### Classical MVVM (Reactive Frameworks)
```
View ⟷ ViewModel ⟷ Model
     (auto sync)    (commands)
```

### PHP MVVM (Request-Response)
```
Request → ViewModel → Model → Database
             ↓
          View ← Response
```

**Key Difference:** In PHP, ViewModel is a **request-scoped data preparer and coordinator**, not a reactive state manager. Each HTTP request creates fresh ViewModel instances that:
1. Accept sanitized input
2. Call Model layer
3. Transform results for View
4. Return data structures

This is an **adaptation** of MVVM for server-side, stateless environments. It's closer to **MVP (Model-View-Presenter)** with MVVM principles.

---

## Files that belong here

Typical examples:

- `LoginViewModel.php`
- `DashboardViewModel.php`
- `ClinicianViewModel.php`
- `PatientRecordViewModel.php`
- `SettingsViewModel.php`
- `EncounterFormViewModel.php`
- `ReportViewModel.php`

**Naming Convention:** One ViewModel per:
- Screen/page (e.g., `DashboardViewModel`)
- Major workflow (e.g., `NewInjuryCaseViewModel`, `ReturnToWorkEvaluationViewModel`)
- Complex form (e.g., `EncounterFormViewModel`)

Each ViewModel should represent **one** coherent UI context.

---

## These files SHOULD:

### 1. Receive Sanitized Input from HTTP/Request Layer

```php
// ✅ CORRECT - ViewModel receives clean input
class EncounterFormViewModel {
    private EncounterService $encounterService;
    
    public function handleSubmit(array $sanitizedInput): array {
        // $sanitizedInput already trimmed, type-cast, basic-validated
        $patientId = (int)$sanitizedInput['patient_id'];
        $visitDate = new DateTime($sanitizedInput['visit_date']);
        
        // Call Model
        $encounter = $this->encounterService->createEncounter(
            $patientId,
            $visitDate,
            EncounterType::from($sanitizedInput['type'])
        );
        
        return $this->prepareSuccessResponse($encounter);
    }
}

// ❌ WRONG - ViewModel touching raw superglobals
class EncounterFormViewModel {
    public function handleSubmit(): array {
        $patientId = $_POST['patient_id']; // NO!
        $visitDate = $_POST['visit_date']; // NO!
    }
}
```

**Key Principle:** ViewModel should **never** access `$_POST`, `$_GET`, `$_SESSION`, `$_SERVER`, or `$_FILES` directly. Those are handled by the infrastructure/routing layer before reaching ViewModel.

### 2. Call Model-Layer Code

```php
// ✅ CORRECT - ViewModel delegates to Model
class PatientDashboardViewModel {
    private PatientRepository $patientRepo;
    private EncounterService $encounterService;
    private AuthorizationService $authService;
    
    public function getDashboardData(int $userId, int $patientId): array {
        // Fetch entities
        $patient = $this->patientRepo->findById($patientId);
        $encounters = $this->encounterService->getOpenEncountersForPatient($patientId);
        
        // Check permissions via Model
        $user = $this->userRepo->findById($userId);
        $canEdit = $this->authService->canUserEditPatient($user, $patient);
        
        // Transform for View
        return $this->prepareViewData($patient, $encounters, $canEdit);
    }
}

// ❌ WRONG - ViewModel doing database queries
class PatientDashboardViewModel {
    private PDO $pdo;
    
    public function getDashboardData(int $patientId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        // NO! This belongs in Model layer
    }
}
```

### 3. Prepare Data Structures for the View

```php
// ✅ CORRECT - Transform domain objects into UI-ready format
class ClinicianViewModel {
    public function getClinicianList(): array {
        $clinicians = $this->clinicianService->getAllActiveClinicians();
        
        return array_map(function($clinician) {
            return [
                'id' => $clinician->getId(),
                'displayName' => $clinician->getFullName(),
                'licenseNumber' => $clinician->getLicenseNumber(),
                'specialties' => implode(', ', $clinician->getSpecialties()),
                'activePatientCount' => $clinician->getActivePatientCount(),
                'canAcceptNewPatients' => $clinician->canAcceptNewPatients(),
                'statusBadge' => $this->getStatusBadgeData($clinician)
            ];
        }, $clinicians);
    }
    
    private function getStatusBadgeData(Clinician $clinician): array {
        return [
            'text' => $clinician->isActive() ? 'Active' : 'Inactive',
            'color' => $clinician->isActive() ? 'success' : 'warning'
        ];
    }
}
```

**Transform patterns:**
- Flatten nested structures
- Add UI-specific flags (`canEdit`, `showWarning`, `isExpanded`)
- Format numbers and dates for display
- Map codes to human-readable labels
- Group/categorize data for rendering

### 4. Handle UI-Side Flow Logic

```php
// ✅ CORRECT - ViewModel manages UI flow
class EncounterFormViewModel {
    public function getFormData(int $encounterId = null): array {
        $messages = [];
        $encounter = null;
        $isEditMode = false;
        
        if ($encounterId) {
            $encounter = $this->encounterService->getEncounter($encounterId);
            $isEditMode = true;
            
            if ($encounter->isLocked()) {
                $messages[] = [
                    'type' => 'warning',
                    'text' => 'This encounter is locked for editing'
                ];
            }
        }
        
        return [
            'encounter' => $encounter ? $this->formatEncounter($encounter) : null,
            'isEditMode' => $isEditMode,
            'canSave' => $this->canUserSave(),
            'availableEncounterTypes' => $this->getEncounterTypeOptions(),
            'messages' => $messages,
            'formAction' => $isEditMode ? '/encounter/update' : '/encounter/create'
        ];
    }
}
```

**UI Flow Examples:**
- Deciding which messages to show (success/error/warning)
- Loading different data sets based on user role
- Handling filter/sort/pagination parameters
- Providing conditional flags for View (`showDeleteButton`, `isReadOnly`)
- Managing multi-step form state

### 5. Transform Model Output → Ready to Render

```php
// ✅ CORRECT - Format for display
class ReportViewModel {
    public function getCaseReportData(DateTime $startDate, DateTime $endDate): array {
        $cases = $this->caseService->getCasesInDateRange($startDate, $endDate);
        
        return [
            'reportTitle' => sprintf(
                'Injury Cases: %s to %s',
                $startDate->format('M j, Y'),
                $endDate->format('M j, Y')
            ),
            'totalCases' => count($cases),
            'recordableCases' => $this->countRecordable($cases),
            'casesByCategory' => $this->groupByCategory($cases),
            'summary' => $this->generateSummaryText($cases),
            'generatedAt' => (new DateTime())->format('F j, Y g:i A')
        ];
    }
    
    private function groupByCategory(array $cases): array {
        $grouped = [];
        foreach ($cases as $case) {
            $category = $case->getOSHACategory()->getLabel();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'label' => $category,
                    'count' => 0,
                    'cases' => []
                ];
            }
            $grouped[$category]['count']++;
            $grouped[$category]['cases'][] = [
                'caseNumber' => $case->getCaseNumber(),
                'patientName' => $case->getPatientName(),
                'injuryDate' => $case->getInjuryDate()->format('m/d/Y')
            ];
        }
        return array_values($grouped);
    }
}
```

**Transformation Examples:**
- Format dates: `DateTime → "November 24, 2025"`
- Format currency: `1234.56 → "$1,234.56"`
- Map codes to labels: `OSHA_CAT_1 → "Days Away From Work"`
- Calculate derived values: `birthDate → age`
- Group/aggregate data: Cases by month, patients by clinic

### 6. Coordinate Simple Workflows

```php
// ✅ CORRECT - Coordinate multi-step workflow
class NewCaseWorkflowViewModel {
    public function handleStep(int $step, array $stepData, array $previousData = []): array {
        $allData = array_merge($previousData, $stepData);
        
        switch ($step) {
            case 1:
                // Validate patient selection
                return $this->handlePatientSelection($stepData);
                
            case 2:
                // Validate injury details
                return $this->handleInjuryDetails($allData);
                
            case 3:
                // Finalize and save
                return $this->createCase($allData);
                
            default:
                return ['error' => 'Invalid step'];
        }
    }
    
    private function createCase(array $data): array {
        // Validate all accumulated data
        $errors = $this->validateCompleteCase($data);
        if (!empty($errors)) {
            return ['errors' => $errors, 'step' => 2]; // Go back
        }
        
        // Delegate to Model to create case
        $case = $this->caseService->createNewCase(
            $data['patient_id'],
            new DateTime($data['injury_date']),
            $data['injury_type'],
            $data['description']
        );
        
        return [
            'success' => true,
            'message' => 'Case created successfully',
            'caseId' => $case->getId(),
            'redirect' => '/case/view/' . $case->getId()
        ];
    }
}
```

**Important:** "Coordinate workflows" means **UI-level flow**, not business logic. If the workflow involves domain rules (like "can only close case if all forms completed"), that logic lives in Model.

### 7. Enforce Light, UI-Adjacent Validation

```php
// ✅ CORRECT - Input format validation
class EncounterFormViewModel {
    public function validateInput(array $data): array {
        $errors = [];
        
        // Required fields for this form
        if (empty($data['patient_id'])) {
            $errors['patient_id'] = 'Please select a patient';
        }
        
        // Format checks
        if (!empty($data['visit_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['visit_date'])) {
                $errors['visit_date'] = 'Date must be in YYYY-MM-DD format';
            }
        }
        
        // Length checks
        if (!empty($data['chief_complaint']) && strlen($data['chief_complaint']) > 500) {
            $errors['chief_complaint'] = 'Chief complaint must be 500 characters or less';
        }
        
        return $errors;
    }
}

// ❌ WRONG - Business rule validation in ViewModel
class EncounterFormViewModel {
    public function validateInput(array $data): array {
        $errors = [];
        
        // This is business logic! Belongs in Model
        if ($data['injury_type'] === 'NEEDLE_STICK' && empty($data['exposure_date'])) {
            $errors['exposure_date'] = 'Exposure date required for needle stick injuries';
        }
        
        return $errors;
    }
}
```

**Validation Split:**
- **ViewModel:** Format, length, required-for-this-form, type checking
- **Model:** Business rules, domain constraints, relationships

If the validation is still true in a CLI script or API endpoint, it belongs in Model.

### 8. Convert Model Errors to View-Friendly Format

```php
// ✅ CORRECT - Translate Model exceptions to user messages
class EncounterFormViewModel {
    public function saveEncounter(array $data): array {
        try {
            $encounter = $this->encounterService->createEncounter(
                $data['patient_id'],
                new DateTime($data['visit_date']),
                EncounterType::from($data['type'])
            );
            
            return [
                'success' => true,
                'message' => 'Encounter saved successfully',
                'encounterId' => $encounter->getId()
            ];
            
        } catch (InvalidEncounterStateException $e) {
            return [
                'success' => false,
                'errors' => ['general' => 'Unable to create encounter: ' . $e->getMessage()]
            ];
            
        } catch (PatientNotFoundException $e) {
            return [
                'success' => false,
                'errors' => ['patient_id' => 'Selected patient not found']
            ];
            
        } catch (Exception $e) {
            // Log unexpected errors
            error_log("Unexpected error in EncounterFormViewModel: " . $e->getMessage());
            
            return [
                'success' => false,
                'errors' => ['general' => 'An unexpected error occurred. Please try again.']
            ];
        }
    }
}
```

---

## These files SHOULD NEVER:

### ❌ Query the Database Directly

```php
// ❌ WRONG - ViewModel with database access
class PatientViewModel {
    private PDO $pdo;
    
    public function getPatients(): array {
        $stmt = $this->pdo->query("SELECT * FROM patients");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ✅ CORRECT - Use Model layer
class PatientViewModel {
    private PatientRepository $patientRepo;
    
    public function getPatients(): array {
        $patients = $this->patientRepo->findAll();
        return $this->formatPatientsForView($patients);
    }
}
```

### ❌ Implement Core Business Rules

```php
// ❌ WRONG - Business logic in ViewModel
class CaseViewModel {
    public function isRecordable(array $caseData): bool {
        // This is OSHA business logic!
        if ($caseData['days_away'] >= 1) {
            return true;
        }
        if ($caseData['restricted_work_days'] >= 1) {
            return true;
        }
        if ($caseData['required_medical_treatment']) {
            return true;
        }
        return false;
    }
}

// ✅ CORRECT - Delegate to Model
class CaseViewModel {
    private OschaCaseClassifier $caseClassifier;
    
    public function getCaseData(int $caseId): array {
        $case = $this->caseService->getCase($caseId);
        
        // Model determines recordability
        $isRecordable = $this->caseClassifier->isRecordable($case);
        
        return [
            'caseNumber' => $case->getCaseNumber(),
            'isRecordable' => $isRecordable,
            'recordabilityBadge' => $this->getBadgeData($isRecordable)
        ];
    }
}
```

### ❌ Perform Core Authentication/Authorization

```php
// ❌ WRONG - Core auth logic in ViewModel
class EncounterViewModel {
    public function canUserEdit(int $userId, int $encounterId): bool {
        // This policy decision belongs in Model
        $user = $this->getUser($userId);
        $encounter = $this->getEncounter($encounterId);
        
        if ($user['role'] === 'admin') return true;
        if ($user['clinic_id'] === $encounter['clinic_id']) return true;
        return false;
    }
}

// ✅ CORRECT - Use Model authorization service
class EncounterViewModel {
    private AuthorizationService $authService;
    
    public function getEncounterData(int $userId, int $encounterId): array {
        $user = $this->userRepo->findById($userId);
        $encounter = $this->encounterService->getEncounter($encounterId);
        
        // Model decides authorization
        $canEdit = $this->authService->canUserEditEncounter($user, $encounter);
        
        return [
            'encounter' => $this->formatEncounter($encounter),
            'canEdit' => $canEdit,  // ViewModel exposes flag for UI
            'showEditButton' => $canEdit // UI-specific decision
        ];
    }
}
```

**Note:** ViewModel can *expose* permission flags for UI purposes (`canEdit`, `showDeleteButton`), but the **policy decision** is made by Model.

### ❌ Perform Routing or HTTP Behavior

```php
// ❌ WRONG - ViewModel doing redirects
class LoginViewModel {
    public function handleLogin(string $username, string $password): void {
        $user = $this->authService->authenticate($username, $password);
        if ($user) {
            header('Location: /dashboard');
            exit;
        }
    }
}

// ✅ CORRECT - Return data, let controller handle redirect
class LoginViewModel {
    public function handleLogin(string $username, string $password): array {
        $user = $this->authService->authenticate($username, $password);
        
        if ($user) {
            return [
                'success' => true,
                'redirectTo' => '/dashboard',
                'user' => $this->formatUserData($user)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Invalid username or password'
        ];
    }
}
```

### ❌ Render HTML or Include View Files

```php
// ❌ WRONG - ViewModel rendering HTML
class ReportViewModel {
    public function generateReport(): void {
        $data = $this->getReportData();
        include __DIR__ . '/../view/reports/template.php';
    }
}

// ✅ CORRECT - Return data, View renders it
class ReportViewModel {
    public function getReportData(): array {
        return [
            'title' => 'Monthly Safety Report',
            'sections' => $this->buildReportSections(),
            'generatedAt' => (new DateTime())->format('F j, Y')
        ];
    }
}
```

### ❌ Send Emails, SMS, or Call External APIs

```php
// ❌ WRONG - ViewModel sending email
class CaseViewModel {
    public function notifyManager(int $caseId): void {
        $case = $this->caseService->getCase($caseId);
        mail($case->getManagerEmail(), 'New Case', '...');
    }
}

// ✅ CORRECT - Delegate to Model/infrastructure
class CaseViewModel {
    private NotificationService $notificationService;
    
    public function createCase(array $data): array {
        $case = $this->caseService->createNewCase(/* ... */);
        
        // Model handles notification
        $this->notificationService->notifyManagerOfNewCase($case);
        
        return [
            'success' => true,
            'caseId' => $case->getId()
        ];
    }
}
```

### ❌ Handle Encryption, Keys, or Low-Level Security

```php
// ❌ WRONG - ViewModel doing encryption
class PatientViewModel {
    public function savePatient(array $data): void {
        $encryptedSSN = openssl_encrypt($data['ssn'], 'AES-256-CBC', $key, 0, $iv);
        // NO! This belongs in Model
    }
}

// ✅ CORRECT - Model handles encryption
class PatientViewModel {
    public function savePatient(array $data): array {
        // Model's PatientRepository handles encryption internally
        $patient = $this->patientService->createPatient(
            $data['first_name'],
            $data['last_name'],
            $data['ssn'] // Passed as plain text to Model, which encrypts it
        );
        
        return [
            'success' => true,
            'patientId' => $patient->getId()
        ];
    }
}
```

### ❌ Parse Heavy Data Formats

```php
// ❌ WRONG - ViewModel parsing PDF
class DocumentViewModel {
    public function processPDF(string $filepath): array {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filepath);
        $text = $pdf->getText();
        // Complex parsing logic...
    }
}

// ✅ CORRECT - Model handles parsing
class DocumentViewModel {
    private DocumentParsingService $parsingService;
    
    public function getDocumentData(int $documentId): array {
        // Model does the heavy lifting
        $document = $this->parsingService->parseDocument($documentId);
        
        return [
            'documentId' => $document->getId(),
            'title' => $document->getTitle(),
            'extractedText' => $document->getExtractedText(),
            'metadata' => $document->getMetadata()
        ];
    }
}
```

---

## Common Anti-Patterns to Avoid

### Anti-Pattern #1: God ViewModel
```php
// ❌ WRONG - One ViewModel doing everything
class ApplicationViewModel {
    public function handleLogin() { }
    public function handleLogout() { }
    public function getDashboard() { }
    public function getPatientList() { }
    public function createEncounter() { }
    public function generateReport() { }
    // 100 more methods...
}

// ✅ CORRECT - Focused ViewModels
class LoginViewModel { }
class DashboardViewModel { }
class PatientListViewModel { }
class EncounterFormViewModel { }
class ReportViewModel { }
```

### Anti-Pattern #2: Duplicate Business Logic
```php
// ❌ WRONG - Replicating Model logic
class EncounterViewModel {
    // This logic also exists in Model!
    public function calculateRestrictedDays(array $restrictions): int {
        $totalDays = 0;
        foreach ($restrictions as $restriction) {
            $start = new DateTime($restriction['start_date']);
            $end = new DateTime($restriction['end_date']);
            $totalDays += $start->diff($end)->days;
        }
        return $totalDays;
    }
}

// ✅ CORRECT - Call Model
class EncounterViewModel {
    public function getEncounterSummary(int $encounterId): array {
        $encounter = $this->encounterService->getEncounter($encounterId);
        
        // Model calculates
        $restrictedDays = $encounter->getTotalRestrictedWorkDays();
        
        return [
            'encounterId' => $encounter->getId(),
            'restrictedDays' => $restrictedDays,
            'isRecordable' => $restrictedDays > 0
        ];
    }
}
```

### Anti-Pattern #3: Session Management in ViewModel
```php
// ❌ WRONG - ViewModel managing session
class DashboardViewModel {
    public function loadUserPreferences(): array {
        if (!isset($_SESSION['user_prefs'])) {
            $_SESSION['user_prefs'] = $this->getDefaultPrefs();
        }
        return $_SESSION['user_prefs'];
    }
}

// ✅ CORRECT - Accept session data as input
class DashboardViewModel {
    public function getDashboardData(int $userId, array $userPreferences = []): array {
        // Session already loaded by infrastructure layer
        $defaultView = $userPreferences['default_view'] ?? 'summary';
        
        return [
            'currentView' => $defaultView,
            'widgets' => $this->getWidgetsForView($defaultView)
        ];
    }
}
```

---

## Smell Tests for ViewModel

### Smell Test #1 – "Can I swap the View?"
If you swapped HTML for JSON API responses, CLI output, or mobile app and the ViewModel still made sense → good.

If it breaks because it's glued to specific HTML/DOM details → you've leaked View into ViewModel.

### Smell Test #2 – "Can the Model survive without this?"
If you deleted the ViewModel and the system still *knows what's legally/compliantly correct* → good.

If deleting ViewModel changes what's "allowed" or "valid" from a domain perspective → you've leaked Model into ViewModel.

### Smell Test #3 – "Do I see database queries, HTML, or business rules?"
- `SELECT`, `INSERT`, `PDO` → Wrong, move to Model
- `<div>`, `echo`, `include 'view/...'` → Wrong, move to View  
- OSHA logic, DOT rules, complex domain calculations → Wrong, move to Model

### Smell Test #4 – "Is this ViewModel > 500 lines?"
If yes, it's probably doing too much. Split it into:
- Multiple focused ViewModels (one per screen/workflow)
- Helper classes for complex transformations
- Delegate more to Model

---

## When ViewModel Gets Too Big

**Warning Signs:**
- File exceeds 500 lines
- More than 20 public methods
- Complex nested conditionals
- Duplicating logic from other ViewModels
- Methods with 5+ parameters

**Solutions:**
1. **Split by workflow:** `NewCaseViewModel` + `EditCaseViewModel` instead of `CaseViewModel`
2. **Extract transformers:** `CaseDataTransformer`, `EncounterFormatter`
3. **Push logic down:** If it's domain logic, move to Model
4. **Create helper services:** `DateFormatter`, `StatusBadgeGenerator`

---

## Logging in ViewModel

### ViewModel SHOULD Log:
- ✅ User actions (form submissions, button clicks)
- ✅ Validation failures from user input
- ✅ Unexpected Model exceptions
- ✅ Data transformation errors

### ViewModel SHOULD NOT Log:
- ❌ Business rule violations (Model logs those)
- ❌ Database query performance (Model/infrastructure logs that)
- ❌ Low-level security events (infrastructure logs those)

```php
// ✅ CORRECT - ViewModel logging
class EncounterFormViewModel {
    private LoggerInterface $logger;
    
    public function handleSubmit(int $userId, array $data): array {
        $this->logger->info('User submitted encounter form', [
            'user_id' => $userId,
            'patient_id' => $data['patient_id'],
            'encounter_type' => $data['type']
        ]);
        
        try {
            $encounter = $this->encounterService->createEncounter(/* ... */);
            
            $this->logger->info('Encounter created successfully', [
                'encounter_id' => $encounter->getId(),
                'user_id' => $userId
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create encounter', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['success' => false, 'error' => 'Unable to save encounter'];
        }
    }
}
```

---

## Relationship to Other Layers

```
┌──────────────────────────────────────────────┐
│  HTTP Request (Router/Controller)            │
│  - Handles routing                           │
│  - Validates CSRF tokens                     │
│  - Sanitizes input                           │
│  - Manages sessions                          │
└─────────────┬────────────────────────────────┘
              │ Sanitized Input
              ↓
┌──────────────────────────────────────────────┐
│  ViewModel                                   │
│  - Validates input format                    │
│  - Calls Model operations                    │
│  - Transforms data for View                  │
│  - Handles UI flow logic                     │
└─────────────┬────────────────────────────────┘
              │ Domain Operations
              ↓
┌──────────────────────────────────────────────┐
│  Model                                       │
│  - Business rules                            │
│  - Data persistence                          │
│  - Domain logic                              │
│  - Authorization policies                    │
└─────────────┬────────────────────────────────┘
              │ Raw Data
              ↓
┌──────────────────────────────────────────────┐
│  Database                                    │
└──────────────────────────────────────────────┘

              Data Flow Back Up
              
Database → Model → ViewModel → View → HTML Response
```

**Key Points:**
- ViewModel **never** calls View
- ViewModel **never** accesses Database
- ViewModel acts as a **translator** between Model (domain) and View (UI)

---

## Summary

- **Model** = brains & rules (business logic, persistence)
- **ViewModel** = coordinator & translator (request in → Model → UI-ready data out)
- **View** = dumb renderer (take data, show it)

If a ViewModel class starts looking like a mini framework, a second Model, or a web controller, it's time to refactor:
- Push business logic **down** to Model
- Push rendering logic **up** to View
- Keep ViewModel focused on **coordination and transformation**

**ViewModel is the thinnest layer — if it's the fattest, something's wrong.**