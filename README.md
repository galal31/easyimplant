# Easy Implant

منصة PHP/MySQL لإدارة طلبات الأدلة الجراحية وطلبات جرّاحي زراعة الأسنان، بما يشمل مراجعة الطلبات والدفع والتسليم وحسابات العيادات.

## التشغيل المحلي

1. شغّل `composer install` لتثبيت مكتبات PHP.
2. انسخ `includes/database_config.example.php` إلى `includes/database_config.php` واضبط اتصال قاعدة البيانات.
3. انسخ `includes/r2_config.example.php` إلى `includes/r2_config.php` واضبط بيانات Cloudflare R2.
4. أنشئ قاعدة البيانات من `database_schema.example.sql` أو طبّق ملفات `migrations/` على قاعدة موجودة.
5. وجّه Apache أو خادم PHP إلى مجلد المشروع.

ملفات الإعداد الفعلية وبيانات الرفع والنسخ الاحتياطية مستبعدة من Git حتى لا تُرفع بيانات حساسة أو ملفات تشغيلية إلى المستودع.

راجع `PROJECT_CONTEXT.md` لفهم دورة العمل والقرارات الحالية قبل تنفيذ تعديلات جديدة.
