@extends('layouts.app')

@section('title', 'عن النظام وحقوق الاستخدام')
@section('page_title', 'عن النظام وحقوق الاستخدام')
@section('page_subtitle', 'توثيق ملكية النظام وحقوق التطوير والاستخدام')

@section('content')
<div class="system-about-page">
    <section class="system-about-hero">
        <div class="system-about-seal">🗂️</div>
        <div class="system-about-hero-content">
            <span class="system-about-kicker">وثيقة ملكية واستخدام</span>
            <h2>{{ $systemName }}</h2>
            <p>
                نظام إلكتروني مخصص لإدارة وأرشفة الكتب والمذكرات والمرفقات والنماذج والمراسلات الداخلية،
                مع دعم الصلاحيات، الإشعارات، النسخ الاحتياطي، المشاركة الآمنة، وإدارة دورة المستندات داخل بيئة العمل.
            </p>
        </div>
        <div class="system-about-version">
            <span>الإصدار الحالي</span>
            <strong>{{ $version }}</strong>
        </div>
    </section>

    <div class="system-about-grid">
        <section class="system-about-card owner-card">
            <div class="card-icon">🏛️</div>
            <h3>الجهة المالكة</h3>
            <p>{{ $ownerName }}</p>
        </section>

        <section class="system-about-card developer-card">
            <div class="card-icon">👨‍💻</div>
            <h3>المطور</h3>
            <p>{{ $developerName }}</p>
            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
        </section>

        <section class="system-about-card copyright-card">
            <div class="card-icon">©️</div>
            <h3>حقوق الملكية</h3>
            <p>جميع الحقوق محفوظة © {{ $copyrightYear }}</p>
        </section>
    </div>

    <section class="system-about-card system-about-wide">
        <div class="section-heading">
            <span>📌</span>
            <h3>بيان حقوق النظام</h3>
        </div>
        <p>
            تم تطوير هذا النظام خصيصًا لاستخدام <strong>{{ $ownerName }}</strong>،
            ولا يجوز نسخ أو إعادة بيع أو إعادة توزيع أو تعديل أو استخدام الكود المصدري أو أي جزء من النظام خارج نطاق الجهة المصرح لها،
            إلا بإذن خطي مسبق من الجهة المالكة والمطور.
        </p>
        <p>
            يشمل ذلك ملفات النظام، قاعدة البيانات، واجهات الاستخدام، السكربتات، هيكل الصلاحيات، آليات الترقيم، آليات الأرشفة،
            وأي إضافات أو تحديثات تم تطويرها ضمن هذا المشروع.
        </p>
    </section>

    <section class="system-about-card system-about-wide warning-card">
        <div class="section-heading">
            <span>⚠️</span>
            <h3>تنبيه هام</h3>
        </div>
        <ul>
            <li>يمنع مشاركة الكود المصدري أو مجلد المشروع مع أي طرف غير مصرح له.</li>
            <li>يمنع نشر النظام أو رفعه على أي خادم خارجي دون موافقة الجهة المالكة.</li>
            <li>يجب حفظ ملفات النسخ الاحتياطي وقاعدة البيانات في مكان آمن ومحمي.</li>
            <li>أي استخدام خارج النطاق المصرح به يعد مخالفة لحقوق الملكية والاستخدام.</li>
        </ul>
    </section>

    <section class="system-about-card system-about-wide tech-card">
        <div class="section-heading">
            <span>🧾</span>
            <h3>معلومات النسخة</h3>
        </div>
        <div class="system-about-meta">
            <div>
                <span>اسم النظام</span>
                <strong>{{ $systemName }}</strong>
            </div>
            <div>
                <span>الجهة المالكة</span>
                <strong>{{ $ownerName }}</strong>
            </div>
            <div>
                <span>المطور</span>
                <strong>{{ $developerName }}</strong>
            </div>
            <div>
                <span>بيئة التشغيل</span>
                <strong>{{ app()->environment() }}</strong>
            </div>
            <div>
                <span>رابط النظام</span>
                <strong>{{ config('app.url') }}</strong>
            </div>
            <div>
                <span>وقت العرض</span>
                <strong>{{ now()->format('Y-m-d H:i') }}</strong>
            </div>
        </div>
    </section>
</div>
@endsection
