@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'ხშირად დასმული კითხვები — MyTechnic' : 'FAQ — MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'ხშირად დასმული კითხვები MyTechnic სმარტ საათებზე — SIM, GPS, მიწოდება, გარანტია.' : 'Frequently asked questions about MyTechnic smartwatches — SIM, GPS, delivery, warranty.')
@section('canonical', url('/faq'))
@section('og_title', app()->getLocale() === 'ka' ? 'ხშირად დასმული კითხვები — MyTechnic' : 'FAQ — MyTechnic')
@section('og_url', url('/faq'))

@push('json_ld')
@php
$_faqItems = [];
foreach ($faqCategories as $category => $items) {
    foreach ($items as $faq) {
        $_faqItems[] = [
            '@type' => 'Question',
            'name' => $faq->localizedQuestion(),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq->localizedAnswer(),
            ],
        ];
    }
}
$_faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $_faqItems,
];
@endphp
<script type="application/ld+json">{!! json_encode($_faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')
  <section class="tech-surface overflow-hidden">
    <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
      <header class="text-center">
        <h1 class="mt-3 text-3xl sm:text-5xl font-semibold tracking-tight text-white">{{ __('storefront.faq.title') }}</h1>
        <p class="mt-4 text-sm sm:text-base text-white/70 max-w-2xl mx-auto">
          {{ __('storefront.faq.subtitle') }}
        </p>
      </header>

      <div class="mt-10">
        <div class="grid grid-cols-12 gap-4 items-start content-start" data-accordion-root>
          <article class="col-span-12 lg:col-span-4 glass-card p-5 sm:p-6">
            <h2 class="text-xl sm:text-2xl font-semibold tracking-tight text-white">{{ $contactSettings['faq_support_title'] ?? __('storefront.faq.support_title') }}</h2>
            <p class="mt-2 text-sm text-white/70">{{ $contactSettings['faq_support_description'] ?? __('storefront.faq.support_description') }}</p>
            <div class="mt-5 flex flex-wrap gap-3">
              <button type="button" data-open-chat class="tech-pulse inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30">
                <i class="fas fa-comment-dots text-white/90"></i>
                <span>Live Chat</span>
              </button>
              @if (!empty($contactSettings['whatsapp_url']))
              <a href="{{ $contactSettings['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15">
                <i class="fab fa-whatsapp text-white/90"></i>
                <span>WhatsApp</span>
              </a>
              @endif
              @if (!empty($contactSettings['messenger_url']))
              <a href="{{ $contactSettings['messenger_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15">
                <i class="fab fa-facebook-messenger text-white/90"></i>
                <span>Messenger</span>
              </a>
              @endif
              <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-white text-slate-950 px-4 py-2 text-sm font-semibold hover:bg-white/90">
                <i class="fas fa-envelope"></i>
                <span>{{ __('storefront.common.contact') }}</span>
              </a>
            </div>
            <div class="mt-6 tech-hairline"></div>
            <ul class="mt-5 grid grid-cols-2 gap-4 text-sm">
              <li class="rounded-xl bg-white/5 border border-white/10 p-4">
                <p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">SIM</p>
                <p class="mt-2 font-semibold text-white">{{ __('storefront.faq.connection_title') }}</p>
                <p class="mt-1 text-xs text-white/70">{{ __('storefront.faq.connection_text') }}</p>
              </li>
              <li class="rounded-xl bg-white/5 border border-white/10 p-4">
                <p class="font-mono text-[11px] uppercase tracking-[0.26em] text-white/60">GPS</p>
                <p class="mt-2 font-semibold text-white">{{ __('storefront.faq.tracking_title') }}</p>
                <p class="mt-1 text-xs text-white/70">{{ __('storefront.faq.tracking_text') }}</p>
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
                  <p class="text-xs text-white/60">{{ trans_choice('storefront.common.questions_count', $items->count(), ['count' => $items->count()]) }}</p>
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
                          <h3 class="text-base sm:text-lg font-semibold tracking-tight text-white/95 pr-6">{{ $faq->localizedQuestion() }}</h3>
                          <span data-accordion-chevron class="mt-1 inline-flex size-8 items-center justify-center rounded-full bg-white/5 border border-white/10 text-white/70 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                          </span>
                        </div>
                      </button>

                      <div id="faq-panel-{{ $faq->id }}" class="accordion-panel" data-accordion-panel data-open="0" style="display: none; opacity: 0; height: 0; overflow: hidden;">
                        <div class="pt-4 text-sm leading-relaxed text-white/75 whitespace-pre-line">
                          {{ str_replace(["\\r\\n", "\\n", "\\r"], "\n", $faq->localizedAnswer()) }}
                        </div>
                      </div>
                    </li>
                  @endforeach
                </ul>
              </article>
            @empty
              <article class="glass-card p-6 text-center">
                <p class="text-white/70">{{ __('storefront.faq.empty') }}</p>
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

      const animateOpen = (panel) => {
        panel.style.display = 'block';
        panel.style.overflow = 'hidden';
        panel.style.height = '0px';
        panel.style.opacity = '0';

        requestAnimationFrame(() => {
          panel.style.height = `${panel.scrollHeight}px`;
          panel.style.opacity = '1';
        });
      };

      const animateClose = (panel) => {
        panel.style.overflow = 'hidden';
        panel.style.height = `${panel.scrollHeight}px`;
        panel.style.opacity = '1';

        requestAnimationFrame(() => {
          panel.style.height = '0px';
          panel.style.opacity = '0';
        });
      };

      const setOpen = (button, panel, open) => {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        const chevron = button.querySelector('[data-accordion-chevron]');
        if (chevron) chevron.classList.toggle('rotate-180', open);
        panel.dataset.open = open ? '1' : '0';

        if (prefersReducedMotion) {
          panel.style.display = open ? 'block' : 'none';
          panel.style.height = open ? 'auto' : '0px';
          panel.style.opacity = open ? '1' : '0';
          panel.style.overflow = open ? 'visible' : 'hidden';
          return;
        }

        if (open) {
          animateOpen(panel);
        } else {
          animateClose(panel);
        }
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

        panel.style.transition = prefersReducedMotion
          ? 'none'
          : 'height 220ms ease, opacity 160ms ease';

        panel.addEventListener('transitionend', (event) => {
          if (event.propertyName && event.propertyName !== 'height') return;

          const isOpen = panel.dataset.open === '1';
          if (isOpen) {
            panel.style.height = 'auto';
            panel.style.opacity = '1';
            panel.style.overflow = 'visible';
          } else {
            panel.style.display = 'none';
            panel.style.overflow = 'hidden';
          }
        });

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
