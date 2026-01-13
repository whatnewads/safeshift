<?php
namespace Core\Services;

use Core\Database;
use Core\Services\BaseService;
use Core\Services\AuditService;
use Core\Services\EmailService;
use PDO;
use Exception;

class RegulatoryUpdateService extends BaseService
{
    private $auditService;
    private $emailService;
    private $openaiApiKey;
    
    // Document parsing executables
    private $pdfToTextPath = 'pdftotext'; // Adjust path as needed
    private $antivirusPath = 'clamscan'; // Adjust path as needed
    
    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
        $this->emailService = new EmailService();
        $this->openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? '';
    }
    
    /**
     * Upload and process a regulatory document
     */
    public function uploadDocument($fileData, $metadata, $uploadedBy)
    {
        try {
            $this->db->beginTransaction();
            
            // Validate file
            $this->validateDocument($fileData);
            
            // Scan for viruses
            if (!$this->scanForViruses($fileData['tmp_name'])) {
                throw new Exception("File failed virus scan");
            }
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($fileData['name']);
            $uploadPath = UPLOAD_PATH . '/regulations/' . $filename;
            
            // Create directory if it doesn't exist
            if (!is_dir(dirname($uploadPath))) {
                mkdir(dirname($uploadPath), 0755, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
                throw new Exception("Failed to save uploaded file");
            }
            
            // Extract text from document
            $fullText = $this->extractTextFromDocument($uploadPath, $fileData['type']);
            
            // Create regulatory update record
            $updateId = $this->createRegulatoryUpdate($metadata, $uploadPath, $fullText, $uploadedBy);
            
            // Log the upload
            $this->auditService->log('upload', 'regulatory_updates', $updateId,
                "Uploaded regulatory document: " . $metadata['regulation_title']);
            
            $this->db->commit();
            
            return [
                'update_id' => $updateId,
                'filename' => $filename,
                'text_length' => strlen($fullText)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to upload regulatory document: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate AI summary of regulation
     */
    public function generateSummary($updateId)
    {
        try {
            // Get regulation text
            $stmt = $this->db->prepare("
                SELECT full_text, regulation_title
                FROM regulatory_updates
                WHERE update_id = :update_id
            ");
            $stmt->execute(['update_id' => $updateId]);
            $regulation = $stmt->fetch();
            
            if (!$regulation) {
                throw new Exception("Regulation not found");
            }
            
            // Call OpenAI API
            $summary = $this->callOpenAIForSummary($regulation['full_text'], $regulation['regulation_title']);
            
            // Update record with summary
            $stmt = $this->db->prepare("
                UPDATE regulatory_updates
                SET summary = :summary
                WHERE update_id = :update_id
            ");
            $stmt->execute([
                'update_id' => $updateId,
                'summary' => $summary
            ]);
            
            // Log the generation
            $this->auditService->log('generate', 'regulatory_updates', $updateId,
                "Generated AI summary for regulation");
            
            return $summary;
            
        } catch (Exception $e) {
            $this->logError("Failed to generate summary: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate implementation checklist
     */
    public function generateChecklist($updateId)
    {
        try {
            // Get regulation text
            $stmt = $this->db->prepare("
                SELECT full_text, regulation_title, regulation_agency
                FROM regulatory_updates
                WHERE update_id = :update_id
            ");
            $stmt->execute(['update_id' => $updateId]);
            $regulation = $stmt->fetch();
            
            if (!$regulation) {
                throw new Exception("Regulation not found");
            }
            
            // Call OpenAI API for checklist
            $checklistItems = $this->callOpenAIForChecklist(
                $regulation['full_text'], 
                $regulation['regulation_title'],
                $regulation['regulation_agency']
            );
            
            // Create checklist record
            $stmt = $this->db->prepare("
                INSERT INTO implementation_checklists (
                    checklist_id, update_id, checklist_items, completion_pct, created_at
                ) VALUES (
                    UUID(), :update_id, :checklist_items, 0, NOW()
                )
            ");
            
            $stmt->execute([
                'update_id' => $updateId,
                'checklist_items' => json_encode($checklistItems)
            ]);
            
            // Log the generation
            $this->auditService->log('generate', 'implementation_checklists', $updateId,
                "Generated implementation checklist with " . count($checklistItems) . " items");
            
            return $checklistItems;
            
        } catch (Exception $e) {
            $this->logError("Failed to generate checklist: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create training module from regulation
     */
    public function createTrainingModule($updateId, $assignedRoles, $dueDate)
    {
        try {
            $this->db->beginTransaction();
            
            // Get regulation summary
            $stmt = $this->db->prepare("
                SELECT regulation_title, summary
                FROM regulatory_updates
                WHERE update_id = :update_id
            ");
            $stmt->execute(['update_id' => $updateId]);
            $regulation = $stmt->fetch();
            
            if (!$regulation || !$regulation['summary']) {
                throw new Exception("Regulation summary not found");
            }
            
            // Generate training content
            $trainingContent = $this->generateTrainingContent(
                $regulation['regulation_title'],
                $regulation['summary']
            );
            
            // Create training record
            $stmt = $this->db->prepare("
                INSERT INTO regulation_trainings (
                    training_id, update_id, training_content, assigned_roles, due_date, created_at
                ) VALUES (
                    UUID(), :update_id, :training_content, :assigned_roles, :due_date, NOW()
                )
            ");
            
            $stmt->execute([
                'update_id' => $updateId,
                'training_content' => json_encode($trainingContent),
                'assigned_roles' => json_encode($assignedRoles),
                'due_date' => $dueDate
            ]);
            
            $trainingId = $this->db->lastInsertId();
            
            // Create training requirements for affected roles
            $this->createTrainingRequirements($trainingId, $regulation['regulation_title'], $assignedRoles);
            
            // Send notifications
            $this->notifyAffectedStaff($updateId, $assignedRoles, $dueDate);
            
            $this->db->commit();
            
            return $trainingId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to create training module: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update checklist item status
     */
    public function updateChecklistItem($updateId, $itemIndex, $updates)
    {
        try {
            $this->db->beginTransaction();
            
            // Get current checklist
            $stmt = $this->db->prepare("
                SELECT checklist_id, checklist_items
                FROM implementation_checklists
                WHERE update_id = :update_id
            ");
            $stmt->execute(['update_id' => $updateId]);
            $checklist = $stmt->fetch();
            
            if (!$checklist) {
                throw new Exception("Checklist not found");
            }
            
            $items = json_decode($checklist['checklist_items'], true);
            
            if (!isset($items[$itemIndex])) {
                throw new Exception("Invalid checklist item index");
            }
            
            // Update the item
            $items[$itemIndex] = array_merge($items[$itemIndex], $updates);
            
            // Calculate completion percentage
            $completedCount = 0;
            foreach ($items as $item) {
                if (isset($item['status']) && $item['status'] === 'completed') {
                    $completedCount++;
                }
            }
            $completionPct = round(($completedCount / count($items)) * 100, 2);
            
            // Update checklist
            $stmt = $this->db->prepare("
                UPDATE implementation_checklists
                SET checklist_items = :items,
                    completion_pct = :completion_pct,
                    updated_at = NOW()
                WHERE checklist_id = :checklist_id
            ");
            
            $stmt->execute([
                'checklist_id' => $checklist['checklist_id'],
                'items' => json_encode($items),
                'completion_pct' => $completionPct
            ]);
            
            // Log the update
            $this->auditService->log('update', 'implementation_checklists', $updateId,
                "Updated checklist item $itemIndex");
            
            $this->db->commit();
            
            return [
                'completion_pct' => $completionPct,
                'items' => $items
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to update checklist item: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all regulatory updates
     */
    public function getRegulatoryUpdates($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $query = "
                SELECT 
                    ru.*,
                    u.username as created_by_name,
                    ic.completion_pct,
                    ic.checklist_items,
                    rt.training_content,
                    rt.assigned_roles,
                    rt.due_date as training_due_date
                FROM regulatory_updates ru
                LEFT JOIN user u ON ru.created_by = u.id
                LEFT JOIN implementation_checklists ic ON ru.update_id = ic.update_id
                LEFT JOIN regulation_trainings rt ON ru.update_id = rt.update_id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['agency'])) {
                $query .= " AND ru.regulation_agency = :agency";
                $params['agency'] = $filters['agency'];
            }
            
            if (!empty($filters['type'])) {
                $query .= " AND ru.regulation_type = :type";
                $params['type'] = $filters['type'];
            }
            
            if (!empty($filters['date_from'])) {
                $query .= " AND ru.effective_date >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            
            // Order by effective date descending
            $query .= " ORDER BY ru.effective_date DESC, ru.created_at DESC";
            
            // Add pagination
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $updates = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM regulatory_updates WHERE 1=1";
            if (!empty($filters['agency'])) {
                $countQuery .= " AND regulation_agency = :agency";
            }
            if (!empty($filters['type'])) {
                $countQuery .= " AND regulation_type = :type";
            }
            
            $countStmt = $this->db->prepare($countQuery);
            foreach ($params as $key => $value) {
                if ($key !== 'limit' && $key !== 'offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];
            
            // Format updates
            foreach ($updates as &$update) {
                if ($update['checklist_items']) {
                    $update['checklist_items'] = json_decode($update['checklist_items'], true);
                }
                if ($update['training_content']) {
                    $update['training_content'] = json_decode($update['training_content'], true);
                }
                if ($update['assigned_roles']) {
                    $update['assigned_roles'] = json_decode($update['assigned_roles'], true);
                }
            }
            
            return [
                'updates' => $updates,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to get regulatory updates: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validate uploaded document
     */
    private function validateDocument($fileData)
    {
        $allowedTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword'
        ];
        
        if (!in_array($fileData['type'], $allowedTypes)) {
            throw new Exception("Invalid file type. Only PDF and DOCX files are allowed.");
        }
        
        // Max 10MB
        if ($fileData['size'] > 10 * 1024 * 1024) {
            throw new Exception("File size exceeds 10MB limit");
        }
        
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $fileData['error']);
        }
    }
    
    /**
     * Scan file for viruses using ClamAV
     */
    private function scanForViruses($filePath)
    {
        // Skip if antivirus not available
        if (!$this->antivirusPath || !is_executable($this->antivirusPath)) {
            return true;
        }
        
        $command = escapeshellcmd($this->antivirusPath) . ' ' . escapeshellarg($filePath);
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        // ClamAV returns 0 if no virus found
        return $returnVar === 0;
    }
    
    /**
     * Extract text from document
     */
    private function extractTextFromDocument($filePath, $mimeType)
    {
        switch ($mimeType) {
            case 'application/pdf':
                return $this->extractTextFromPDF($filePath);
                
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/msword':
                return $this->extractTextFromDOCX($filePath);
                
            default:
                throw new Exception("Unsupported file type for text extraction");
        }
    }
    
    /**
     * Extract text from PDF
     */
    private function extractTextFromPDF($filePath)
    {
        $outputFile = $filePath . '.txt';
        $command = escapeshellcmd($this->pdfToTextPath) . ' ' . 
                   escapeshellarg($filePath) . ' ' . 
                   escapeshellarg($outputFile);
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Failed to extract text from PDF");
        }
        
        $text = file_get_contents($outputFile);
        unlink($outputFile); // Clean up temp file
        
        return $text;
    }
    
    /**
     * Extract text from DOCX
     */
    private function extractTextFromDOCX($filePath)
    {
        $zip = new \ZipArchive();
        if (!$zip->open($filePath)) {
            throw new Exception("Failed to open DOCX file");
        }
        
        $text = '';
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml = $zip->getFromIndex($index);
            $dom = new \DOMDocument();
            $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            $text = strip_tags($dom->saveXML());
        }
        
        $zip->close();
        
        return $text;
    }
    
    /**
     * Create regulatory update record
     */
    private function createRegulatoryUpdate($metadata, $documentPath, $fullText, $uploadedBy)
    {
        $stmt = $this->db->prepare("
            INSERT INTO regulatory_updates (
                update_id, regulation_title, regulation_agency, regulation_type,
                effective_date, document_path, full_text, created_by, created_at
            ) VALUES (
                UUID(), :title, :agency, :type,
                :effective_date, :document_path, :full_text, :created_by, NOW()
            )
        ");
        
        $stmt->execute([
            'title' => $metadata['regulation_title'],
            'agency' => $metadata['regulation_agency'],
            'type' => $metadata['regulation_type'] ?? 'new_rule',
            'effective_date' => $metadata['effective_date'],
            'document_path' => $documentPath,
            'full_text' => $fullText,
            'created_by' => $uploadedBy
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $cleanBasename = preg_replace('/[^a-zA-Z0-9-_]/', '', $basename);
        
        return uniqid($cleanBasename . '_') . '.' . $extension;
    }
    
    /**
     * Call OpenAI API for summary
     */
    private function callOpenAIForSummary($regulationText, $title)
    {
        $prompt = "Summarize the following regulation in plain language suitable for healthcare staff. " .
                  "Include: 1) Executive summary (2-3 sentences), 2) Key changes or requirements (bullet points), " .
                  "3) Effective date, 4) Affected parties/roles. " .
                  "Keep the summary concise and focused on actionable information.\n\n" .
                  "Regulation Title: $title\n\n" .
                  "Regulation Text:\n$regulationText";
        
        $response = $this->callOpenAI($prompt);
        
        return $response;
    }
    
    /**
     * Call OpenAI API for checklist
     */
    private function callOpenAIForChecklist($regulationText, $title, $agency)
    {
        $prompt = "Generate an implementation checklist for the following $agency regulation. " .
                  "Create a JSON array of tasks with these fields:\n" .
                  "- task: Clear action item\n" .
                  "- responsible_party: Who should do it (e.g., 'Privacy Officer', 'IT Administrator', 'Clinical Director')\n" .
                  "- due_date_offset: Days from effective date\n" .
                  "- priority: 'high', 'medium', or 'low'\n\n" .
                  "Focus on specific, actionable tasks like updating policies, training staff, modifying forms, etc.\n\n" .
                  "Regulation Title: $title\n\n" .
                  "Regulation Text:\n$regulationText";
        
        $response = $this->callOpenAI($prompt, true);
        
        return json_decode($response, true);
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt, $jsonResponse = false)
    {
        if (empty($this->openaiApiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openaiApiKey,
            'Content-Type: application/json'
        ]);
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a regulatory compliance expert for healthcare organizations.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $data = [
            'model' => 'gpt-4',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 2000
        ];
        
        if ($jsonResponse) {
            $data['response_format'] = ['type' => 'json_object'];
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP $httpCode");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected OpenAI response format");
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * Generate training content from summary
     */
    private function generateTrainingContent($title, $summary)
    {
        // Parse summary to create structured training content
        $content = [
            'title' => $title,
            'summary' => $summary,
            'sections' => $this->parseSummaryIntoSections($summary),
            'quiz' => $this->generateQuizQuestions($title, $summary),
            'passing_score' => 80
        ];
        
        return $content;
    }
    
    /**
     * Parse summary into training sections
     */
    private function parseSummaryIntoSections($summary)
    {
        // Basic parsing - in production, use more sophisticated NLP
        $sections = [];
        
        // Split by common section markers
        $parts = preg_split('/(?:Executive Summary:|Key Changes:|Affected Parties:)/i', $summary);
        
        if (count($parts) > 1) {
            $sections[] = [
                'title' => 'Executive Summary',
                'content' => trim($parts[1] ?? '')
            ];
            
            $sections[] = [
                'title' => 'Key Changes',
                'content' => trim($parts[2] ?? '')
            ];
            
            $sections[] = [
                'title' => 'Affected Parties',
                'content' => trim($parts[3] ?? '')
            ];
        } else {
            // Fallback - use entire summary
            $sections[] = [
                'title' => 'Overview',
                'content' => $summary
            ];
        }
        
        return $sections;
    }
    
    /**
     * Generate quiz questions from summary
     */
    private function generateQuizQuestions($title, $summary)
    {
        // In production, use AI to generate contextual questions
        // For now, create template questions
        
        return [
            [
                'question' => "What is the main purpose of the $title regulation?",
                'type' => 'multiple_choice',
                'options' => [
                    'To improve patient safety',
                    'To reduce healthcare costs',
                    'To increase documentation requirements',
                    'To simplify billing procedures'
                ],
                'correct_answer' => 0
            ],
            [
                'question' => "Which roles are most affected by this regulation?",
                'type' => 'multiple_choice',
                'options' => [
                    'Only administrators',
                    'Only clinical staff',
                    'All healthcare workers',
                    'Only IT personnel'
                ],
                'correct_answer' => 2
            ],
            [
                'question' => "True or False: This regulation requires immediate implementation.",
                'type' => 'true_false',
                'correct_answer' => false
            ]
        ];
    }
    
    /**
     * Create training requirements for affected roles
     */
    private function createTrainingRequirements($trainingId, $title, $roles)
    {
        // Create a training requirement entry
        $stmt = $this->db->prepare("
            INSERT INTO training_requirements (
                requirement_id, training_name, training_description, 
                training_category, required_roles, recurrence_interval, 
                is_active, created_at
            ) VALUES (
                UUID(), :training_name, :description,
                'compliance', :required_roles, NULL,
                1, NOW()
            )
        ");
        
        $stmt->execute([
            'training_name' => "Regulatory Update: $title",
            'description' => "Training on new regulatory requirements from $title",
            'required_roles' => json_encode($roles)
        ]);
    }
    
    /**
     * Notify affected staff
     */
    private function notifyAffectedStaff($updateId, $roles, $dueDate)
    {
        // Get regulation details
        $stmt = $this->db->prepare("
            SELECT regulation_title, regulation_agency, summary, effective_date
            FROM regulatory_updates
            WHERE update_id = :update_id
        ");
        $stmt->execute(['update_id' => $updateId]);
        $regulation = $stmt->fetch();
        
        // Get affected users
        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id, u.email, u.first_name, u.last_name
            FROM user u
            JOIN userrole ur ON u.id = ur.user_id
            WHERE ur.role_slug IN ($placeholders)
            AND u.status = 'active'
            AND u.email IS NOT NULL
        ");
        $stmt->execute($roles);
        $users = $stmt->fetchAll();
        
        // Send email to each user
        foreach ($users as $user) {
            $this->sendRegulationNotificationEmail($user, $regulation, $dueDate);
        }
        
        // Log notification
        $this->auditService->log('notify', 'regulatory_updates', $updateId,
            "Notified " . count($users) . " staff members about new regulation");
    }
    
    /**
     * Send regulation notification email
     */
    private function sendRegulationNotificationEmail($user, $regulation, $trainingDueDate)
    {
        $subject = "New Regulatory Requirement: " . $regulation['regulation_title'];
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>New Regulatory Requirement</h2>
            
            <p>Hello {$user['first_name']},</p>
            
            <p>A new regulation has been published that affects your role:</p>
            
            <div style='background-color: #f0f0f0; padding: 15px; border-radius: 5px;'>
                <h3>{$regulation['regulation_title']}</h3>
                <p><strong>Agency:</strong> {$regulation['regulation_agency']}</p>
                <p><strong>Effective Date:</strong> {$regulation['effective_date']}</p>
            </div>
            
            <h4>Summary</h4>
            <p>{$regulation['summary']}</p>
            
            <p style='background-color: #fff3cd; padding: 10px; border-radius: 5px;'>
                <strong>Action Required:</strong> You must complete the associated training by {$trainingDueDate}.
            </p>
            
            <p>
                <a href='" . BASE_URL . "/dashboards/regulatory-updates.php' 
                   style='background-color: #007bff; color: white; padding: 10px 20px; 
                          text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Full Details
                </a>
            </p>
            
            <hr>
            <p style='font-size: 12px; color: #666;'>
                This is an automated notification from SafeShift EHR. 
                Please do not reply to this email.
            </p>
        </body>
        </html>
        ";
        
        try {
            $this->emailService->send($user['email'], $subject, $body);
        } catch (Exception $e) {
            $this->logError("Failed to send regulation notification to {$user['email']}: " . $e->getMessage());
        }
    }
}