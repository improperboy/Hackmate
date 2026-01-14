<?php
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set up DomPDF options
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);

// Initialize DomPDF
$dompdf = new Dompdf($options);

// Read the HTML content
$htmlContent = file_get_contents('hackmate_analysis.html');

// Add some PDF-specific styling
$pdfStyles = '
<style>
    body { 
        font-size: 10pt; 
        line-height: 1.4;
    }
    .header h1 { 
        font-size: 18pt; 
    }
    .section h2 { 
        font-size: 14pt; 
        page-break-before: auto;
    }
    .section h3 { 
        font-size: 12pt; 
    }
    .feature-grid { 
        display: block; 
    }
    .feature-card { 
        margin-bottom: 15px; 
        break-inside: avoid;
    }
    .tech-list {
        display: block;
    }
    .tech-category {
        margin-bottom: 15px;
        break-inside: avoid;
    }
    .stat-grid {
        display: block;
    }
    .stat-item {
        display: inline-block;
        margin: 5px;
        break-inside: avoid;
    }
    @page {
        margin: 2cm;
        size: A4;
    }
</style>';

// Insert PDF styles before closing head tag
$htmlContent = str_replace('</head>', $pdfStyles . '</head>', $htmlContent);

// Load HTML content
$dompdf->loadHtml($htmlContent);

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// Get current timestamp for filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "HackMate_Analysis_{$timestamp}.pdf";

// Output PDF
$dompdf->stream($filename, array("Attachment" => true));
?>
