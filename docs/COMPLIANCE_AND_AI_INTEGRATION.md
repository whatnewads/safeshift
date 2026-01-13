# Compliance & AI Integration Documentation

## Table of Contents
1. [Compliance Notification System](#compliance-notification-system)
2. [AWS AI Integration for Narrative Generation](#aws-ai-integration-for-narrative-generation)
3. [Technical Implementation](#technical-implementation)
4. [Future Roadmap](#future-roadmap)

---

## Compliance Notification System

### Overview
The Admin Dashboard → Compliance tab displays regulatory updates from authorities such as HHS and OSHA. This system is designed to help occupational health clinics stay compliant with changing requirements in a regulated healthcare environment.

### Current State: Mock Data

**Status:** Currently using hard-coded mock data to simulate cached compliance notifications.

The compliance notifications are currently **hard-coded** in `/src/app/pages/dashboards/Admin.tsx` (lines 132-220) as `mockComplianceData`. This mock data simulates what would be returned from a backend scheduled job that monitors regulatory updates.

**Mock Data Structure:**
```typescript
interface ComplianceNotification {
  id: string;
  authority: 'HHS' | 'OSHA';
  program: string;
  title: string;
  type: 'new requirement' | 'updated requirement' | 'enforcement clarification' | 'guidance';
  status: 'new' | 'acknowledged' | 'archived';
  published_at: string;
  effective_date: string | null;
  first_seen_at: string;
  last_seen_at: string;
  source_url: string;
  content_body?: string;
  internal_notes?: string;
  acknowledged_at?: string;
  acknowledged_by?: string;
  last_reviewed_at?: string;
  last_reviewed_by?: string;
}
```

### Connecting to Real API

To connect to a real compliance notification API, replace the `useEffect` hook in `/src/app/pages/dashboards/Admin.tsx` (line 223):

**Current Code:**
```typescript
// Initialize compliance notifications from cached data
// NOTE: In production, replace mockComplianceData with API call to fetch cached compliance notifications
React.useEffect(() => {
  setComplianceNotifications(mockComplianceData);
}, []);
```

**Production Code:**
```typescript
// Initialize compliance notifications from cached data
React.useEffect(() => {
  // Fetch cached compliance notifications from backend
  fetch('/api/compliance/notifications', {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${authToken}`, // Include auth token
    },
  })
    .then(res => {
      if (!res.ok) throw new Error('Failed to fetch compliance notifications');
      return res.json();
    })
    .then(data => setComplianceNotifications(data))
    .catch(err => {
      console.error('Failed to load compliance notifications:', err);
      // Optionally: Show user-facing error message
    });
}, []);
```

### Backend Requirements

The backend API should:
1. **Run scheduled jobs** to monitor HHS, OSHA, and other regulatory sources
2. **Cache notifications** in a database (not fetched live from the UI)
3. **Return data** matching the `ComplianceNotification` interface
4. **Track metadata** including `first_seen_at`, `last_seen_at` for audit purposes
5. **Support filtering** by authority, type, status, date range (optional - can be done client-side)

**Example API Endpoint:**
```
GET /api/compliance/notifications
Response: ComplianceNotification[]
```

### Features Implemented

✅ **Data Binding:**
- Notifications list sorted by `first_seen_at DESC`
- "New" badge indicator when `first_seen_at > last_fetch_at`
- System refresh timestamp displayed

✅ **Filtering:**
- Authority (HHS, OSHA, All)
- Type (new requirement, updated requirement, enforcement clarification, guidance)
- Status (new, acknowledged, archived)
- Effective Date (all, with date, without date)
- Date Range (all time, last 7/30/90 days)

✅ **State Management:**
- Acknowledge notifications (updates status, timestamps, and user)
- Archive notifications (hides from default view)
- Save internal notes per notification
- Immutable audit trail display

✅ **Detail Panel:**
- Full notification content
- Source URL linking to external regulatory documents
- Editable internal notes field
- Audit metadata (first detected, last reviewed, acknowledged by)
- Action buttons (Acknowledge, Archive) based on status

---

## AWS AI Integration for Narrative Generation

### Current Status: In Negotiation

**Status:** We are currently in talks with **Amazon Web Services (AWS)** to obtain access to their AI services that are designed to work with **Protected Health Information (PHI)** for auto-generating clinical narratives.

### Use Case: Clinical Narrative Auto-Generation

**Objective:** Automate the generation of clinical encounter narratives while maintaining HIPAA compliance and PHI security requirements.

**Current Manual Process:**
- Providers manually write narrative summaries for each patient encounter
- Time-consuming and prone to inconsistency
- Requires detailed documentation of symptoms, findings, treatments, and outcomes

**AI-Powered Solution:**
- Auto-generate structured narratives from encounter data (vitals, assessments, treatments)
- Include OSHA recordability detection based on injury/illness classification
- Maintain compliance with HIPAA, HITECH, and occupational health regulations
- Allow provider review and editing before finalization

### Why AWS?

**HIPAA-Compliant AI Services:**
- AWS offers **HIPAA-eligible AI/ML services** with Business Associate Agreements (BAA)
- Services under consideration:
  - **Amazon Comprehend Medical** - Extract medical information from unstructured text
  - **Amazon Bedrock** - Foundation models with PHI handling capabilities
  - **Amazon SageMaker** - Custom model deployment with PHI security controls

**Security & Compliance:**
- Encryption at rest and in transit
- Audit logging and monitoring
- Data residency controls
- BAA coverage for PHI processing

### Integration Architecture (Planned)

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend (Encounter Workspace)                             │
│  - Provider enters clinical data                            │
│  - Clicks "Generate Narrative" button                       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Backend API Server                                          │
│  - Validates encounter data                                  │
│  - Prepares structured payload                               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  AWS AI Service (PHI-Enabled)                                │
│  - Receives anonymized/encrypted encounter data              │
│  - Generates clinical narrative                              │
│  - Detects OSHA recordability indicators                     │
│  - Returns structured response                               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Backend API Server                                          │
│  - Validates AI response                                     │
│  - Logs generation for audit trail                           │
│  - Returns narrative to frontend                             │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Frontend (Encounter Workspace)                             │
│  - Displays AI-generated narrative                          │
│  - Provider reviews, edits, and approves                    │
│  - Saves to encounter record                                │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow Example

**Input (Encounter Data):**
```json
{
  "patient_id": "PT-12345",
  "encounter_type": "work_injury",
  "chief_complaint": "Laceration to left hand",
  "vitals": {
    "blood_pressure": "128/82",
    "heart_rate": 78,
    "temperature": 98.6
  },
  "injury_details": {
    "body_part": "Left hand",
    "nature": "Laceration",
    "mechanism": "Cut by machinery blade"
  },
  "treatment": [
    "Wound irrigation",
    "Sutures (3 stitches)",
    "Tetanus prophylaxis",
    "Antibiotic ointment"
  ],
  "work_status": "restricted_duty"
}
```

**Output (AI-Generated Narrative):**
```
Patient presented to occupational health clinic with a laceration to the left 
hand sustained during operation of machinery. Vital signs stable with BP 128/82, 
HR 78, temp 98.6°F. Physical examination revealed a 2cm laceration to the dorsal 
aspect of the left hand. Wound was irrigated with sterile saline and closed with 
3 interrupted sutures. Tetanus prophylaxis administered. Antibiotic ointment 
applied and wound dressed. Patient instructed on wound care and given work 
restrictions: no use of left hand for grasping or lifting. Follow-up in 7 days 
for suture removal.

OSHA Recordability: RECORDABLE - Work-related injury requiring medical treatment 
beyond first aid (sutures).
```

### Compliance Considerations

**PHI Protection:**
- All AI API calls must use encrypted connections (TLS 1.2+)
- PHI should be de-identified or tokenized where possible
- Audit logging required for all AI interactions
- BAA with AWS required before production deployment

**Provider Oversight:**
- AI-generated narratives are **suggestions only**
- Provider must review and approve all AI-generated content
- Provider maintains final authority over clinical documentation
- Edit history tracked for regulatory compliance

**Data Retention:**
- AI-generated narratives stored with audit metadata
- Original input data preserved for review
- Prompt/response pairs logged for quality improvement
- Retention periods aligned with medical record requirements (7+ years)

---

## Technical Implementation

### Frontend Components

**Location:** `/src/app/pages/dashboards/Admin.tsx`

**Key State Variables:**
```typescript
// Compliance Notification State
const [complianceNotifications, setComplianceNotifications] = useState<ComplianceNotification[]>([]);
const [selectedComplianceNotification, setSelectedComplianceNotification] = useState<ComplianceNotification | null>(null);
const [complianceFilter, setComplianceFilter] = useState({
  authority: 'all',
  type: 'all',
  status: 'all',
  effectiveDate: 'all',
  dateRange: 'all',
});
const [complianceInternalNotes, setComplianceInternalNotes] = useState<Record<string, string>>({});
```

**Key Functions:**
- `handleAcknowledgeCompliance(id)` - Mark notification as acknowledged
- `handleArchiveCompliance(id)` - Archive notification (hide from default view)
- `handleSaveComplianceNotes(id)` - Save internal notes
- `filteredComplianceNotifications` - Memoized filtered/sorted list
- `isNewNotification(notification)` - Check if notification is new since last fetch

### Backend API Endpoints (To Be Implemented)

**Compliance Notifications:**
```
GET    /api/compliance/notifications          # Fetch all cached notifications
POST   /api/compliance/notifications/:id/acknowledge  # Acknowledge notification
POST   /api/compliance/notifications/:id/archive      # Archive notification
PATCH  /api/compliance/notifications/:id/notes        # Update internal notes
```

**AI Narrative Generation (Future):**
```
POST   /api/encounters/:id/generate-narrative  # Generate AI narrative from encounter data
POST   /api/encounters/:id/detect-osha         # Detect OSHA recordability
GET    /api/ai/audit-log                       # Retrieve AI generation audit log
```

---

## Future Roadmap

### Phase 1: Compliance System (✅ Complete)
- [x] UI for compliance notifications
- [x] Filtering and sorting
- [x] Acknowledge/Archive functionality
- [x] Internal notes
- [x] Audit trail display
- [ ] Backend API integration
- [ ] Scheduled regulatory monitoring jobs

### Phase 2: AWS AI Integration (🔄 In Progress)
- [ ] Finalize AWS contract and BAA
- [ ] Set up AWS Bedrock or SageMaker environment
- [ ] Develop AI prompt engineering for clinical narratives
- [ ] Build backend API for AI integration
- [ ] Implement PHI encryption/tokenization
- [ ] Create provider review workflow UI
- [ ] OSHA recordability detection logic
- [ ] Audit logging for all AI interactions

### Phase 3: Advanced Features (📋 Planned)
- [ ] Multi-language narrative generation
- [ ] Voice-to-text integration for provider dictation
- [ ] Real-time OSHA recordability alerts during encounter
- [ ] Automated compliance report generation
- [ ] Integration with external EHR systems
- [ ] Machine learning for compliance risk prediction

---

## Contact & Support

For questions about compliance integration or AWS AI implementation:

**Compliance System:**
- Review code in `/src/app/pages/dashboards/Admin.tsx`
- Check interface definitions starting at line 78

**AWS AI Integration:**
- Status: Actively in negotiation with AWS
- Target timeline: Q1-Q2 2025 (pending AWS contract finalization)

**Development Team:**
- Compliance UI: ✅ Implemented
- Backend API: 🔄 In Development
- AWS AI Integration: 📋 Planned

---

## Security & Privacy Notice

⚠️ **Important:** This system is designed for occupational health environments handling Protected Health Information (PHI). All implementations must comply with:

- **HIPAA** - Health Insurance Portability and Accountability Act
- **HITECH** - Health Information Technology for Economic and Clinical Health Act
- **OSHA** - Occupational Safety and Health Administration recordkeeping requirements
- **State-specific** privacy and data protection laws

**Figma Make Disclaimer:** This application is built using Figma Make, which is **not intended for production collection of PII or PHI**. For production deployment, migrate to a HIPAA-compliant hosting environment with proper security controls, encryption, and Business Associate Agreements.

---

*Last Updated: December 23, 2024*
