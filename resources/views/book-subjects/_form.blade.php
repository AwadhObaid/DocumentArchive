<div class="form-grid">
    <div class="form-group">
        <label>اسم الموضوع</label>
        <input type="text" name="name" value="{{ old('name', $bookSubject->name ?? '') }}" required autofocus placeholder="مثال: مطالبة تأمين">
    </div>

    <div class="form-group">
        <label>الكود</label>
        <input type="text" name="code" value="{{ old('code', $bookSubject->code ?? '') }}" placeholder="اختياري مثل CLAIM">
    </div>

    <div class="form-group">
        <label>الترتيب</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $bookSubject->sort_order ?? 0) }}" min="0" step="1">
    </div>

    <div class="form-group full">
        <label>الوصف</label>
        <textarea name="description" rows="4" placeholder="وصف مختصر للموضوع">{{ old('description', $bookSubject->description ?? '') }}</textarea>
    </div>

    <div class="form-group full">
        <label style="display:flex; gap:8px; align-items:center;">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bookSubject->is_active ?? true))>
            موضوع نشط ويظهر في قائمة إضافة الكتاب
        </label>
    </div>
</div>
