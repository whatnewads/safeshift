<?php

declare(strict_types=1);

namespace Model\Repositories;

use Model\Entities\Encounter;
use Model\Interfaces\RepositoryInterface;
use PDO;
use DateTimeImmutable;

/**
 * Encounter Repository
 *
 * Data access layer for clinical encounter records.
 * Handles all database operations for the encounter table.
 *
 * @package Model\Repositories
 */
class EncounterRepository implements RepositoryInterface
{
    private PDO $pdo;
    private string $table = 'encounters';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get the PDO connection
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Find encounter by ID
     */
    public function findById(string $id): ?Encounter
    {
        $sql = "SELECT * FROM {$this->table} WHERE encounter_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrateEncounter($row);
    }

    /**
     * Find all encounters matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria (e.g., ['patient_id' => '...', 'status' => '...'])
     * @param array<string, string> $orderBy Order by fields ['field' => 'ASC|DESC']
     * @param int|null $limit Maximum number of results (default: 50)
     * @param int|null $offset Number of results to skip (default: 0)
     * @return array<int, Encounter> Array of matching encounters
     */
    public function findAll(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        // Build WHERE clause from criteria
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Build ORDER BY clause
        $orderClauses = [];
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "{$field} {$direction}";
            }
        }
        // Default ordering if none specified
        if (empty($orderClauses)) {
            $orderClauses[] = 'occurred_on DESC';
        }
        $orderByClause = 'ORDER BY ' . implode(', ', $orderClauses);
        
        // Apply defaults for limit and offset
        $limitValue = $limit ?? 50;
        $offsetValue = $offset ?? 0;
        
        $sql = "SELECT * FROM {$this->table}
                {$whereClause}
                {$orderByClause}
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind criteria parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->bindValue(':limit', $limitValue, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offsetValue, PDO::PARAM_INT);
        $stmt->execute();

        $encounters = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $encounters[] = $this->hydrateEncounter($row);
        }

        return $encounters;
    }

    /**
     * Find encounters by patient ID
     * NOTE: Uses occurred_on (actual DB column) instead of encounter_date
     */
    public function findByPatientId(string $patientId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE patient_id = :patient_id
                ORDER BY occurred_on DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $encounters = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $encounters[] = $this->hydrateEncounter($row);
        }

        return $encounters;
    }

    /**
     * Find encounters by provider ID
     * NOTE: Uses npi_provider (actual DB column) instead of provider_id
     */
    public function findByProviderId(string $providerId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE npi_provider = :provider_id
                ORDER BY occurred_on DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':provider_id', $providerId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $encounters = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $encounters[] = $this->hydrateEncounter($row);
        }

        return $encounters;
    }

    /**
     * Search encounters with filters
     * NOTE: Uses actual DB column names: npi_provider, site_id, occurred_on
     */
    public function search(
        ?string $patientId = null,
        ?string $providerId = null,
        ?string $clinicId = null,
        ?string $status = null,
        ?string $encounterType = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 50,
        int $offset = 0,
        string $sortBy = 'occurred_on',
        string $sortOrder = 'DESC'
    ): array {
        $conditions = [];
        $params = [];

        if ($patientId !== null) {
            $conditions[] = 'patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        if ($providerId !== null) {
            $conditions[] = 'npi_provider = :provider_id';
            $params['provider_id'] = $providerId;
        }

        if ($clinicId !== null) {
            $conditions[] = 'site_id = :clinic_id';
            $params['clinic_id'] = $clinicId;
        }

        if ($status !== null && $status !== 'all') {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($encounterType !== null && $encounterType !== 'all') {
            $conditions[] = 'encounter_type = :encounter_type';
            $params['encounter_type'] = $encounterType;
        }

        if ($startDate !== null) {
            $conditions[] = 'DATE(occurred_on) >= :start_date';
            $params['start_date'] = $startDate;
        }

        if ($endDate !== null) {
            $conditions[] = 'DATE(occurred_on) <= :end_date';
            $params['end_date'] = $endDate;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Validate sort column - map encounter_date to occurred_on
        $sortColumnMap = [
            'encounter_date' => 'occurred_on',
            'occurred_on' => 'occurred_on',
            'created_at' => 'created_at',
            'status' => 'status',
            'encounter_type' => 'encounter_type'
        ];
        $sortBy = $sortColumnMap[$sortBy] ?? 'occurred_on';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table}
                {$whereClause}
                ORDER BY {$sortBy} {$sortOrder}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $encounters = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $encounters[] = $this->hydrateEncounter($row);
        }

        return $encounters;
    }

    /**
     * Count encounters matching criteria (interface method)
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int Number of matching encounters
     */
    public function count(array $criteria = []): int
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count encounters with extended filters (legacy method)
     * NOTE: Uses actual DB column names: npi_provider, site_id, occurred_on
     */
    public function countWithFilters(
        ?string $patientId = null,
        ?string $providerId = null,
        ?string $clinicId = null,
        ?string $status = null,
        ?string $encounterType = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): int {
        $conditions = [];
        $params = [];

        if ($patientId !== null) {
            $conditions[] = 'patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        if ($providerId !== null) {
            $conditions[] = 'npi_provider = :provider_id';
            $params['provider_id'] = $providerId;
        }

        if ($clinicId !== null) {
            $conditions[] = 'site_id = :clinic_id';
            $params['clinic_id'] = $clinicId;
        }

        if ($status !== null && $status !== 'all') {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($encounterType !== null && $encounterType !== 'all') {
            $conditions[] = 'encounter_type = :encounter_type';
            $params['encounter_type'] = $encounterType;
        }

        if ($startDate !== null) {
            $conditions[] = 'DATE(occurred_on) >= :start_date';
            $params['start_date'] = $startDate;
        }

        if ($endDate !== null) {
            $conditions[] = 'DATE(occurred_on) <= :end_date';
            $params['end_date'] = $endDate;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find encounters by status
     */
    public function findByStatus(string $status, int $limit = 50, int $offset = 0): array
    {
        return $this->search(null, null, null, $status, null, null, null, $limit, $offset);
    }

    /**
     * Find today's encounters for a clinic
     */
    public function findTodaysEncounters(string $clinicId, int $limit = 100): array
    {
        $today = date('Y-m-d');
        return $this->search(null, null, $clinicId, null, null, $today, $today, $limit, 0);
    }

    /**
     * Find pending encounters for a provider
     * NOTE: Uses npi_provider (actual DB column) instead of provider_id
     */
    public function findPendingForProvider(string $providerId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE npi_provider = :provider_id
                AND status IN ('planned', 'arrived', 'in_progress')
                ORDER BY occurred_on ASC
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

    /**
     * Find single encounter matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return Encounter|null
     */
    public function findOneBy(array $criteria): ?Encounter
    {
        $results = $this->findAll($criteria, [], 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Create a new encounter from array data
     *
     * @param array<string, mixed> $data Encounter data
     * @return Encounter Created encounter
     * @throws \InvalidArgumentException If required data is missing
     */
    public function create(array $data): Encounter
    {
        $requiredFields = ['patient_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $encounter = Encounter::fromArray($data);
        
        if (!$this->insert($encounter)) {
            throw new \RuntimeException('Failed to create encounter');
        }

        return $this->findById($encounter->getId());
    }

    /**
     * Update an existing encounter (interface method)
     *
     * @param string $id Encounter ID to update
     * @param array<string, mixed> $data Updated encounter data
     * @return Encounter Updated encounter
     * @throws \RuntimeException If encounter not found
     */
    public function update(string $id, array $data): Encounter
    {
        $encounter = $this->findById($id);
        if (!$encounter) {
            throw new \RuntimeException("Encounter not found: {$id}");
        }

        // Build update SQL dynamically based on provided data
        $allowedFields = [
            'provider_id', 'clinic_id', 'encounter_type', 'status',
            'chief_complaint', 'hpi', 'ros', 'physical_exam',
            'assessment', 'plan', 'vitals', 'clinical_data',
            'encounter_date', 'supervising_provider_id',
            'icd_codes', 'cpt_codes', 'service_location'
        ];
        $setClauses = [];
        $params = ['encounter_id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['vitals', 'clinical_data', 'icd_codes', 'cpt_codes']) && is_array($data[$field])) {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = json_encode($data[$field]);
                } else {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
        }

        if (empty($setClauses)) {
            return $encounter; // Nothing to update
        }

        $setClauses[] = 'updated_at = NOW()';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE encounter_id = :encounter_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Return fresh entity
        return $this->findById($id);
    }

    /**
     * Check if encounter exists
     *
     * @param string $id Encounter ID
     * @return bool True if exists
     */
    public function exists(string $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE encounter_id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Save (insert or update) an encounter
     *
     * @param \Model\Interfaces\EntityInterface $entity Encounter entity to save
     * @return Encounter Saved encounter
     */
    public function save($entity): Encounter
    {
        if (!$entity instanceof Encounter) {
            throw new \InvalidArgumentException('Entity must be an Encounter');
        }

        if ($entity->getId()) {
            $existing = $this->findById($entity->getId());
            if ($existing) {
                $this->updateEntity($entity);
                return $this->findById($entity->getId());
            }
        }

        $this->insert($entity);
        return $this->findById($entity->getId());
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get the entity class this repository manages
     */
    public function getEntityClass(): string
    {
        return Encounter::class;
    }

    /**
     * Get the database table name
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Insert a new encounter
     *
     * NOTE: Actual database schema uses these columns:
     *   encounter_id, patient_id, site_id, employer_name, encounter_type,
     *   encounter_type_other, status, chief_complaint, onset_context,
     *   occurred_on, arrived_on, discharged_on, disposition, npi_provider,
     *   created_at, created_by, modified_at, modified_by, deleted_at, deleted_by
     */
    private function insert(Encounter $encounter): bool
    {
        $encounterId = $encounter->getId() ?? $this->generateUuid();
        $data = $encounter->toArray();

        $sql = "INSERT INTO {$this->table} (
                    encounter_id, patient_id, site_id, employer_name,
                    encounter_type, status, chief_complaint, onset_context,
                    occurred_on, arrived_on, disposition, npi_provider,
                    created_at, created_by
                ) VALUES (
                    :encounter_id, :patient_id, :site_id, :employer_name,
                    :encounter_type, :status, :chief_complaint, :onset_context,
                    :occurred_on, :arrived_on, :disposition, :npi_provider,
                    NOW(), :created_by
                )";

        $stmt = $this->pdo->prepare($sql);
        
        // Map entity fields to database columns
        $success = $stmt->execute([
            'encounter_id' => $encounterId,
            'patient_id' => $encounter->getPatientId(),
            'site_id' => $encounter->getClinicId() ?? 1,  // Map clinic_id to site_id
            'employer_name' => $data['employer_name'] ?? null,
            'encounter_type' => $encounter->getEncounterType(),
            'status' => $encounter->getStatus(),
            'chief_complaint' => $encounter->getChiefComplaint(),
            'onset_context' => $data['onset_context'] ?? 'unknown',
            'occurred_on' => $encounter->getEncounterDate()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'arrived_on' => $data['arrived_on'] ?? date('Y-m-d H:i:s'),
            'disposition' => $data['disposition'] ?? null,
            'npi_provider' => $encounter->getProviderId(),  // Map provider_id to npi_provider
            'created_by' => $data['created_by'] ?? 1,
        ]);

        if ($success) {
            $encounter->setId($encounterId);
        }

        return $success;
    }

    /**
     * Update an existing encounter entity (internal use)
     *
     * NOTE: Uses actual database schema columns:
     *   site_id, employer_name, encounter_type, status, chief_complaint,
     *   onset_context, occurred_on, arrived_on, discharged_on, disposition,
     *   npi_provider, modified_at, modified_by
     */
    private function updateEntity(Encounter $encounter): bool
    {
        $data = $encounter->toArray();
        
        $sql = "UPDATE {$this->table} SET
                    site_id = :site_id,
                    employer_name = :employer_name,
                    encounter_type = :encounter_type,
                    status = :status,
                    chief_complaint = :chief_complaint,
                    onset_context = :onset_context,
                    occurred_on = :occurred_on,
                    arrived_on = :arrived_on,
                    discharged_on = :discharged_on,
                    disposition = :disposition,
                    npi_provider = :npi_provider,
                    modified_at = NOW(),
                    modified_by = :modified_by
                WHERE encounter_id = :encounter_id";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'encounter_id' => $encounter->getId(),
            'site_id' => $encounter->getClinicId() ?? 1,  // Map clinic_id to site_id
            'employer_name' => $data['employer_name'] ?? null,
            'encounter_type' => $encounter->getEncounterType(),
            'status' => $encounter->getStatus(),
            'chief_complaint' => $encounter->getChiefComplaint(),
            'onset_context' => $data['onset_context'] ?? 'unknown',
            'occurred_on' => $encounter->getEncounterDate()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'arrived_on' => $data['arrived_on'] ?? null,
            'discharged_on' => $data['discharged_on'] ?? null,
            'disposition' => $data['disposition'] ?? null,
            'npi_provider' => $encounter->getProviderId(),  // Map provider_id to npi_provider
            'modified_by' => $data['created_by'] ?? 1,
        ]);
    }

    /**
     * Delete an encounter (soft delete by setting status to cancelled)
     * NOTE: Uses modified_at instead of updated_at (actual DB schema)
     */
    public function delete(string $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'cancelled', modified_at = NOW()
                WHERE encounter_id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Lock an encounter
     * NOTE: locked_at/locked_by columns may not exist - uses status='completed' instead
     */
    public function lockEncounter(string $encounterId, string $userId): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'completed', modified_at = NOW(), modified_by = :user_id
                WHERE encounter_id = :encounter_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'encounter_id' => $encounterId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Start an amendment on a locked encounter
     * NOTE: Amendment columns may not exist - this is a no-op in minimal schema
     */
    public function startAmendment(string $encounterId, string $userId, string $reason): bool
    {
        // In the actual DB schema, amendment tracking columns don't exist
        // Just update modified_at/modified_by to track the change
        $sql = "UPDATE {$this->table}
                SET modified_at = NOW(), modified_by = :user_id
                WHERE encounter_id = :encounter_id AND status = 'completed'";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'encounter_id' => $encounterId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update encounter status
     * NOTE: Uses modified_at instead of updated_at (actual DB schema)
     */
    public function updateStatus(string $encounterId, string $status): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = :status, modified_at = NOW()
                WHERE encounter_id = :encounter_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'encounter_id' => $encounterId,
            'status' => $status,
        ]);
    }

    /**
     * Get encounter statistics for a provider
     * NOTE: Uses npi_provider and occurred_on (actual DB columns)
     */
    public function getProviderStats(string $providerId, ?string $startDate = null, ?string $endDate = null): array
    {
        $conditions = ['npi_provider = :provider_id'];
        $params = ['provider_id' => $providerId];

        if ($startDate) {
            $conditions[] = 'DATE(occurred_on) >= :start_date';
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $conditions[] = 'DATE(occurred_on) <= :end_date';
            $params['end_date'] = $endDate;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT
                    status,
                    encounter_type,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE {$whereClause}
                GROUP BY status, encounter_type";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hydrate an Encounter entity from database row
     *
     * NOTE: Actual database schema uses:
     *   encounter_id, patient_id, site_id, employer_name, encounter_type,
     *   encounter_type_other, status, chief_complaint, onset_context,
     *   occurred_on, arrived_on, discharged_on, disposition, npi_provider,
     *   created_at, created_by, modified_at, modified_by
     */
    private function hydrateEncounter(array $row): Encounter
    {
        // Map actual database columns to entity expected fields
        $data = [
            'encounter_id' => $row['encounter_id'],
            'patient_id' => $row['patient_id'],
            // Map site_id to clinic_id and npi_provider to provider_id
            'provider_id' => $row['npi_provider'] ?? $row['provider_id'] ?? null,
            'clinic_id' => isset($row['site_id']) ? (string)$row['site_id'] : ($row['clinic_id'] ?? null),
            'encounter_type' => $row['encounter_type'] ?? 'clinic',
            'status' => $row['status'] ?? 'planned',
            'chief_complaint' => $row['chief_complaint'] ?? null,
            // Map database date columns
            'encounter_date' => $row['occurred_on'] ?? $row['encounter_date'] ?? null,
            // Additional database fields
            'onset_context' => $row['onset_context'] ?? null,
            'employer_name' => $row['employer_name'] ?? null,
            'disposition' => $row['disposition'] ?? null,
            'arrived_on' => $row['arrived_on'] ?? null,
            'discharged_on' => $row['discharged_on'] ?? null,
            // These columns may not exist in actual DB, provide defaults
            'hpi' => $row['hpi'] ?? null,
            'ros' => $row['ros'] ?? null,
            'physical_exam' => $row['physical_exam'] ?? null,
            'assessment' => $row['assessment'] ?? null,
            'plan' => $row['plan'] ?? null,
            'locked_at' => $row['locked_at'] ?? null,
            'locked_by' => isset($row['locked_by']) ? (string)$row['locked_by'] : null,
            'is_amended' => $row['is_amended'] ?? false,
            'amendment_reason' => $row['amendment_reason'] ?? null,
            'amended_at' => $row['amended_at'] ?? null,
            'amended_by' => isset($row['amended_by']) ? (string)$row['amended_by'] : null,
            'supervising_provider_id' => $row['supervising_provider_id'] ?? null,
            'appointment_id' => $row['appointment_id'] ?? null,
            'service_location' => $row['service_location'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['modified_at'] ?? $row['updated_at'] ?? null,
            // Cast int to string for created_by
            'created_by' => isset($row['created_by']) ? (string)$row['created_by'] : null,
        ];

        // Handle JSON fields if they exist
        if (!empty($row['vitals'])) {
            $data['vitals'] = is_string($row['vitals'])
                ? json_decode($row['vitals'], true)
                : $row['vitals'];
        }

        if (!empty($row['clinical_data'])) {
            $data['clinical_data'] = is_string($row['clinical_data'])
                ? json_decode($row['clinical_data'], true)
                : $row['clinical_data'];
        }

        if (!empty($row['icd_codes'])) {
            $data['icd_codes'] = is_string($row['icd_codes'])
                ? json_decode($row['icd_codes'], true)
                : $row['icd_codes'];
        }

        if (!empty($row['cpt_codes'])) {
            $data['cpt_codes'] = is_string($row['cpt_codes'])
                ? json_decode($row['cpt_codes'], true)
                : $row['cpt_codes'];
        }

        return Encounter::fromArray($data);
    }

    /**
     * Generate a new UUID
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
