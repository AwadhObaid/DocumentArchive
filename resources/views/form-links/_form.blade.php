<div class="form-grid">
    <div class="form-group">
        <label>اسم النموذج</label>
        <input type="text" name="title" value="{{ old('title', $formLink->title) }}" required placeholder="مثال: نموذج طلب إجازة">
    </div>

    <div class="form-group">
        <label>رابط النموذج</label>
        <input type="text" name="url" value="{{ old('url', $formLink->url) }}" required dir="ltr" placeholder="https://example.com/form أو /documents/create">
        <small class="hint">يقبل رابطاً خارجياً يبدأ بـ https:// أو مساراً داخلياً يبدأ بـ /.</small>
    </div>

    <div class="form-group">
        <label>التصنيف</label>
        <input type="text" name="category" value="{{ old('category', $formLink->category) }}" placeholder="مثال: الموارد البشرية، المالية، الصادر والوارد">
    </div>

    <div class="form-group">
        <label>الأيقونة</label>
        <input type="text" name="icon" value="{{ old('icon', $formLink->icon ?: '📝') }}" maxlength="20" placeholder="📝">
    </div>

    <div class="form-group">
        <label>ترتيب العرض</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $formLink->sort_order ?? 0) }}" min="0" max="999999">
    </div>

    <div class="form-group">
        <label>الحالة والفتح</label>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formLink->exists ? $formLink->is_active : true))>
            نموذج نشط
        </label>
        <label class="checkbox-line">
            <input type="checkbox" name="opens_new_tab" value="1" @checked(old('opens_new_tab', $formLink->exists ? $formLink->opens_new_tab : true))>
            فتح في نافذة جديدة
        </label>
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <label>الوصف</label>
    <textarea name="description" rows="4" placeholder="وصف مختصر يساعد المستخدم على معرفة وظيفة النموذج">{{ old('description', $formLink->description) }}</textarea>
</div>
