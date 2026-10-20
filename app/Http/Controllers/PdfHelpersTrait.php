<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use TCPDF;

trait PdfHelpersTrait
{
    protected function getOrganizationData()
    {
        $org = Setting::where('key', 'organization')->first();
        return $org ? $org->value : [
            'name' => 'SCARLET HR',
            'address' => '',
            'phone' => '',
            'email' => '',
            'tax_number' => '',
            'logo' => null,
            'stamp' => null,
            'gm_signature' => null,
            'hr_signature' => null,
            'finance_signature' => null,
            'general_manager_name' => '',
            'hr_manager_name' => '',
            'finance_manager_name' => '',
        ];
    }

    protected function getFitImageSize($path, $boxW, $boxH)
    {
        $boxW = max(1, (int)$boxW);
        $boxH = max(1, (int)$boxH);
        $size = @getimagesize($path);
        if (!$size || empty($size[0]) || empty($size[1])) {
            return [$boxW, $boxH];
        }
        $ratio = $size[0] / $size[1];
        $w = (float)$boxW;
        $h = (float)$boxH;
        if ($w / $h > $ratio) {
            $w = $h * $ratio;
        } else {
            $h = $w / $ratio;
        }
        return [(int)round($w), (int)round($h)];
    }

    protected function getLogoHtml($org, $height = 60, $width = null)
    {
        $logoHtml = '';
        if (!empty($org['logo'])) {
            $logoPath = public_path('storage/' . $org['logo']);
            if ($logoPath && file_exists($logoPath) && is_readable($logoPath)) {
                $logoBase64 = @base64_encode(@file_get_contents($logoPath));
                if ($logoBase64) {
                    $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                    $logoMime = ($logoExt === 'jpg' || $logoExt === 'jpeg') ? 'image/jpeg' : 'image/png';
                    $boxW = $width ?: $height;
                    [$dispW, $dispH] = $this->getFitImageSize($logoPath, $boxW, $height);
                    $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoBase64 . '" style="width:' . $dispW . 'px;height:' . $dispH . 'px;">';
                }
            }
        }
        return $logoHtml;
    }

    protected function getStampHtml($org, $height = 70, $width = null)
    {
        $stampHtml = '';
        if (!empty($org['stamp'])) {
            $stampPath = public_path('storage/' . $org['stamp']);
            if ($stampPath && file_exists($stampPath) && is_readable($stampPath)) {
                $stampBase64 = @base64_encode(@file_get_contents($stampPath));
                if ($stampBase64) {
                    $stampExt = strtolower(pathinfo($stampPath, PATHINFO_EXTENSION));
                    $stampMime = ($stampExt === 'jpg' || $stampExt === 'jpeg') ? 'image/jpeg' : 'image/png';
                    $stampW = $width ?: $height;
                    [$dispW, $dispH] = $this->getFitImageSize($stampPath, $stampW, $height);
                    $stampHtml = '<img src="data:' . $stampMime . ';base64,' . $stampBase64 . '" style="width:' . $dispW . 'px;height:' . $dispH . 'px;opacity:0.85;">';
                }
            }
        }
        return $stampHtml;
    }

    protected function getPdfSettingsData($cfg = null)
    {
        if (is_array($cfg) && $cfg !== []) {
            return array_merge($this->defaultPdfSettingsData(), $cfg);
        }
        $row = Setting::where('key', 'pdf_settings')->first();
        return array_merge($this->defaultPdfSettingsData(), $row ? $row->value : []);
    }

    protected function defaultPdfSettingsData()
    {
        return [
            'logo_width' => 55,
            'logo_height' => 55,
            'logo_position' => 'center',
            'stamp_width' => 55,
            'stamp_height' => 55,
            'stamp_position' => 'center',
            'font_size' => 12,
            'line_height' => 2,
            'show_header' => true,
            'show_footer' => true,
            'show_stamp' => true,
            'show_signatures' => true,
            'show_gm_signature' => true,
            'show_hr_signature' => true,
            'show_finance_signature' => true,
            'header_text' => '',
            'footer_text' => '',
            'gm_title' => 'المدير العام',
            'hr_title' => 'مدير الموارد البشرية',
            'finance_title' => 'المدير المالي',
            'font_family' => 'dejavusans',
            'header_color' => '#1e3a5f',
            'title_color' => '#1e3a5f',
            'page_size' => 'A4',
            'page_orientation' => 'P',
            'show_reference' => true,
            'footer_left_text' => '',
            'footer_right_text' => '',
            'rows_per_page' => 25,
        ];
    }

    protected function getConfiguredFont($cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        $font = $cfg['font_family'] ?? 'dejavusans';
        $supported = ['dejavusans', 'dejavuserif', 'freeserif', 'freesans', 'aealarabiya', 'aefurat'];
        return in_array($font, $supported, true) ? $font : 'dejavusans';
    }

    protected function getPageFormat($cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        return (($cfg['page_size'] ?? 'A4') === 'Letter') ? 'LETTER' : 'A4';
    }

    protected function getPageOrientation($cfg = null, $default = 'P')
    {
        $cfg = $this->getPdfSettingsData($cfg);
        $o = $cfg['page_orientation'] ?? $default;
        return ($o === 'L' || $o === 'landscape') ? 'L' : 'P';
    }

    protected function getHeaderColor($cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        return $cfg['header_color'] ?? '#1e3a5f';
    }

    protected function getTitleColor($cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        return $cfg['title_color'] ?? '#1e3a5f';
    }

    protected function createTcpdf($cfg = null, $orientation = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        $orient = $orientation ?: $this->getPageOrientation($cfg);
        $format = $this->getPageFormat($cfg);
        return new \TCPDF($orient, 'mm', $format, true, 'UTF-8', false);
    }

    protected function applyPdfTheme($html, $cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        $font = $this->getConfiguredFont($cfg);
        $headerColor = $this->getHeaderColor($cfg);
        $titleColor = $this->getTitleColor($cfg);
        $html = str_replace('Amiri, dejavusans, sans-serif', $font . ', sans-serif', $html);
        $html = str_replace('Amiri, "DejaVu Sans", sans-serif', $font . ', sans-serif', $html);
        $html = str_replace('#1e3a5f', $headerColor, $html);
        $html = str_replace('#3b82f6', $titleColor, $html);
        return $html;
    }

    protected function paginateTableHtml($theadHtml, array $rowHtmls, $perPage, $totalRowHtml = '', $afterTbody = '')
    {
        $perPage = max(1, (int)$perPage);
        if (count($rowHtmls) <= $perPage) {
            return '<table>' . $theadHtml . '<tbody>' . implode('', $rowHtmls) . $totalRowHtml . '</tbody></table>' . $afterTbody;
        }
        $out = '';
        foreach (array_chunk($rowHtmls, $perPage) as $idx => $chunk) {
            $sep = $idx > 0 ? '<pagebreak>' : '';
            $out .= $sep . '<table>' . $theadHtml . '<tbody>' . implode('', $chunk) . '</tbody></table>';
        }
        if ($totalRowHtml !== '') {
            $out .= '<pagebreak><table>' . $theadHtml . '<tbody>' . $totalRowHtml . '</tbody></table>';
        }
        return $out . $afterTbody;
    }

    protected function getOfficialHeaderHtml($org, $cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);

        if (($cfg['show_header'] ?? true) === false) {
            return '';
        }

        $logoSize = (int)($cfg['logo_width'] ?? 55);
        $logoHeight = (int)($cfg['logo_height'] ?? 55);
        $logoPosition = $cfg['logo_position'] ?? 'center';

        $orgName = htmlspecialchars($org['name'] ?? 'SCARLET HR');
        $orgAddress = htmlspecialchars($org['address'] ?? '');
        $orgPhone = htmlspecialchars($org['phone'] ?? '');
        $orgEmail = htmlspecialchars($org['email'] ?? '');
        $taxNumber = htmlspecialchars($org['tax_number'] ?? '');
        $headerText = htmlspecialchars($cfg['header_text'] ?? '');
        $headerColor = $this->getHeaderColor($cfg);
        $titleColor = $this->getTitleColor($cfg);

        $logoHtml = $this->getLogoHtml($org, $logoHeight, $logoSize);
        $logoPlaceholder = '<div style="width:' . $logoSize . 'px;height:' . $logoHeight . 'px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:9px;font-weight:bold;">شعار</div>';
        $logoImg = $logoHtml ?: $logoPlaceholder;

        // Actual rendered logo width (aspect-preserved) so the side cell fits a wide logo.
        $logoCellW = $logoSize + 12;
        if (!empty($org['logo'])) {
            $logoPath = public_path('storage/' . $org['logo']);
            if (is_file($logoPath)) {
                [$fitW, $fitH] = $this->getFitImageSize($logoPath, $logoSize, $logoHeight);
                $logoCellW = $fitW + 12;
            }
        }

        $infoParts = array_filter([
            $orgAddress ? 'العنوان: ' . $orgAddress : null,
            $orgPhone ? 'هاتف: ' . $orgPhone : null,
            $orgEmail ? 'بريد: ' . $orgEmail : null,
        ]);

        $titleBlock = '
            <h1 style="font-size:20px;margin:0;color:' . $titleColor . ';font-weight:bold;letter-spacing:0.5px;">' . $orgName . '</h1>
            ' . ($headerText ? '<p style="font-size:10px;color:' . $headerColor . ';margin:2px 0 0 0;font-weight:bold;">' . $headerText . '</p>' : '') . '
            ' . (!empty($infoParts) ? '<p style="font-size:9px;color:#64748b;margin:3px 0 0 0;">' . implode(' &nbsp;|&nbsp; ', $infoParts) . '</p>' : '') . '
            ' . ($taxNumber ? '<p style="font-size:8px;color:#94a3b8;margin:2px 0 0 0;">الرقم الضريبي: ' . $taxNumber . '</p>' : '') . '
        ';

        if ($logoPosition === 'center') {
            $headerInner = '
                <div style="text-align:center;margin-bottom:8px;">' . $logoImg . '</div>
                <div style="text-align:center;">' . $titleBlock . '</div>';
        } else {
            // Three-column layout: the logo occupies either side, the title always stays centered.
            $logoCell = '<td style="width:' . $logoCellW . 'px;text-align:center;border:none;vertical-align:middle;padding:4px;">' . $logoImg . '</td>';
            $titleCell = '<td style="border:none;padding:4px 8px;vertical-align:middle;text-align:center;">' . $titleBlock . '</td>';
            $spacerCell = '<td style="width:' . $logoCellW . 'px;border:none;"></td>';

            $cols = ($logoPosition === 'left')
                ? $spacerCell . $titleCell . $logoCell
                : $logoCell . $titleCell . $spacerCell;

            $headerInner = '<table style="width:100%;border:none;border-collapse:collapse;"><tr>' . $cols . '</tr></table>';
        }

        return '
        <div style="margin-bottom:10px;">' . $headerInner . '</div>
        <div style="height:3px;background:linear-gradient(90deg,' . $headerColor . ',#3b82f6,#6366f1,#3b82f6,' . $headerColor . ');margin:8px 0 15px 0;border-radius:2px;"></div>';
    }

    protected function getSignatureImgHtml($path, $height = 70)
    {
        if (empty($path)) return '';
        $abs = public_path('storage/' . $path);
        if (!$abs || !file_exists($abs) || !is_readable($abs)) return '';
        $b64 = @base64_encode(@file_get_contents($abs));
        if (!$b64) return '';
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
        return '<img src="data:' . $mime . ';base64,' . $b64 . '" style="height:' . $height . 'px;object-fit:contain;">';
    }

    protected function getOfficialStampBlock($org, $cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);

        if (($cfg['show_stamp'] ?? true) === false) {
            return '';
        }

        $stampSize = (int)($cfg['stamp_width'] ?? 55);
        $stampHeight = (int)($cfg['stamp_height'] ?? 55);
        $headerColor = $this->getHeaderColor($cfg);

        $stampHtml = $this->getStampHtml($org, $stampHeight, $stampSize);
        $stampPlaceholder = '<div style="width:' . $stampSize . 'px;height:' . $stampHeight . 'px;background:#f8fafc;border:1.5px dashed ' . $headerColor . ';border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div>';
        $stampImg = $stampHtml ?: $stampPlaceholder;

        $align = in_array($cfg['stamp_position'] ?? 'center', ['left', 'right']) ? $cfg['stamp_position'] : 'center';

        return '
        <div style="margin-top:18px;text-align:' . $align . ';page-break-inside:avoid;">
            <div style="display:inline-block;text-align:center;">
                ' . $stampImg . '
                <p style="font-size:8px;color:#64748b;margin:4px 0 0 0;">ختم المؤسسة</p>
            </div>
        </div>';
    }

    protected function getOfficialSignatureBlock($org, $cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);

        if (($cfg['show_signatures'] ?? true) === false) {
            return '';
        }

        $showGM = ($cfg['show_gm_signature'] ?? true) !== false;
        $showHR = ($cfg['show_hr_signature'] ?? true) !== false;
        $showFinance = ($cfg['show_finance_signature'] ?? true) !== false;
        $gmTitle = htmlspecialchars($cfg['gm_title'] ?? 'المدير العام');
        $hrTitle = htmlspecialchars($cfg['hr_title'] ?? 'مدير الموارد البشرية');
        $financeTitle = htmlspecialchars($cfg['finance_title'] ?? 'المدير المالي');
        $gmName = ($org['general_manager_name'] ?? '') !== '' ? htmlspecialchars($org['general_manager_name']) : '';
        $hrName = ($org['hr_manager_name'] ?? '') !== '' ? htmlspecialchars($org['hr_manager_name']) : '';
        $financeName = ($org['finance_manager_name'] ?? '') !== '' ? htmlspecialchars($org['finance_manager_name']) : '';

        $gmSig = $this->getSignatureImgHtml($org['gm_signature'] ?? '');
        $hrSig = $this->getSignatureImgHtml($org['hr_signature'] ?? '');
        $financeSig = $this->getSignatureImgHtml($org['finance_signature'] ?? '');
        $headerColor = $this->getHeaderColor($cfg);

        $cells = '';
        $colWidth = $showFinance ? 30 : (($showGM && $showHR) ? 40 : 50);

        if ($showFinance) {
            $cells .= '
                <td style="width:' . $colWidth . '%;text-align:center;vertical-align:top;padding:6px;">
                    <div style="min-height:52px;display:flex;align-items:flex-end;justify-content:center;">' . ($financeSig ?: '<div style="border-bottom:1.5px solid ' . $headerColor . ';width:120px;"></div>') . '</div>
                    <p style="font-size:10px;font-weight:bold;color:' . $headerColor . ';margin:6px 0 2px 0;">' . $financeTitle . '</p>
                    ' . ($financeName ? '<p style="font-size:9px;color:#1e293b;margin:1px 0;font-weight:bold;">' . $financeName . '</p>' : '<p style="font-size:7px;color:#94a3b8;margin:1px 0;">الاسم: ....................</p>') . '
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التوقيع: ....................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التاريخ: ....................</p>
                </td>';
        }

        if ($showHR) {
            $cells .= '
                <td style="width:' . $colWidth . '%;text-align:center;vertical-align:top;padding:6px;">
                    <div style="min-height:52px;display:flex;align-items:flex-end;justify-content:center;">' . ($hrSig ?: '<div style="border-bottom:1.5px solid ' . $headerColor . ';width:120px;"></div>') . '</div>
                    <p style="font-size:10px;font-weight:bold;color:' . $headerColor . ';margin:6px 0 2px 0;">' . $hrTitle . '</p>
                    ' . ($hrName ? '<p style="font-size:9px;color:#1e293b;margin:1px 0;font-weight:bold;">' . $hrName . '</p>' : '<p style="font-size:7px;color:#94a3b8;margin:1px 0;">الاسم: ....................</p>') . '
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التوقيع: ....................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التاريخ: ....................</p>
                </td>';
        }

        if ($showGM) {
            $cells .= '
                <td style="width:' . $colWidth . '%;text-align:center;vertical-align:top;padding:6px;">
                    <div style="min-height:52px;display:flex;align-items:flex-end;justify-content:center;">' . ($gmSig ?: '<div style="border-bottom:1.5px solid ' . $headerColor . ';width:120px;"></div>') . '</div>
                    <p style="font-size:10px;font-weight:bold;color:' . $headerColor . ';margin:6px 0 2px 0;">' . $gmTitle . '</p>
                    ' . ($gmName ? '<p style="font-size:9px;color:#1e293b;margin:1px 0;font-weight:bold;">' . $gmName . '</p>' : '<p style="font-size:7px;color:#94a3b8;margin:1px 0;">الاسم: ....................</p>') . '
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التوقيع: ....................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:1px 0;">التاريخ: ....................</p>
                </td>';
        }

        if ($cells === '') return '';

        return '
        <div style="margin-top:18px;page-break-inside:avoid;">
            <table style="width:100%;border:none;border-collapse:collapse;">
                <tr>' . $cells . '</tr>
            </table>
        </div>';
    }

    protected function getOfficialFooterHtml($org, $cfg = null)
    {
        $cfg = $this->getPdfSettingsData($cfg);
        $orgName = htmlspecialchars($org['name'] ?? 'SCARLET HR');

        $stampBlock = $this->getOfficialStampBlock($org, $cfg);
        $sigBlock = $this->getOfficialSignatureBlock($org, $cfg);

        $footerInfo = '';
        if (($cfg['show_footer'] ?? true) !== false) {
            $footerText = htmlspecialchars($cfg['footer_text'] ?? '');
            $footerLeft = htmlspecialchars($cfg['footer_left_text'] ?? '');
            $footerRight = htmlspecialchars($cfg['footer_right_text'] ?? '');
            $headerColor = $this->getHeaderColor($cfg);
            $centerContent = '<p style="font-size:7px;color:#64748b;margin:0;">
                    ' . ($footerText ? '<strong>' . $footerText . '</strong> — ' : '') . '
                    <strong>' . $orgName . '</strong> — نظام إدارة الموارد البشرية | تاريخ الطباعة: ' . now()->format('Y-m-d H:i') . '
                </p>';
            if ($footerLeft !== '' || $footerRight !== '') {
                $footerInfo = '
                <div style="background:#f1f5f9;border-left:3px solid ' . $headerColor . ';border-right:3px solid ' . $headerColor . ';border-radius:6px;padding:6px 12px;margin-top:12px;">
                    <table style="width:100%;border:none;border-collapse:collapse;">
                        <tr>
                            <td style="width:33%;border:none;text-align:right;font-size:7px;color:#64748b;padding:0;">' . ($footerRight ?: '&nbsp;') . '</td>
                            <td style="width:34%;border:none;text-align:center;padding:0;">' . $centerContent . '</td>
                            <td style="width:33%;border:none;text-align:left;font-size:7px;color:#64748b;padding:0;">' . ($footerLeft ?: '&nbsp;') . '</td>
                        </tr>
                    </table>
                </div>';
            } else {
                $footerInfo = '
                <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:6px 12px;margin-top:12px;text-align:center;">
                    ' . $centerContent . '
                </div>';
            }
        }

        return '
        <div style="margin-top:25px;page-break-inside:avoid;">
            <div style="height:1.5px;background:linear-gradient(90deg,transparent,#cbd5e1,transparent);margin:12px 0;"></div>
            ' . ($stampBlock ?: '') . '
            ' . ($sigBlock ?: '') . '
            ' . $footerInfo . '
        </div>';
    }

    protected function getAllowanceTypeName($type)
    {
        $type = strtolower($type);
        $type = str_replace(['_allowance', '_'], '', $type);
        
        $names = [
            'transport' => 'بدل نقل',
            'housing' => 'بدل سكن',
            'food' => 'بدل طعام',
            'phone' => 'بدل هاتف',
            'education' => 'بدل تعليم',
            'medical' => 'بدل علاج',
            'other' => 'بدل أخرى',
            't' => 'بدل نقل',
            'h' => 'بدل سكن',
            'f' => 'بدل طعام',
            'p' => 'بدل هاتف',
            'e' => 'بدل تعليم',
            'm' => 'بدل علاج',
        ];
        return $names[$type] ?? 'بدل ' . $type;
    }

    protected function getIncentiveTypeName($type)
    {
        $type = strtolower($type);
        $type = str_replace(['_incentive', '_'], '', $type);
        
        $names = [
            'bonus' => 'مكافأة',
            'commission' => 'عمولة',
            'performance' => 'حافز اداء',
            'allowance' => 'بدل اضافي',
            'other' => 'مكافأة اخرى',
            'b' => 'مكافأة',
            'c' => 'عمولة',
            'perf' => 'حافز اداء',
        ];
        return $names[$type] ?? $type;
    }

    protected function getInsuranceTypeName($type)
    {
        $names = [
            'none' => 'بدون',
            'health' => 'صحي',
            'social' => 'اجتماعي',
            'both' => 'صحي واجتماعي',
        ];
        return $names[$type] ?? $type;
    }

    protected function getDefaultTaxBrackets()
    {
        $raw = DB::table('settings')->where('key', 'tax-brackets')->value('value');
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        // backward compatibility: unwrap {brackets: [...]} from old saves
        if (isset($data['brackets']) && is_array($data['brackets'])) {
            $data = $data['brackets'];
        }
        return $data;
    }
}
