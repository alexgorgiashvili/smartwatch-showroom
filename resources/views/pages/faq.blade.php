@extends('layouts.app')

@section('title', 'ხშირად დასმული კითხვები')
@section('meta_description', 'ხშირად დასმული კითხვები KidSIM Watch საათებზე, მიწოდებაზე, დაბრუნებაზე და ბავშვის უსაფრთხოებაზე.')

@section('content')
  <section class="tech-surface overflow-hidden">
    <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
      <header class="text-center">
        <p class="font-mono text-[11px] sm:text-xs uppercase tracking-[0.26em] text-white/60">[01] SUPPORT • FAQ</p>
        <h1 class="mt-3 text-3xl sm:text-5xl font-semibold tracking-tight text-white">ხშირად დასმული კითხვები</h1>
        <p class="mt-4 text-sm sm:text-base text-white/70 max-w-2xl mx-auto">
          სწრაფი პასუხები KidSIM Watch-ის SIM-კავშირზე, უსაფრთხოებაზე, მიწოდებასა და დაბრუნებაზე.
        </p>
      </header>

      <div class="mt-10">
        <div class="grid grid-cols-12 gap-4 items-start content-start" data-accordion-root>
          <article class="col-span-12 lg:col-span-4 glass-card p-5 sm:p-6">
            <p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">[LIVE] CONNECTIVITY</p>
            <h2 class="mt-3 text-xl sm:text-2xl font-semibold tracking-tight text-white">დაგჭირდათ სწრაფი პასუხი?</h2>
            <p class="mt-2 text-sm text-white/70">დაწერეთ Live Chat-ში ან მოგვწერეთ WhatsApp-ზე — ჩვეულებრივ სწრაფად გპასუხობთ.</p>
            <div class="mt-5 flex flex-wrap gap-3">
              <button type="button" data-open-chat class="tech-pulse inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30">
                <i class="fas fa-comment-dots text-white/90"></i>
                <span>Live Chat</span>
              </button>
              <a href="{{ $contactSettings['whatsapp_url'] ?? 'https://wa.me/995555123456' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15">
                <i class="fab fa-whatsapp text-white/90"></i>
                <span>WhatsApp</span>
              </a>
              <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-white text-slate-950 px-4 py-2 text-sm font-semibold hover:bg-white/90">
                <i class="fas fa-envelope"></i>
                <span>კონტაქტი</span>
              </a>
            </div>
            <div class="mt-6 tech-hairline"></div>
            <ul class="mt-5 grid grid-cols-2 gap-4 text-sm">
              <li class="rounded-xl bg-white/5 border border-white/10 p-4">
                <p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">SIM</p>
                <p class="mt-2 font-semibold text-white">კავშირი 24/7</p>
                <p class="mt-1 text-xs text-white/70">ზარები + მდებარეობა</p>
              </li>
              <li class="rounded-xl bg-white/5 border border-white/10 p-4">
                <p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">GPS</p>
                <p class="mt-2 font-semibold text-white">ტრეკინგი</p>
                <p class="mt-1 text-xs text-white/70">მშვიდი კონტროლი</p>
              </li>
            </ul>
          </article>

          <div class="col-span-12 lg:col-span-8 space-y-4">
            @php
              $categoryNumber = 1;
            @endphp

            @forelse($faqCategories as $category => $items)
              @php
                $categoryTag = sprintf('[%02d] %s', $categoryNumber, $category);
                $categoryNumber++;
              @endphp

              <article class="glass-card p-5 sm:p-6" data-accordion-scope>
                <div class="flex flex-wrap items-end justify-between gap-3">
                  <div>
                    <h2 class="mt-3 text-xl sm:text-2xl font-semibold tracking-tight text-white">{{ $category }}</h2>
                  </div>
                  <p class="text-xs text-white/60">{{ $items->count() }} კითხვა</p>
                </div>

                <div class="mt-5 tech-hairline"></div>

                <ul class="mt-2">
                  @foreach($items as $faq)
                    <li class="py-4 border-b border-white/10 last:border-b-0">
                      <button
                        type="button"
                        class="w-full text-left"
                        data-accordion-trigger
                        aria-expanded="false"
                        aria-controls="faq-panel-{{ $faq->id }}"
                      >
                        <div class="flex items-start justify-between gap-4">
                          <h3 class="text-base sm:text-lg font-semibold tracking-tight text-white/95 pr-6">{{ $faq->question }}</h3>
                          <span data-accordion-chevron class="mt-1 inline-flex size-8 items-center justify-center rounded-full bg-white/5 border border-white/10 text-white/70 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                          </span>
                        </div>
                      </button>

                      <div id="faq-panel-{{ $faq->id }}" class="accordion-panel" data-accordion-panel data-open="0">
                        <div class="pt-4 text-sm leading-relaxed text-white/75 whitespace-pre-line">
                          {{ $faq->answer }}
                        </div>
                      </div>
                    </li>
                  @endforeach
                </ul>
              </article>
            @empty
              <article class="glass-card p-6 text-center">
                <p class="text-white/70">FAQ სია ამჟამად ცარიელია. მოგვიანებით შეამოწმეთ კიდევ ერთხელ.</p>
              </article>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const triggers = document.querySelectorAll('[data-accordion-trigger]');
      const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      const setOpen = (button, panel, open) => {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        const chevron = button.querySelector('[data-accordion-chevron]');
        if (chevron) chevron.classList.toggle('rotate-180', open);
        panel.dataset.open = open ? '1' : '0';
        const height = open ? panel.scrollHeight : 0;
        panel.style.setProperty('--accordion-max-height', `${height}px`);
      };

      const getPanelForTrigger = (button) => {
        const panelId = button.getAttribute('aria-controls');
        if (!panelId) return null;
        return document.getElementById(panelId);
      };

      const closeAllInScope = (scopeEl, exceptButton = null) => {
        const scopeTriggers = scopeEl.querySelectorAll('[data-accordion-trigger]');
        scopeTriggers.forEach((btn) => {
          if (exceptButton && btn === exceptButton) return;
          const panel = getPanelForTrigger(btn);
          if (!panel) return;
          setOpen(btn, panel, false);
        });
      };

      triggers.forEach((button) => {
        const panel = getPanelForTrigger(button);
        if (!panel) return;

        if (prefersReducedMotion) {
          panel.style.transition = 'none';
        }

        button.addEventListener('click', () => {
          const isOpen = button.getAttribute('aria-expanded') === 'true';
          const nextOpen = !isOpen;
          const scope = button.closest('[data-accordion-scope]');

          if (scope && nextOpen) {
            closeAllInScope(scope, button);
          }

          setOpen(button, panel, nextOpen);
        });
      });

      window.addEventListener('resize', () => {
        triggers.forEach((button) => {
          const isOpen = button.getAttribute('aria-expanded') === 'true';
          if (!isOpen) return;
          const panel = getPanelForTrigger(button);
          if (!panel) return;
          setOpen(button, panel, true);
        });
      });

      const openChatButtons = document.querySelectorAll('[data-open-chat]');
      openChatButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const chatbotToggle = document.querySelector('[data-chatbot-toggle]');
          if (chatbotToggle) chatbotToggle.click();
        });
      });
    });
  </script>
@endpush
