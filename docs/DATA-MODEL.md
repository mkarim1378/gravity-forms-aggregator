# Data Model — محدوده داده

## منبع داده

ابزار از جداول استاندارد Gravity Forms می‌خواند. در MVP **جدول جدیدی ساخته نمی‌شود** — فقط داده تولید (export) می‌شود.

## جداول معمول Gravity Forms

| جدول (پیشوند wp_) | کاربرد |
|-------------------|--------|
| `{prefix}gf_form` | تعریف فرم‌ها (id, title, …) |
| `{prefix}gf_form_meta` | متادیتای فرم (فیلدها، تنظیمات) |
| `{prefix}gf_entry` | ورودی‌ها (entry) |
| `{prefix}gf_entry_meta` | مقادیر فیلدهای هر entry |

> نام دقیق جداول و فیلدها در **فاز 2** (بررسی ساختار GF) تأیید می‌شود.

## موجودیت‌های منطقی

### Form

```
form_id, form_title, is_active, entry_count (computed)
```

### Entry

```
entry_id, form_id, date_created, date_updated, status, ...
```

### Entry Field Value

```
entry_id, form_id, field_id, field_label, field_value
```

## Unified Export Row

هر ردیف خروجی یک مقدار فیلد از یک entry است (long format):

```
Form ID | Form Title | Entry ID | Entry Date | Field Label | Field Value
```

## روش خواندن (تصمیم فاز 2)

دو گزینه:

| روش | مزیت | عیب |
|-----|------|-----|
| کوئری مستقیم SQL | سریع، کنترل بیشتر | وابسته به schema |
| API داخلی GF (`GFAPI`) | پایدارتر، رسمی | ممکن است کندتر باشد |

> در فاز 2 مشخص می‌شود کدام روش برای MVP انتخاب شود.

## Mapping فیلدها

- `field_label` از تعریف فرم (form meta) خوانده شود
- `field_value` از entry meta
- فیلدهای composite (نام، آدرس) طبق رفتار GF تجمیع یا جدا export شوند — در فاز 2 تعیین می‌شود

## فیلتر تاریخ

فیلتر روی `date_created` (یا معادل GF) در جدول entry اعمال می‌شود. جزئیات در [EXPORT-SPEC.md](EXPORT-SPEC.md).
