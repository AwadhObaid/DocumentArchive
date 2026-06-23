@extends('layouts.app')

@section('title', 'إضافة مستند')

@section('content')
    <div class="page-title">
        <h1>إضافة مستند جديد</h1>

        <a href="{{ route('documents.index') }}" class="btn btn-secondary">
            رجوع
        </a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label>تاريخ الإشارة</label>
                    <input type="date" name="reference_date" value="{{ old('reference_date', date('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label>عنوان المستند</label>
                    <input type="text" name="title" value="{{ old('title') }}" required>
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">-- اختر الإدارة --</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
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
                            <option value="{{ $type->id }}" @selected(old('document_type_id') == $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>المرسل</label>
                    <input type="text" name="sender" value="{{ old('sender') }}">
                </div>

                <div class="form-group">
                    <label>المستلم</label>
                    <input type="text" name="receiver" value="{{ old('receiver') }}">
                </div>

                <div class="form-group">
                    <label>درجة السرية</label>
                    <select name="confidentiality" required>
                        <option value="normal" @selected(old('confidentiality') === 'normal')>عادي</option>
                        <option value="confidential" @selected(old('confidentiality') === 'confidential')>سري</option>
                        <option value="very_confidential" @selected(old('confidentiality') === 'very_confidential')>سري للغاية</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority" required>
                        <option value="normal" @selected(old('priority') === 'normal')>عادي</option>
                        <option value="high" @selected(old('priority') === 'high')>هام</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>عاجل</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>الموضوع</label>
                    <textarea name="subject">{{ old('subject') }}</textarea>
                </div>

                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>

                <div class="form-group full">
                    <label>مرفق المستند PDF / صورة / ملف</label>
                    <input type="file" name="attachment">
                </div>

                <div class="form-group full">
                    <label>ملاحظات</label>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    حفظ وتوليد الإشارة
                </button>
            </div>
        </form>
    </div>
@endsection
