@php
    $smartReferenceNumber = (string) ($referenceNumber ?? '');
    $smartReferenceYear = (string) ($referenceYear ?? date('Y'));
    $smartReferenceElement = (string) ($referenceElement ?? '');
    $smartYearElement = (string) ($yearElement ?? '');
    $smartDefaultSource = (string) ($defaultSource ?? 'outgoing');
    $smartOldToken = (string) old('smart_attachment_token', '');
@endphp

<div
    class="da-smart-attachment-field"
    data-smart-attachment-field
    data-sources-url="{{ route('smart-attachment-browser.sources') }}"
    data-search-url="{{ route('smart-attachment-browser.search') }}"
    data-settings-url="{{ route('settings.edit') }}"
    data-reference-number="{{ $smartReferenceNumber }}"
    data-reference-year="{{ $smartReferenceYear }}"
    data-reference-element="{{ $smartReferenceElement }}"
    data-year-element="{{ $smartYearElement }}"
    data-default-source="{{ $smartDefaultSource }}"
>
    <input
        type="file"
        name="attachment"
        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx"
        data-smart-native-file
        class="da-smart-native-input"
    >

    <input
        type="hidden"
        name="smart_attachment_token"
        value="{{ $smartOldToken }}"
        data-smart-attachment-token
    >

    <div class="da-smart-attachment-actions">
        <button type="button" class="btn btn-primary" data-smart-attachment-open>
            🔎 البحث في الأرشيف
        </button>

        <button type="button" class="btn btn-secondary" data-smart-attachment-native>
            📁 اختيار من الجهاز
        </button>

        <button type="button" class="btn btn-light" data-smart-attachment-clear hidden>
            إزالة الاختيار
        </button>
    </div>

    <div
        class="da-smart-attachment-selected"
        data-smart-attachment-selected
        @if($smartOldToken === '') hidden @endif
    >
        @if($smartOldToken !== '')
            تم الاحتفاظ باختيار من البحث الذكي. يمكن إزالته أو اختيار ملف آخر.
        @endif
    </div>

    <small>
        البحث الذكي يقرأ المسارات المحفوظة في الإعدادات على السيرفر أو الشبكة. للملفات الموجودة على جهازك استخدم «اختيار من الجهاز».
    </small>

    @error('smart_attachment_token')
        <small class="da-smart-attachment-error">{{ $message }}</small>
    @enderror

    @error('attachment')
        <small class="da-smart-attachment-error">{{ $message }}</small>
    @enderror
</div>
