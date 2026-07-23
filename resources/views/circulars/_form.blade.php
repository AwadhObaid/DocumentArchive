<div class="archive-module-form-grid">
    <div class="form-group">
        <label>تاريخ التعميم</label>
        <input type="date" name="circular_date" value="{{ old('circular_date', isset($circular) && $circular->circular_date ? $circular->circular_date->format('Y-m-d') : date('Y-m-d')) }}" required>
    </div>

    <div class="form-group">
        <label>رقم التعميم الأصلي</label>
        <input type="text" name="original_number" value="{{ old('original_number', $circular->original_number ?? '') }}" placeholder="اختياري">
    </div>

    <div class="form-group">
        <label>التصنيف</label>
        <select name="category_id">
            <option value="">-- اختر التصنيف --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string)old('category_id', $circular->category_id ?? '') === (string)$category->id)>
                    {{ $category->full_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>الجهة المصدرة</label>
        <input type="text" name="issuing_entity" value="{{ old('issuing_entity', $circular->issuing_entity ?? '') }}">
    </div>

    <div class="form-group full">
        <label>موضوع التعميم</label>
        <textarea name="subject" rows="4" required>{{ old('subject', $circular->subject ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>نطاق التطبيق</label>
        <input type="text" name="scope" value="{{ old('scope', $circular->scope ?? '') }}" placeholder="مثال: جميع الموظفين أو إدارات محددة">
    </div>

    <div class="form-group">
        <label>الحالة</label>
        <select name="status" required>
            @foreach(['active' => 'ساري', 'expired' => 'منتهي', 'cancelled' => 'ملغي', 'archived' => 'مؤرشف'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $circular->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>بدء السريان</label>
        <input type="date" name="effective_date" value="{{ old('effective_date', isset($circular) && $circular->effective_date ? $circular->effective_date->format('Y-m-d') : '') }}">
    </div>

    <div class="form-group">
        <label>انتهاء السريان</label>
        <input type="date" name="expiry_date" value="{{ old('expiry_date', isset($circular) && $circular->expiry_date ? $circular->expiry_date->format('Y-m-d') : '') }}">
    </div>

    <div class="form-group">
        <label>درجة السرية</label>
        <select name="confidentiality" required>
            @foreach(['normal' => 'عادي', 'confidential' => 'سري', 'secret' => 'سري جدًا', 'top_secret' => 'سري للغاية'] as $value => $label)
                <option value="{{ $value }}" @selected(old('confidentiality', $circular->confidentiality ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>الأولوية</label>
        <select name="priority" required>
            @foreach(['normal' => 'عادي', 'urgent' => 'عاجل', 'very_urgent' => 'عاجل جدًا'] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $circular->priority ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group full">
        <label>الكلمات المفتاحية</label>
        <input type="text" name="keywords" value="{{ old('keywords', $circular->keywords ?? '') }}" placeholder="افصل بين الكلمات بفاصلة">
    </div>

    <div class="form-group full">
        <label>المرفقات</label>
        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx">
        <small>يمكن رفع أكثر من ملف، بحد أقصى 20 MB لكل ملف.</small>
    </div>

    <div class="form-group full">
        <label>ملاحظات</label>
        <textarea name="notes" rows="4">{{ old('notes', $circular->notes ?? '') }}</textarea>
    </div>
</div>
