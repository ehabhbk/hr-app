<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
use Dompdf\Dompdf;
use Dompdf\Options;
require __DIR__ . '/vendor/autoload.php';

$options = new Options();
$options->set('fontDir', __DIR__ . '/storage/fonts');
$options->set('fontCache', __DIR__ . '/storage/fonts');
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Amiri');

$html = '<html dir="rtl"><head><meta charset="UTF-8"><style>
body { font-family: "Amiri", sans-serif; direction: rtl; }
</style></head><body><h1>بنك الخرطوم - كشف تحويل مرتبات</h1><p>اسم الموظف: محمد أحمد</p></body></html>';

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();
file_put_contents(__DIR__ . '/test_rtl.pdf', $dompdf->output());
echo "PDF size: " . filesize(__DIR__ . '/test_rtl.pdf') . "\n";

// Extract text with pdftotext if available, or just note
echo "done\n";