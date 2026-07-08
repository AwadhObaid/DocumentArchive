DocumentArchive - Internal Chat Advanced V38
===========================================

هذا التحديث يضيف طبقة الدردشة المتقدمة التالية:

1) إرفاق كتاب أو مذكرة داخل رسالة الدردشة
   - البحث عن كتاب بالرقم/الموضوع ثم إرفاقه كرابط داخل فقاعة الرسالة.
   - البحث عن مذكرة بالرقم/الموضوع ثم إرفاقها كرابط داخل فقاعة الرسالة.

2) البحث داخل المحادثة النشطة
   - مربع بحث أعلى المحادثة يبحث في نص الرسائل وروابط الكتب والمذكرات المرفقة.

3) أرشفة محادثة
   - الأرشفة تكون لكل مستخدم على حدة عبر internal_chat_participants.archived_at.
   - لا يتم حذف الرسائل من قاعدة البيانات.

4) حذف ظاهري وليس حذف نهائي
   - الحذف الظاهري يكون لكل مستخدم على حدة عبر internal_chat_participants.deleted_at.
   - المحادثة تختفي من قائمة المستخدم فقط ولا يتم حذف الرسائل فعليًا.

5) محادثات جماعية
   - جداول جديدة للمحادثات والمشاركين:
     internal_chat_conversations
     internal_chat_participants
   - يمكن إنشاء مجموعة من نافذة الدردشة واختيار أكثر من مستخدم.

6) حالة المستخدم متصل / غير متصل
   - عمود users.last_seen_at يتم تحديثه عند bootstrap / poll / إرسال الرسائل.
   - المستخدم يعتبر متصلًا إذا كان آخر ظهور خلال آخر دقيقتين تقريبًا.

ملفات التحديث الرئيسية:
-----------------------
app/Http/Controllers/InternalChatController.php
app/Models/InternalChatConversation.php
app/Models/InternalChatParticipant.php
app/Models/InternalChatAttachment.php
app/Models/InternalChatMessage.php
app/Models/User.php
database/migrations/2026_07_08_103000_upgrade_internal_chat_advanced_v38.php
resources/views/partials/internal-chat-widget.blade.php
public/js/internal-chat.js
public/css/internal-chat.css
routes/web.php
scripts/check_internal_chat_advanced_v38.php

أوامر التشغيل بعد التركيب:
--------------------------
cd E:\LaravelProjects\DocumentArchive
php artisan migrate
php scripts/check_internal_chat_advanced_v38.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
npm run build
php artisan serve

على السيرفر:
-------------
cd C:\laragon\www\DocumentArchive
git pull origin master
php artisan migrate
php scripts/check_internal_chat_advanced_v38.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
npm run build
