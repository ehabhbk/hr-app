<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>كشف المرتبات</title>
    <style>
        @font-face {
            font-family: 'ArabicFont';
            font-style: normal;
            font-weight: normal;
            src: url("https://fonts.gstatic.com/s/notoarabic/v21/nwpBtKy2OAdR1K-IwhWudF-R9QMylBJAV3Bo8Ky46lEN_io6npfB.woff2") format('woff2');
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans Arabic', 'Segoe UI', Arial, sans-serif; font-size: 11px; direction: rtl; unicode-bidi: bidi-override; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 18px; margin-bottom: 5px; }
        .header p { font-size: 10px; color: #666; }
        .info { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 10px; }
        .info-box { background: #f5f5f5; padding: 8px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: right; }
        th { background: #4a5568; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #888; }
        .total-row { background: #e2e8f0 !important; font-weight: bold; }
        .currency { direction: ltr; display: inline-block; text-align: left; }
        .rtl-text { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $org['name'] ?? 'Jawda HR' }}</h1>
        <p>{{ $org['address'] ?? '' }} | {{ $org['phone'] ?? '' }} | {{ $org['email'] ?? '' }}</p>
        <h2 style="margin-top: 10px;">كشف المرتبات الشهري</h2>
        <p>شهر {{ $month }} {{ $year }}</p>
    </div>

    <div class="info">
        <div class="info-box">
            <strong>تاريخ التقرير:</strong> {{ $generated_at }}
        </div>
        <div class="info-box">
            <strong>عدد الموظفين:</strong> {{ count($employees) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>الاسم</th>
                <th>القسم</th>
                <th>الراتب الأساسي</th>
                <th>بدل الوظيفة</th>
                <th>إجمالي البدلات</th>
                <th>إجمالي الراتب</th>
                <th>التأمينات الاجتماعية</th>
                <th>صافي الراتب</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalBaseSalary = 0;
                $totalPositionAllowance = 0;
                $totalAllowances = 0;
                $totalGross = 0;
                $totalSocial = 0;
                $totalNet = 0;
            @endphp
            @foreach($employees as $index => $item)
            @php
                $totalBaseSalary += $item['base_salary'];
                $totalPositionAllowance += $item['position_allowance'];
                $totalAllowances += $item['total_allowances'];
                $totalGross += $item['gross_salary'];
                $totalSocial += $item['social_insurance'];
                $net = $item['gross_salary'] - $item['social_insurance'];
                $totalNet += $net;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['employee']->name }}</td>
                <td>{{ $item['employee']->department->name ?? '-' }}</td>
                <td class="currency">{{ number_format($item['base_salary'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['position_allowance'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['total_allowances'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['gross_salary'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['social_insurance'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($net, 2) }} ج.س</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" class="text-center"><strong>الإجمالي</strong></td>
                <td class="currency"><strong>{{ number_format($totalBaseSalary, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalPositionAllowance, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalAllowances, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalGross, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalSocial, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalNet, 2) }} ج.س</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Jawda HR - نظام إدارة الموارد البشرية</p>
        <p>تم إنشاء هذا التقرير تلقائياً</p>
    </div>
</body>
</html>
