<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير الإجازات والإنذارات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; direction: rtl; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin-bottom: 5px; }
        .header p { font-size: 10px; color: #666; }
        .info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 10px; }
        .info-box { background: #f5f5f5; padding: 8px; border-radius: 5px; }
        .employee-section { margin-bottom: 20px; page-break-inside: avoid; }
        .employee-header { background: #4a5568; color: white; padding: 8px; border-radius: 5px 5px 0 0; }
        .employee-header h3 { margin: 0; font-size: 12px; }
        .employee-header span { font-size: 10px; opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: right; }
        th { background: #e2e8f0; font-weight: bold; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #888; }
        .summary { display: flex; gap: 15px; margin-top: 5px; }
        .summary-item { background: #f0f0f0; padding: 5px 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $org['name'] ?? 'Jawda HR' }}</h1>
        <p>{{ $org['address'] ?? '' }} | {{ $org['phone'] ?? '' }} | {{ $org['email'] ?? '' }}</p>
        <h2 style="margin-top: 8px;">تقرير الإجازات والإنذارات</h2>
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

    @foreach($employees as $empData)
    <div class="employee-section">
        <div class="employee-header">
            <h3>{{ $empData['employee']->name }}</h3>
            <span>{{ $empData['employee']->department->name ?? '-' }} | {{ $empData['employee']->position ?? '-' }}</span>
            <div class="summary">
                <span class="summary-item">الإجازات: {{ $empData['leaves']->count() }}</span>
                <span class="summary-item">الإنذارات: {{ $empData['warnings']->count() }}</span>
            </div>
        </div>
        
        @if($empData['leaves']->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع الإجازة</th>
                    <th>من تاريخ</th>
                    <th>إلى تاريخ</th>
                    <th>عدد الأيام</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($empData['leaves'] as $index => $leave)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $leave->leave_type ?? '-' }}</td>
                    <td>{{ $leave->from_date }}</td>
                    <td>{{ $leave->to_date }}</td>
                    <td class="text-center">{{ $leave->days ?? '-' }}</td>
                    <td>{{ $leave->status ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($empData['warnings']->count() > 0)
        <table style="margin-top: 5px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع الإنذار</th>
                    <th>التاريخ</th>
                    <th>السبب</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($empData['warnings'] as $index => $warning)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $warning->type ?? '-' }}</td>
                    <td>{{ $warning->date }}</td>
                    <td>{{ $warning->reason ?? '-' }}</td>
                    <td>{{ $warning->status ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($empData['leaves']->count() == 0 && $empData['warnings']->count() == 0)
        <p style="padding: 10px; text-align: center; color: #888;">لا توجد سجلات</p>
        @endif
    </div>
    @endforeach

    <div class="footer">
        <p>Jawda HR - نظام إدارة الموارد البشرية</p>
        <p>تم إنشاء هذا التقرير تلقائياً</p>
    </div>
</body>
</html>
