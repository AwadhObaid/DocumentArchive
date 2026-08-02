@php
    $smartReferenceNumber = (string) ($referenceNumber ?? '');
    $smartReferenceYear = (string) ($referenceYear ?? date('Y'));
    $smartReferenceElement = (string) ($referenceElement ?? '');
    $smartYearElement = (string) ($yearElement ?? '');
    $smartDefaultSource = (string) ($defaultSource ?? 'outgoing');
    $smartOldToken = (string) old('smart_attachment_token', '');
    $fileBridgeOldToken = (string) old('file_bridge_token', '');
    $fileBridgeDocumentId = isset($document) && $document ? (string) $document->getKey() : '';
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
    data-file-bridge-create-url="{{ route('file-bridge.create') }}"
    data-file-bridge-download-url="{{ route('file-bridge.download-client') }}"
    data-file-bridge-document-id="{{ $fileBridgeDocumentId }}"
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

    <input
        type="hidden"
        name="file_bridge_token"
        value="{{ $fileBridgeOldToken }}"
        data-file-bridge-token
    >

    <div class="da-smart-attachment-actions">
        <button type="button" class="btn btn-primary" data-smart-attachment-open>
            🔎 البحث في أرشيف الشبكة
        </button>

        <button type="button" class="btn da-file-bridge-button" data-file-bridge-open>
            🖥️ البحث في ملفات الجهاز
        </button>

        <button type="button" class="btn btn-secondary" data-smart-attachment-native>
            📁 اختيار ملف مباشرة
        </button>

        <button type="button" class="btn btn-light" data-smart-attachment-clear @if($smartOldToken === '' && $fileBridgeOldToken === '') hidden @endif>
            إزالة الاختيار
        </button>
    </div>

    <div
        class="da-smart-attachment-selected"
        data-smart-attachment-selected
        @if($smartOldToken === '' && $fileBridgeOldToken === '') hidden @endif
    >
        @if($fileBridgeOldToken !== '')
            تم الاحتفاظ باختيار من File Bridge. يمكن إزالته أو اختيار ملف آخر.
        @elseif($smartOldToken !== '')
            تم الاحتفاظ باختيار من البحث الذكي. يمكن إزالته أو اختيار ملف آخر.
        @endif
    </div>

    <small>
        «أرشيف الشبكة» يبحث في المسارات المحفوظة على السيرفر، و«ملفات الجهاز» يستخدم أداة File Bridge للبحث بالرقم داخل المجلدات المحلية المسموح بها، بينما «اختيار ملف مباشرة» يفتح نافذة Windows التقليدية.
    </small>

    @error('file_bridge_token')
        <small class="da-smart-attachment-error">{{ $message }}</small>
    @enderror

    @error('smart_attachment_token')
        <small class="da-smart-attachment-error">{{ $message }}</small>
    @enderror

    @error('attachment')
        <small class="da-smart-attachment-error">{{ $message }}</small>
    @enderror
</div>
