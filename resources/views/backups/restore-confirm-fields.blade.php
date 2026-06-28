<div class="restore-check-row">
    <input type="checkbox" name="confirm_restore" value="1" id="confirm_restore_{{ md5(request()->path() . microtime()) }}" required>
    <label>أؤكد أنني أفهم
 أن هذه العم
لية قد تستبدل البيانات أو الم
لفات الحالية.</label>
</div>
<label>اكتب كلم
ة <strong>استعادة</strong> لتأكيد العم
لية:</label>
<input type="text" name="confirm_text" class="form-control" required autocomplete="off" placeholder="اكتب: استعادة">
