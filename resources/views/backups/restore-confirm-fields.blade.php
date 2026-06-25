<div class="restore-check-row">
    <input type="checkbox" name="confirm_restore" value="1" id="confirm_restore_{{ md5(request()->path() . microtime()) }}" required>
    <label>أؤكد أنني أفهم أن هذه العملية قد تستبدل البيانات أو الملفات الحالية.</label>
</div>
<label>اكتب كلمة <strong>استعادة</strong> لتأكيد العملية:</label>
<input type="text" name="confirm_text" class="form-control" required autocomplete="off" placeholder="اكتب: استعادة">
