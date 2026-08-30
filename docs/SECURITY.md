# Security — امنیت و دسترسی

## کاربران مجاز

| نقش | دسترسی MVP |
|-----|------------|
| Administrator | ✅ |
| ادمین‌های تعریف‌شده | ✅ (با capability مناسب) |
| `forms_manager` (سفارشی) | ❌ فاز بعد |
| Subscriber / Editor | ❌ |

## Capability پیشنهادی

```php
// MVP
'manage_options'

// فاز بعد
'gfa_export_forms'  // capability سفارشی
```

## الزامات امنیتی

### 1. احراز هویت و مجوز

- [ ] بررسی `current_user_can()` قبل از هر action
- [ ] صفحه admin فقط برای نقش‌های مجاز
- [ ] جلوگیری از دسترسی مستقیم به فایل‌های PHP (`ABSPATH` check)

### 2. CSRF Protection

- [ ] nonce برای فرم انتخاب فرم و export
- [ ] verify nonce در هر درخواست POST/AJAX

### 3. Input Validation

| ورودی | validation |
|-------|------------|
| Form IDs | آرایه integer، فقط IDهای موجود |
| From Date | sanitize + valid date format |
| To Date | sanitize + valid date format |
| Export format | whitelist: `csv` \| `xlsx` |

### 4. Output Safety

- [ ] escape در UI (form titles, counts)
- [ ] no sensitive data in error messages
- [ ] download headers صحیح (`Content-Disposition`, `Content-Type`)

### 5. Rate / Abuse

- [ ] محدودیت منطقی تعداد فرم در یک export (اختیاری)
- [ ] log export actions (فاز بعد)

## Sanitization

```php
// نمونه
$form_ids = array_map( 'absint', (array) $_POST['form_ids'] );
$from_date = sanitize_text_field( $_POST['from_date'] ?? '' );
$to_date   = sanitize_text_field( $_POST['to_date'] ?? '' );
```

## جلوگیری از دانلود غیرمجاز

- export فقط از طریق admin page با nonce معتبر
- بدون endpoint عمومی
- بدون ذخیره فایل در مسیر public قابل دسترس

## Audit (فاز بعد)

- ثبت چه کسی، چه زمانی، چه فرم‌هایی export کرد
- بدون ذخیره محتوای export
