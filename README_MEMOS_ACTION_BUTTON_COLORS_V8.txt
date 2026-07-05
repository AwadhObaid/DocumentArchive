DocumentArchive - Memo Action Button Colors V8

هذا التحديث ينسّق ألوان أزرار إجراءات جدول المذكرات فقط:
- عرض: رمادي/فاتح
- إرسال البريد: أزرق
- إرسال واتساب: أخضر
- رابط المرفقات: أصفر/ذهبي
- تعديل: أزرق واضح
- حذف: أحمر

لا يحتوي هذا التحديث على migrations ولا يعدّل قاعدة البيانات.

طريقة التركيب:
1) فك الضغط داخل جذر مشروع DocumentArchive مع الاستبدال.
2) نفذ:
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
3) شغل النظام ثم افتح /memos واضغط Ctrl+F5.
