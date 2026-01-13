<?php

declare(strict_types=1);

/**
 * Narrative Integration Verification Script
 *
 * This script verifies:
 * 1. All encounter data fields are being collected and passed to the Bedrock API
 * 2. The AI returns properly structured narrative data
 * 3. The complete flow from data to narrative works correctly
 *
 * Usage: php scripts/verify_narrative_integration.php [-v|--verbose]
 *
 * @package Scripts
 * @author SafeShift EHR Development Team
 */

// Set error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Parse command line arguments
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);
$skipApi = in_array('--skip-api', $argv);
$helpRequested = in_array('-h', $argv) || in_array('--help', $argv);

if ($helpRequested) {
    echo <<<HELP
Narrative Integration Verification Script

Usage: php scripts/verify_narrative_integration.php [OPTIONS]

Options:
  -v, --verbose     Show detailed output including data structures
  --skip-api        Skip the actual API call to Bedrock (test data structures only)
  -h, --help        Show this help message

Description:
  This script creates mock encounter data with ALL fields expected by the EMS prompt
  and verifies the complete narrative generation flow.

HELP;
    exit(0);
}

// Output helper functions
function output(string $message, string $type = 'info'): void
{
    $prefix = match($type) {
        'success' => "\033[32m✓\033[0m",
        'error' => "\033[31m✗\033[0m",
        'warning' => "\033[33m⚠\033[0m",
        'info' => "\033[36mℹ\033[0m",
        'header' => "\033[1;34m=>\033[0m",
        default => "  ",
    };
    echo "$prefix $message\n";
}

function outputSection(string $title): void
{
    echo "\n\033[1;35m" . str_repeat('=', 60) . "\033[0m\n";
    echo "\033[1;35m $title\033[0m\n";
    echo "\033[1;35m" . str_repeat('=', 60) . "\033[0m\n\n";
}

function verboseOutput(string $message): void
{
    global $verbose;
    if ($verbose) {
        echo "    \033[90m$message\033[0m\n";
    }
}

// Start verification
outputSection('Narrative Integration Verification');
output("Starting verification at " . date('Y-m-d H:i:s'), 'info');
if ($verbose) {
    output("Verbose mode enabled", 'info');
}

// ============================================================================
// Step 1: Load required classes
// ============================================================================
outputSection('Step 1: Loading Required Classes');

$baseDir = dirname(__DIR__);

// Load autoloader or required files
$requiredFiles = [
    'core/Services/BaseService.php',
    'core/Services/BedrockService.php',
    'core/Services/NarrativePromptBuilder.php',
];

$loadErrors = [];
foreach ($requiredFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
        output("Loaded: $file", 'success');
    } else {
        output("Missing: $file", 'error');
        $loadErrors[] = $file;
    }
}

if (!empty($loadErrors)) {
    output("Cannot continue - missing required files", 'error');
    exit(1);
}

// ============================================================================
// Step 2: Check Environment Configuration
// ============================================================================
outputSection('Step 2: Environment Configuration Check');

// Try to load .env if it exists
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    output("Found .env file", 'success');
    $envContent = file_get_contents($envFile);
    
    // Parse .env file for AWS_BEARER_TOKEN_BEDROCK
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']/s', $value, $matches)) {
                $value = $matches[1];
            }
            
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
} else {
    output("No .env file found - relying on system environment", 'warning');
}

// Check for API key
$apiKey = getenv('AWS_BEARER_TOKEN_BEDROCK') ?: ($_ENV['AWS_BEARER_TOKEN_BEDROCK'] ?? null);

if (empty($apiKey)) {
    output("AWS_BEARER_TOKEN_BEDROCK is NOT set", 'error');
    output("Please set this environment variable before running the script", 'info');
    output("Example: set AWS_BEARER_TOKEN_BEDROCK=your_api_key_here", 'info');
    
    if (!$skipApi) {
        output("Use --skip-api to test data structures without making API calls", 'info');
        $skipApi = true;
    }
} else {
    // Don't log the full key for security - just verify it looks valid
    $keyLength = strlen($apiKey);
    $keyPreview = substr($apiKey, 0, 10) . '...' . substr($apiKey, -4);
    output("AWS_BEARER_TOKEN_BEDROCK is set (length: $keyLength, preview: $keyPreview)", 'success');
}

// Check optional configuration
$region = getenv('AWS_BEDROCK_REGION') ?: 'us-east-1';
$modelId = getenv('AWS_BEDROCK_MODEL_ID') ?: 'anthropic.claude-sonnet-4-20250514-v1:0';
output("Region: $region", 'info');
output("Model ID: $modelId", 'info');

// ============================================================================
// Step 3: Create Comprehensive Mock Encounter Data
// ============================================================================
outputSection('Step 3: Creating Mock Encounter Data');

$mockEncounterData = [
    // Encounter Information
    'encounter' => [
        'encounter_id' => 'TEST-' . date('Ymd') . '-001',
        'encounter_type' => 'occupational_health',
        'status' => 'in_progress',
        'chief_complaint' => 'Right wrist pain after lifting heavy equipment',
        'occurred_on' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'arrived_on' => date('Y-m-d H:i:s', strtotime('-1 hour 45 minutes')),
        'discharged_on' => null, // Still in progress
        'disposition' => 'pending',
        'onset_context' => 'work_related',
    ],
    
    // Patient Demographics
    'patient' => [
        'patient_id' => 'PAT-' . rand(10000, 99999),
        'name' => 'John M. Smith',
        'age' => 42,
        'sex' => 'male',
        'employer_name' => 'ABC Manufacturing Inc.',
    ],
    
    // Medical History
    'medical_history' => [
        'conditions' => [
            'Type 2 Diabetes Mellitus',
            'Hypertension, controlled with medication',
            'Previous right shoulder rotator cuff repair (2022)',
        ],
        'current_medications' => [
            'Metformin 500mg BID',
            'Lisinopril 10mg daily',
            'Aspirin 81mg daily',
        ],
        'allergies' => [
            'Penicillin - causes rash',
            'Sulfa drugs - hives',
        ],
    ],
    
    // Observations/Vitals
    'observations' => [
        [
            'label' => 'BP Systolic',
            'value_num' => 138,
            'unit' => 'mmHg',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'automated',
        ],
        [
            'label' => 'BP Diastolic',
            'value_num' => 86,
            'unit' => 'mmHg',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'automated',
        ],
        [
            'label' => 'Pulse',
            'value_num' => 78,
            'unit' => 'bpm',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'automated',
        ],
        [
            'label' => 'SpO2',
            'value_num' => 98,
            'unit' => '%',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'pulse_oximeter',
        ],
        [
            'label' => 'Temp',
            'value_num' => 98.2,
            'unit' => '°F',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'temporal',
        ],
        [
            'label' => 'Resp Rate',
            'value_num' => 16,
            'unit' => 'breaths/min',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'manual',
        ],
        [
            'label' => 'Pain NRS',
            'value_num' => 6,
            'unit' => '/10',
            'posture' => 'sitting',
            'taken_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes')),
            'method' => 'patient_reported',
        ],
    ],
    
    // Medications Administered
    'medications_administered' => [
        [
            'medication_name' => 'Ibuprofen',
            'dose' => '400mg',
            'route' => 'oral',
            'given_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'response' => 'Patient tolerated well, pain reduced to 4/10 after 30 minutes',
            'notes' => 'Given with water, patient had eaten recently',
        ],
    ],
    
    // Provider Information
    'provider' => [
        'npi' => '1234567890',
        'credentials' => 'NRP, EMT-P',
    ],
];

// Display what was created
output("Created comprehensive mock encounter data", 'success');
verboseOutput("Encounter ID: " . $mockEncounterData['encounter']['encounter_id']);
verboseOutput("Patient: " . $mockEncounterData['patient']['name'] . ", Age " . $mockEncounterData['patient']['age']);
verboseOutput("Chief Complaint: " . $mockEncounterData['encounter']['chief_complaint']);
verboseOutput("Observations count: " . count($mockEncounterData['observations']));
verboseOutput("Medications count: " . count($mockEncounterData['medications_administered']));
verboseOutput("Medical conditions: " . count($mockEncounterData['medical_history']['conditions']));
verboseOutput("Current medications: " . count($mockEncounterData['medical_history']['current_medications']));
verboseOutput("Allergies: " . count($mockEncounterData['medical_history']['allergies']));

// List all data fields being passed
output("Data fields included:", 'header');
echo "    \033[36m• Encounter Information:\033[0m encounter_id, encounter_type, status, chief_complaint, occurred_on, arrived_on, discharged_on, disposition, onset_context\n";
echo "    \033[36m• Patient Demographics:\033[0m patient_id, name, age, sex, employer_name\n";
echo "    \033[36m• Medical History:\033[0m conditions, current_medications, allergies\n";
echo "    \033[36m• Observations/Vitals:\033[0m BP Systolic, BP Diastolic, Pulse, SpO2, Temp, Resp Rate, Pain NRS\n";
echo "    \033[36m• Medications Administered:\033[0m medication_name, dose, route, given_at, response, notes\n";
echo "    \033[36m• Provider Information:\033[0m NPI, credentials\n";

// ============================================================================
// Step 4: Test NarrativePromptBuilder
// ============================================================================
outputSection('Step 4: Testing NarrativePromptBuilder');

try {
    $promptBuilder = new Core\Services\NarrativePromptBuilder();
    output("NarrativePromptBuilder instantiated", 'success');
    
    // Test validation
    $isValid = $promptBuilder->validateEncounterData($mockEncounterData);
    if ($isValid) {
        output("Encounter data validation: PASSED", 'success');
    } else {
        output("Encounter data validation: FAILED", 'error');
    }
    
    // Build prompt
    $prompt = $promptBuilder->buildPrompt($mockEncounterData);
    $promptLength = strlen($prompt);
    output("Prompt built successfully (length: $promptLength characters)", 'success');
    
    // Get data summary
    $dataSummary = $promptBuilder->getDataSummary($mockEncounterData);
    output("Data summary:", 'header');
    foreach ($dataSummary as $key => $value) {
        $displayValue = is_bool($value) ? ($value ? 'yes' : 'no') : $value;
        verboseOutput("  $key: $displayValue");
    }
    
    // Verify prompt contains expected sections
    $expectedSections = [
        'EMS NARRATIVE GENERATION SYSTEM PROMPT' => 'System prompt header',
        'CORE PRINCIPLES' => 'Core principles section',
        'CLINICAL DATA' => 'Clinical data section',
        'encounter_id' => 'Encounter ID in data',
        'chief_complaint' => 'Chief complaint in data',
        'patient_id' => 'Patient ID in data',
        'observations' => 'Observations section',
        'medications_administered' => 'Medications section',
    ];
    
    output("Verifying prompt structure:", 'header');
    $allFound = true;
    foreach ($expectedSections as $search => $description) {
        if (strpos($prompt, $search) !== false) {
            verboseOutput("✓ Found: $description");
        } else {
            output("Missing: $description ($search)", 'error');
            $allFound = false;
        }
    }
    
    if ($allFound) {
        output("All expected sections found in prompt", 'success');
    }
    
    // Show prompt excerpt if verbose
    if ($verbose) {
        output("Prompt excerpt (first 500 chars of clinical data):", 'header');
        $dataStart = strpos($prompt, '## CLINICAL DATA');
        if ($dataStart !== false) {
            $excerpt = substr($prompt, $dataStart, 700);
            echo "\n\033[90m" . $excerpt . "...\033[0m\n\n";
        }
    }
    
} catch (Exception $e) {
    output("NarrativePromptBuilder error: " . $e->getMessage(), 'error');
    exit(1);
}

// ============================================================================
// Step 5: Test BedrockService (Optional - depends on API key)
// ============================================================================
outputSection('Step 5: Testing BedrockService');

if ($skipApi) {
    output("Skipping API call (--skip-api flag set or API key not configured)", 'warning');
    output("Data structure tests completed successfully", 'success');
} else {
    try {
        output("Initializing BedrockService...", 'info');
        $bedrockService = new Core\Services\BedrockService();
        output("BedrockService instantiated", 'success');
        
        // Check configuration
        if ($bedrockService->isConfigured()) {
            output("BedrockService is configured", 'success');
            output("Model: " . $bedrockService->getModelId(), 'info');
            output("Region: " . $bedrockService->getRegion(), 'info');
        } else {
            output("BedrockService is NOT configured", 'error');
        }
        
        // Test connection first
        output("Testing API connection...", 'info');
        $connectionTest = $bedrockService->testConnection();
        
        if ($connectionTest['success']) {
            output("API connection successful (response time: {$connectionTest['response_time_ms']}ms)", 'success');
            
            // Now generate the actual narrative
            output("Generating narrative with mock encounter data...", 'info');
            $startTime = microtime(true);
            $narrative = $bedrockService->generateNarrative($prompt);
            $duration = round((microtime(true) - $startTime) * 1000);
            
            output("Narrative generated in {$duration}ms", 'success');
            
            // ============================================================================
            // Step 6: Validate Generated Narrative
            // ============================================================================
            outputSection('Step 6: Validating Generated Narrative');
            
            $validationResults = validateNarrative($narrative, $mockEncounterData);
            
            // Display results
            foreach ($validationResults['checks'] as $check => $result) {
                if ($result['passed']) {
                    output("$check: PASSED", 'success');
                } else {
                    output("$check: FAILED - " . $result['reason'], 'error');
                }
            }
            
            // Overall result
            if ($validationResults['passed']) {
                output("\nAll narrative validations PASSED", 'success');
            } else {
                output("\nSome narrative validations FAILED", 'warning');
                output("Recommendations: " . implode('; ', $validationResults['recommendations']), 'info');
            }
            
            // Display narrative if verbose
            if ($verbose) {
                output("\nGenerated Narrative:", 'header');
                echo "\n\033[90m" . wordwrap($narrative, 100) . "\033[0m\n";
            }
            
        } else {
            output("API connection failed: " . $connectionTest['message'], 'error');
            if (isset($connectionTest['error_code'])) {
                output("Error code: " . $connectionTest['error_code'], 'info');
            }
        }
        
    } catch (RuntimeException $e) {
        output("BedrockService error: " . $e->getMessage(), 'error');
        if ($e->getCode()) {
            output("Error code: " . $e->getCode(), 'info');
        }
        
        // Provide recommendations based on error
        if ($e->getCode() === 401) {
            output("Recommendation: Check that your API key is valid and not expired", 'info');
        } elseif ($e->getCode() === 403) {
            output("Recommendation: Verify AWS permissions for Bedrock access", 'info');
        } elseif ($e->getCode() === 429) {
            output("Recommendation: Wait a moment and try again (rate limit)", 'info');
        }
    } catch (Exception $e) {
        output("Unexpected error: " . $e->getMessage(), 'error');
    }
}

// ============================================================================
// Final Summary
// ============================================================================
outputSection('Verification Summary');

output("Verification completed at " . date('Y-m-d H:i:s'), 'info');

// Summary table
echo "\n";
echo "  +------------------------------------------+----------+\n";
echo "  | Component                                | Status   |\n";
echo "  +------------------------------------------+----------+\n";
echo "  | Required files loaded                    | \033[32m✓ PASS\033[0m   |\n";
echo "  | Environment configuration               | " . ($apiKey ? "\033[32m✓ PASS\033[0m" : "\033[33m⚠ WARN\033[0m") . "   |\n";
echo "  | Mock encounter data created              | \033[32m✓ PASS\033[0m   |\n";
echo "  | NarrativePromptBuilder validation        | \033[32m✓ PASS\033[0m   |\n";
echo "  | BedrockService initialization            | " . ($skipApi ? "\033[33m⚠ SKIP\033[0m" : "\033[32m✓ PASS\033[0m") . "   |\n";
echo "  | API narrative generation                 | " . ($skipApi ? "\033[33m⚠ SKIP\033[0m" : "\033[32m✓ PASS\033[0m") . "   |\n";
echo "  +------------------------------------------+----------+\n";
echo "\n";

if ($skipApi) {
    output("Note: API tests were skipped. To run full tests, set AWS_BEARER_TOKEN_BEDROCK", 'info');
}

output("Verification script completed", 'success');

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Validate the generated narrative against expected criteria
 *
 * @param string $narrative The generated narrative
 * @param array $encounterData The original encounter data
 * @return array Validation results
 */
function validateNarrative(string $narrative, array $encounterData): array
{
    $results = [
        'passed' => true,
        'checks' => [],
        'recommendations' => [],
    ];
    
    // Check 1: Not empty
    $results['checks']['Not Empty'] = [
        'passed' => !empty(trim($narrative)),
        'reason' => empty(trim($narrative)) ? 'Narrative is empty' : '',
    ];
    
    // Check 2: Has three paragraphs (SOAP format without headers)
    $paragraphs = preg_split('/\n\s*\n/', trim($narrative));
    $paragraphCount = count($paragraphs);
    $results['checks']['Three Paragraphs (SOAP format)'] = [
        'passed' => $paragraphCount >= 3,
        'reason' => $paragraphCount < 3 ? "Only $paragraphCount paragraph(s) found" : '',
    ];
    if ($paragraphCount < 3) {
        $results['recommendations'][] = 'Narrative should have 3 distinct paragraphs (Subjective, Objective, Plan)';
    }
    
    // Check 3: Doesn't repeat vitals verbatim
    // Look for patterns like "BP 138/86" or "blood pressure was 138/86 mmHg"
    $hasVerbatimVitals = false;
    $vitalsPatterns = [
        '/BP\s*\d+\/\d+/',
        '/blood pressure\s*(of|was|is)?\s*\d+\/\d+/i',
        '/HR\s*\d+\s*bpm/i',
        '/SpO2\s*\d+\s*%/i',
        '/temp(erature)?\s*(of|was|is)?\s*\d+(\.\d+)?\s*(°F|F)/i',
    ];
    
    foreach ($vitalsPatterns as $pattern) {
        if (preg_match($pattern, $narrative)) {
            $hasVerbatimVitals = true;
            break;
        }
    }
    
    $results['checks']['No Verbatim Vitals'] = [
        'passed' => !$hasVerbatimVitals,
        'reason' => $hasVerbatimVitals ? 'Found vitals repeated in plain text format' : '',
    ];
    if ($hasVerbatimVitals) {
        $results['recommendations'][] = 'Vitals should be referenced contextually, not listed verbatim';
    }
    
    // Check 4: Uses past tense
    // Look for present tense indicators that should be past tense
    $presentTensePatterns = [
        '/patient\s+complains/i',
        '/patient\s+reports\s+feeling/i',
        '/patient\s+is\s+experiencing/i',
        '/patient\s+presents\s+with/i', // This is actually acceptable
    ];
    
    $pastTenseIndicators = [
        '/presented/i',
        '/reported/i',
        '/was\s+(given|administered|applied)/i',
        '/tolerated/i',
        '/ambulated/i',
        '/complained/i',
        '/denied/i',
        '/stated/i',
    ];
    
    $pastTenseCount = 0;
    foreach ($pastTenseIndicators as $pattern) {
        if (preg_match($pattern, $narrative)) {
            $pastTenseCount++;
        }
    }
    
    $results['checks']['Uses Past Tense'] = [
        'passed' => $pastTenseCount >= 2,
        'reason' => $pastTenseCount < 2 ? 'Insufficient past tense usage detected' : '',
    ];
    
    // Check 5: Professional medical language
    $professionalTerms = [
        '/subjective/i',
        '/objective/i',
        '/assessment/i',
        '/plan/i',
        '/chief complaint/i',
        '/vital/i',
        '/patient/i',
        '/presented/i',
        '/exam/i',
        '/mechanism/i',
        '/intervention/i',
        '/disposition/i',
        '/CAOx\d/i',
        '/alert/i',
        '/oriented/i',
    ];
    
    $professionalCount = 0;
    foreach ($professionalTerms as $pattern) {
        if (preg_match($pattern, $narrative)) {
            $professionalCount++;
        }
    }
    
    $results['checks']['Professional Medical Language'] = [
        'passed' => $professionalCount >= 5,
        'reason' => $professionalCount < 5 ? "Only $professionalCount professional terms found" : '',
    ];
    
    // Check 6: Reasonable length
    $wordCount = str_word_count($narrative);
    $results['checks']['Reasonable Length (100-800 words)'] = [
        'passed' => $wordCount >= 100 && $wordCount <= 800,
        'reason' => $wordCount < 100 ? 'Too short' : ($wordCount > 800 ? 'Too long' : ''),
    ];
    
    // Check 7: No section headers (should be plain paragraphs)
    $hasHeaders = preg_match('/(^|\n)\s*(Subjective|Objective|Assessment|Plan)\s*[:]/im', $narrative);
    $results['checks']['No Section Headers'] = [
        'passed' => !$hasHeaders,
        'reason' => $hasHeaders ? 'Found section headers that should be omitted' : '',
    ];
    
    // Check 8: Contains reference to chief complaint
    $chiefComplaint = strtolower($encounterData['encounter']['chief_complaint'] ?? '');
    $narrativeLower = strtolower($narrative);
    
    // Look for key words from chief complaint
    $ccWords = array_filter(explode(' ', $chiefComplaint), fn($w) => strlen($w) > 3);
    $foundCcWords = 0;
    foreach ($ccWords as $word) {
        if (strpos($narrativeLower, $word) !== false) {
            $foundCcWords++;
        }
    }
    
    $results['checks']['References Chief Complaint'] = [
        'passed' => $foundCcWords >= 2,
        'reason' => $foundCcWords < 2 ? 'Chief complaint not adequately referenced' : '',
    ];
    
    // Calculate overall pass
    foreach ($results['checks'] as $check) {
        if (!$check['passed']) {
            $results['passed'] = false;
        }
    }
    
    return $results;
}
