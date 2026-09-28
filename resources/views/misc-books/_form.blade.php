<div class="archive-module-form-grid">
    <div class="form-group">
        <label>تاريخ الكتاب</label>
        <input type="date" name="book_date" value="{{ old('book_date', isset($miscBook) && $miscBook->book_date ? $miscBook->book_date->format('Y-m-d') : date('Y-m-d')) }}" required>
    </div>

    <div class="form-group">
        <label>الرقم الأصلي</label>
        <input type="text" name="original_number" value="{{ old('original_number', $miscBook->original_number ?? '') }}" placeholder="اختياري">
    </div>

    <div class="form-group">
        <label>التصنيف</label>
        <select name="category_id">
            <option value="">-- اختر التصنيف --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string)old('category_id', $miscBook->category_id ?? '') === (string)$category->id)>
                    {{ $category->full_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>اتجاه المخاطبة</label>
        <select name="correspondence_direction" required>
            @foreach(['incoming' => 'وارد', 'outgoing' => 'صادر', 'internal' => 'داخلي'] as $value => $label)
                <option value="{{ $value }}" @selected(old('correspondence_direction', $miscBook->correspondence_direction ?? 'incoming') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>طبيعة المخاطبة</label>
        <select name="nature" required>
            @foreach(['unspecified' => 'غير محدد', 'military' => 'عسكري', 'civil' => 'مدني'] as $value => $label)
                <option value="{{ $value }}" @selected(old('nature', $miscBook->nature ?? 'unspecified') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>الحالة</label>
        <select name="status" required>
            @foreach(['active' => 'نشط', 'archived' => 'مؤرشف', 'cancelled' => 'ملغي'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $miscBook->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group full">
        <label>موضوع الكتاب</label>
        <textarea name="subject" rows="4" required>{{ old('subject', $miscBook->subject ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>الجهة المرسلة</label>
        <input type="text" name="sender" value="{{ old('sender', $miscBook->sender ?? '') }}">
    </div>

    <div class="form-group">
        <label>الجهة المستلمة</label>
        <input type="text" name="receiver" value="{{ old('receiver', $miscBook->receiver ?? '') }}">
    </div>

    <div class="form-group">
        <label>اسم الموظف</label>
        <input type="text" name="employee_name" value="{{ old('employee_name', $miscBook->employee_name ?? '') }}" placeholder="يستخدم عند تصنيف كتب الموظفين">
    </div>

    <div class="form-group">
        <label>اسم الهيئة</label>
        <input type="text" name="authority_name" value="{{ old('authority_name', $miscBook->authority_name ?? '') }}" placeholder="يستخدم عند تصنيف كتب الهيئات">
    </div>

    <div class="form-group">
        <label>درجة السرية</label>
        <select name="confidentiality" required>
            @foreach(['normal' => 'عادي', 'confidential' => 'سري', 'secret' => 'سري جدًا', 'top_secret' => 'سري للغاية'] as $value => $label)
                <option value="{{ $value }}" @selected(old('confidentiality', $miscBook->confidentiality ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>الأولوية</label>
        <select name="priority" required>
            @foreach(['normal' => 'عادي', 'urgent' => 'عاجل', 'very_urgent' => 'عاجل جدًا'] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $miscBook->priority ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group full">
        <label>الكلمات المفتاحية</label>
        <input type="text" name="keywords" value="{{ old('keywords', $miscBook->keywords ?? '') }}" placeholder="افصل بين الكلمات بفاصلة">
    </div>

    <div class="form-group full">
        <label>المرفقات</label>
        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx">
        <small>يمكن رفع أكثر من ملف، بحد أقصى 50 MB لكل ملف.</small>
    </div>

    <div class="form-group full">
        <label>ملاحظات</label>
        <textarea name="notes" rows="4">{{ old('notes', $miscBook->notes ?? '') }}</textarea>
    </div>
</div>
