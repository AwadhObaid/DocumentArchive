@extends('layouts.app')

@section('title', 'سلة المحذوفات')

@section('content')
    @php
        $documentsTotal = $stats['documents'] ?? $documents->total();
        $memosTotal = $stats['memos'] ?? $memos->total();
        $canRestoreDocuments = auth()->user()?->hasPermission('documents.restore');
        $canForceDeleteDocuments = auth()->user()?->hasPermission('documents.force_delete');
        $canRestoreMemos = auth()->user()?->hasPermission('documents.restore') || auth()->user()?->hasPermission('memos.restore');
        $canForceDeleteMemos = auth()->user()?->hasPermission('memos.force_delete');
    @endphp

    <div class="trash-page">
        <div class="page-title trash-page-title">
            <div class="trash-page-heading">
                <h1>سلة المحذوفات</h1>
                <p class="muted">إدارة الكتب والمذكرات المحذوفة ظاهريًا، مع إمكانية الاستعادة أو الحذف النهائي حسب الصلاحية.</p>
            </div>

            <div class="actions trash-top-actions">
                @if(auth()->user()?->hasPermission('documents.view'))
                    <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع للكتب</a>
                @endif

                @if(auth()->user()?->hasPermission('memos.view'))
                    <a href="{{ route('memos.index') }}" class="btn btn-secondary">رجوع للمذكرات</a>
                @endif
            </div>
        </div>

        <div class="trash-overview-grid" aria-label="ملخص سلة المحذوفات">
            <div class="trash-overview-card trash-overview-documents">
                <div class="trash-overview-icon" aria-hidden="true">📄</div>
                <div class="trash-overview-meta">
                    <span class="trash-overview-label">الكتب المحذوفة</span>
                    <small>يمكن استعادتها قبل الحذف النهائي</small>
                </div>
                <strong class="trash-overview-number">{{ number_format($documentsTotal) }}</strong>
            </div>

            <div class="trash-overview-card trash-overview-memos">
                <div class="trash-overview-icon" aria-hidden="true">📝</div>
                <div class="trash-overview-meta">
                    <span class="trash-overview-label">المذكرات المحذوفة</span>
                    <small>تتبع نفس استراتيجية الكتب</small>
                </div>
                <strong class="trash-overview-number">{{ number_format($memosTotal) }}</strong>
            </div>
        </div>

        <div class="card trash-section-card" id="trash-documents">
            <div class="trash-section-header">
                <div class="trash-section-title">
                    <h2>الكتب المحذوفة</h2>
                    <p class="muted">هذا الجدول خاص بالكتب التي تم حذفها ظاهريًا فقط.</p>
                </div>
                <span class="badge trash-count-badge">{{ number_format($documents->total()) }} كتاب</span>
            </div>

            <div class="trash-table-wrap">
                <table class="trash-table">
                    <thead>
                    <tr>
                        <th>رقم الكتاب</th>
                        <th>تاريخ الكتاب</th>
                        <th>موضوع الكتاب</th>
                        <th>البوليصة الرئيسية</th>
                        <th>تاريخ الحذف</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td><strong>{{ $document->reference_number }}</strong></td>
                            <td>{{ $document->formatted_date }}</td>
                            <td>{{ $document->subject ?: $document->title }}</td>
                            <td>{{ $document->main_policy_number ?? '-' }}</td>
                            <td>{{ $document->deleted_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions trash-row-actions">
                                    @if($canRestoreDocuments)
                                        <form method="POST" action="{{ route('documents.restore', $document->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success">استعادة</button>
                                        </form>
                                    @endif

                                    @if($canForceDeleteDocuments)
                                        <form method="POST" action="{{ route('documents.force-delete', $document->id) }}" data-confirm="تحذير: سيتم حذف الكتاب ومرفقاته نهائياً. هل أنت متأكد؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">حذف نهائي</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="trash-empty">لا توجد كتب في سلة المحذوفات.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination trash-pagination">
                {{ $documents->appends(request()->except('documents_page'))->links() }}
            </div>
        </div>

        <div class="card trash-section-card" id="trash-memos">
            <div class="trash-section-header">
                <div class="trash-section-title">
                    <h2>المذكرات المحذوفة</h2>
                    <p class="muted">هذا الجدول خاص بالمذكرات، ولا يتم حذف المرفقات نهائيًا إلا من هنا.</p>
                </div>
                <span class="badge trash-count-badge">{{ number_format($memos->total()) }} مذكرة</span>
            </div>

            <div class="trash-table-wrap">
                <table class="trash-table">
                    <thead>
                    <tr>
                        <th>رقم المذكرة</th>
                        <th>تاريخ المذكرة</th>
                        <th>موضوع المذكرة</th>
                        <th>الإدارة</th>
                        <th>المرفقات</th>
                        <th>تاريخ الحذف</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($memos as $memo)
                        <tr>
                            <td><strong>{{ $memo->memo_number }}</strong></td>
                            <td>{{ $memo->formatted_date }}</td>
                            <td>{{ $memo->subject }}</td>
                            <td>{{ $memo->department?->name ?? '-' }}</td>
                            <td>{{ number_format($memo->attachments_count ?? 0) }}</td>
                            <td>{{ $memo->deleted_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions trash-row-actions">
                                    @if($canRestoreMemos)
                                        <form method="POST" action="{{ route('memos.restore', $memo->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success">استعادة</button>
                                        </form>
                                    @endif

                                    @if($canForceDeleteMemos)
                                        <form method="POST" action="{{ route('memos.force-delete', $memo->id) }}" data-confirm="تحذير: سيتم حذف المذكرة ومرفقاتها نهائياً. هل أنت متأكد؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">حذف نهائي</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="trash-empty">لا توجد مذكرات في سلة المحذوفات.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination trash-pagination">
                {{ $memos->appends(request()->except('memos_page'))->links() }}
            </div>
        </div>
    </div>
@endsection
