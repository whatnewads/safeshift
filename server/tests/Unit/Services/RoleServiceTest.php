<?php
/**
 * RoleService Unit Tests
 * 
 * Tests for the RoleService class which handles role mapping,
 * permission checking, and role hierarchy in SafeShift EHR.
 * 
 * @package SafeShift\Tests\Unit\Services
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Model\Services\RoleService;

/**
 * @covers \Model\Services\RoleService
 */
class RoleServiceTest extends TestCase
{
    // =========================================================================
    // UI Role Mapping Tests
    // =========================================================================
    
    /**
     * @test
     * @dataProvider providerRoleMappingProvider
     */
    public function toUiRoleMapsProviderRolesCorrectly(string $backendRole, string $expectedUiRole): void
    {
        $this->assertEquals($expectedUiRole, RoleService::toUiRole($backendRole));
    }
    
    public static function providerRoleMappingProvider(): array
    {
        return [
            'pclinician maps to provider' => ['pclinician', 'provider'],
            'dclinician maps to technician' => ['dclinician', 'technician'],
            '1clinician maps to registration' => ['1clinician', 'registration'],
        ];
    }
    
    /**
     * @test
     * @dataProvider adminRoleMappingProvider
     */
    public function toUiRoleMapsAdminRolesCorrectly(string $backendRole, string $expectedUiRole): void
    {
        $this->assertEquals($expectedUiRole, RoleService::toUiRole($backendRole));
    }
    
    public static function adminRoleMappingProvider(): array
    {
        return [
            'Admin maps to super-admin' => ['Admin', 'super-admin'],
            'cadmin maps to admin' => ['cadmin', 'admin'],
            'tadmin maps to admin' => ['tadmin', 'admin'],
        ];
    }
    
    /**
     * @test
     * @dataProvider otherRoleMappingProvider
     */
    public function toUiRoleMapsOtherRolesCorrectly(string $backendRole, string $expectedUiRole): void
    {
        $this->assertEquals($expectedUiRole, RoleService::toUiRole($backendRole));
    }
    
    public static function otherRoleMappingProvider(): array
    {
        return [
            'Manager maps to manager' => ['Manager', 'manager'],
            'QA maps to qa' => ['QA', 'qa'],
            'PrivacyOfficer maps to privacy-officer' => ['PrivacyOfficer', 'privacy-officer'],
            'SecurityOfficer maps to security-officer' => ['SecurityOfficer', 'security-officer'],
        ];
    }
    
    /**
     * @test
     */
    public function toUiRoleReturnsDefaultForUnknownRole(): void
    {
        $result = RoleService::toUiRole('unknown_role');
        
        // Should return default (provider)
        $this->assertEquals('provider', $result);
    }
    
    /**
     * @test
     * @dataProvider legacyRoleMappingProvider
     */
    public function toUiRoleHandlesLegacyRoleNames(string $legacyRole, string $expectedUiRole): void
    {
        $this->assertEquals($expectedUiRole, RoleService::toUiRole($legacyRole));
    }
    
    public static function legacyRoleMappingProvider(): array
    {
        return [
            'Clinician maps to provider' => ['Clinician', 'provider'],
            'Doctor maps to provider' => ['Doctor', 'provider'],
            'Nurse maps to provider' => ['Nurse', 'provider'],
            'SuperAdmin maps to super-admin' => ['SuperAdmin', 'super-admin'],
            'FrontDesk maps to registration' => ['FrontDesk', 'registration'],
            'LabTech maps to technician' => ['LabTech', 'technician'],
        ];
    }
    
    // =========================================================================
    // Permission Tests
    // =========================================================================
    
    /**
     * @test
     */
    public function getPermissionsReturnsArrayForValidRole(): void
    {
        $permissions = RoleService::getPermissions('pclinician');
        
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
    }
    
    /**
     * @test
     */
    public function getPermissionsReturnsEmptyArrayForUnknownRole(): void
    {
        $permissions = RoleService::getPermissions('unknown_role');
        
        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }
    
    /**
     * @test
     */
    public function getPermissionsReturnsCorrectPermissionsForPclinician(): void
    {
        $permissions = RoleService::getPermissions('pclinician');
        
        $this->assertContains('patient.view', $permissions);
        $this->assertContains('patient.create', $permissions);
        $this->assertContains('patient.edit', $permissions);
        $this->assertContains('encounter.view', $permissions);
        $this->assertContains('encounter.create', $permissions);
        $this->assertContains('encounter.sign', $permissions);
    }
    
    /**
     * @test
     */
    public function getPermissionsReturnsWildcardForAdmin(): void
    {
        $permissions = RoleService::getPermissions('Admin');
        
        $this->assertContains('*', $permissions);
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsTrueForValidPermission(): void
    {
        $this->assertTrue(RoleService::hasPermission('pclinician', 'patient.view'));
        $this->assertTrue(RoleService::hasPermission('pclinician', 'encounter.create'));
        $this->assertTrue(RoleService::hasPermission('pclinician', 'encounter.sign'));
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsFalseForInvalidPermission(): void
    {
        $this->assertFalse(RoleService::hasPermission('QA', 'patient.delete'));
        $this->assertFalse(RoleService::hasPermission('1clinician', 'system.configure'));
        $this->assertFalse(RoleService::hasPermission('dclinician', 'user.manage'));
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsTrueForAdminWithAnyPermission(): void
    {
        // Admin has '*' wildcard which should match any permission
        $this->assertTrue(RoleService::hasPermission('Admin', 'patient.view'));
        $this->assertTrue(RoleService::hasPermission('Admin', 'patient.delete'));
        $this->assertTrue(RoleService::hasPermission('Admin', 'system.configure'));
        $this->assertTrue(RoleService::hasPermission('Admin', 'any.random.permission'));
    }
    
    /**
     * @test
     */
    public function hasPermissionHandlesWildcardPermissions(): void
    {
        // cadmin has 'patient.*' which should match any patient permission
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.view'));
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.create'));
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.edit'));
        $this->assertTrue(RoleService::hasPermission('cadmin', 'patient.delete'));
        
        // But not other resource types
        $this->assertFalse(RoleService::hasPermission('cadmin', 'system.configure'));
    }
    
    /**
     * @test
     */
    public function hasPermissionReturnsFalseForUnknownRole(): void
    {
        $this->assertFalse(RoleService::hasPermission('unknown_role', 'patient.view'));
    }
    
    // =========================================================================
    // Display Name Tests
    // =========================================================================
    
    /**
     * @test
     * @dataProvider displayNameProvider
     */
    public function getDisplayNameReturnsCorrectName(string $role, string $expectedName): void
    {
        $this->assertEquals($expectedName, RoleService::getDisplayName($role));
    }
    
    public static function displayNameProvider(): array
    {
        return [
            '1clinician' => ['1clinician', 'Intake Clinician'],
            'dclinician' => ['dclinician', 'Drug Screen Technician'],
            'pclinician' => ['pclinician', 'Clinical Provider'],
            'cadmin' => ['cadmin', 'Clinic Administrator'],
            'tadmin' => ['tadmin', 'Technical Administrator'],
            'Admin' => ['Admin', 'System Administrator'],
            'Manager' => ['Manager', 'Manager'],
            'QA' => ['QA', 'Quality Assurance'],
            'PrivacyOfficer' => ['PrivacyOfficer', 'Privacy Officer'],
            'SecurityOfficer' => ['SecurityOfficer', 'Security Officer'],
        ];
    }
    
    /**
     * @test
     */
    public function getDisplayNameReturnsCapitalizedRoleForUnknown(): void
    {
        $result = RoleService::getDisplayName('customrole');
        
        $this->assertEquals('Customrole', $result);
    }
    
    // =========================================================================
    // Dashboard Route Tests
    // =========================================================================
    
    /**
     * @test
     * @dataProvider dashboardRouteProvider
     */
    public function getDashboardRouteReturnsCorrectPath(string $role, string $expectedRoute): void
    {
        $this->assertEquals($expectedRoute, RoleService::getDashboardRoute($role));
    }
    
    public static function dashboardRouteProvider(): array
    {
        return [
            '1clinician' => ['1clinician', '/dashboard/registration'],
            'dclinician' => ['dclinician', '/dashboard/technician'],
            'pclinician' => ['pclinician', '/dashboard/provider'],
            'cadmin' => ['cadmin', '/dashboard/admin'],
            'tadmin' => ['tadmin', '/dashboard/admin'],
            'Admin' => ['Admin', '/dashboard/super-admin'],
            'Manager' => ['Manager', '/dashboard/manager'],
            'QA' => ['QA', '/dashboard/qa'],
            'PrivacyOfficer' => ['PrivacyOfficer', '/dashboard/privacy'],
            'SecurityOfficer' => ['SecurityOfficer', '/dashboard/security'],
        ];
    }
    
    /**
     * @test
     */
    public function getDashboardRouteReturnsDefaultForUnknownRole(): void
    {
        $route = RoleService::getDashboardRoute('unknown_role');
        
        $this->assertEquals('/dashboard', $route);
    }
    
    // =========================================================================
    // Role Validation Tests
    // =========================================================================
    
    /**
     * @test
     */
    public function isValidRoleReturnsTrueForValidRoles(): void
    {
        $this->assertTrue(RoleService::isValidRole('pclinician'));
        $this->assertTrue(RoleService::isValidRole('Admin'));
        $this->assertTrue(RoleService::isValidRole('Manager'));
        $this->assertTrue(RoleService::isValidRole('QA'));
    }
    
    /**
     * @test
     */
    public function isValidRoleReturnsFalseForInvalidRoles(): void
    {
        $this->assertFalse(RoleService::isValidRole('invalid_role'));
        $this->assertFalse(RoleService::isValidRole(''));
        $this->assertFalse(RoleService::isValidRole('ADMIN')); // Case sensitive
    }
    
    // =========================================================================
    // Admin/SuperAdmin Check Tests
    // =========================================================================
    
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
        $this->assertFalse(RoleService::isAdmin('1clinician'));
    }
    
    /**
     * @test
     */
    public function isSuperAdminReturnsTrueOnlyForAdmin(): void
    {
        $this->assertTrue(RoleService::isSuperAdmin('Admin'));
    }
    
    /**
     * @test
     */
    public function isSuperAdminReturnsFalseForOtherAdminRoles(): void
    {
        $this->assertFalse(RoleService::isSuperAdmin('cadmin'));
        $this->assertFalse(RoleService::isSuperAdmin('tadmin'));
    }
    
    /**
     * @test
     */
    public function isSuperAdminReturnsFalseForNonAdminRoles(): void
    {
        $this->assertFalse(RoleService::isSuperAdmin('pclinician'));
        $this->assertFalse(RoleService::isSuperAdmin('Manager'));
    }
    
    // =========================================================================
    // Role Hierarchy Tests
    // =========================================================================
    
    /**
     * @test
     */
    public function getRoleLevelReturnsCorrectHierarchy(): void
    {
        // Admin should be highest
        $this->assertEquals(10, RoleService::getRoleLevel('Admin'));
        
        // Manager should be high but below Admin
        $this->assertLessThan(
            RoleService::getRoleLevel('Admin'),
            RoleService::getRoleLevel('Manager')
        );
        
        // Clinical roles should be lower than Manager
        $this->assertLessThan(
            RoleService::getRoleLevel('Manager'),
            RoleService::getRoleLevel('pclinician')
        );
        
        // 1clinician should be lowest clinical role
        $this->assertLessThanOrEqual(
            RoleService::getRoleLevel('pclinician'),
            RoleService::getRoleLevel('1clinician')
        );
    }
    
    /**
     * @test
     */
    public function getRoleLevelReturnsZeroForUnknownRole(): void
    {
        $this->assertEquals(0, RoleService::getRoleLevel('unknown_role'));
    }
    
    /**
     * @test
     */
    public function compareRolesReturnsCorrectComparison(): void
    {
        // Admin > Manager
        $this->assertGreaterThan(0, RoleService::compareRoles('Admin', 'Manager'));
        
        // Manager > pclinician
        $this->assertGreaterThan(0, RoleService::compareRoles('Manager', 'pclinician'));
        
        // pclinician == pclinician
        $this->assertEquals(0, RoleService::compareRoles('pclinician', 'pclinician'));
        
        // 1clinician < Manager
        $this->assertLessThan(0, RoleService::compareRoles('1clinician', 'Manager'));
    }
    
    // =========================================================================
    // Role List Tests
    // =========================================================================
    
    /**
     * @test
     */
    public function getAllRolesReturnsAllBackendRoles(): void
    {
        $roles = RoleService::getAllRoles();
        
        $this->assertIsArray($roles);
        $this->assertContains('pclinician', $roles);
        $this->assertContains('Admin', $roles);
        $this->assertContains('Manager', $roles);
        $this->assertContains('QA', $roles);
        $this->assertContains('1clinician', $roles);
        $this->assertContains('dclinician', $roles);
    }
    
    /**
     * @test
     */
    public function getAllUiRolesReturnsAllUiRoles(): void
    {
        $roles = RoleService::getAllUiRoles();
        
        $this->assertIsArray($roles);
        $this->assertContains('provider', $roles);
        $this->assertContains('super-admin', $roles);
        $this->assertContains('admin', $roles);
        $this->assertContains('manager', $roles);
        $this->assertContains('qa', $roles);
        $this->assertContains('registration', $roles);
        $this->assertContains('technician', $roles);
    }
    
    /**
     * @test
     */
    public function getAllPermissionsReturnsAllPermissions(): void
    {
        $permissions = RoleService::getAllPermissions();
        
        $this->assertIsArray($permissions);
        $this->assertContains('patient.view', $permissions);
        $this->assertContains('patient.*', $permissions);
        $this->assertContains('encounter.sign', $permissions);
        $this->assertContains('*', $permissions);
    }
    
    // =========================================================================
    // User Formatting Tests
    // =========================================================================
    
    /**
     * @test
     */
    public function formatUserWithRoleReturnsCorrectStructure(): void
    {
        $user = [
            'user_id' => '123',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'pclinician',
            'clinic_id' => 'clinic_1',
            'two_factor_enabled' => true,
            'last_login' => '2024-01-01 12:00:00',
        ];
        
        $formatted = RoleService::formatUserWithRole($user);
        
        $this->assertEquals('123', $formatted['id']);
        $this->assertEquals('testuser', $formatted['username']);
        $this->assertEquals('pclinician', $formatted['role']);
        $this->assertEquals('provider', $formatted['uiRole']);
        $this->assertEquals('Clinical Provider', $formatted['displayRole']);
        $this->assertEquals('/dashboard/provider', $formatted['dashboardRoute']);
        $this->assertIsArray($formatted['permissions']);
        $this->assertTrue($formatted['twoFactorEnabled']);
    }
    
    /**
     * @test
     */
    public function formatUserWithRoleHandlesPrimaryRoleObject(): void
    {
        $user = [
            'id' => '456',
            'username' => 'adminuser',
            'primary_role' => [
                'slug' => 'Admin',
                'name' => 'System Administrator'
            ],
        ];
        
        $formatted = RoleService::formatUserWithRole($user);
        
        $this->assertEquals('Admin', $formatted['role']);
        $this->assertEquals('super-admin', $formatted['uiRole']);
    }
}
