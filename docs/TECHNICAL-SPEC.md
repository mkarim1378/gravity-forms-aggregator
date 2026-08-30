# Technical Spec — مشخصات فنی

## معماری پیشنهادی

افزونه وردپرس اختصاصی با ساختار لایه‌ای:

```
gravity-forms-aggregator/
├── gravity-forms-aggregator.php   # bootstrap
├── includes/
│   ├── class-plugin.php           # init, hooks
│   ├── class-admin-page.php       # UI
│   ├── class-data-extractor.php   # query GF data
│   ├── class-date-filter.php      # date range logic
│   └── export/
│       ├── class-csv-exporter.php
│       └── class-excel-exporter.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── languages/
```

## وابستگی‌ها

| وابستگی | نوع |
|---------|-----|
| WordPress | الزامی |
| Gravity Forms | الزامی |
| PhpSpreadsheet (یا مشابه) | برای Excel — فقط در صورت نیاز |

## جریان داده

```
User Request (form IDs + date range)
    ↓
Validate & Sanitize
    ↓
Count entries (preview)
    ↓
Extract entries (batched)
    ↓
Build unified rows
    ↓
Stream CSV / generate XLSX
    ↓
Download response
```

## استخراج داده (فاز 4 — هسته MVP)

1. دریافت entryهای فرم‌های انتخاب‌شده
2. اعمال فیلتر تاریخ
3. join با form meta برای label فیلدها
4. تبدیل به ساختار unified row

## ملاحظات عملکردی

| سناریو | راهکار |
|--------|--------|
| تعداد ورودی زیاد | batching — داده یک‌باره لود نشود |
| کوئری سنگین | index-friendly filters، محدود کردن SELECT |
| تولید فایل بزرگ | chunked / streaming export |
| Excel حجیم | کتابخانه memory-friendly، PhpSpreadsheet با writer streaming |
| timeout PHP | افزایش موقت `max_execution_time` یا export async (فاز بعد) |

## ریسک‌ها

| ریسک | شدت | mitigation |
|------|-----|------------|
| حجم داده زیاد | بالا | pagination, batching |
| محدودیت PHP time | متوسط | chunked export |
| schema تغییر GF | پایین | ترجیح GFAPI یا abstraction layer |
| encoding فارسی | متوسط | UTF-8 BOM در CSV |

## تصمیم‌های فنی MVP

- بدون دیتابیس جدید
- کوئری مستقیم یا GFAPI — در فاز 2
- CSV قبل از Excel
- admin-only page زیر منوی WordPress

## هوک‌ها و نقاط توسعه

برای extensibility آینده:

```php
// پیشنهادی
apply_filters( 'gfa_export_columns', $columns );
apply_filters( 'gfa_export_row', $row, $entry, $field );
apply_filters( 'gfa_export_query_args', $args );
```

## محیط توسعه

- WordPress local (Local WP, Docker, یا XAMPP)
- Gravity Forms نصب‌شده با چند فرم و entry نمونه
- PHP 7.4+ (یا مطابق نیاز WordPress فعلی)
