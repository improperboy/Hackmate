<?php

/**
 * Blockchain Certificate Utility Class
 * Handles certificate generation, hashing, and verification
 */
class BlockchainCertificate {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate a unique certificate ID
     */
    public function generateCertificateId($participantData, $templateId) {
        $data = [
            'participant_id' => $participantData['id'],
            'participant_name' => $participantData['name'],
            'participant_email' => $participantData['email'],
            'template_id' => $templateId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        return hash('sha256', json_encode($data));
    }
    
    /**
     * Generate blockchain hash for certificate verification
     */
    public function generateBlockchainHash($certificateId, $certificateData) {
        $hashData = [
            'certificate_id' => $certificateId,
            'certificate_data' => $certificateData,
            'blockchain_version' => '1.0',
            'hash_algorithm' => 'sha256'
        ];
        
        return hash('sha256', json_encode($hashData));
    }
    
    /**
     * Verify certificate integrity
     */
    public function verifyCertificate($certificateId, $expectedHash) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT certificate_data, blockchain_hash 
                FROM blockchain_certificates 
                WHERE certificate_id = ?
            ");
            $stmt->execute([$certificateId]);
            $certificate = $stmt->fetch();
            
            if (!$certificate) {
                return ['valid' => false, 'reason' => 'Certificate not found'];
            }
            
            // Regenerate hash and compare
            $regeneratedHash = $this->generateBlockchainHash($certificateId, $certificate['certificate_data']);
            
            if ($regeneratedHash === $certificate['blockchain_hash'] && $certificate['blockchain_hash'] === $expectedHash) {
                return ['valid' => true, 'reason' => 'Certificate is authentic'];
            } else {
                return ['valid' => false, 'reason' => 'Certificate hash mismatch - possible tampering'];
            }
            
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => 'Verification error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate certificate data for PDF creation
     */
    public function prepareCertificateData($participant, $team, $template, $settings) {
        return [
            'participant_name' => $participant['name'],
            'participant_email' => $participant['email'],
            'team_name' => $team['name'] ?? 'Individual Participant',
            'hackathon_name' => $settings['hackathon_name'] ?? 'Hackathon',
            'hackathon_start_date' => $settings['hackathon_start_date'] ?? '',
            'hackathon_end_date' => $settings['hackathon_end_date'] ?? '',
            'template_name' => $template['name'],
            'issue_date' => date('Y-m-d'),
            'issue_timestamp' => date('Y-m-d H:i:s'),
            'certificate_version' => '1.0'
        ];
    }
    
    /**
     * Create certificate PDF (simplified version)
     * In a real implementation, you would use TCPDF, FPDF, or similar library
     */
    public function generateCertificatePDF($templatePath, $certificateData, $outputPath) {
        try {
            // For now, just copy the template
            // In a real implementation, you would:
            // 1. Load the PDF template
            // 2. Fill in the form fields or overlay text
            // 3. Save the customized PDF
            
            if (!copy($templatePath, $outputPath)) {
                throw new Exception('Failed to create certificate PDF');
            }
            
            // TODO: Implement actual PDF generation with participant data
            // This would require a PDF library like TCPDF or FPDF
            
            return true;
            
        } catch (Exception $e) {
            error_log('Certificate PDF generation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store certificate in blockchain (database)
     */
    public function storeCertificate($certificateData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO blockchain_certificates 
                (certificate_id, participant_id, team_id, template_id, participant_name, team_name, 
                 hackathon_name, issue_date, certificate_data, pdf_file_path, blockchain_hash) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $certificateData['certificate_id'],
                $certificateData['participant_id'],
                $certificateData['team_id'],
                $certificateData['template_id'],
                $certificateData['participant_name'],
                $certificateData['team_name'],
                $certificateData['hackathon_name'],
                $certificateData['issue_date'],
                json_encode($certificateData['data']),
                $certificateData['pdf_file_path'],
                $certificateData['blockchain_hash']
            ]);
            
        } catch (PDOException $e) {
            error_log('Certificate storage error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoke a certificate
     */
    public function revokeCertificate($certificateId, $adminId, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE blockchain_certificates 
                SET is_revoked = 1, revoked_at = NOW(), revoked_by = ?, revocation_reason = ?
                WHERE certificate_id = ?
            ");
            
            return $stmt->execute([$adminId, $reason, $certificateId]);
            
        } catch (PDOException $e) {
            error_log('Certificate revocation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get certificate statistics
     */
    public function getCertificateStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_certificates,
                    COUNT(CASE WHEN is_revoked = 0 THEN 1 END) as active_certificates,
                    COUNT(CASE WHEN is_revoked = 1 THEN 1 END) as revoked_certificates,
                    SUM(download_count) as total_downloads,
                    COUNT(DISTINCT participant_id) as unique_participants,
                    COUNT(DISTINCT template_id) as templates_used,
                    AVG(download_count) as avg_downloads_per_certificate
                FROM blockchain_certificates
            ");
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log('Certificate stats error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log verification attempt
     */
    public function logVerification($certificateId, $method, $input, $result, $responseTime = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO certificate_verification_logs 
                (certificate_id, verifier_ip, verifier_user_agent, verification_method, 
                 verification_input, verification_result, response_time_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $certificateId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $method,
                $input,
                $result,
                $responseTime
            ]);
            
        } catch (PDOException $e) {
            error_log('Verification logging error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate verification URL
     */
    public function generateVerificationUrl($certificateId, $baseUrl = '') {
        if (empty($baseUrl)) {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                     . '://' . $_SERVER['HTTP_HOST'];
        }
        
        return rtrim($baseUrl, '/') . '/verify_certificate.php?id=' . urlencode($certificateId);
    }
    
    /**
     * Validate certificate data before generation
     */
    public function validateCertificateData($participantId, $templateId) {
        $errors = [];
        
        // Check if participant exists
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'participant'");
            $stmt->execute([$participantId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Participant not found';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error checking participant';
        }
        
        // Check if template exists and is active
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM certificate_templates WHERE id = ? AND is_active = 1");
            $stmt->execute([$templateId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Template not found or inactive';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error checking template';
        }
        
        // Check if certificate already exists
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM blockchain_certificates WHERE participant_id = ? AND template_id = ?");
            $stmt->execute([$participantId, $templateId]);
            if ($stmt->fetch()) {
                $errors[] = 'Certificate already exists for this participant and template';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error checking existing certificates';
        }
        
        return $errors;
    }
}