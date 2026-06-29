@php
    $restoreConfirmId = 'confirm_restore_' . md5(request()->path() . '-' . uniqid('', true));
@endphp
<div class="restore-confirm-panel" data-restore-confirm-panel>
    <label class="restore-check-row" for="{{ $restoreConfirmId }}">
        <input type="checkbox" name="confirm_restore" value="1" id="{{ $restoreConfirmId }}" required data-restore-checkbox>
        <span>أؤكد أنني أفهم أن هذه العملية قد تستبدل البيانات أو الملفات الحالية حسب نوع الاستعادة.</span>
    </label>
    <label class="restore-confirm-label">اكتب كلمة <strong>استعادة</strong> لتأكيد العملية:</label>
    <input type="text" name="confirm_text" class="form-control" required autocomplete="off" placeholder="اكتب: استعادة" data-restore-text>
</div>
