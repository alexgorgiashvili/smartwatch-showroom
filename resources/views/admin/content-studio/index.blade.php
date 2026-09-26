@extends('admin.layout')
@section('title', 'Content Studio — Admin')
@section('content')
@fragment('content')
<div data-page-title="Content Studio">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin"><h4 class="mb-0">Content Studio — მფლობელის ინბოქსი</h4><small class="text-muted">ყველა დრო: Asia/Tbilisi</small></div>
    <div class="row g-2 mb-3">
        @foreach(\App\Models\ContentItem::STATES as $state)
        <div class="col-auto"><a data-pjax class="btn btn-sm {{ request('status') === $state ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.content-studio.index', ['status' => $state, 'channel' => request('channel')]) }}">{{ $state }} <span class="badge bg-light text-dark">{{ $counters[$state] ?? 0 }}</span></a></div>
        @endforeach
    </div>
    <form class="row g-2 mb-3" method="GET"><div class="col-md-3"><select name="channel" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">ყველა არხი</option>@foreach(\App\Models\ContentItem::CHANNELS as $channel)<option value="{{ $channel }}" @selected(request('channel') === $channel)>{{ $channel }}</option>@endforeach</select></div><input type="hidden" name="status" value="{{ request('status') }}"></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>კამპანია</th><th>არხი</th><th>სტატუსი</th><th>რევიზია</th><th>ვერიფიკაცია</th><th></th></tr></thead><tbody>
    @forelse($items as $item)<tr><td>{{ $item->campaign->name }}</td><td><span class="badge bg-secondary">{{ $item->channel }}</span></td><td><span class="badge bg-{{ in_array($item->status, ['approved','published']) ? 'success' : ($item->status === 'failed' || $item->status === 'rejected' ? 'danger' : 'warning') }}">{{ $item->status }}</span></td><td>#{{ $item->currentRevision?->revision_number ?? '—' }}</td><td>{{ $item->verification_checked_at?->timezone(config('app.timezone'))->format('d.m H:i') ?? '—' }}</td><td><a data-pjax class="btn btn-sm btn-outline-primary" href="{{ route('admin.content-studio.show', $item) }}">რევიუ</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">ინბოქსი ცარიელია</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $items->links() }}</div></div>
</div>
@endfragment
@endsection
