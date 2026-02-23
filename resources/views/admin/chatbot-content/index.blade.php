@extends('admin.layout')

@section('title', 'Chatbot Content')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Chatbot Content</h4>
        <p class="text-muted mb-0">FAQ და Contact ინფორმაციის მართვა ერთი გვერდიდან</p>
    </div>
</div>

@if (session('warning'))
    <div class="alert alert-warning" role="alert">
        {{ session('warning') }}
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">FAQ ჩანაწერები</h5>

                <form method="POST" action="{{ route('admin.chatbot-content.faqs.store') }}" class="border rounded p-3 mb-4">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">კითხვა</label>
                        <input type="text" name="question" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">პასუხი</label>
                        <textarea name="answer" rows="4" class="form-control" required></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">კატეგორია</label>
                            <input type="text" name="category" class="form-control" value="სხვა" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">რიგი</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <label class="form-check-label">აქტიური</label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-2" type="submit">დამატება</button>
                </form>

                @forelse ($faqs as $faq)
                    <form method="POST" action="{{ route('admin.chatbot-content.faqs.update', $faq) }}" class="border rounded p-3 mb-3">
                        @csrf
                        @method('PATCH')
                        <div class="mb-2">
                            <label class="form-label">კითხვა</label>
                            <input type="text" name="question" class="form-control" value="{{ $faq->question }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">პასუხი</label>
                            <textarea name="answer" rows="4" class="form-control" required>{{ $faq->answer }}</textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">კატეგორია</label>
                                <input type="text" name="category" class="form-control" value="{{ $faq->category }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">რიგი</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ $faq->sort_order }}" min="0">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $faq->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label">აქტიური</label>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end gap-2">
                                <button class="btn btn-success" type="submit">Save</button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.chatbot-content.faqs.destroy', $faq) }}" class="mb-3">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit" onclick="return confirm('წავშალო FAQ?')">წაშლა</button>
                    </form>
                @empty
                    <p class="text-muted mb-0">FAQ ჩანაწერები ჯერ არ არსებობს.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Contact Settings</h5>

                <form method="POST" action="{{ route('admin.chatbot-content.contacts.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-2">
                        <label class="form-label">Phone (display)</label>
                        <input type="text" name="phone_display" class="form-control" value="{{ old('phone_display', $contactSettings['phone_display'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone (link digits)</label>
                        <input type="text" name="phone_link" class="form-control" value="{{ old('phone_link', $contactSettings['phone_link'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">WhatsApp URL</label>
                        <input type="url" name="whatsapp_url" class="form-control" value="{{ old('whatsapp_url', $contactSettings['whatsapp_url'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $contactSettings['email'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" value="{{ old('location', $contactSettings['location'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Hours</label>
                        <input type="text" name="hours" class="form-control" value="{{ old('hours', $contactSettings['hours'] ?? '') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-control" value="{{ old('instagram_url', $contactSettings['instagram_url'] ?? '') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-control" value="{{ old('facebook_url', $contactSettings['facebook_url'] ?? '') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Messenger URL</label>
                        <input type="url" name="messenger_url" class="form-control" value="{{ old('messenger_url', $contactSettings['messenger_url'] ?? '') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telegram URL</label>
                        <input type="url" name="telegram_url" class="form-control" value="{{ old('telegram_url', $contactSettings['telegram_url'] ?? '') }}">
                    </div>

                    <button class="btn btn-primary" type="submit">შენახვა</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
