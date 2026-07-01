@php
    $types = ['internal'=>'داخلية','external'=>'خارجية','government'=>'جهة حكومية','company'=>'شركة','department'=>'إدارة','other'=>'أخرى'];
@endphp
<div class="ct-form-grid">
    <div class="form-group">
        <label>اسم الجهة <span class="required">*</span></label>
        <input type="text" name="name" value="{{ old('name', $contact->name) }}" required>
    </div>

    <div class="form-group">
        <label>نوع الجهة</label>
        <select name="type" required>
            @foreach($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $contact->type ?: 'external') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>اسم المؤسسة / الإدارة</label>
        <input type="text" name="organization" value="{{ old('organization', $contact->organization) }}">
    </div>

    <div class="form-group">
        <label>اسم الشخص</label>
        <input type="text" name="contact_person" value="{{ old('contact_person', $contact->contact_person) }}">
    </div>

    <div class="form-group">
        <label>البريد الإلكتروني</label>
        <input type="email" name="email" value="{{ old('email', $contact->email) }}" dir="ltr" placeholder="example@domain.com">
    </div>

    <div class="form-group">
        <label>رقم واتساب</label>
        <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $contact->whatsapp_number) }}" dir="ltr" placeholder="+965XXXXXXXX">
    </div>

    <div class="form-group">
        <label>رقم الهاتف</label>
        <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}" dir="ltr">
    </div>

    <div class="form-group">
        <label>ترتيب العرض</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $contact->sort_order ?? 0) }}" min="0">
    </div>

    <div class="form-group full">
        <label>ملاحظات</label>
        <textarea name="notes" rows="4">{{ old('notes', $contact->notes) }}</textarea>
    </div>

    <div class="form-group full ct-check-row">
        <input type="hidden" name="is_active" value="0">
        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $contact->is_active ?? true))>
            جهة نشطة ويمكن استخدامها في البريد وواتساب
        </label>
    </div>
</div>
