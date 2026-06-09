<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>عقد عمل توظيفي</title>
    <style>
        @font-face {
            font-family: 'aealarabiya';
            src: url('{{ base_path('vendor/tecnickcom/tcpdf/fonts/aealarabiya.ttf') }}');
            font-weight: normal;
            font-style: normal;
        }
        * {
            font-family: 'aealarabiya', 'Tahoma', 'Arial', sans-serif;
        }
        body {
            font-size: 11pt;
            line-height: 1.8;
            padding: 15px;
            margin: 0;
        }
        .header-table {
            width: 100%;
            border: none;
            margin-bottom: 15px;
        }
        .header-table td {
            border: none;
            vertical-align: middle;
        }
        .header-gradient {
            height: 3px;
            background: linear-gradient(to left, #1e40af, #3b82f6, #1e40af);
            margin: 10px 0;
            border-radius: 2px;
        }
        .org-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
            margin: 0;
        }
        .org-subtitle {
            font-size: 9pt;
            color: #4b5563;
            margin: 3px 0;
        }
        .contract-title {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 3px solid #1e40af;
            background: #eff6ff;
            border-radius: 10px;
        }
        .contract-title h1 {
            font-size: 20pt;
            margin: 0;
            color: #1e40af;
        }
        .contract-title p {
            font-size: 10pt;
            margin: 5px 0 0 0;
            color: #3b82f6;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            font-weight: bold;
            background: #1e40af;
            color: white;
            padding: 8px 12px;
            border-radius: 6px 6px 0 0;
            margin-bottom: 0;
        }
        .section-content {
            border: 1px solid #334155;
            border-top: none;
            padding: 12px;
            background: white;
            border-radius: 0 0 6px 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 4px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        td:nth-child(odd) {
            font-weight: bold;
            width: 30%;
            color: #374151;
        }
        td:nth-child(even) {
            width: 70%;
        }
        .salary-table td {
            border: 1px solid #334155;
            padding: 8px;
        }
        .total-row {
            background: #dcfce7;
            font-weight: bold;
        }
        .terms-list {
            padding-right: 20px;
        }
        .terms-list li {
            margin: 8px 0;
            line-height: 1.6;
        }
        .signatures-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .signature-box {
            width: 48%;
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .signature-box h4 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 11pt;
        }
        .signature-line {
            border-bottom: 2px solid #1e40af;
            width: 120px;
            margin: 0 auto 8px auto;
        }
        .signature-line-green {
            border-bottom-color: #059669;
        }
        .signature-name {
            font-size: 9pt;
            color: #6b7280;
            margin: 2px 0;
        }
        .stamp-area {
            min-height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }
        .footer-divider {
            height: 2px;
            background: #e5e7eb;
            margin: 20px 0;
        }
        .footer-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
        }
        .footer-info p {
            font-size: 8pt;
            color: #64748b;
            margin: 0;
        }
        .contract-footer {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    {{-- Header with Logo --}}
    <table class="header-table">
        <tr>
            <td style="width:80px;text-align:center;">
                @if(isset($logoBase64))
                    <img src="{{ $logoBase64 }}" style="height:65px;width:65px;object-fit:contain;">
                @else
                    <div style="width:70px;height:70px;background:#f3f4f6;border:2px dashed #9ca3af;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:10px;">شعار</div>
                @endif
            </td>
            <td style="text-align:center;padding:10px 20px;">
                <h1 class="org-name">{{ $orgName }}</h1>
                <p class="org-subtitle">
                    @if($orgAddress)العنوان: {{ $orgAddress }}@endif
                    @if($orgPhone) | هاتف: {{ $orgPhone }}@endif
                </p>
            </td>
            <td style="width:80px;"></td>
        </tr>
    </table>
    <div class="header-gradient"></div>

    {{-- Contract Title --}}
    <div class="contract-title">
        <h1>عقد عمل توظيفي</h1>
        <p>التاريخ: {{ $date }} | رقم المرجع: {{ $orgName }}-CONTRACT-{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}-{{ date('Y') }}</p>
    </div>

    {{-- Party 1 Info --}}
    <div class="section">
        <div class="section-title">اولاً: بيانات الطرف الأول (المؤسسة)</div>
        <div class="section-content">
            <table>
                <tr>
                    <td>اسم المؤسسة:</td>
                    <td>{{ $orgName }}</td>
                </tr>
                <tr>
                    <td>العنوان:</td>
                    <td>{{ $orgAddress }}</td>
                </tr>
                <tr>
                    <td>الهاتف:</td>
                    <td>{{ $orgPhone }}</td>
                </tr>
                <tr>
                    <td>المدير العام:</td>
                    <td>{{ $managerName }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Party 2 Info --}}
    <div class="section">
        <div class="section-title">ثانياً: بيانات الطرف الثاني (الموظف)</div>
        <div class="section-content">
            <table>
                <tr>
                    <td>الاسم:</td>
                    <td>{{ $employee->name }}</td>
                </tr>
                <tr>
                    <td>الوظيفة:</td>
                    <td>{{ $employee->position ?? '_________' }}</td>
                </tr>
                <tr>
                    <td>الدرجة الوظيفية:</td>
                    <td>{{ $employee->position_grade ?? '_________' }}</td>
                </tr>
                <tr>
                    <td>القسم:</td>
                    <td>{{ $employee->department->name ?? '_________' }}</td>
                </tr>
                <tr>
                    <td>رقم الملف:</td>
                    <td>{{ $employee->file_number ?? '_________' }}</td>
                </tr>
                <tr>
                    <td>تاريخ التعيين:</td>
                    <td>{{ $employee->hire_date ?? '_________' }}</td>
                </tr>
                <tr>
                    <td>العنوان:</td>
                    <td>{{ $employee->address ?? '_________' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Financial Terms --}}
    <div class="section">
        <div class="section-title">ثالثاً: الشروط المالية للعقد</div>
        <div class="section-content">
            <table class="salary-table">
                <tr>
                    <td style="width:50%;font-weight:bold;">الراتب الأساسي الشهري</td>
                    <td style="text-align:center;">{{ number_format($baseSalary) }} {{ $employee->currency_symbol ?? 'جنيه' }}</td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">بدل الدرجة الوظيفية</td>
                    <td style="text-align:center;">{{ number_format($positionAllowance) }} {{ $employee->currency_symbol ?? 'جنيه' }}</td>
                </tr>
                <tr class="total-row">
                    <td style="font-weight:bold;">إجمالي الراتب الشهري</td>
                    <td style="text-align:center;font-weight:bold;">{{ number_format($totalSalary) }} {{ $employee->currency_symbol ?? 'جنيه' }}</td>
                </tr>
            </table>
            <p style="margin-top:10px;"><strong>مدة العقد:</strong> {{ $durationMonths }} شهر</p>
            <p><strong>فترة التجربة:</strong> 3 أشهر</p>
        </div>
    </div>

    {{-- Contract Terms --}}
    <div class="section">
        <div class="section-title">رابعاً: شروط العقد</div>
        <div class="section-content">
            <ol class="terms-list">
                <li>يلتزم الموظف بالحضور والانصراف حسب نظام العمل المعتمد في المؤسسة.</li>
                <li>يحق للمؤسسة إنهاء العقد في حالة إخلال الموظف بالتزاماته الوظيفية.</li>
                <li>يحق لأي من الطرفين إنهاء العقد بإخطار كتابي قبل 30 يوم.</li>
                <li>يتم صرف الراتب في نهاية كل شهر ميلادي.</li>
                <li>يلتزم الموظف بالمحافظة على أسرار العمل وعدم إفشائها.</li>
                <li>يخضع الموظف لتقييم الأداء بشكل دوري.</li>
            </ol>
        </div>
    </div>

    {{-- Signatures Section --}}
    <div class="signatures-section">
        <div class="section-title">خامساً: التوقيعات والاعتماد</div>
        <div class="section-content">
            <div class="signatures-row">
                {{-- HR Manager --}}
                <div class="signature-box">
                    <h4>مدير الموارد البشرية</h4>
                    <div class="signature-line"></div>
                    <p class="signature-name">الاسم: ........................</p>
                    <p class="signature-name">التوقيع: ....................</p>
                    <p class="signature-name">التاريخ: ....................</p>
                    <div class="stamp-area">
                        @if(isset($stampBase64))
                            <img src="{{ $stampBase64 }}" style="height:65px;width:65px;object-fit:contain;opacity:0.85;">
                        @else
                            <div style="width:60px;height:60px;border:2px dashed #9ca3af;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:8px;">ختم</div>
                        @endif
                    </div>
                </div>

                {{-- General Manager --}}
                <div class="signature-box">
                    <h4 style="color:#059669;">المدير العام</h4>
                    <div class="signature-line signature-line-green"></div>
                    <p class="signature-name">الاسم: {{ $managerName }}</p>
                    <p class="signature-name">التوقيع: ....................</p>
                    <p class="signature-name">التاريخ: ....................</p>
                    <div class="stamp-area">
                        @if(isset($stampBase64))
                            <img src="{{ $stampBase64 }}" style="height:65px;width:65px;object-fit:contain;opacity:0.85;">
                        @else
                            <div style="width:60px;height:60px;border:2px dashed #9ca3af;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:8px;">ختم</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="footer-divider"></div>

            {{-- Employee Signature --}}
            <div class="signature-box" style="width:100%;margin-top:15px;">
                <h4>الطرف الثاني (الموظف)</h4>
                <div style="display:inline-block;width:45%;text-align:center;">
                    <div class="signature-line"></div>
                    <p class="signature-name">توقيع الموظف</p>
                    <p class="signature-name">الاسم: {{ $employee->name }}</p>
                    <p class="signature-name">التاريخ: ....................</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer-divider"></div>
    <div class="footer-info">
        <p>Jawda HR - نظام إدارة الموارد البشرية | تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</p>
    </div>
    <div class="contract-footer">
        <p style="text-align:center;font-size:9pt;color:#64748b;">
            {{ $orgName }} - {{ $orgAddress }} - ت: {{ $orgPhone }}
        </p>
    </div>
</body>
</html>
