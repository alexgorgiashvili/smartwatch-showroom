@extends('layouts.app')

@section('title', __('ui.contact_title'))
@section('meta_description', __('ui.contact_sub'))

@section('content')
	<section class="tech-surface overflow-hidden">
		<div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
			<header class="text-center">
				<p class="font-mono text-[11px] sm:text-xs uppercase tracking-[0.26em] text-white/60">[02] CONNECT</p>
				<h1 class="mt-3 text-3xl sm:text-5xl font-semibold tracking-tight text-white">{{ __('ui.contact_title') }}</h1>
				<p class="mt-4 text-sm sm:text-base text-white/70 max-w-2xl mx-auto">{{ __('ui.contact_sub') }}</p>
			</header>

			<div class="mt-10 grid grid-cols-12 gap-4 [grid-auto-flow:dense] items-start content-start">
				<article class="col-span-12 lg:col-span-5 glass-card p-5 sm:p-6">
					<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">[FAST] CHANNELS</p>
					<h2 class="mt-3 text-xl sm:text-2xl font-semibold tracking-tight text-white">აირჩიე კონტაქტის გზა</h2>
					<p class="mt-2 text-sm text-white/70">SIM-იანი სმარტ საათების არჩევაში დაგეხმარებით — მოდელი, ქსელი, ბატარეა, GPS და უსაფრთხოების ფუნქციები.</p>

					<div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
						<a href="tel:{{ $contactSettings['phone_link'] ?? '+995555123456' }}" class="glass-card p-4 hover:bg-white/5">
							<div class="flex items-center justify-between gap-3">
								<div>
									<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">CALL</p>
									<p class="mt-2 font-semibold text-white">{{ $contactSettings['phone_display'] ?? '+995 555 123 456' }}</p>
									<p class="mt-1 text-xs text-white/70">სწრაფი კონსულტაცია</p>
								</div>
								<i class="fas fa-phone text-white/70"></i>
							</div>
						</a>

						<a href="{{ $contactSettings['whatsapp_url'] ?? 'https://wa.me/995555123456' }}" target="_blank" rel="noopener noreferrer" class="glass-card p-4 hover:bg-white/5">
							<div class="flex items-center justify-between gap-3">
								<div>
									<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">WHATSAPP</p>
									<p class="mt-2 font-semibold text-white">Chat</p>
									<p class="mt-1 text-xs text-white/70">ფოტო/ვიდეო + კითხვა</p>
								</div>
								<i class="fab fa-whatsapp text-white/70"></i>
							</div>
						</a>

						<button type="button" data-open-chat class="glass-card p-4 hover:bg-white/5 tech-pulse">
							<div class="flex items-center justify-between gap-3">
								<div>
									<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">LIVE CHAT</p>
									<p class="mt-2 font-semibold text-white">KidSIM Assistant</p>
									<p class="mt-1 text-xs text-white/70">ონლაინ დახმარება</p>
								</div>
								<i class="fas fa-comment-dots text-white/70"></i>
							</div>
						</button>

						<a href="mailto:{{ $contactSettings['email'] ?? 'info@kidsimwatch.ge' }}" class="glass-card p-4 hover:bg-white/5">
							<div class="flex items-center justify-between gap-3">
								<div>
									<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">EMAIL</p>
									<p class="mt-2 font-semibold text-white">{{ $contactSettings['email'] ?? 'info@kidsimwatch.ge' }}</p>
									<p class="mt-1 text-xs text-white/70">დეტალური მოთხოვნა</p>
								</div>
								<i class="fas fa-envelope text-white/70"></i>
							</div>
						</a>
					</div>

					<div class="mt-6 tech-hairline"></div>
					<div class="mt-5 grid grid-cols-2 gap-3">
						<div class="rounded-xl bg-white/5 border border-white/10 p-4">
							<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">LOCATION</p>
							<p class="mt-2 font-semibold text-white">{{ $contactSettings['location'] ?? 'Tbilisi, Georgia' }}</p>
							<p class="mt-1 text-xs text-white/70">შეხვედრა შეთანხმებით</p>
						</div>
						<div class="rounded-xl bg-white/5 border border-white/10 p-4">
							<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">HOURS</p>
							<p class="mt-2 font-semibold text-white">{{ $contactSettings['hours'] ?? __('ui.contact_hours_text') }}</p>
							<p class="mt-1 text-xs text-white/70">წერილები 24/7</p>
						</div>
					</div>
				</article>

				<article class="col-span-12 lg:col-span-7 glass-card p-5 sm:p-7">
					<div class="flex items-start justify-between gap-4">
						<div>
							<p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">[FORM] INQUIRY</p>
							<h2 class="mt-3 text-2xl sm:text-3xl font-semibold tracking-tight text-white">{{ __('ui.contact_form_title') }}</h2>
							<p class="mt-2 text-sm text-white/70">მოკლე შეტყობინება — ჩვენ დაგიბრუნდებით ზარით ან WhatsApp-ით.</p>
						</div>
						<span class="text-white/40" aria-hidden="true"><i class="fas fa-sim-card"></i></span>
					</div>

					@if ($errors->any())
						<div class="mt-6 rounded-xl border border-white/10 bg-white/5 p-4">
							<p class="text-sm font-medium text-white/90">შეასწორეთ ველები და სცადეთ თავიდან.</p>
							<ul class="mt-2 list-disc list-inside text-sm text-white/70">
								@foreach ($errors->all() as $error)
									<li>{{ $error }}</li>
								@endforeach
							</ul>
						</div>
					@endif

					@if (session('status'))
						<div class="mt-6 rounded-xl border border-white/10 bg-white/5 p-4">
							<p class="text-sm font-medium text-white/90">
								<i class="fas fa-check-circle mr-2 text-white/80"></i>
								{{ session('status') }}
							</p>
						</div>
					@endif

					<form action="{{ route('inquiries.store') }}" method="POST" class="mt-7 space-y-6">
						@csrf

						<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
							<div>
								<label for="name" class="block font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">{{ __('ui.form_name') }} *</label>
								<input
									type="text"
									id="name"
									name="name"
									value="{{ old('name') }}"
									required
									class="mt-2 w-full bg-transparent px-0 py-3 text-white placeholder:text-white/35 border-0 border-b border-white/15 focus:border-white/40 focus:ring-0 @error('name') border-b-red-400 @enderror"
									placeholder="{{ __('ui.form_name') }}"
								/>
								@error('name')
									<p class="mt-2 text-sm text-red-200">{{ $message }}</p>
								@enderror
							</div>

							<div>
								<label for="phone" class="block font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">{{ __('ui.form_phone') }} *</label>
								<input
									type="tel"
									id="phone"
									name="phone"
									value="{{ old('phone') }}"
									required
									class="mt-2 w-full bg-transparent px-0 py-3 text-white placeholder:text-white/35 border-0 border-b border-white/15 focus:border-white/40 focus:ring-0 @error('phone') border-b-red-400 @enderror"
									placeholder="+995 55X XXX XXX"
								/>
								@error('phone')
									<p class="mt-2 text-sm text-red-200">{{ $message }}</p>
								@enderror
							</div>
						</div>

						<div>
							<label for="email" class="block font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">{{ __('ui.form_email') }}</label>
							<input
								type="email"
								id="email"
								name="email"
								value="{{ old('email') }}"
								class="mt-2 w-full bg-transparent px-0 py-3 text-white placeholder:text-white/35 border-0 border-b border-white/15 focus:border-white/40 focus:ring-0 @error('email') border-b-red-400 @enderror"
								placeholder="you@example.com"
							/>
							@error('email')
								<p class="mt-2 text-sm text-red-200">{{ $message }}</p>
							@enderror
						</div>

						<div>
							<label for="message" class="block font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">{{ __('ui.form_message') }}</label>
							<textarea
								id="message"
								name="message"
								rows="5"
								class="mt-2 w-full resize-none bg-transparent px-0 py-3 text-white placeholder:text-white/35 border-0 border-b border-white/15 focus:border-white/40 focus:ring-0 @error('message') border-b-red-400 @enderror"
								placeholder="მაგ: რომელი მოდელი ჯობს eSIM/SIM-ზე, GPS სიზუსტე, ბატარეა..."
							>{{ old('message') }}</textarea>
							@error('message')
								<p class="mt-2 text-sm text-red-200">{{ $message }}</p>
							@enderror
						</div>

						<div class="pt-2">
							<button type="submit" class="tech-pulse w-full rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-white/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30">
								<span class="inline-flex items-center justify-center gap-2">
									<i class="fas fa-paper-plane"></i>
									{{ __('ui.form_submit') }}
								</span>
							</button>
							<p class="mt-3 text-xs text-white/60">დაჩქარებისთვის: WhatsApp ან Live Chat.</p>
						</div>
					</form>
				</article>
			</div>
		</div>
	</section>

@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			document.querySelectorAll('[data-open-chat]').forEach((btn) => {
				btn.addEventListener('click', () => {
					const chatbotToggle = document.querySelector('[data-chatbot-toggle]');
					if (chatbotToggle) chatbotToggle.click();
				});
			});
		});
	</script>
@endpush
