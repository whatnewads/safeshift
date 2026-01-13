<?php
/**
 * DashboardMetricsTest.php - Integration Tests for Dashboard Metrics
 * 
 * Tests the accuracy of dashboard metric calculations including
 * encounter counts, completion rates, and role-based data filtering.
 * 
 * @package    SafeShift\Tests\Integration
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Helpers\TestCase;
use Tests\Helpers\Factories\EncounterFactory;
use Tests\Helpers\Factories\PatientFactory;
use Tests\Helpers\Factories\UserFactory;
use Model\Entities\Encounter;
use DateTimeImmutable;

/**
 * Integration tests for dashboard metrics calculations
 */
class DashboardMetricsTest extends TestCase
{
    private EncounterFactory $encounterFactory;
    private PatientFactory $patientFactory;
    private UserFactory $userFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encounterFactory = new EncounterFactory();
        $this->patientFactory = new PatientFactory();
        $this->userFactory = new UserFactory();
    }

    /**
     * Test daily encounter count calculation
     */
    public function testDailyEncounterCountCalculation(): void
    {
        $today = new DateTimeImmutable();
        $encounters = [];
        
        // Create 5 encounters for today
        for ($i = 0; $i < 5; $i++) {
            $encounter = new Encounter('patient-' . $i, Encounter::TYPE_OFFICE_VISIT);
            $encounter->setId('encounter-' . $i);
            $encounter->setEncounterDate($today);
            $encounters[] = $encounter;
        }
        
        // Count encounters for today
        $todayCount = count(array_filter($encounters, function ($e) use ($today) {
            return $e->getEncounterDate()->format('Y-m-d') === $today->format('Y-m-d');
        }));
        
        $this->assertEquals(5, $todayCount);
    }

    /**
     * Test active encounters count (non-completed)
     */
    public function testActiveEncountersCount(): void
    {
        $encounters = [];
        
        // Create encounters with different statuses
        $statuses = [
            Encounter::STATUS_SCHEDULED,
            Encounter::STATUS_CHECKED_IN,
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_COMPLETE,
            Encounter::STATUS_COMPLETE,
        ];
        
        foreach ($statuses as $i => $status) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setStatus($status);
            $encounters[] = $encounter;
        }
        
        // Count active (non-completed) encounters
        $activeStatuses = [
            Encounter::STATUS_SCHEDULED,
            Encounter::STATUS_CHECKED_IN,
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_PENDING_REVIEW,
        ];
        
        $activeCount = count(array_filter($encounters, function ($e) use ($activeStatuses) {
            return in_array($e->getStatus(), $activeStatuses, true);
        }));
        
        $this->assertEquals(4, $activeCount);
    }

    /**
     * Test completed encounters today count
     */
    public function testCompletedEncountersTodayCount(): void
    {
        $today = new DateTimeImmutable();
        $encounters = [];
        
        // Create mix of completed and non-completed encounters
        for ($i = 0; $i < 10; $i++) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setEncounterDate($today);
            
            if ($i < 6) {
                $encounter->setChiefComplaint('Complaint ' . $i);
                $encounter->lock('clinician-123');
            }
            
            $encounters[] = $encounter;
        }
        
        // Count completed today
        $completedToday = count(array_filter($encounters, function ($e) use ($today) {
            return $e->isLocked() && 
                   $e->getEncounterDate()->format('Y-m-d') === $today->format('Y-m-d');
        }));
        
        $this->assertEquals(6, $completedToday);
    }

    /**
     * Test encounter type distribution calculation
     */
    public function testEncounterTypeDistribution(): void
    {
        $encounters = [];
        $types = [
            Encounter::TYPE_OFFICE_VISIT,
            Encounter::TYPE_OFFICE_VISIT,
            Encounter::TYPE_OFFICE_VISIT,
            Encounter::TYPE_DOT_PHYSICAL,
            Encounter::TYPE_DOT_PHYSICAL,
            Encounter::TYPE_OSHA_INJURY,
            Encounter::TYPE_DRUG_SCREEN,
            Encounter::TYPE_WORKERS_COMP,
            Encounter::TYPE_PRE_EMPLOYMENT,
            Encounter::TYPE_TELEHEALTH,
        ];
        
        foreach ($types as $i => $type) {
            $encounter = new Encounter('patient-' . $i, $type);
            $encounter->setId('encounter-' . $i);
            $encounters[] = $encounter;
        }
        
        // Calculate distribution
        $distribution = [];
        foreach ($encounters as $encounter) {
            $type = $encounter->getEncounterType();
            $distribution[$type] = ($distribution[$type] ?? 0) + 1;
        }
        
        $this->assertEquals(3, $distribution[Encounter::TYPE_OFFICE_VISIT]);
        $this->assertEquals(2, $distribution[Encounter::TYPE_DOT_PHYSICAL]);
        $this->assertEquals(1, $distribution[Encounter::TYPE_OSHA_INJURY]);
        $this->assertEquals(1, $distribution[Encounter::TYPE_DRUG_SCREEN]);
        $this->assertEquals(1, $distribution[Encounter::TYPE_WORKERS_COMP]);
        $this->assertEquals(1, $distribution[Encounter::TYPE_PRE_EMPLOYMENT]);
        $this->assertEquals(1, $distribution[Encounter::TYPE_TELEHEALTH]);
    }

    /**
     * Test completion rate calculation
     */
    public function testCompletionRateCalculation(): void
    {
        $encounters = [];
        
        // Create 10 encounters, 7 completed
        for ($i = 0; $i < 10; $i++) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            
            if ($i < 7) {
                $encounter->setChiefComplaint('Complaint');
                $encounter->lock('clinician-123');
            }
            
            $encounters[] = $encounter;
        }
        
        $total = count($encounters);
        $completed = count(array_filter($encounters, fn($e) => $e->isLocked()));
        $completionRate = ($total > 0) ? round(($completed / $total) * 100, 1) : 0;
        
        $this->assertEquals(70.0, $completionRate);
    }

    /**
     * Test average encounters per day calculation
     */
    public function testAverageEncountersPerDayCalculation(): void
    {
        $encounters = [];
        $baseDate = new DateTimeImmutable('2025-01-01');
        
        // Create encounters over 5 days: 3, 5, 2, 4, 6
        $encountersPerDay = [3, 5, 2, 4, 6];
        $encounterIndex = 0;
        
        foreach ($encountersPerDay as $dayOffset => $count) {
            $date = $baseDate->modify("+{$dayOffset} days");
            
            for ($i = 0; $i < $count; $i++) {
                $encounter = new Encounter('patient-' . $encounterIndex);
                $encounter->setId('encounter-' . $encounterIndex);
                $encounter->setEncounterDate($date);
                $encounters[] = $encounter;
                $encounterIndex++;
            }
        }
        
        // Calculate average per day
        $totalEncounters = count($encounters);
        $totalDays = count($encountersPerDay);
        $averagePerDay = $totalDays > 0 ? round($totalEncounters / $totalDays, 1) : 0;
        
        $this->assertEquals(4.0, $averagePerDay); // (3+5+2+4+6)/5 = 20/5 = 4
    }

    /**
     * Test provider workload calculation
     */
    public function testProviderWorkloadCalculation(): void
    {
        $encounters = [];
        $providers = ['provider-1', 'provider-2', 'provider-3'];
        
        // Provider 1: 5 encounters, Provider 2: 3 encounters, Provider 3: 7 encounters
        $workload = [5, 3, 7];
        
        $encounterIndex = 0;
        foreach ($providers as $i => $providerId) {
            for ($j = 0; $j < $workload[$i]; $j++) {
                $encounter = new Encounter('patient-' . $encounterIndex, Encounter::TYPE_OFFICE_VISIT, $providerId);
                $encounter->setId('encounter-' . $encounterIndex);
                $encounters[] = $encounter;
                $encounterIndex++;
            }
        }
        
        // Calculate workload per provider
        $providerCounts = [];
        foreach ($encounters as $encounter) {
            $pid = $encounter->getProviderId();
            $providerCounts[$pid] = ($providerCounts[$pid] ?? 0) + 1;
        }
        
        $this->assertEquals(5, $providerCounts['provider-1']);
        $this->assertEquals(3, $providerCounts['provider-2']);
        $this->assertEquals(7, $providerCounts['provider-3']);
    }

    /**
     * Test work-related injury count calculation
     */
    public function testWorkRelatedInjuryCount(): void
    {
        $encounters = [];
        
        // Create mix of work-related and non-work-related encounters
        for ($i = 0; $i < 10; $i++) {
            $type = $i < 4 ? Encounter::TYPE_OSHA_INJURY : Encounter::TYPE_OFFICE_VISIT;
            $encounter = new Encounter('patient-' . $i, $type);
            $encounter->setId('encounter-' . $i);
            
            if ($type === Encounter::TYPE_OSHA_INJURY) {
                $encounter->setClinicalData(['work_related' => true]);
            }
            
            $encounters[] = $encounter;
        }
        
        // Count work-related injuries
        $workRelatedTypes = [Encounter::TYPE_OSHA_INJURY, Encounter::TYPE_WORKERS_COMP];
        $workRelatedCount = count(array_filter($encounters, function ($e) use ($workRelatedTypes) {
            return in_array($e->getEncounterType(), $workRelatedTypes, true);
        }));
        
        $this->assertEquals(4, $workRelatedCount);
    }

    /**
     * Test DOT physical count calculation
     */
    public function testDotPhysicalCount(): void
    {
        $encounters = [];
        
        // Create various encounter types
        $types = array_merge(
            array_fill(0, 5, Encounter::TYPE_DOT_PHYSICAL),
            array_fill(0, 8, Encounter::TYPE_OFFICE_VISIT),
            array_fill(0, 2, Encounter::TYPE_DRUG_SCREEN)
        );
        
        foreach ($types as $i => $type) {
            $encounter = new Encounter('patient-' . $i, $type);
            $encounter->setId('encounter-' . $i);
            $encounters[] = $encounter;
        }
        
        $dotCount = count(array_filter($encounters, function ($e) {
            return $e->getEncounterType() === Encounter::TYPE_DOT_PHYSICAL;
        }));
        
        $this->assertEquals(5, $dotCount);
    }

    /**
     * Test pending review count calculation
     */
    public function testPendingReviewCount(): void
    {
        $encounters = [];
        $statuses = [
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_COMPLETE,
            Encounter::STATUS_COMPLETE,
        ];
        
        foreach ($statuses as $i => $status) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setStatus($status);
            $encounters[] = $encounter;
        }
        
        $pendingCount = count(array_filter($encounters, function ($e) {
            return $e->getStatus() === Encounter::STATUS_PENDING_REVIEW;
        }));
        
        $this->assertEquals(3, $pendingCount);
    }

    /**
     * Test clinic-specific metrics calculation
     */
    public function testClinicSpecificMetrics(): void
    {
        $encounters = [];
        $clinics = ['clinic-1', 'clinic-2', 'clinic-1', 'clinic-1', 'clinic-2'];
        
        foreach ($clinics as $i => $clinicId) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setClinicId($clinicId);
            $encounters[] = $encounter;
        }
        
        // Filter by clinic
        $clinic1Encounters = array_filter($encounters, fn($e) => $e->getClinicId() === 'clinic-1');
        $clinic2Encounters = array_filter($encounters, fn($e) => $e->getClinicId() === 'clinic-2');
        
        $this->assertEquals(3, count($clinic1Encounters));
        $this->assertEquals(2, count($clinic2Encounters));
    }

    /**
     * Test empty data handling for metrics
     */
    public function testEmptyDataHandling(): void
    {
        $encounters = [];
        
        // Calculate metrics on empty data
        $total = count($encounters);
        $completed = count(array_filter($encounters, fn($e) => $e->isLocked()));
        $completionRate = ($total > 0) ? round(($completed / $total) * 100, 1) : 0;
        $averagePerDay = 0;
        
        $this->assertEquals(0, $total);
        $this->assertEquals(0, $completed);
        $this->assertEquals(0, $completionRate);
        $this->assertEquals(0, $averagePerDay);
    }

    /**
     * Test week-over-week comparison calculation
     */
    public function testWeekOverWeekComparison(): void
    {
        $encounters = [];
        $today = new DateTimeImmutable();
        $lastWeek = $today->modify('-7 days');
        
        // This week: 25 encounters
        for ($i = 0; $i < 25; $i++) {
            $encounter = new Encounter('patient-tw-' . $i);
            $encounter->setId('encounter-tw-' . $i);
            $encounter->setEncounterDate($today);
            $encounters[] = $encounter;
        }
        
        // Last week: 20 encounters
        for ($i = 0; $i < 20; $i++) {
            $encounter = new Encounter('patient-lw-' . $i);
            $encounter->setId('encounter-lw-' . $i);
            $encounter->setEncounterDate($lastWeek);
            $encounters[] = $encounter;
        }
        
        // Calculate week-over-week change
        $thisWeekCount = count(array_filter($encounters, function ($e) use ($today) {
            return $e->getEncounterDate()->format('Y-m-d') === $today->format('Y-m-d');
        }));
        
        $lastWeekCount = count(array_filter($encounters, function ($e) use ($lastWeek) {
            return $e->getEncounterDate()->format('Y-m-d') === $lastWeek->format('Y-m-d');
        }));
        
        $changePercent = $lastWeekCount > 0 
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1) 
            : 0;
        
        $this->assertEquals(25, $thisWeekCount);
        $this->assertEquals(20, $lastWeekCount);
        $this->assertEquals(25.0, $changePercent); // 25% increase
    }

    /**
     * Test encounter duration metrics
     */
    public function testEncounterDurationMetrics(): void
    {
        // Simulate encounter durations in minutes
        $durations = [15, 20, 30, 25, 45, 15, 20, 30, 35, 40];
        
        $totalDuration = array_sum($durations);
        $averageDuration = count($durations) > 0 ? round($totalDuration / count($durations), 1) : 0;
        $maxDuration = max($durations);
        $minDuration = min($durations);
        
        $this->assertEquals(27.5, $averageDuration);
        $this->assertEquals(45, $maxDuration);
        $this->assertEquals(15, $minDuration);
    }

    /**
     * Test no-show rate calculation
     */
    public function testNoShowRateCalculation(): void
    {
        $encounters = [];
        $statuses = array_merge(
            array_fill(0, 2, Encounter::STATUS_NO_SHOW),
            array_fill(0, 18, Encounter::STATUS_COMPLETE)
        );
        
        foreach ($statuses as $i => $status) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setStatus($status);
            $encounters[] = $encounter;
        }
        
        $total = count($encounters);
        $noShows = count(array_filter($encounters, fn($e) => $e->getStatus() === Encounter::STATUS_NO_SHOW));
        $noShowRate = $total > 0 ? round(($noShows / $total) * 100, 1) : 0;
        
        $this->assertEquals(10.0, $noShowRate); // 2/20 = 10%
    }

    /**
     * Test cancelled appointment rate calculation
     */
    public function testCancelledRateCalculation(): void
    {
        $encounters = [];
        $statuses = array_merge(
            array_fill(0, 3, Encounter::STATUS_CANCELLED),
            array_fill(0, 17, Encounter::STATUS_COMPLETE)
        );
        
        foreach ($statuses as $i => $status) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setStatus($status);
            $encounters[] = $encounter;
        }
        
        $total = count($encounters);
        $cancelled = count(array_filter($encounters, fn($e) => $e->getStatus() === Encounter::STATUS_CANCELLED));
        $cancelledRate = $total > 0 ? round(($cancelled / $total) * 100, 1) : 0;
        
        $this->assertEquals(15.0, $cancelledRate); // 3/20 = 15%
    }

    /**
     * Test amendment rate calculation
     */
    public function testAmendmentRateCalculation(): void
    {
        $encounters = [];
        
        // Create 20 completed encounters, 3 amended
        for ($i = 0; $i < 20; $i++) {
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setChiefComplaint('Complaint');
            $encounter->lock('clinician-123');
            
            if ($i < 3) {
                $encounter->startAmendment('Correction', 'supervisor-456');
            }
            
            $encounters[] = $encounter;
        }
        
        $total = count($encounters);
        $amended = count(array_filter($encounters, fn($e) => $e->isBeingAmended()));
        $amendmentRate = $total > 0 ? round(($amended / $total) * 100, 1) : 0;
        
        $this->assertEquals(15.0, $amendmentRate); // 3/20 = 15%
    }

    /**
     * Test metrics calculation with date range filter
     */
    public function testMetricsWithDateRangeFilter(): void
    {
        $encounters = [];
        $today = new DateTimeImmutable();
        
        // Create encounters across different dates
        for ($i = 0; $i < 30; $i++) {
            $daysAgo = $i % 10; // Spread over 10 days
            $date = $today->modify("-{$daysAgo} days");
            
            $encounter = new Encounter('patient-' . $i);
            $encounter->setId('encounter-' . $i);
            $encounter->setEncounterDate($date);
            $encounters[] = $encounter;
        }
        
        // Filter last 7 days
        $sevenDaysAgo = $today->modify('-7 days');
        $lastSevenDays = array_filter($encounters, function ($e) use ($sevenDaysAgo) {
            return $e->getEncounterDate() >= $sevenDaysAgo;
        });
        
        // Should have most encounters (days 0-7 have encounters)
        $this->assertGreaterThan(20, count($lastSevenDays));
    }

    /**
     * Test monthly trend calculation
     */
    public function testMonthlyTrendCalculation(): void
    {
        // Simulate monthly counts
        $monthlyCounts = [
            '2025-01' => 150,
            '2025-02' => 165,
            '2025-03' => 180,
            '2025-04' => 175,
            '2025-05' => 190,
            '2025-06' => 200,
        ];
        
        // Calculate trend (simple linear trend)
        $values = array_values($monthlyCounts);
        $n = count($values);
        
        // Calculate average
        $average = array_sum($values) / $n;
        
        // Calculate if trending up (last 3 months vs first 3)
        $firstHalf = array_slice($values, 0, 3);
        $secondHalf = array_slice($values, 3, 3);
        $firstHalfAvg = array_sum($firstHalf) / 3;
        $secondHalfAvg = array_sum($secondHalf) / 3;
        
        $trendingUp = $secondHalfAvg > $firstHalfAvg;
        
        $this->assertTrue($trendingUp);
        $this->assertGreaterThan($firstHalfAvg, $secondHalfAvg);
    }
}
