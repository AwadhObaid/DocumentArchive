@extends('layouts.app')

@section('title', 'تعديل مستند')

@section('content')
    <div class="page-title">
        <h1>تعديل المستند</h1>

        <div class="actions">
            <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">عرض</a>
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع</a>
        </div>
    </div>

    <div class="card">
        <h2>الإشارة: {{ $document->reference_number }}</h2>

        <form method="POST" action="{{ route('documents.update', $document) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label>تاريخ الإشارة</label>
                    <input type="date" name="reference_date" value="{{ old('reference_date', $document->reference_date->format('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label>عنوان المستند</label>
                    <input type="text" name="title" value="{{ old('title', $document->title) }}" required>
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">-- اختر الإدارة --</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $document->department_id) == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع المستند</label>
                    <select name="document_type_id">
                        <option value="">-- اختر النوع --</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('document_type_id', $document->document_type_id) == $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>المرسل</label>
                    <input type="text" name="sender" value="{{ old('sender', $document->sender) }}">
                </div>

                <div class="form-group">
                    <label>المستلم</label>
                    <input type="text" name="receiver" value="{{ old('receiver', $document->receiver) }}">
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status" required>
                        <option value="active" @selected(old('status', $document->status) === 'active')>نشط</option>
                        <option value="archived" @selected(old('status', $document->status) === 'archived')>مؤرشف</option>
                        <option value="cancelled" @selected(old('status', $document->status) === 'cancelled')>ملغي</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>درجة السرية</label>
                    <select name="confidentiality" required>
                        <option value="normal" @selected(old('confidentiality', $document->confidentiality) === 'normal')>عادي</option>
                        <option value="confidential" @selected(old('confidentiality', $document->confidentiality) === 'confidential')>سري</option>
                        <option value="very_confidential" @selected(old('confidentiality', $document->confidentiality) === 'very_confidential')>سري للغاية</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority" required>
                        <option value="normal" @selected(old('priority', $document->priority) === 'normal')>عادي</option>
                        <option value="high" @selected(old('priority', $document->priority) === 'high')>هام</option>
                        <option value="urgent" @selected(old('priority', $document->priority) === 'urgent')>عاجل</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>الموضوع</label>
                    <textarea name="subject">{{ old('subject', $document->subject) }}</textarea>
                </div>

                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description', $document->description) }}</textarea>
                </div>

                <div class="form-group full">
                    <label>رفع مرفق جديد</label>
                    <input type="file" name="attachment">
                    <small>عند رفع مرفق جديد سيتم حفظه كنسخة جديدة، ولن يتم حذف النسخ السابقة.</small>
                </div>

                <div class="form-group full">
                    <label>ملاحظات</label>
                    <textarea name="notes">{{ old('notes', $document->notes) }}</textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
@endsection