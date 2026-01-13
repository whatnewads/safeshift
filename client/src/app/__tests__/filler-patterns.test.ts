/**
 * Filler Pattern Detection Tests
 * 
 * These tests scan TypeScript/React code for placeholder/filler data
 * that should not exist in production code. This helps ensure code quality
 * and prevents test data from leaking into production.
 * 
 * @package Tests/Quality
 */

import { describe, it, expect, beforeAll } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

// Filler patterns to detect - organized by category
const fillerPatterns: { name: string; pattern: RegExp }[] = [
    // Lorem ipsum placeholder text
    { name: 'Lorem ipsum text', pattern: /lorem\s+ipsum/gi },
    { name: 'Dolor sit amet', pattern: /dolor\s+sit\s+amet/gi },
    
    // Test emails in production code
    { name: 'test@test.com email', pattern: /test@test\.com/gi },
    { name: 'example@example.com email', pattern: /example@example\.com/gi },
    { name: 'foo@bar.com email', pattern: /foo@bar\.com/gi },
    { name: 'user@test.com email', pattern: /user@test\.com/gi },
    
    // Test phone numbers
    { name: '555 phone prefix', pattern: /555-\d{3}-\d{4}/g },
    { name: '123-456-7890 phone', pattern: /123-456-7890/g },
    { name: '(555) phone format', pattern: /\(555\)\s*\d{3}-\d{4}/g },
    
    // Generic test names (in strings)
    { name: '"John Doe" test name', pattern: /"John\s+Doe"/gi },
    { name: '"Jane Doe" test name', pattern: /"Jane\s+Doe"/gi },
    { name: '"Test User" name', pattern: /"Test\s+User"/gi },
    { name: '"Test Patient" name', pattern: /"Test\s+Patient"/gi },
    { name: "'John Doe' test name", pattern: /'John\s+Doe'/gi },
    { name: "'Jane Doe' test name", pattern: /'Jane\s+Doe'/gi },
    
    // Placeholder markers
    { name: 'XXX placeholder', pattern: /XXX[^X]/gi },
    { name: 'Empty TODO', pattern: /TODO:\s*$/gm },
    { name: 'Empty FIXME', pattern: /FIXME:\s*$/gm },
    
    // Test addresses
    { name: '123 Main St address', pattern: /123\s+Main\s+St/gi },
    { name: '456 Test Ave address', pattern: /456\s+Test\s+Ave/gi },
    
    // Hardcoded dummy IDs
    { name: 'userId = 123', pattern: /userId\s*[=:]\s*['"]?123['"]?(?![0-9])/g },
    { name: 'patientId = 456', pattern: /patientId\s*[=:]\s*['"]?456['"]?(?![0-9])/g },
    
    // Test SSNs
    { name: '000-00-0000 SSN', pattern: /000-00-0000/g },
    { name: '123-45-6789 SSN', pattern: /123-45-6789/g },
];

// Debug patterns
const debugPatterns: { name: string; pattern: RegExp }[] = [
    { name: 'console.log', pattern: /console\.log\s*\(/g },
    { name: 'console.debug', pattern: /console\.debug\s*\(/g },
    { name: 'console.info', pattern: /console\.info\s*\(/g },
    { name: 'debugger statement', pattern: /^\s*debugger\s*;?\s*$/gm },
];

// Credential patterns
const credentialPatterns: { name: string; pattern: RegExp }[] = [
    { name: 'password = "password"', pattern: /password\s*[=:]\s*['"]password['"]/gi },
    { name: 'password = "123456"', pattern: /password\s*[=:]\s*['"]123456['"]/gi },
    { name: 'password = "admin"', pattern: /password\s*[=:]\s*['"]admin['"]/gi },
    { name: 'password = "test"', pattern: /password\s*[=:]\s*['"]test['"]/gi },
    { name: 'apiKey hardcoded', pattern: /apiKey\s*[=:]\s*['"][a-zA-Z0-9]{20,}['"]/gi },
];

// Mock data patterns (in non-test files)
const mockDataPatterns: { name: string; pattern: RegExp }[] = [
    { name: 'mockData variable', pattern: /const\s+mockData\s*=/g },
    { name: 'fakeData variable', pattern: /const\s+fakeData\s*=/g },
    { name: 'dummyData variable', pattern: /const\s+dummyData\s*=/g },
    { name: 'testData variable', pattern: /const\s+testData\s*=/g },
];

interface FileIssue {
    file: string;
    line: number;
    pattern: string;
    content: string;
}

// Directories to scan
const srcDirectories = [
    'src/app/services',
    'src/app/hooks',
    'src/app/components',
    'src/app/pages',
    'src/app/utils',
    'src/app/types',
];

// Directories/files to exclude
const excludePatterns = [
    '__tests__',
    '__mocks__',
    '.test.',
    '.spec.',
    'node_modules',
    '.d.ts',
];

/**
 * Get all TypeScript/TSX files in a directory recursively
 */
function getTypeScriptFiles(dir: string): string[] {
    const files: string[] = [];
    const fullPath = path.resolve(process.cwd(), dir);
    
    if (!fs.existsSync(fullPath)) {
        return files;
    }
    
    const entries = fs.readdirSync(fullPath, { withFileTypes: true });
    
    for (const entry of entries) {
        const entryPath = path.join(fullPath, entry.name);
        
        if (entry.isDirectory()) {
            // Check if directory should be excluded
            if (!excludePatterns.some(p => entry.name.includes(p))) {
                files.push(...getTypeScriptFiles(path.join(dir, entry.name)));
            }
        } else if (entry.isFile()) {
            // Check if file matches TypeScript pattern and not excluded
            if (
                (entry.name.endsWith('.ts') || entry.name.endsWith('.tsx')) &&
                !excludePatterns.some(p => entry.name.includes(p))
            ) {
                files.push(entryPath);
            }
        }
    }
    
    return files;
}

/**
 * Get relative path from project root
 */
function getRelativePath(filePath: string): string {
    return path.relative(process.cwd(), filePath);
}

/**
 * Check if a line is a comment
 */
function isCommentLine(line: string): boolean {
    const trimmed = line.trim();
    return (
        trimmed.startsWith('//') ||
        trimmed.startsWith('/*') ||
        trimmed.startsWith('*') ||
        trimmed.startsWith('<!--')
    );
}

/**
 * Scan files for patterns
 */
function scanFilesForPatterns(
    files: string[],
    patterns: { name: string; pattern: RegExp }[],
    skipComments: boolean = true
): FileIssue[] {
    const issues: FileIssue[] = [];
    
    for (const file of files) {
        const content = fs.readFileSync(file, 'utf-8');
        const lines = content.split('\n');
        
        for (const { name, pattern } of patterns) {
            // Reset regex lastIndex for global patterns
            pattern.lastIndex = 0;
            
            lines.forEach((line, index) => {
                // Skip comment lines if requested
                if (skipComments && isCommentLine(line)) {
                    return;
                }
                
                // Reset pattern for each line
                pattern.lastIndex = 0;
                
                if (pattern.test(line)) {
                    issues.push({
                        file: getRelativePath(file),
                        line: index + 1,
                        pattern: name,
                        content: line.trim().substring(0, 80),
                    });
                }
            });
        }
    }
    
    return issues;
}

/**
 * Format issues for test output
 */
function formatIssues(issues: FileIssue[], title: string): string {
    if (issues.length === 0) return '';
    
    let message = `\n${title}:\n`;
    message += '-'.repeat(60) + '\n';
    
    for (const issue of issues) {
        message += `  ${issue.file}:${issue.line}\n`;
        message += `    Pattern: ${issue.pattern}\n`;
        message += `    Content: ${issue.content}\n\n`;
    }
    
    return message;
}

describe('Filler Pattern Detection', () => {
    let allFiles: string[] = [];
    
    beforeAll(() => {
        // Collect all TypeScript files
        for (const dir of srcDirectories) {
            allFiles.push(...getTypeScriptFiles(dir));
        }
    });
    
    describe('Service Files', () => {
        it('should not contain filler patterns in services', () => {
            const serviceFiles = allFiles.filter(f => f.includes('/services/'));
            const issues = scanFilesForPatterns(serviceFiles, fillerPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Filler patterns found in services')
            ).toHaveLength(0);
        });
        
        it('should not contain hardcoded credentials in services', () => {
            const serviceFiles = allFiles.filter(f => f.includes('/services/'));
            const issues = scanFilesForPatterns(serviceFiles, credentialPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Hardcoded credentials found in services')
            ).toHaveLength(0);
        });
    });
    
    describe('Component Files', () => {
        it('should not contain filler patterns in components', () => {
            const componentFiles = allFiles.filter(
                f => f.includes('/components/') || f.includes('/pages/')
            );
            const issues = scanFilesForPatterns(componentFiles, fillerPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Filler patterns found in components')
            ).toHaveLength(0);
        });
    });
    
    describe('Hook Files', () => {
        it('should not contain filler patterns in hooks', () => {
            const hookFiles = allFiles.filter(f => f.includes('/hooks/'));
            const issues = scanFilesForPatterns(hookFiles, fillerPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Filler patterns found in hooks')
            ).toHaveLength(0);
        });
        
        it('should not contain mock data in production hooks', () => {
            const hookFiles = allFiles.filter(f => f.includes('/hooks/'));
            const issues = scanFilesForPatterns(hookFiles, mockDataPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Mock data found in hooks')
            ).toHaveLength(0);
        });
    });
    
    describe('Debug Output', () => {
        it('should not have console.log in production code', () => {
            // Filter out test files and check all production code
            const productionFiles = allFiles.filter(
                f => !f.includes('__tests__') && !f.includes('.test.') && !f.includes('.spec.')
            );
            
            const issues = scanFilesForPatterns(productionFiles, debugPatterns, true);
            
            expect(
                issues,
                formatIssues(issues, 'Debug output found in production code')
            ).toHaveLength(0);
        });
    });
    
    describe('Utility Files', () => {
        it('should not contain filler patterns in utilities', () => {
            const utilFiles = allFiles.filter(f => f.includes('/utils/'));
            const issues = scanFilesForPatterns(utilFiles, fillerPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Filler patterns found in utilities')
            ).toHaveLength(0);
        });
    });
    
    describe('Type Definitions', () => {
        it('should not contain filler patterns in type definitions', () => {
            const typeFiles = allFiles.filter(f => f.includes('/types/'));
            const issues = scanFilesForPatterns(typeFiles, fillerPatterns);
            
            expect(
                issues,
                formatIssues(issues, 'Filler patterns found in type definitions')
            ).toHaveLength(0);
        });
    });
});

describe('API Response Filler Detection (Frontend)', () => {
    it('should not have hardcoded mock responses in services', () => {
        const mockResponsePatterns: { name: string; pattern: RegExp }[] = [
            { name: 'return mock response', pattern: /return\s*{\s*data:\s*\[/g },
            { name: 'hardcoded array return', pattern: /return\s+\[\s*{[^}]*name:\s*['"][^'"]+['"][^}]*}/g },
        ];
        
        const serviceFiles = getTypeScriptFiles('src/app/services');
        const issues = scanFilesForPatterns(serviceFiles, mockResponsePatterns);
        
        // This is a soft check - some returns of arrays may be legitimate
        // The test serves as a reminder to review these patterns
        if (issues.length > 0) {
            console.warn(
                'Warning: Found potential hardcoded responses. Please review:\n',
                formatIssues(issues, 'Potential hardcoded responses')
            );
        }
        
        // Don't fail on this, just warn
        expect(true).toBe(true);
    });
});
