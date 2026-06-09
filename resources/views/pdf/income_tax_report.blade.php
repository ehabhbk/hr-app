<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير ضريبة الدخل</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; direction: rtl; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 20px; margin-bottom: 5px; }
        .header p { font-size: 11px; color: #666; }
        .info { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 11px; }
        .info-box { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: right; }
        th { background: #4a5568; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .text-center { text-align: center; }
        .currency { direction: ltr; display: inline-block; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #888; }
        .tax-brackets { margin-top: 20px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $org['name'] ?? 'Jawda HR' }}</h1>
        <p>{{ $org['address'] ?? '' }} | {{ $org['phone'] ?? '' }} | {{ $org['email'] ?? '' }}</p>
        <h2 style="margin-top: 10px;">تقرير ضريبة الدخل السنوي</h2>
        <p>السنة {{ $year }}</p>
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
                <th>الراتب الشهري</th>
                <th>الراتب السنوي</th>
                <th>الضريبة السنوية</th>
                <th>الضريبة الشهرية</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalMonthly = 0;
                $totalAnnual = 0;
                $totalAnnualTax = 0;
                $totalMonthlyTax = 0;
            @endphp
            @foreach($employees as $index => $item)
            @php
                $totalMonthly += $item['monthly_salary'];
                $totalAnnual += $item['annual_salary'];
                $totalAnnualTax += $item['annual_tax'];
                $totalMonthlyTax += $item['monthly_tax'];
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['employee']->name }}</td>
                <td>{{ $item['employee']->department->name ?? '-' }}</td>
                <td class="currency">{{ number_format($item['monthly_salary'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['annual_salary'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['annual_tax'], 2) }} ج.س</td>
                <td class="currency">{{ number_format($item['monthly_tax'], 2) }} ج.س</td>
            </tr>
            @endforeach
            <tr style="background: #e2e8f0; font-weight: bold;">
                <td colspan="3" class="text-center"><strong>الإجمالي</strong></td>
                <td class="currency"><strong>{{ number_format($totalMonthly, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalAnnual, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalAnnualTax, 2) }} ج.س</strong></td>
                <td class="currency"><strong>{{ number_format($totalMonthlyTax, 2) }} ج.س</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="tax-brackets">
        <h4>شرائح الضريبة المطبقة:</h4>
        <ul>
            <li>0 - 72,000 ج.س سنوياً: معفى</li>
            <li>72,001 - 144,000 ج.س سنوياً: 5%</li>
            <li>144,001 - 288,000 ج.س سنوياً: 10%</li>
            <li>أكثر من 288,000 ج.س سنوياً: 15%</li>
        </ul>
    </div>

    <div class="footer">
        <p>Jawda HR - نظام إدارة الموارد البشرية</p>
        <p>تم إنشاء هذا التقرير تلقائياً</p>
    </div>
</body>
</html>
