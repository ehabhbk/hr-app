<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
use Dompdf\Dompdf;
use Dompdf\Options;
require __DIR__ . '/vendor/autoload.php';

function render($family, $fileName) {
    $options = new Options();
    $options->set('fontDir', __DIR__ . '/storage/fonts');
    $options->set('fontCache', __DIR__ . '/storage/fonts');
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', $family);
    $html = '<html dir="rtl"><head><meta charset="UTF-8"><style>
    body { font-family: "' . $family . '", sans-serif; direction: rtl; }
    .cell { border:1px solid #ccc; padding:4px; }
    </style></head><body>
    <h1>بنك الخرطوم</h1>
    <p>كشف تحويل مرتبات - الموظف محمد أحمد</p>
    <table><tr><td class="cell">اسم الموظف</td><td class="cell">صافي المرتب</td></tr></table>
    </body></html>';
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $path = __DIR__ . "/test_{$fileName}.pdf";
    file_put_contents($path, $dompdf->output());
    echo "{$family}: " . filesize($path) . " bytes\n";
}
render('DejaVu Sans', 'dejavu');
render('Amiri', 'amiri');