# DocumentArchive Workflow Approval Update V0.6.22

هذا التحديث يضيف مسار اعتماد للكتب والمذكرات:

- مسودة
- قيد المراجعة
- معتمد
- مرفوض
- مرجع للتعديل
- مؤرشف نهائيًا

## أهم المزايا

- إرسال الكتاب أو المذكرة للمراجعة.
- اعتماد أو رفض أو إرجاع للتعديل.
- أرشفة نهائية تمنع التعديل والحذف.
- سجل قرارات الاعتماد لكل كتاب ومذكرة.
- إشعارات للمستخدمين عند تغير حالة الاعتماد.
- صلاحيات مستقلة للـ Workflow.

## أوامر التركيب

```powershell
php artisan migrate
php scripts/check_workflow_approval_update.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve
```

## ملاحظات مهمة

- السجلات الموجودة ستبدأ بحالة: مسودة.
- عند الأرشفة النهائية لا يمكن تعديل أو حذف الكتاب/المذكرة إلا لمن لديه صلاحية workflow.override.
- يمكنك إدارة صلاحيات الاعتماد من صفحة المستخدمين.
