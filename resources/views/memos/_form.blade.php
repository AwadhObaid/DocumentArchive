<div class="form-grid">
    <div class="form-group">
        <label>تاريخ المذكرة</label>
        <input type="date" name="memo_date" value="{{ old('memo_date', isset($memo) && $memo->memo_date ? $memo->memo_date->format('Y-m-d') : date('Y-m-d')) }}" required>
    </div>

    <div class="form-group">
        <label>الإدارة</label>
        <select name="department_id">
            <option value="">-- اختر الإدارة --</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $memo->department_id ?? '') === (string) $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group full">
        <label>موضوع المذكرة</label>
        <textarea name="subject" rows="4" required placeholder="اكتب موضوع المذكرة">{{ old('subject', $memo->subject ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>الواردة من</label>
        <input type="text" name="sender" value="{{ old('sender', $memo->sender ?? '') }}" placeholder="الجهة الواردة منها المذكرة">
    </div>

    <div class="form-group">
        <label>موجهة إلى</label>
        <input type="text" name="receiver" value="{{ old('receiver', $memo->receiver ?? '') }}" placeholder="الجهة أو الشخص المستلم">
    </div>

    <div class="form-group">
        <label>الحالة</label>
        <select name="status" required>
            <option value="active" @selected(old('status', $memo->status ?? 'active') === 'active')>نشطة</option>
            <option value="archived" @selected(old('status', $memo->status ?? 'active') === 'archived')>مؤرشفة</option>
            <option value="cancelled" @selected(old('status', $memo->status ?? 'active') === 'cancelled')>ملغاة</option>
        </select>
    </div>

    <div class="form-group full">
        <label>تفاصيل / وصف</label>
        <textarea name="description" rows="4" placeholder="تفاصيل إضافية اختيارية">{{ old('description', $memo->description ?? '') }}</textarea>
    </div>

    <div class="form-group full">
        <label>مرفقات المذكرة</label>
        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx">
        <small>يمكن رفع أكثر من مرفق. الحد الأقصى لكل ملف 50 MB.</small>
    </div>

    <div class="form-group full">
        <label>ملاحظات</label>
        <textarea name="notes" rows="4">{{ old('notes', $memo->notes ?? '') }}</textarea>
    </div>
</div>
