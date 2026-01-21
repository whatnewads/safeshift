# Saved Reports Dropdown & Patient Timeline Architecture

**Version:** 1.0  
**Date:** January 2026  
**Author:** Architecture Team  
**Status:** Design Document

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Feature 1: Saved Reports Dropdown](#feature-1-saved-reports-dropdown)
3. [Feature 2: Patient Timeline View](#feature-2-patient-timeline-view)
4. [Database Schema Changes](#database-schema-changes)
5. [API Endpoints](#api-endpoints)
6. [Frontend Component Structure](#frontend-component-structure)
7. [State Management](#state-management)
8. [UI/UX Wireframes](#uiux-wireframes)
9. [Implementation Order](#implementation-order)

---

## Executive Summary

This document outlines the technical architecture for two new features in the SafeShift EHR system:

1. **Saved Reports Dropdown** - A dropdown menu in the header (WiFi icon area) that lists all saved draft reports for the current user, allowing quick access to resume editing.

2. **Patient Timeline View** - A visual timeline interface accessible from the Patients sidebar link, showing a chronological view of all encounters for a selected patient with read-only access to historical reports.

---

## Feature 1: Saved Reports Dropdown

### Overview

Transform the WiFi status indicator area in the header into a dropdown menu that displays saved draft reports while maintaining the connection status functionality.

### Requirements Analysis

**Current Implementation:**
- WiFi icon in [`DashboardLayout.tsx`](../client/src/app/components/layout/DashboardLayout.tsx:396-420)
- Uses Lucide icons (`Wifi`, `WifiOff`)
- Shows connection status and pending sync count
- Located in header right-side actions area

**Draft Detection Logic:**
- Existing status enum: `planned`, `arrived`, `in_progress`, `completed`, `cancelled`, `voided`
- `draft` maps to `in_progress` status (see [`EncounterViewModel.php`](../server/ViewModel/EncounterViewModel.php:1167-1200))
- Reports are draft/saved when:
  - `status = 'in_progress'` AND
  - `npi_provider = {current_user_id}` AND
  - NOT submitted for review

### Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        User clicks dropdown                      │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Frontend: SavedReportsDropdown component                       │
│  - Calls useSavedReports hook                                   │
│  - Displays loading state                                       │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  API: GET /api/encounters/drafts                                │
│  - Auth middleware validates user                               │
│  - EncounterViewModel::getMyDraftEncounters                     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Database Query:                                                │
│  SELECT e.*, p.legal_first_name, p.legal_last_name              │
│  FROM encounters e                                              │
│  LEFT JOIN patients p ON e.patient_id = p.patient_id            │
│  WHERE e.npi_provider = :user_id                                │
│    AND e.status = 'in_progress'                                │
│  ORDER BY e.modified_at DESC                                    │
│  LIMIT 10                                                       │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Response: Array of draft encounters with patient names         │
│  - encounter_id, patient_name, created_at, modified_at          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Feature 2: Patient Timeline View

### Overview

Create a visual timeline interface showing all encounters for a specific patient, with the ability to view historical reports in read-only mode.

### Requirements Analysis

**Current Implementation:**
- Patients link in sidebar at [`DashboardLayout.tsx`](../client/src/app/components/layout/DashboardLayout.tsx:170)
- Currently navigates to `/patients` route
- Patient data model in [`PatientViewModel.php`](../server/ViewModel/PatientViewModel.php)

**Timeline Requirements:**
- Horizontal timeline from first report date to current date
- Dots representing each encounter
- Click dot to open report in READ-ONLY mode
- Clean, modern UI

### Timeline Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  User navigates to /patients                                    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  PatientListPage                                                │
│  - Displays searchable patient list                             │
│  - User selects a patient                                       │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Navigate to /patients/:patientId/timeline                      │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  PatientTimelinePage                                            │
│  - Calls usePatientTimeline hook                                │
│  - Renders TimelineComponent                                    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  API: GET /api/patients/:patientId/timeline                     │
│  - Returns all encounters for patient                           │
│  - Sorted by date                                               │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  User clicks timeline dot                                       │
│  - Navigate to /encounters/:encounterId?mode=readonly           │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema Changes

### Analysis: No Schema Changes Required

After analyzing the existing schema, **no new database tables or columns are required** for either feature. The existing `encounters` table already supports all needed functionality:

**Existing Schema (relevant columns):**
```sql
-- encounters table (already exists)
CREATE TABLE encounters (
    encounter_id CHAR(36) PRIMARY KEY,
    patient_id CHAR(36) NOT NULL,
    npi_provider VARCHAR(255),           -- Provider user ID
    status ENUM('planned','arrived','in_progress','completed','cancelled','voided'),
    occurred_on DATETIME,                 -- Encounter date
    created_at DATETIME,
    modified_at DATETIME,
    -- ... other columns
    FOREIGN KEY (patient_id) REFERENCES patients(patient_uuid)
);
```

**Status Mapping for Drafts:**
| Frontend Status | Database Status | Description |
|----------------|-----------------|-------------|
| `draft` | `in_progress` | Saved but not submitted |
| `submitted` | `completed` | Submitted for review |
| `pending_review` | `completed` + review flag | Under QA review |

### Optional Enhancement: Add Index for Performance

```sql
-- Recommended: Add composite index for draft queries
CREATE INDEX idx_encounters_provider_status_modified 
ON encounters (npi_provider, status, modified_at DESC);

-- Recommended: Add composite index for patient timeline queries
CREATE INDEX idx_encounters_patient_occurred 
ON encounters (patient_id, occurred_on DESC);
```

---

## API Endpoints

### Feature 1: Saved Reports Dropdown

#### GET /api/encounters/drafts

**Purpose:** Retrieve all draft encounters for the current user

**Request:**
```http
GET /api/encounters/drafts HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | integer | 10 | Maximum number of drafts to return |

**Response:**
```json
{
  "success": true,
  "data": {
    "drafts": [
      {
        "encounter_id": "abc-123-def",
        "patient_id": "pat-456-ghi",
        "patient_name": "John Doe",
        "patient_first_name": "John",
        "patient_last_name": "Doe",
        "created_at": "2026-01-20T14:30:00Z",
        "modified_at": "2026-01-21T09:15:00Z",
        "encounter_type": "clinic",
        "chief_complaint": "Annual checkup"
      },
      {
        "encounter_id": "jkl-789-mno",
        "patient_id": null,
        "patient_name": "New Patient",
        "patient_first_name": null,
        "patient_last_name": null,
        "created_at": "2026-01-21T10:00:00Z",
        "modified_at": "2026-01-21T10:00:00Z",
        "encounter_type": "clinic",
        "chief_complaint": null
      }
    ],
    "count": 2
  }
}
```

**Backend Implementation:**

Add to [`EncounterViewModel.php`](../server/ViewModel/EncounterViewModel.php):

```php
/**
 * Get draft (in_progress) encounters for the current user
 * 
 * @param int $limit Maximum number of drafts to return
 * @return array API response with draft encounters
 */
public function getMyDraftEncounters(int $limit = 10): array
{
    if (!$this->currentUserId) {
        return ApiResponse::unauthorized('User not authenticated');
    }

    try {
        $drafts = $this->encounterRepository->findDraftsForProvider(
            $this->currentUserId, 
            $limit
        );

        $draftData = array_map(
            fn($encounter) => $this->formatDraftForDropdown($encounter),
            $drafts
        );

        return ApiResponse::success([
            'drafts' => $draftData,
            'count' => count($draftData),
        ]);
    } catch (\Exception $e) {
        error_log("EncounterViewModel::getMyDraftEncounters error: " . $e->getMessage());
        return ApiResponse::serverError('Failed to retrieve draft encounters', $e);
    }
}

private function formatDraftForDropdown(Encounter $encounter): array
{
    // Get patient info if available
    $patient = null;
    if ($encounter->getPatientId()) {
        $patient = $this->patientRepository->findById($encounter->getPatientId());
    }

    return [
        'encounter_id' => $encounter->getId(),
        'patient_id' => $encounter->getPatientId(),
        'patient_name' => $patient 
            ? $patient->getFullName() 
            : 'New Patient',
        'patient_first_name' => $patient?->getFirstName(),
        'patient_last_name' => $patient?->getLastName(),
        'created_at' => $encounter->getCreatedAt()?->format('Y-m-d\TH:i:s.000\Z'),
        'modified_at' => $encounter->getUpdatedAt()?->format('Y-m-d\TH:i:s.000\Z'),
        'encounter_type' => $encounter->getEncounterType(),
        'chief_complaint' => $encounter->getChiefComplaint(),
    ];
}
```

Add to [`EncounterRepository.php`](../server/model/Repositories/EncounterRepository.php):

```php
/**
 * Find draft encounters for a provider
 * Draft = status is 'in_progress'
 */
public function findDraftsForProvider(string $providerId, int $limit = 10): array
{
    $sql = "SELECT * FROM {$this->table}
            WHERE npi_provider = :provider_id
            AND status = 'in_progress'
            ORDER BY modified_at DESC
            LIMIT :limit";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':provider_id', $providerId);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $encounters = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $encounters[] = $this->hydrateEncounter($row);
    }

    return $encounters;
}
```

---

### Feature 2: Patient Timeline

#### GET /api/patients/:patientId/timeline

**Purpose:** Retrieve timeline data for all encounters of a patient

**Request:**
```http
GET /api/patients/abc-123-def/timeline HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json
```

**Response:**
```json
{
  "success": true,
  "data": {
    "patient": {
      "patient_id": "abc-123-def",
      "full_name": "John Doe",
      "date_of_birth": "1985-03-15",
      "mrn": "MRN-2024-0156"
    },
    "timeline": {
      "first_encounter_date": "2024-06-15",
      "last_encounter_date": "2026-01-20",
      "total_encounters": 8
    },
    "encounters": [
      {
        "encounter_id": "enc-001",
        "date": "2024-06-15T10:30:00Z",
        "encounter_type": "clinic",
        "status": "completed",
        "chief_complaint": "Initial visit - onboarding physical",
        "provider_name": "Dr. Smith"
      },
      {
        "encounter_id": "enc-002",
        "date": "2024-09-22T14:15:00Z",
        "encounter_type": "clinic",
        "status": "completed",
        "chief_complaint": "Follow-up visit",
        "provider_name": "Dr. Jones"
      }
    ]
  }
}
```

**Backend Implementation:**

Add to [`PatientViewModel.php`](../server/ViewModel/PatientViewModel.php):

```php
/**
 * Get patient timeline with all encounters
 *
 * @param string $patientId Patient UUID
 * @return array API response with timeline data
 */
public function getPatientTimeline(string $patientId): array
{
    try {
        // Get patient
        $patient = $this->repository->findById($patientId);
        if (!$patient) {
            return ApiResponse::error('Patient not found', 404);
        }

        // Log PHI access for HIPAA compliance
        $this->logRead(
            resourceType: 'patient_timeline',
            resourceId: $patientId,
            patientId: $patientId,
            description: "Accessed patient timeline: {$patient->getFullName()}"
        );

        // Get all encounters for patient (using EncounterRepository)
        $encounterRepo = new EncounterRepository($this->getPdo());
        $encounters = $encounterRepo->findByPatientId($patientId, 1000);

        // Build timeline data
        $timelineEncounters = [];
        $firstDate = null;
        $lastDate = null;

        foreach ($encounters as $encounter) {
            $encounterDate = $encounter->getEncounterDate();
            
            if ($encounterDate) {
                if (!$firstDate || $encounterDate < $firstDate) {
                    $firstDate = $encounterDate;
                }
                if (!$lastDate || $encounterDate > $lastDate) {
                    $lastDate = $encounterDate;
                }
            }

            $timelineEncounters[] = [
                'encounter_id' => $encounter->getId(),
                'date' => $encounterDate?->format('Y-m-d\TH:i:s.000\Z'),
                'encounter_type' => $encounter->getEncounterType(),
                'status' => $encounter->getStatus(),
                'chief_complaint' => $encounter->getChiefComplaint(),
                'provider_name' => $this->getProviderName($encounter->getProviderId()),
            ];
        }

        // Sort by date ascending for timeline display
        usort($timelineEncounters, function($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        return ApiResponse::success([
            'patient' => [
                'patient_id' => $patient->getId(),
                'full_name' => $patient->getFullName(),
                'date_of_birth' => $patient->getDateOfBirth()->format('Y-m-d'),
                'mrn' => $patient->getMrn(),
            ],
            'timeline' => [
                'first_encounter_date' => $firstDate?->format('Y-m-d'),
                'last_encounter_date' => $lastDate?->format('Y-m-d'),
                'total_encounters' => count($timelineEncounters),
            ],
            'encounters' => $timelineEncounters,
        ]);
    } catch (\Exception $e) {
        error_log("PatientViewModel::getPatientTimeline error: " . $e->getMessage());
        return ApiResponse::error('Failed to retrieve patient timeline', 500);
    }
}
```

---

## Frontend Component Structure

### Feature 1: Saved Reports Dropdown

#### Component Hierarchy

```
DashboardLayout
└── Header
    └── SavedReportsDropdown
        ├── DropdownTrigger (WiFi icon + badge)
        ├── ConnectionStatus (inline indicator)
        └── DropdownContent
            ├── SavedReportsList
            │   └── SavedReportItem (multiple)
            └── EmptyState (if no drafts)
```

#### Component: SavedReportsDropdown

**File:** `client/src/app/components/header/SavedReportsDropdown.tsx`

```typescript
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Wifi, WifiOff, FileText, Clock, Loader2 } from 'lucide-react';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { 
  SimpleDropdown, 
  SimpleDropdownItem, 
  SimpleDropdownLabel, 
  SimpleDropdownSeparator 
} from '../ui/simple-dropdown';
import { useSavedReports } from '../../hooks/useSavedReports';
import { useSync } from '../../contexts/SyncContext';

interface SavedReportsDropdownProps {
  connectionStatus: 'connected' | 'disconnected' | 'reconnecting';
  onReconnect: () => void;
}

export function SavedReportsDropdown({ 
  connectionStatus, 
  onReconnect 
}: SavedReportsDropdownProps) {
  const navigate = useNavigate();
  const { drafts, loading, error, refetch } = useSavedReports();
  const { pendingCount, isSyncing, triggerSync } = useSync();

  const handleDraftClick = (encounterId: string) => {
    navigate(`/encounters/${encounterId}`);
  };

  const getWifiIcon = () => {
    switch (connectionStatus) {
      case 'connected':
        return <Wifi className="h-4 w-4 text-green-600" />;
      case 'disconnected':
        return <WifiOff className="h-4 w-4 text-red-600" />;
      case 'reconnecting':
        return <Wifi className="h-4 w-4 text-yellow-500 animate-pulse" />;
    }
  };

  const formatTimeAgo = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${diffDays}d ago`;
  };

  return (
    <SimpleDropdown
      align="end"
      trigger={
        <Button
          variant="ghost"
          size="sm"
          className="gap-2"
          title={connectionStatus === 'connected' 
            ? 'Connected - Click to view saved reports' 
            : 'Offline - Click to reconnect'}
        >
          {getWifiIcon()}
          {(drafts.length > 0 || pendingCount > 0) && (
            <Badge variant="secondary" className="ml-1">
              {drafts.length + pendingCount}
            </Badge>
          )}
          {(isSyncing || connectionStatus === 'reconnecting') && (
            <Loader2 className="h-3 w-3 animate-spin" />
          )}
        </Button>
      }
      className="w-80"
    >
      {/* Connection Status Section */}
      <div className="px-3 py-2 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium">Connection Status</span>
          <div className="flex items-center gap-2">
            {getWifiIcon()}
            <span className="text-xs text-slate-500">
              {connectionStatus === 'connected' ? 'Online' : 
               connectionStatus === 'reconnecting' ? 'Connecting...' : 'Offline'}
            </span>
          </div>
        </div>
        {connectionStatus === 'disconnected' && (
          <Button
            variant="link"
            size="sm"
            onClick={onReconnect}
            className="text-blue-600 p-0 h-auto text-xs"
          >
            Attempt reconnect
          </Button>
        )}
      </div>

      <SimpleDropdownSeparator />
      
      {/* Saved Reports Section */}
      <SimpleDropdownLabel>Saved Reports</SimpleDropdownLabel>
      
      {loading ? (
        <div className="px-3 py-4 text-center">
          <Loader2 className="h-5 w-5 animate-spin mx-auto text-slate-400" />
          <p className="text-xs text-slate-500 mt-2">Loading drafts...</p>
        </div>
      ) : error ? (
        <div className="px-3 py-4 text-center">
          <p className="text-xs text-red-500">{error}</p>
          <Button
            variant="link"
            size="sm"
            onClick={refetch}
            className="text-blue-600 p-0 h-auto text-xs mt-1"
          >
            Retry
          </Button>
        </div>
      ) : drafts.length === 0 ? (
        <div className="px-3 py-4 text-center">
          <FileText className="h-8 w-8 text-slate-300 mx-auto" />
          <p className="text-xs text-slate-500 mt-2">No saved reports</p>
        </div>
      ) : (
        drafts.map((draft) => (
          <SimpleDropdownItem
            key={draft.encounter_id}
            onClick={() => handleDraftClick(draft.encounter_id)}
            className="flex flex-col items-start py-3"
          >
            <div className="flex items-center gap-2 w-full">
              <FileText className="h-4 w-4 text-blue-600 flex-shrink-0" />
              <span className="font-medium truncate flex-1">
                {draft.patient_name}
              </span>
            </div>
            <div className="flex items-center gap-2 mt-1 text-xs text-slate-500 pl-6">
              <Clock className="h-3 w-3" />
              <span>{formatTimeAgo(draft.modified_at)}</span>
              {draft.chief_complaint && (
                <>
                  <span>•</span>
                  <span className="truncate max-w-32">{draft.chief_complaint}</span>
                </>
              )}
            </div>
          </SimpleDropdownItem>
        ))
      )}

      {drafts.length > 0 && (
        <>
          <SimpleDropdownSeparator />
          <SimpleDropdownItem
            onClick={() => navigate('/dashboard')}
            className="text-center text-blue-600"
          >
            View all on Dashboard
          </SimpleDropdownItem>
        </>
      )}
    </SimpleDropdown>
  );
}
```

#### Hook: useSavedReports

**File:** `client/src/app/hooks/useSavedReports.ts`

```typescript
import { useState, useEffect, useCallback } from 'react';
import { encounterService } from '../services/encounter.service';

interface SavedReport {
  encounter_id: string;
  patient_id: string | null;
  patient_name: string;
  patient_first_name: string | null;
  patient_last_name: string | null;
  created_at: string;
  modified_at: string;
  encounter_type: string;
  chief_complaint: string | null;
}

interface UseSavedReportsReturn {
  drafts: SavedReport[];
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

export function useSavedReports(autoRefresh = true): UseSavedReportsReturn {
  const [drafts, setDrafts] = useState<SavedReport[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDrafts = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await encounterService.getMyDrafts();
      
      if (response.success && response.data?.drafts) {
        setDrafts(response.data.drafts);
      } else {
        setError(response.message || 'Failed to load drafts');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load drafts');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDrafts();

    // Auto-refresh every 30 seconds if enabled
    if (autoRefresh) {
      const interval = setInterval(fetchDrafts, 30000);
      return () => clearInterval(interval);
    }
  }, [fetchDrafts, autoRefresh]);

  return { drafts, loading, error, refetch: fetchDrafts };
}
```

---

### Feature 2: Patient Timeline View

#### Component Hierarchy

```
PatientsPage
├── PatientSearchBar
├── PatientList
│   └── PatientCard (multiple)
└── (on patient select) → Navigate to PatientTimelinePage

PatientTimelinePage
├── PatientHeader
│   └── PatientInfo
├── TimelineVisualization
│   ├── TimelineAxis
│   │   ├── StartMarker
│   │   ├── TimelineBar
│   │   └── EndMarker (today)
│   └── TimelineDots
│       └── EncounterDot (multiple)
└── EncounterPreviewPanel (on dot hover/click)

ReadOnlyEncounterView (separate route)
└── EncounterWorkspace (with readOnly prop)
```

#### Component: PatientTimelinePage

**File:** `client/src/app/pages/patients/PatientTimelinePage.tsx`

```typescript
import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Calendar, FileText, User } from 'lucide-react';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { TimelineVisualization } from '../../components/patient/TimelineVisualization';
import { usePatientTimeline } from '../../hooks/usePatientTimeline';

export default function PatientTimelinePage() {
  const { patientId } = useParams<{ patientId: string }>();
  const navigate = useNavigate();
  const { data, loading, error } = usePatientTimeline(patientId!);
  const [selectedEncounter, setSelectedEncounter] = useState<string | null>(null);

  const handleEncounterClick = (encounterId: string) => {
    // Navigate to read-only encounter view
    navigate(`/encounters/${encounterId}?mode=readonly`);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <Card className="p-6 text-center">
          <p className="text-red-600">{error || 'Failed to load patient timeline'}</p>
          <Button
            variant="outline"
            onClick={() => navigate('/patients')}
            className="mt-4"
          >
            Back to Patients
          </Button>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate('/patients')}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Patients
          </Button>
        </div>
      </div>

      {/* Patient Info Card */}
      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
            <User className="h-8 w-8 text-blue-600" />
          </div>
          <div>
            <h1 className="text-2xl font-semibold">{data.patient.full_name}</h1>
            <div className="flex items-center gap-4 mt-1 text-sm text-slate-600">
              <span>DOB: {data.patient.date_of_birth}</span>
              <span>MRN: {data.patient.mrn}</span>
            </div>
          </div>
          <div className="ml-auto text-right">
            <Badge variant="outline" className="text-lg">
              {data.timeline.total_encounters} Encounters
            </Badge>
            <p className="text-xs text-slate-500 mt-1">
              {data.timeline.first_encounter_date} - {data.timeline.last_encounter_date}
            </p>
          </div>
        </div>
      </Card>

      {/* Timeline Visualization */}
      <Card className="p-6">
        <h2 className="text-lg font-semibold mb-6 flex items-center gap-2">
          <Calendar className="h-5 w-5" />
          Encounter Timeline
        </h2>
        
        <TimelineVisualization
          encounters={data.encounters}
          firstDate={data.timeline.first_encounter_date}
          lastDate={data.timeline.last_encounter_date}
          onEncounterClick={handleEncounterClick}
          selectedEncounterId={selectedEncounter}
          onEncounterHover={setSelectedEncounter}
        />
      </Card>

      {/* Encounters List */}
      <Card className="p-6">
        <h2 className="text-lg font-semibold mb-4">All Encounters</h2>
        <div className="space-y-3">
          {data.encounters.map((encounter) => (
            <div
              key={encounter.encounter_id}
              className={`p-4 border rounded-lg cursor-pointer transition-colors ${
                selectedEncounter === encounter.encounter_id
                  ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                  : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
              }`}
              onClick={() => handleEncounterClick(encounter.encounter_id)}
              onMouseEnter={() => setSelectedEncounter(encounter.encounter_id)}
              onMouseLeave={() => setSelectedEncounter(null)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <FileText className="h-5 w-5 text-slate-400" />
                  <div>
                    <p className="font-medium">
                      {new Date(encounter.date).toLocaleDateString()}
                    </p>
                    <p className="text-sm text-slate-600">
                      {encounter.chief_complaint || 'No chief complaint recorded'}
                    </p>
                  </div>
                </div>
                <div className="text-right">
                  <Badge variant={encounter.status === 'completed' ? 'default' : 'secondary'}>
                    {encounter.status}
                  </Badge>
                  <p className="text-xs text-slate-500 mt-1">{encounter.provider_name}</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
```

#### Component: TimelineVisualization

**File:** `client/src/app/components/patient/TimelineVisualization.tsx`

```typescript
import { useMemo } from 'react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '../ui/tooltip';

interface TimelineEncounter {
  encounter_id: string;
  date: string;
  encounter_type: string;
  status: string;
  chief_complaint: string | null;
  provider_name: string;
}

interface TimelineVisualizationProps {
  encounters: TimelineEncounter[];
  firstDate: string;
  lastDate: string;
  onEncounterClick: (encounterId: string) => void;
  selectedEncounterId: string | null;
  onEncounterHover: (encounterId: string | null) => void;
}

export function TimelineVisualization({
  encounters,
  firstDate,
  lastDate,
  onEncounterClick,
  selectedEncounterId,
  onEncounterHover,
}: TimelineVisualizationProps) {
  // Calculate positions for each encounter
  const encounterPositions = useMemo(() => {
    const startDate = new Date(firstDate);
    const endDate = new Date();
    const totalDays = Math.max(1, (endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24));

    return encounters.map((encounter) => {
      const encounterDate = new Date(encounter.date);
      const daysFromStart = (encounterDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24);
      const position = (daysFromStart / totalDays) * 100;
      return {
        ...encounter,
        position: Math.min(Math.max(position, 2), 98), // Keep within bounds
      };
    });
  }, [encounters, firstDate]);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-500 hover:bg-green-600';
      case 'in_progress':
        return 'bg-yellow-500 hover:bg-yellow-600';
      case 'cancelled':
        return 'bg-red-500 hover:bg-red-600';
      default:
        return 'bg-blue-500 hover:bg-blue-600';
    }
  };

  return (
    <div className="relative py-12">
      {/* Timeline Bar */}
      <div className="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-slate-200 dark:bg-slate-700 rounded-full" />

      {/* Start Marker */}
      <div className="absolute left-0 top-1/2 -translate-y-1/2 flex flex-col items-center">
        <div className="w-3 h-3 bg-slate-400 rounded-full" />
        <span className="text-xs text-slate-500 mt-2 whitespace-nowrap">
          {new Date(firstDate).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
        </span>
      </div>

      {/* End Marker (Today) */}
      <div className="absolute right-0 top-1/2 -translate-y-1/2 flex flex-col items-center">
        <div className="w-3 h-3 bg-blue-600 rounded-full" />
        <span className="text-xs text-slate-500 mt-2">Today</span>
      </div>

      {/* Encounter Dots */}
      <TooltipProvider>
        {encounterPositions.map((encounter) => (
          <Tooltip key={encounter.encounter_id}>
            <TooltipTrigger asChild>
              <button
                className={`
                  absolute top-1/2 -translate-y-1/2 -translate-x-1/2
                  w-4 h-4 rounded-full cursor-pointer
                  transition-all duration-200
                  ${getStatusColor(encounter.status)}
                  ${selectedEncounterId === encounter.encounter_id 
                    ? 'ring-4 ring-blue-300 scale-125' 
                    : 'hover:scale-110'}
                `}
                style={{ left: `${encounter.position}%` }}
                onClick={() => onEncounterClick(encounter.encounter_id)}
                onMouseEnter={() => onEncounterHover(encounter.encounter_id)}
                onMouseLeave={() => onEncounterHover(null)}
              />
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs">
              <div className="text-sm">
                <p className="font-medium">
                  {new Date(encounter.date).toLocaleDateString()}
                </p>
                <p className="text-slate-300">
                  {encounter.chief_complaint || 'No chief complaint'}
                </p>
                <p className="text-xs text-slate-400 mt-1">
                  {encounter.provider_name} • {encounter.status}
                </p>
              </div>
            </TooltipContent>
          </Tooltip>
        ))}
      </TooltipProvider>
    </div>
  );
}
```

---

## State Management

### Global State (Context)

The existing context providers are sufficient. New state will be managed locally with hooks.

```
AuthContext (existing)
├── user
├── currentRole
└── userId ← Used for fetching user's drafts

SyncContext (existing)
├── isSyncing
├── pendingCount
└── triggerSync

EncounterContext (existing)
├── activeEncounter
├── setActiveEncounter
└── encounterData
    └── Add: isReadOnly property
```

### Local State Management

```typescript
// useSavedReports hook
{
  drafts: SavedReport[]
  loading: boolean
  error: string | null
}

// usePatientTimeline hook
{
  data: PatientTimelineData | null
  loading: boolean
  error: string | null
}

// ReadOnly mode in EncounterContext
{
  isReadOnly: boolean  // NEW: Add to context
}
```

### Read-Only Mode Implementation

Add to [`EncounterContext.tsx`](../client/src/app/contexts/EncounterContext.tsx):

```typescript
interface EncounterContextType {
  // ... existing fields
  isReadOnly: boolean;
  setIsReadOnly: (value: boolean) => void;
}

// In EncounterWorkspace.tsx, check URL params:
const [searchParams] = useSearchParams();
const isReadOnlyMode = searchParams.get('mode') === 'readonly';

// Use in useEffect to set context
useEffect(() => {
  if (encounterContext?.setIsReadOnly) {
    encounterContext.setIsReadOnly(isReadOnlyMode);
  }
}, [isReadOnlyMode]);

// Disable all form inputs when read-only
// Add readOnly prop to all Input, Textarea, Select components
```

---

## UI/UX Wireframes

### Feature 1: Saved Reports Dropdown

```
┌────────────────────────────────────────────────────────────┐
│  Header Bar                                                │
│  ┌─────────┐ ┌───────────────────┐  ┌──────┐ ┌──────┐     │
│  │ ☰ Menu  │ │ 🔍 Search...      │  │ 🌙  │ │ 📶 3│ ← Dropdown Trigger
│  └─────────┘ └───────────────────┘  └──────┘ └──┬───┘     │
│                                                 │         │
│                                          ┌──────▼─────────┐
│                                          │ Connection     │
│                                          │ ● Online       │
│                                          │ [Sync Now]     │
│                                          ├────────────────┤
│                                          │ Saved Reports  │
│                                          ├────────────────┤
│                                          │ 📄 John Doe    │
│                                          │    5m ago      │
│                                          ├────────────────┤
│                                          │ 📄 New Patient │
│                                          │    2h ago      │
│                                          ├────────────────┤
│                                          │ 📄 Jane Smith  │
│                                          │    1d ago      │
│                                          ├────────────────┤
│                                          │ View all →     │
│                                          └────────────────┘
└────────────────────────────────────────────────────────────┘
```

### Feature 2: Patient Timeline View

```
┌────────────────────────────────────────────────────────────────────┐
│  ← Back to Patients                                                │
├────────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  👤 John Doe                                    8 Encounters  │  │
│  │  DOB: 1985-03-15  MRN: MRN-2024-0156                         │  │
│  │  First visit: Jun 2024 - Latest: Jan 2026                    │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  📅 Encounter Timeline                                        │  │
│  │                                                               │  │
│  │  Jun 2024                                            Today    │  │
│  │    ○───────●────●────────●─────●──●────●────●──────────○      │  │
│  │            │    │        │     │  │    │    │                 │  │
│  │            │    │        │     │  │    │    └─ Jan 2026       │  │
│  │            │    │        │     │  │    └─ Dec 2025            │  │
│  │            │    │        │     │  └─ Oct 2025                 │  │
│  │            │    │        │     └─ Aug 2025                    │  │
│  │            │    │        └─ Mar 2025                          │  │
│  │            │    └─ Nov 2024                                   │  │
│  │            └─ Sep 2024                                        │  │
│  │                                                               │  │
│  │  ● Completed  ○ In Progress  ○ Cancelled                     │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  All Encounters                                               │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │ 📄 Jan 20, 2026                            Completed   │  │  │
│  │  │    Annual physical examination             Dr. Smith   │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │ 📄 Dec 5, 2025                             Completed   │  │  │
│  │  │    Follow-up for back pain                 Dr. Jones   │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  │  ...                                                         │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────┘
```

### Read-Only Encounter View

```
┌────────────────────────────────────────────────────────────────────┐
│  ┌────────────────────────────────────────────────────────────┐   │
│  │ ⚠️ READ-ONLY MODE - Viewing historical record              │   │
│  │ You are viewing a completed encounter from Jan 20, 2026     │   │
│  └────────────────────────────────────────────────────────────┘   │
├────────────────────────────────────────────────────────────────────┤
│  ← Back to Timeline    John Doe    MRN: MRN-2024-0156             │
│                        Encounter: ENC-2026-0234  [Completed]      │
├────────────────────────────────────────────────────────────────────┤
│  [Incident] [Patient] [Objective Findings] [Narrative] [...]      │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  (All form fields displayed but DISABLED/READ-ONLY)               │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Clinic Information                                          │ │
│  │  ┌─────────────────────────────────────────────────────────┐ │ │
│  │  │ Clinic Name: SafeShift Occupational Health        [🔒] │ │ │
│  │  └─────────────────────────────────────────────────────────┘ │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Order

### Phase 1: Backend API (Backend Team)

1. **Add Repository Methods**
   - Add `findDraftsForProvider()` to EncounterRepository
   - Add index for performance optimization

2. **Add ViewModel Methods**
   - Add `getMyDraftEncounters()` to EncounterViewModel
   - Add `getPatientTimeline()` to PatientViewModel

3. **Add API Routes**
   - Register `GET /api/encounters/drafts`
   - Register `GET /api/patients/:patientId/timeline`

### Phase 2: Feature 1 - Saved Reports Dropdown (Frontend Team)

1. **Create Service Layer**
   - Add `getMyDrafts()` to encounter.service.ts

2. **Create Hook**
   - Create `useSavedReports.ts` hook

3. **Create Component**
   - Create `SavedReportsDropdown.tsx`

4. **Integrate into Layout**
   - Replace WiFi icon in DashboardLayout with SavedReportsDropdown
   - Maintain connection status functionality

5. **Testing**
   - Unit tests for hook
   - Integration tests for dropdown behavior

### Phase 3: Feature 2 - Patient Timeline (Frontend Team)

1. **Create Service Layer**
   - Add `getPatientTimeline()` to patient.service.ts

2. **Create Hook**
   - Create `usePatientTimeline.ts` hook

3. **Create Components**
   - Create `TimelineVisualization.tsx`
   - Create `PatientTimelinePage.tsx`

4. **Add Routes**
   - Add `/patients/:patientId/timeline` route
   - Update Patients sidebar link

5. **Implement Read-Only Mode**
   - Add `isReadOnly` to EncounterContext
   - Create read-only wrapper for EncounterWorkspace
   - Add banner component for read-only indication
   - Disable all form inputs when in read-only mode

6. **Testing**
   - Unit tests for timeline calculation
   - Integration tests for navigation flow
   - E2E tests for complete user journey

### Phase 4: Polish & QA

1. **Performance Optimization**
   - Add loading states
   - Implement data caching
   - Optimize timeline rendering for many encounters

2. **Accessibility**
   - Keyboard navigation for dropdown
   - Screen reader support for timeline
   - ARIA labels

3. **Error Handling**
   - Network error states
   - Empty states
   - Retry mechanisms

4. **Documentation**
   - Update API documentation
   - Add inline code comments
   - Update user documentation

---

## Appendix: File Changes Summary

### New Files to Create

```
Frontend:
├── client/src/app/components/header/SavedReportsDropdown.tsx
├── client/src/app/components/patient/TimelineVisualization.tsx
├── client/src/app/pages/patients/PatientTimelinePage.tsx
├── client/src/app/hooks/useSavedReports.ts
└── client/src/app/hooks/usePatientTimeline.ts

Backend:
└── (No new files - extend existing ViewModels/Repositories)
```

### Files to Modify

```
Frontend:
├── client/src/app/components/layout/DashboardLayout.tsx
│   └── Replace WiFi button with SavedReportsDropdown
├── client/src/app/contexts/EncounterContext.tsx
│   └── Add isReadOnly state
├── client/src/app/pages/encounters/EncounterWorkspace.tsx
│   └── Add read-only mode support
├── client/src/app/services/encounter.service.ts
│   └── Add getMyDrafts method
├── client/src/app/services/patient.service.ts
│   └── Add getPatientTimeline method
└── client/src/App.tsx (or routes file)
    └── Add patient timeline route

Backend:
├── server/ViewModel/EncounterViewModel.php
│   └── Add getMyDraftEncounters method
├── server/ViewModel/PatientViewModel.php
│   └── Add getPatientTimeline method
├── server/model/Repositories/EncounterRepository.php
│   └── Add findDraftsForProvider method
└── server/includes/router.php (or API routes file)
    └── Register new endpoints
```

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | Architecture Team | Initial design document |
