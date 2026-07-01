<div class="ct-template-layout">
    <div class="ct-form-grid">
        <div class="form-group">
            <label>اسم القالب <span class="required">*</span></label>
            <input type="text" name="name" value="{{ old('name', $template->name) }}" required>
        </div>
        <div class="form-group">
            <label>القناة</label>
            <select name="channel" required>
                <option value="email" @selected(old('channel', $template->channel ?: 'both') === 'email')>البريد الإلكتروني فقط</option>
                <option value="whatsapp" @selected(old('channel', $template->channel ?: 'both') === 'whatsapp')>واتساب فقط</option>
                <option value="both" @selected(old('channel', $template->channel ?: 'both') === 'both')>البريد وواتساب</option>
            </select>
        </div>
        <div class="form-group full">
            <label>موضوع البريد</label>
            <input type="text" name="subject_template" value="{{ old('subject_template', $template->subject_template) }}" placeholder="يستخدم في البريد الإلكتروني فقط">
        </div>
        <div class="form-group full">
            <label>نص الرسالة <span class="required">*</span></label>
            <textarea name="body_template" rows="12" required>{{ old('body_template', $template->body_template) }}</textarea>
        </div>
        <div class="form-group">
            <label>ترتيب العرض</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $template->sort_order ?? 0) }}" min="0">
        </div>
        <div class="form-group ct-check-row">
            <input type="hidden" name="is_active" value="0">
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))> قالب نشط</label>
            <input type="hidden" name="is_default" value="0">
            <label><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default ?? false))> قالب افتراضي</label>
        </div>
    </div>

    <aside class="ct-variables-card">
        <h3>المتغيرات المتاحة</h3>
        <p>انسخ أي متغير وضعه داخل النص أو الموضوع.</p>
        <div class="ct-variables-list">
            @foreach($variables as $key => $label)
                <button type="button" class="ct-variable-chip" data-copy="{{ '{' . $key . '}' }}"><code>{{ '{' . $key . '}' }}</code><span>{{ $label }}</span></button>
            @endforeach
        </div>
    </aside>
</div>

<script>
document.addEventListener('click', function(event) {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const value = button.getAttribute('data-copy');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(value);
        button.classList.add('copied');
        setTimeout(() => button.classList.remove('copied'), 900);
    }
});
</script>
