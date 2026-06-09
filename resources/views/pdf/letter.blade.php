<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $content['title'] ?? 'خطاب رسمي' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 14px; 
            direction: rtl; 
            line-height: 2; 
            padding: 40px;
        }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header h1 { font-size: 22px; margin-bottom: 5px; }
        .header p { font-size: 12px; color: #666; }
        .letter-meta { margin-bottom: 30px; }
        .letter-meta p { margin-bottom: 5px; }
        .subject { background: #f5f5f5; padding: 10px; border-right: 4px solid #4a5568; margin: 20px 0; }
        .subject strong { display: block; margin-bottom: 5px; }
        .content { text-align: justify; margin: 30px 0; line-height: 2.5; }
        .salutation { margin-bottom: 20px; }
        .body-text { margin: 20px 0; text-indent: 30px; }
        .signature { margin-top: 60px; text-align: left; }
        .signature p { margin-bottom: 5px; }
        .stamp-area { 
            width: 150px; 
            height: 150px; 
            border: 2px dashed #ccc; 
            border-radius: 50%;
            position: absolute;
            left: 100px;
            top: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 12px;
        }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ddd; padding-top: 15px; }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.03);
            pointer-events: none;
            z-index: -1;
        }
        .document-info { 
            position: fixed; 
            bottom: 20px; 
            left: 20px; 
            font-size: 9px; 
            color: #aaa; 
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $org['name'] ?? 'Jawda HR' }}</h1>
        <p>{{ $org['address'] ?? '' }}</p>
        <p>{{ $org['phone'] ?? '' }} | {{ $org['email'] ?? '' }}</p>
    </div>

    <div class="letter-meta">
        <p><strong>التاريخ:</strong> {{ date('Y-m-d') }}</p>
        <p><strong>الموضوع:</strong> {{ $content['title'] ?? 'خطاب رسمي' }}</p>
    </div>

    @if(isset($content['subject']))
    <div class="subject">
        <strong>الموضوع:</strong>
        {{ $content['subject'] }}
    </div>
    @endif

    <div class="content">
        <p class="salutation">السيد/ة {{ $employee->name }} المحترم/ة،</p>
        
        <p class="body-text">{{ $content['body'] ?? '' }}</p>
        
        @if(isset($content['additional']))
            @foreach($content['additional'] as $line)
            <p class="body-text">{{ $line }}</p>
            @endforeach
        @endif
    </div>

    <div class="signature">
        <p><strong>مع وافر التحية والاحترام،</strong></p>
        <br>
        <p><strong>{{ $org['name'] ?? 'Jawda HR' }}</strong></p>
        <br>
        <p>التوقيع: ________________</p>
        <p>الختم:</p>
    </div>

    <div class="footer">
        <p>Jawda HR - نظام إدارة الموارد البشرية</p>
        <p>تم إنشاء هذا الخطاب إلكترونياً في {{ $generated_at }}</p>
    </div>

    <div class="document-info">
        رقم المرجع: {{ strtoupper(substr(md5($employee->id . $generated_at), 0, 8)) }}
    </div>
</body>
</html>
