@extends('layouts.app')

@section('title', 'حاسبة الإجازات')

@section('content')
<link rel="stylesheet" href="{{ asset('css/leave-calculator-v63.css') }}?v=63">

<div class="leave-calculator-page" dir="rtl">
    <div class="page-header leave-header-card">
        <div>
            <h1>حاسبة الإجازات</h1>
            <p>حساب الفرق بين تاريخين، إجمالي الأيام، أيام العطل، وصافي أيام الإجازة.</p>
        </div>
        <div class="leave-header-icon">🧮</div>
    </div>

    <div class="leave-alert leave-alert-error" id="leaveCalculatorError" hidden></div>

    <section class="leave-card">
        <div class="leave-title-row">
            <h2>حساب الفرق بين تاريخين بالأيام</h2>
            <span>اختر الفترة ثم اضغط احسب</span>
        </div>

        <div class="leave-form-grid">
            <div class="leave-field">
                <label for="leaveStartDate">من تاريخ</label>
                <input type="date" id="leaveStartDate" value="{{ now()->format('Y-m-d') }}">
            </div>

            <div class="leave-field">
                <label for="leaveEndDate">إلى تاريخ</label>
                <input type="date" id="leaveEndDate" value="{{ now()->format('Y-m-d') }}">
            </div>
        </div>

        <div class="leave-options">
            <label class="leave-check">
                <input type="checkbox" id="leaveIncludeStart">
                <span>احتساب يوم البداية ضمن الإجازة</span>
            </label>

            <div class="leave-weekend-box">
                <strong>عطلة نهاية الأسبوع:</strong>
                <label class="leave-check inline">
                    <input type="checkbox" class="leaveWeekendDay" value="5" checked>
                    <span>الجمعة</span>
                </label>
                <label class="leave-check inline">
                    <input type="checkbox" class="leaveWeekendDay" value="6" checked>
                    <span>السبت</span>
                </label>
            </div>
        </div>

        <details class="leave-holidays-panel">
            <summary>إضافة عطلات رسمية اختيارية</summary>
            <p>اكتب كل تاريخ في سطر مستقل بصيغة YYYY-MM-DD، وسيتم خصمه من صافي الأيام إذا كان داخل الفترة وليس يوم عطلة أسبوعية.</p>
            <textarea id="leaveOfficialHolidays" rows="4" placeholder="2026-02-25&#10;2026-02-26"></textarea>
        </details>

        <div class="leave-result-grid">
            <div class="leave-result-box">
                <span>السنوات</span>
                <strong id="leaveYears">0</strong>
            </div>
            <div class="leave-result-box">
                <span>الشهور</span>
                <strong id="leaveMonths">0</strong>
            </div>
            <div class="leave-result-box">
                <span>الأيام</span>
                <strong id="leaveDays">0</strong>
            </div>
            <div class="leave-result-box">
                <span>إجمالي الأيام</span>
                <strong id="leaveTotalDays">0</strong>
            </div>
            <div class="leave-result-box">
                <span>أيام العطلة الأسبوعية</span>
                <strong id="leaveWeekendDays">0</strong>
            </div>
            <div class="leave-result-box">
                <span>العطل الرسمية</span>
                <strong id="leaveOfficialDays">0</strong>
            </div>
            <div class="leave-result-box leave-net-box">
                <span>صافي الأيام بدون عطل</span>
                <strong id="leaveNetDays">0</strong>
            </div>
            <div class="leave-result-box leave-total-box">
                <span>الإجمالي مع العطل</span>
                <strong id="leaveGrandTotal">0</strong>
            </div>
        </div>

        <div class="leave-actions">
            <button type="button" class="btn-primary leave-calc-button" id="leaveCalculateButton">احسب</button>
            <button type="button" class="btn-secondary leave-reset-button" id="leaveTodayButton">تاريخ اليوم</button>
            <button type="button" class="btn-secondary leave-reset-button" id="leaveResetButton">تصفير</button>
        </div>
    </section>
</div>

<script defer src="{{ asset('js/leave-calculator-v63.js') }}?v=63"></script>
@endsection
