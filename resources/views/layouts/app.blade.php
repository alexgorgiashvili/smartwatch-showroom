<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <meta name="google-site-verification" content="bPaaNUFXoKn6cHz1mi_-I8lIWApH8S1Z0gKV8LliMlI" />
    {{-- ═══ SEO: Title ═══ --}}
    <title>@yield('title', 'MyTechnic')</title>

    {{-- ═══ SEO: Robots & Canonical ═══ --}}
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- ═══ SEO: Meta Description ═══ --}}
    <meta name="description" content="@yield('meta_description', 'MyTechnic — SIM-იანი სმარტ საათები ბავშვებისთვის. 4G LTE, GPS ტრეკინგი, ზარი ტელეფონის გარეშე. ოფიციალური იმპორტიორი საქართველოში.')">

    {{-- ═══ SEO: hreflang (session-based locale — same URL serves ka/en) ═══ --}}
    <link rel="alternate" hreflang="ka" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="en" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    {{-- ═══ SEO: Open Graph ═══ --}}
    <meta property="og:site_name" content="MyTechnic">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ka' ? 'ka_GE' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ app()->getLocale() === 'ka' ? 'en_US' : 'ka_GE' }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="@hasSection('og_title')@yield('og_title')@else@yield('title', 'MyTechnic')@endif">
    <meta property="og:description" content="@hasSection('og_description')@yield('og_description')@else@yield('meta_description', 'MyTechnic — SIM-იანი სმარტ საათები ბავშვებისთვის. ოფიციალური იმპორტიორი.')@endif">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.webp'))">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="@yield('og_image_alt', 'MyTechnic სმარტ საათები')">

    {{-- ═══ SEO: Twitter Card ═══ --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@hasSection('og_title')@yield('og_title')@else@yield('title', 'MyTechnic')@endif">
    <meta name="twitter:description" content="@hasSection('og_description')@yield('og_description')@else@yield('meta_description', 'MyTechnic — SIM-იანი სმარტ საათები ბავშვებისთვის.')@endif">
    <meta name="twitter:image" content="@yield('og_image', asset('images/og-default.webp'))">

    {{-- ═══ Favicon ═══ --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

    {{-- ═══ Resource Hints for Performance ═══ --}}
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- ═══ Per-page JSON-LD structured data ═══ --}}
    @stack('json_ld')

    {{-- ═══ Per-page extra head meta ═══ --}}
    @stack('head_meta')

    @php
        $gtmId = config('storefront_analytics.gtm_id');
        $metaPixelId = config('storefront_analytics.meta_pixel_id');
        $analyticsFlashEvent = session('analytics_event');
        $shouldLoadMetaPixel = filled($metaPixelId) && ! app()->environment('local');
    @endphp

    @if ($gtmId)
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',@js($gtmId));
    </script>
    @endif

    @if ($shouldLoadMetaPixel)
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @js($metaPixelId));
        fbq('track', 'PageView');
    </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-hidden bg-white text-gray-900">
  @if ($gtmId)
  <noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript>
  @endif
  @if ($shouldLoadMetaPixel)
  <noscript>
    <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ urlencode($metaPixelId) }}&ev=PageView&noscript=1" alt="">
  </noscript>
  @endif
  @php
    $cartCount = app(\App\Services\Cart\CartSnapshotService::class)->roughCount(request());
  @endphp
    <!-- HyperUI Header -->
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-gray-950/95 backdrop-blur-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Logo -->
          <div class="md:flex md:items-center md:gap-12">
            <a class="flex items-center" href="{{ route('home') }}" aria-label="MyTechnic">
              <img src="{{ asset('images/logo.webp') }}" alt="MyTechnic" class="block h-[80px] md:h-[100px] w-auto object-contain">
            </a>
          </div>

          <!-- Desktop Navigation -->
          <div class="hidden md:block">
            <nav aria-label="Global">
              <ul class="flex items-center gap-1 text-sm">
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('home') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('home') }}">მთავარი</a>
                </li>
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('products.*') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('products.index') }}">კატალოგი</a>
                </li>
                @if (config('gift_builder.enabled', false))
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('gift-builder.*') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('gift-builder.show') }}">სასაჩუქრე ყუთის აწყობა</a>
                </li>
                @endif
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('faq') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('faq') }}">კითხვები</a>
                </li>
                {{-- სახელმძღვანელოები dropdown --}}
                <li class="relative group/guides">
                  <button class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm transition-colors {{ request()->routeIs('blog.*','landing.*') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}">
                    {{ app()->getLocale() === 'ka' ? 'სახელმძღვანელოები' : 'Guides' }}
                    <i class="fa-solid fa-chevron-down text-[9px] opacity-60 transition-transform group-hover/guides:rotate-180"></i>
                  </button>
                  <div class="pointer-events-none absolute left-0 top-full z-50 min-w-[220px] translate-y-1 rounded-xl border border-white/10 bg-gray-900 py-2 opacity-0 shadow-2xl transition-all duration-150 group-hover/guides:pointer-events-auto group-hover/guides:translate-y-0 group-hover/guides:opacity-100">
                    <a href="{{ route('blog.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/10 hover:text-white {{ request()->routeIs('blog.*') ? 'text-primary-300 bg-primary-600/20' : '' }}">
                      <i class="fa-solid fa-newspaper w-4 text-center text-xs text-primary-400"></i>
                      {{ app()->getLocale() === 'ka' ? 'ბლოგი' : 'Blog' }}
                    </a>
                    <div class="my-1.5 border-t border-white/10"></div>
                    <a href="{{ route('landing.sim-guide') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-white/10 hover:text-white">
                      <i class="fa-solid fa-sim-card w-4 text-center text-xs text-primary-400"></i>
                      {{ app()->getLocale() === 'ka' ? 'SIM ბარათის გზამკვლევი' : 'SIM Card Guide' }}
                    </a>
                    <a href="{{ route('landing.gift-guide') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-white/10 hover:text-white">
                      <i class="fa-solid fa-gift w-4 text-center text-xs text-primary-400"></i>
                      {{ app()->getLocale() === 'ka' ? 'საჩუქრის გზამკვლევი' : 'Gift Guide' }}
                    </a>
                    @if (config('gift_builder.enabled', false))
                    <a href="{{ route('gift-builder.show') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-300 hover:bg-white/10 hover:text-white {{ request()->routeIs('gift-builder.*') ? 'text-primary-300 bg-primary-600/20' : '' }}">
                      <i class="fa-solid fa-box-open w-4 text-center text-xs text-primary-400"></i>
                      სასაჩუქრე ყუთის აწყობა
                    </a>
                    @endif
                  </div>
                </li>
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('contact') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('contact') }}">კონტაქტი</a>
                </li>
              </ul>
            </nav>
          </div>

          <!-- Mobile Navigation (Slides from right) -->
          <nav id="mobile-menu" class="fixed inset-y-0 right-0 z-50 flex h-screen w-[85vw] max-w-[320px] translate-x-full transform flex-col bg-gray-950 text-white shadow-2xl transition-transform duration-300 ease-in-out md:hidden">
            <!-- Mobile Menu Header with Logo + Close -->
            <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
              <a class="flex items-center" href="{{ route('home') }}">
                <img src="{{ asset('images/logo.webp') }}" alt="MyTechnic" class="block h-[80px] w-auto object-contain">
              </a>
              <button id="mobile-menu-close" aria-label="მენიუს დახურვა" class="flex size-8 items-center justify-center rounded-full text-gray-300 transition hover:bg-white/10 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Navigation Links -->
            <ul class="flex flex-col flex-grow">
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('home') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('home') }}">
                  <i class="fa-solid fa-house w-4 text-center text-xs opacity-60"></i>მთავარი
                </a>
              </li>
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('products.*') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('products.index') }}">
                  <i class="fa-solid fa-table-cells-large w-4 text-center text-xs opacity-60"></i>კატალოგი
                </a>
              </li>
              @if (config('gift_builder.enabled', false))
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('gift-builder.*') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('gift-builder.show') }}">
                  <i class="fa-solid fa-box-open w-4 text-center text-xs opacity-60"></i>სასაჩუქრე ყუთის აწყობა
                </a>
              </li>
              @endif
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('faq') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('faq') }}">
                  <i class="fa-solid fa-circle-question w-4 text-center text-xs opacity-60"></i>კითხვები
                </a>
              </li>
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('contact') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('contact') }}">
                  <i class="fa-solid fa-envelope w-4 text-center text-xs opacity-60"></i>კონტაქტი
                </a>
              </li>
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('blog.*') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('blog.index') }}">
                  <i class="fa-solid fa-newspaper w-4 text-center text-xs opacity-60"></i>{{ app()->getLocale() === 'ka' ? 'ბლოგი' : 'Blog' }}
                </a>
              </li>
              {{-- Mobile guides accordion --}}
              <li class="border-b border-white/10">
                <details class="group/det">
                  <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-medium text-gray-300 hover:bg-white/10 hover:text-white">
                    <span class="flex items-center gap-3"><i class="fa-solid fa-book-open w-4 text-center text-xs opacity-60"></i>{{ app()->getLocale() === 'ka' ? 'სახელმძღვანელოები' : 'Guides' }}</span>
                    <i class="fa-solid fa-chevron-down text-xs opacity-50 transition-transform group-open/det:rotate-180"></i>
                  </summary>
                  <div class="bg-gray-900/60 pb-1">
                    <a href="{{ route('landing.sim-guide') }}" class="flex items-center gap-3 py-2.5 pl-10 pr-5 text-sm text-gray-400 hover:text-white"><i class="fa-solid fa-sim-card text-xs text-primary-400"></i>{{ app()->getLocale() === 'ka' ? 'SIM გზამკვლევი' : 'SIM Guide' }}</a>
                    <a href="{{ route('landing.gift-guide') }}" class="flex items-center gap-3 py-2.5 pl-10 pr-5 text-sm text-gray-400 hover:text-white"><i class="fa-solid fa-gift text-xs text-primary-400"></i>{{ app()->getLocale() === 'ka' ? 'საჩუქარი' : 'Gift Guide' }}</a>
                    @if (config('gift_builder.enabled', false))
                    <a href="{{ route('gift-builder.show') }}" class="flex items-center gap-3 py-2.5 pl-10 pr-5 text-sm text-gray-400 hover:text-white"><i class="fa-solid fa-box-open text-xs text-primary-400"></i>სასაჩუქრე ყუთის აწყობა</a>
                    @endif
                  </div>
                </details>
              </li>
              <li class="border-b border-white/10">
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('cart.*') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('cart.index') }}">
                  <i class="fa-solid fa-cart-shopping w-4 text-center text-xs opacity-60"></i>კალათა
                  <span data-cart-badge class="{{ $cartCount > 0 ? '' : 'hidden' }} inline-flex min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[10px] font-bold text-white">{{ $cartCount }}</span>
                </a>
              </li>
            </ul>

            <!-- Mobile Menu Footer - Social Icons -->
            <div class="border-t border-white/10 p-6">
              <div class="flex gap-6 justify-center">
                @if (!empty($contactSettings['whatsapp_url']))
                <a href="{{ $contactSettings['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-green-400" title="WhatsApp">
                  <i class="fab fa-whatsapp text-3xl"></i>
                </a>
                @endif

                @if (!empty($contactSettings['instagram_url']))
                <a href="{{ $contactSettings['instagram_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-pink-400" title="Instagram">
                  <i class="fab fa-instagram text-3xl"></i>
                </a>
                @endif

                @if (!empty($contactSettings['messenger_url']))
                <a href="{{ $contactSettings['messenger_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-primary-400" title="Messenger">
                  <i class="fab fa-facebook-messenger text-3xl"></i>
                </a>
                @endif
              </div>
            </div>
          </nav>

          <!-- Mobile menu overlay -->
          <div id="mobile-menu-overlay" class="fixed inset-0 z-40 hidden bg-black/60 md:hidden"></div>

          <!-- Right side: Social Icons + Language Switcher -->
          <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <!-- Social Media Icons -->
            <div class="hidden lg:flex lg:gap-3 items-center">
              @if (!empty($contactSettings['whatsapp_url']))
              <a href="{{ $contactSettings['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-green-400" title="WhatsApp">
                <i class="fab fa-whatsapp text-xl"></i>
              </a>
              @endif

              @if (!empty($contactSettings['instagram_url']))
              <a href="{{ $contactSettings['instagram_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-pink-400" title="Instagram">
                <i class="fab fa-instagram text-xl"></i>
              </a>
              @endif

              @if (!empty($contactSettings['messenger_url']))
              <a href="{{ $contactSettings['messenger_url'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-primary-400" title="Messenger">
                <i class="fab fa-facebook-messenger text-xl"></i>
              </a>
              @endif
            </div>



            <!-- Mobile menu toggle -->
            <a href="{{ route('cart.index') }}" class="relative mr-[5px] inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border border-white/15 text-gray-200 transition hover:border-white/30 hover:text-white md:ml-0" aria-label="კალათა">
              <i class="fa-solid fa-cart-shopping text-sm"></i>
              <span data-cart-badge class="{{ $cartCount > 0 ? '' : 'hidden' }} absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[10px] font-bold text-white">{{ $cartCount }}</span>
            </a>
            <div class="block flex-shrink-0 md:hidden">
              <button id="mobile-menu-btn" class="rounded-sm bg-white/10 p-2 text-gray-200 transition hover:bg-white/15 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="pt-16">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer>
        @include('components.footer')
    </footer>

    {{-- Cart toast notification --}}
    <div id="cart-toast" class="pointer-events-none fixed bottom-6 right-6 z-[9999] hidden rounded-xl px-5 py-3 text-sm font-semibold text-white opacity-0 shadow-xl transition-opacity duration-300"></div>

    <div id="quick-review-root" class="pointer-events-none fixed inset-0 z-[110] hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-slate-950/45 opacity-0 backdrop-blur-[1px] transition-opacity duration-300" data-quick-review-overlay></div>
      <aside class="absolute inset-y-0 right-0 flex h-full w-full max-w-md translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 sm:max-w-xl lg:max-w-[820px]" data-quick-review-panel>
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">{{ app()->getLocale() === 'ka' ? 'სწრაფი არჩევა' : 'Quick Review' }}</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ app()->getLocale() === 'ka' ? 'აირჩიეთ ვარიანტი' : 'Choose your option' }}</h2>
          </div>
          <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-900" data-quick-review-close aria-label="{{ app()->getLocale() === 'ka' ? 'დახურვა' : 'Close' }}">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-3.5 sm:px-5 sm:py-4 lg:px-6" data-quick-review-body>
          <div class="animate-pulse space-y-3.5">
            <div class="h-44 rounded-2xl bg-slate-100 sm:h-52 lg:h-64"></div>
            <div class="h-4 w-2/3 rounded-full bg-slate-100"></div>
            <div class="h-4 w-1/2 rounded-full bg-slate-100"></div>
            <div class="grid grid-cols-2 gap-2 lg:grid-cols-3">
              <div class="h-12 rounded-xl bg-slate-100"></div>
              <div class="h-12 rounded-xl bg-slate-100"></div>
              <div class="h-12 rounded-xl bg-slate-100"></div>
              <div class="h-12 rounded-xl bg-slate-100"></div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <div id="chatbot-widget" data-endpoint="{{ route('chatbot.respond') }}" data-history-endpoint="{{ route('chatbot.history') }}">
      <button type="button" class="chatbot-fab" data-chatbot-toggle aria-expanded="false" aria-controls="chatbot-panel">
        <span class="chatbot-fab-icon">🤖</span>
        <span class="chatbot-fab-text">დახმარება</span>
      </button>

      <section id="chatbot-panel" class="chatbot-panel" aria-live="polite" aria-hidden="true">
        <header class="chatbot-header">
          <div>
            <p class="chatbot-title">MyTechnic Assistant</p>
            <p class="chatbot-subtitle">ონლაინ დახმარება</p>
          </div>
          <button type="button" class="chatbot-close" data-chatbot-close aria-label="დახურვა">✕</button>
        </header>

        <div class="chatbot-messages" data-chatbot-messages></div>

        <form class="chatbot-form" data-chatbot-form>
          <input
            type="text"
            name="message"
            class="chatbot-input"
            placeholder="კითხვა მოგვწერე..."
            autocomplete="off"
            required
          />
          <button type="submit" class="chatbot-send">გაგზავნა</button>
        </form>
      </section>
    </div>

    <div id="site-lightbox" class="fixed inset-0 z-[130] hidden" role="dialog" aria-modal="true" aria-hidden="true">
      <div data-site-lightbox-overlay class="absolute inset-0 bg-black/80"></div>
      <div class="relative flex h-full w-full items-center justify-center p-3 sm:p-6">
        <button type="button" data-site-lightbox-close class="absolute right-3 top-3 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/40" aria-label="დახურვა">
          <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <button type="button" data-site-lightbox-prev class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/40" aria-label="წინა სურათი">
          <i class="fa-solid fa-chevron-left"></i>
        </button>

        <button type="button" data-site-lightbox-next class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/40" aria-label="შემდეგი სურათი">
          <i class="fa-solid fa-chevron-right"></i>
        </button>

        <figure class="relative w-full max-w-5xl">
          <div class="flex items-center justify-center">
            <img data-site-lightbox-image src="" alt="" class="max-h-[80vh] w-auto max-w-full select-none rounded-xl shadow-2xl" draggable="false" />
          </div>
          <figcaption class="mt-3 flex items-center justify-between gap-3 text-sm text-white/80">
            <span data-site-lightbox-caption class="min-w-0 truncate"></span>
            <span data-site-lightbox-counter class="shrink-0"></span>
          </figcaption>
        </figure>
      </div>
    </div>

    <script>
    (function () {
        var ga4EventMap = {
            ViewContent: 'view_item',
            AddToCart: 'add_to_cart',
            Lead: 'generate_lead',
            InitiateCheckout: 'begin_checkout',
            Purchase: 'purchase'
        };

        function normalizePayload(payload) {
            return payload && typeof payload === 'object' ? payload : {};
        }

        function track(eventName, payload) {
            var safePayload = normalizePayload(payload);
            window.dataLayer = window.dataLayer || [];

            window.dataLayer.push(Object.assign({
                event: eventName,
                ga4_event_name: ga4EventMap[eventName] || eventName
            }, safePayload));

            if (typeof window.fbq === 'function') {
                var metaPayload = {};
                ['content_ids', 'content_name', 'content_type', 'contents', 'currency', 'num_items', 'transaction_id', 'value'].forEach(function (key) {
                    if (Object.prototype.hasOwnProperty.call(safePayload, key) && safePayload[key] !== null && typeof safePayload[key] !== 'undefined') {
                        metaPayload[key] = safePayload[key];
                    }
                });

                window.fbq('track', eventName, metaPayload);
            }
        }

        window.storefrontAnalytics = {
            track: track
        };

        var flashEvent = @json($analyticsFlashEvent);
        if (flashEvent && flashEvent.name) {
            track(flashEvent.name, flashEvent.payload || {});
        }
    }());
    </script>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
        const chatbotWidget = document.getElementById('chatbot-widget');

      function isMobileMenuOpen() {
        return !mobileMenu.classList.contains('translate-x-full');
      }

        function openMobileMenu() {
            mobileMenu.classList.remove('translate-x-full');
            mobileMenuOverlay.classList.remove('hidden');
          if (chatbotWidget) {
            chatbotWidget.classList.add('hidden');
          }
            document.body.style.overflow = 'hidden';
        }
        function closeMobileMenu() {
            mobileMenu.classList.add('translate-x-full');
            mobileMenuOverlay.classList.add('hidden');
          if (chatbotWidget) {
            chatbotWidget.classList.remove('hidden');
          }
            document.body.style.overflow = '';
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        mobileMenuClose.addEventListener('click', closeMobileMenu);
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
        document.addEventListener('click', function (event) {
      if (!isMobileMenuOpen()) {
        return;
      }

      const clickedInsideMenu = mobileMenu.contains(event.target);
      const clickedMenuButton = mobileMenuBtn.contains(event.target);

      if (!clickedInsideMenu && !clickedMenuButton) {
            event.preventDefault();
            event.stopPropagation();
        closeMobileMenu();
      }
        }, true);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && isMobileMenuOpen()) {
        closeMobileMenu();
      }
    });

    </script>
    <script>
    // ── AJAX Cart ──────────────────────────────────────────────
    (function () {
        function updateCartBadges(count) {
            document.querySelectorAll('[data-cart-badge]').forEach(function (el) {
                el.textContent = count;
                el.classList.toggle('hidden', count === 0);
            });
        }

        function showCartToast(message, isError) {
            var toast = document.getElementById('cart-toast');
            if (!toast) return;
            toast.textContent = message;
            toast.className = 'pointer-events-none fixed bottom-6 right-6 z-[9999] rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-xl transition-opacity duration-300 '
                + (isError ? 'bg-rose-600' : 'bg-emerald-600');
            toast.classList.remove('hidden', 'opacity-0');
            clearTimeout(toast._t);
            toast._t = setTimeout(function () {
                toast.classList.add('opacity-0');
                setTimeout(function () { toast.classList.add('hidden'); }, 300);
            }, 3000);
        }

          window.cartUi = {
            updateBadges: updateCartBadges,
            showToast: showCartToast
          };

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.hasAttribute('data-cart-form')) return;
            e.preventDefault();

            var btn = e.submitter || form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
            }

            var formData = new FormData(form);
            if (btn && btn.name) {
                formData.set(btn.name, btn.value);
            }

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }).catch(function () {
                    return { ok: false, data: { message: 'სერვერმა არასწორი პასუხი დააბრუნა.' } };
                });
            })
            .then(function (result) {
                var data = result.data || {};

                if (result.ok && data.success) {
                    updateCartBadges(data.cart_count || 0);

                    if (window.storefrontAnalytics) {
                        var quantity = parseInt(formData.get('quantity') || '1', 10);
                        var resolvedQuantity = isNaN(quantity) ? 1 : quantity;
                        var unitPrice = parseFloat(form.getAttribute('data-analytics-price') || '0');
                        var itemId = String(form.getAttribute('data-analytics-item-id') || formData.get('variant_id') || '');
                        var itemName = form.getAttribute('data-analytics-item-name') || undefined;

                        window.storefrontAnalytics.track('AddToCart', {
                            content_ids: [itemId],
                            content_name: itemName,
                            content_type: 'product',
                            currency: form.getAttribute('data-analytics-currency') || 'GEL',
                            value: unitPrice > 0 ? unitPrice * resolvedQuantity : undefined,
                            items: [{
                                item_id: itemId,
                                item_name: itemName,
                                price: unitPrice > 0 ? unitPrice : undefined,
                                quantity: resolvedQuantity
                            }],
                            contents: [{
                                id: itemId,
                                quantity: resolvedQuantity,
                                item_price: unitPrice > 0 ? unitPrice : undefined
                            }]
                        });
                    }

                    document.dispatchEvent(new CustomEvent('cart:add-success', {
                        detail: {
                            form: form,
                            data: data
                        }
                    }));

                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                        return;
                    }

                    showCartToast(data.message || 'პროდუქტი დაემატა კალათაში.', false);

                    var qty = form.querySelector('input[name="quantity"][type="number"]');
                    if (qty) {
                        qty.value = 1;
                    }
                } else {
                    showCartToast(data.message || 'შეცდომა მოხდა.', true);
                }
            })
            .catch(function () {
                showCartToast('ქსელური შეცდომა. სცადეთ თავიდან.', true);
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
        });
    }());
    </script>
    <script>
    (function () {
      var root = document.getElementById('quick-review-root');
      if (!root) {
        return;
      }

      var overlay = root.querySelector('[data-quick-review-overlay]');
      var panel = root.querySelector('[data-quick-review-panel]');
      var body = root.querySelector('[data-quick-review-body]');
      var closeButton = root.querySelector('[data-quick-review-close]');
      var lightboxRoot = document.getElementById('site-lightbox');
      var lightboxOverlay = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-overlay]') : null;
      var lightboxClose = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-close]') : null;
      var lightboxPrev = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-prev]') : null;
      var lightboxNext = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-next]') : null;
      var lightboxImage = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-image]') : null;
      var lightboxCaption = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-caption]') : null;
      var lightboxCounter = lightboxRoot ? lightboxRoot.querySelector('[data-site-lightbox-counter]') : null;
      var activeForm = null;
      var activeRequestToken = 0;
      var closeTimer = null;
      var activeGalleryIndex = 0;
      var lightboxOpen = false;
      var lightboxTouchStartX = null;
      var lightboxTouchStartY = null;

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function buildVariantOption(option, selectedId) {
        var isSelected = Number(option.id) === Number(selectedId);
        var isDisabled = !option.in_stock;
        var colorPreview = option.color_hex
          ? '<span class="h-5 w-5 rounded-full border border-slate-200" style="background-color:' + option.color_hex + ';"></span>'
          : '<span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[10px] text-slate-400"><i class="fa-solid fa-palette"></i></span>';

        return '<button type="button" data-quick-review-variant data-variant-id="' + option.id + '" data-variant-stock="' + option.stock + '"'
          + ' data-variant-image-url="' + escapeHtml(option.image_url || '') + '" data-variant-image-alt="' + escapeHtml(option.image_alt || '') + '" data-variant-image-index="' + escapeHtml(option.image_index === 0 || option.image_index ? option.image_index : '') + '"'
          + ' class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left transition '
          + (isSelected ? 'border-primary-500 bg-primary-50 text-slate-900' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300')
          + (isDisabled ? ' opacity-50' : '') + '"'
          + (isDisabled ? ' disabled' : '') + ' aria-pressed="' + (isSelected ? 'true' : 'false') + '">'
          + '<span class="flex min-w-0 items-center gap-3">' + colorPreview + '<span class="min-w-0"><span class="block truncate text-sm font-semibold">'
          + escapeHtml(option.label) + '</span></span></span>'
          + '<i class="fa-solid fa-check text-xs ' + (isSelected ? 'text-primary-600' : 'text-transparent') + '"></i>'
          + '</button>';
      }

      function buildGalleryMarkup(galleryImages, activeIndex) {
        var items = Array.isArray(galleryImages) ? galleryImages : [];
        var safeIndex = items.length > 0 ? Math.max(0, Math.min(activeIndex || 0, items.length - 1)) : 0;
        var currentImage = items[safeIndex] || {
          url: '',
          thumbnail_url: '',
          alt: ''
        };
        var currentImageSrc = currentImage.url || currentImage.thumbnail_url || '';

        if (!items.length) {
          return {
            html: '<div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"><img src="" alt="" data-quick-review-gallery-image data-gallery-index="0" class="h-48 w-full object-contain transition-opacity duration-200 sm:h-56 lg:h-[340px]"></div>',
            activeIndex: 0,
            total: 0
          };
        }

        var thumbs = items.map(function (image, index) {
          var isActive = index === safeIndex;
          return ''
            + '<button type="button" data-quick-review-gallery-thumb data-gallery-index="' + index + '" data-gallery-src="' + escapeHtml(image.url || image.thumbnail_url || '') + '" data-gallery-alt="' + escapeHtml(image.alt || '') + '" class="shrink-0 overflow-hidden rounded-xl border transition ' + (isActive ? 'border-primary-500 ring-2 ring-primary-200' : 'border-slate-200 hover:border-slate-300') + '">'
            +   '<img src="' + escapeHtml(image.thumbnail_url || image.url || '') + '" alt="' + escapeHtml(image.alt || '') + '" class="h-16 w-16 object-cover sm:h-18 sm:w-18">'
            + '</button>';
        }).join('');

        return {
          html: ''
            + '<div class="space-y-3">'
            +   '<div class="relative h-[240px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 sm:h-[260px] lg:h-[280px]">'
            +     '<div class="absolute inset-0 flex items-center justify-center p-3 sm:p-4">'
            +       '<button type="button" data-quick-review-gallery-open data-gallery-index="' + safeIndex + '" class="flex h-full w-full cursor-zoom-in items-center justify-center focus:outline-none">'
            +         '<img src="' + escapeHtml(currentImageSrc) + '" alt="' + escapeHtml(currentImage.alt || '') + '" data-quick-review-gallery-image data-gallery-index="' + safeIndex + '" class="h-full w-full select-none object-contain transition-opacity duration-200">'
            +       '</button>'
            +     '</div>'
            +     (items.length > 1 ? '<button type="button" data-quick-review-gallery-prev class="absolute left-3 top-1/2 -translate-y-1/2 inline-flex size-10 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm transition hover:bg-white"><i class="fa-solid fa-chevron-left text-sm"></i></button>' : '')
            +     (items.length > 1 ? '<button type="button" data-quick-review-gallery-next class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex size-10 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm transition hover:bg-white"><i class="fa-solid fa-chevron-right text-sm"></i></button>' : '')
            +     (items.length > 1 ? '<div class="absolute bottom-3 right-3 rounded-full bg-slate-900/70 px-2.5 py-1 text-[11px] font-semibold text-white" data-quick-review-gallery-counter>' + (safeIndex + 1) + ' / ' + items.length + '</div>' : '')
            +   '</div>'
            +   (items.length > 1 ? '<div class="-mx-1 flex gap-2 overflow-x-auto pb-1 px-1" data-quick-review-gallery-thumbs>' + thumbs + '</div>' : '')
            + '</div>',
          activeIndex: safeIndex,
          total: items.length
        };
      }

      function setGalleryImage(nextIndex) {
        var galleryImage = body.querySelector('[data-quick-review-gallery-image]');
        var thumbs = Array.from(body.querySelectorAll('[data-quick-review-gallery-thumb]'));
        var counter = body.querySelector('[data-quick-review-gallery-counter]');

        if (!galleryImage || !thumbs.length) {
          activeGalleryIndex = 0;
          return;
        }

        var total = thumbs.length;
        var normalizedIndex = ((nextIndex % total) + total) % total;
        var currentThumb = thumbs[normalizedIndex];
        var currentImage = currentThumb ? currentThumb.querySelector('img') : null;
        var nextSrc = currentThumb ? currentThumb.getAttribute('data-gallery-src') || '' : '';
        var nextAlt = currentThumb ? currentThumb.getAttribute('data-gallery-alt') || '' : '';

        if (!nextSrc && currentImage) {
          nextSrc = currentImage.getAttribute('src') || '';
        }
        if (!nextAlt && currentImage) {
          nextAlt = currentImage.getAttribute('alt') || '';
        }

        thumbs.forEach(function (thumb, index) {
          thumb.classList.toggle('border-primary-500', index === normalizedIndex);
          thumb.classList.toggle('ring-2', index === normalizedIndex);
          thumb.classList.toggle('ring-primary-200', index === normalizedIndex);
          thumb.classList.toggle('border-slate-200', index !== normalizedIndex);
        });

        if (nextSrc && galleryImage.getAttribute('src') !== nextSrc) {
          galleryImage.style.opacity = '0.35';
          galleryImage.onload = function () {
            galleryImage.style.opacity = '1';
            galleryImage.onload = null;
          };
          galleryImage.src = nextSrc;
        }

        galleryImage.alt = nextAlt || galleryImage.alt || '';
        galleryImage.setAttribute('data-gallery-index', String(normalizedIndex));
        if (counter) {
          counter.textContent = (normalizedIndex + 1) + ' / ' + total;
        }
        activeGalleryIndex = normalizedIndex;
      }

      function getGalleryActiveIndex() {
        var galleryImage = body.querySelector('[data-quick-review-gallery-image]');
        if (!galleryImage) {
          return activeGalleryIndex || 0;
        }

        var parsedIndex = parseInt(galleryImage.getAttribute('data-gallery-index') || '0', 10);
        return Number.isFinite(parsedIndex) ? parsedIndex : 0;
      }

      function openLightbox(index) {
        if (!lightboxRoot || !lightboxImage || !lightboxCaption) {
          return;
        }

        var thumbs = Array.from(body.querySelectorAll('[data-quick-review-gallery-thumb]'));
        if (!thumbs.length) {
          return;
        }

        var total = thumbs.length;
        var normalizedIndex = ((index % total) + total) % total;
        var thumb = thumbs[normalizedIndex];
        var thumbImage = thumb ? thumb.querySelector('img') : null;
        var src = thumb ? thumb.getAttribute('data-gallery-src') || '' : '';
        var alt = thumb ? thumb.getAttribute('data-gallery-alt') || '' : '';

        if (!src && thumbImage) {
          src = thumbImage.getAttribute('src') || '';
        }
        if (!alt && thumbImage) {
          alt = thumbImage.getAttribute('alt') || '';
        }

        if (!src) {
          return;
        }

        lightboxOpen = true;
        lightboxRoot.classList.remove('hidden');
        lightboxRoot.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        lightboxImage.src = src;
        lightboxImage.alt = alt || '';
        lightboxCaption.textContent = alt || '';
        if (lightboxCounter) {
          lightboxCounter.textContent = (normalizedIndex + 1) + ' / ' + total;
        }
        if (lightboxPrev) {
          lightboxPrev.classList.toggle('hidden', total < 2);
        }
        if (lightboxNext) {
          lightboxNext.classList.toggle('hidden', total < 2);
        }

        if (lightboxClose) {
          lightboxClose.focus();
        }
      }

      function closeLightbox() {
        if (!lightboxRoot || !lightboxImage || !lightboxCaption) {
          return;
        }

        lightboxOpen = false;
        lightboxRoot.classList.add('hidden');
        lightboxRoot.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        lightboxImage.src = '';
        lightboxImage.alt = '';
        lightboxCaption.textContent = '';
        if (lightboxCounter) {
          lightboxCounter.textContent = '';
        }
        lightboxTouchStartX = null;
        lightboxTouchStartY = null;
      }

      function navigateLightbox(step) {
        if (!lightboxOpen) {
          return;
        }

        var targetIndex = getGalleryActiveIndex() + step;
        setGalleryImage(targetIndex);
        openLightbox(targetIndex);
      }

      function setOpenState(isOpen) {
        if (isOpen && closeTimer) {
          clearTimeout(closeTimer);
          closeTimer = null;
        }

        root.classList.toggle('hidden', !isOpen);
        root.classList.toggle('pointer-events-none', !isOpen);
        root.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('overflow-hidden', isOpen);

        requestAnimationFrame(function () {
          overlay.classList.toggle('opacity-0', !isOpen);
          panel.classList.toggle('translate-x-full', !isOpen);
        });
      }

      function closeDrawer() {
        activeForm = null;
        activeRequestToken += 1;
        overlay.classList.add('opacity-0');
        panel.classList.add('translate-x-full');
        if (closeTimer) {
          clearTimeout(closeTimer);
        }
        closeTimer = setTimeout(function () {
          closeTimer = null;
          setOpenState(false);
        }, 250);
      }

      function renderPayload(payload) {
        var product = payload.product || {};
        var variants = Array.isArray(payload.variants) ? payload.variants : [];
        var galleryImages = Array.isArray(payload.gallery_images) ? payload.gallery_images : [];
        var defaultVariantId = payload.default_variant_id || (variants[0] ? variants[0].id : '');
        var maxQuantity = payload.max_quantity || 1;
        var variantButtons = variants.map(function (variant) {
          return buildVariantOption(variant, defaultVariantId);
        }).join('');
        var initialVariant = variants.find(function (variant) {
          return String(variant.id) === String(defaultVariantId);
        }) || variants[0] || null;
        var initialGalleryIndex = initialVariant && initialVariant.image_index !== null && typeof initialVariant.image_index !== 'undefined' && initialVariant.image_index !== ''
          ? parseInt(initialVariant.image_index, 10)
          : 0;
        var gallery = buildGalleryMarkup(galleryImages, Number.isFinite(initialGalleryIndex) ? initialGalleryIndex : 0);

        activeGalleryIndex = gallery.activeIndex;

        body.innerHTML = ''
          + '<div class="space-y-5">'
          +   '<div class="space-y-4">'
          +     gallery.html
          +     '<div>'
          +       '<div class="flex items-start justify-between gap-3">'
          +         '<div class="min-w-0">'
          +           '<h3 class="text-xl font-bold leading-tight text-slate-900">' + escapeHtml(product.name || '') + '</h3>'
          +           '<p class="mt-2 text-sm leading-relaxed text-slate-500">' + escapeHtml(product.short_description || '') + '</p>'
          +         '</div>'
          +         '<a href="' + escapeHtml(product.url || '#') + '" class="shrink-0 text-sm font-semibold text-primary-600 hover:text-primary-700">{{ app()->getLocale() === 'ka' ? 'სრული გვერდი' : 'Full page' }}</a>'
          +       '</div>'
          +       '<div class="mt-3 flex items-end gap-2">'
          +         '<p class="text-3xl font-extrabold leading-none text-slate-900">' + escapeHtml(product.price_formatted || '') + '</p>'
          +         (product.base_price_formatted ? '<p class="price-compare-old text-lg">' + escapeHtml(product.base_price_formatted) + '</p>' : '')
          +       '</div>'
          +     '</div>'
          +   '</div>'
          +   '<form method="POST" action="' + escapeHtml(payload.add_to_cart_url) + '" data-cart-form data-quick-review-form data-analytics-item-id="' + escapeHtml(product.id || '') + '" data-analytics-item-name="' + escapeHtml(product.name || '') + '" data-analytics-price="' + escapeHtml(product.price || 0) + '" data-analytics-currency="' + escapeHtml(product.currency || 'GEL') + '" class="space-y-5">'
          +     '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
          +     '<input type="hidden" name="variant_id" value="' + defaultVariantId + '" data-quick-review-variant-input>'
          +     '<div>'
          +       '<div class="mb-2 flex items-center justify-between gap-2">'
          +         '<p class="text-sm font-semibold text-slate-900">{{ app()->getLocale() === 'ka' ? 'ფერი / ვარიანტი' : 'Color / Variant' }}</p>'
          +       '</div>'
          +       '<div class="grid gap-2 sm:grid-cols-2" data-quick-review-variants>' + variantButtons + '</div>'
          +     '</div>'
          +     '<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">'
          +       '<div class="flex items-center justify-between gap-3">'
          +         '<label for="quick-review-quantity" class="text-sm font-semibold text-slate-700">{{ app()->getLocale() === 'ka' ? 'რაოდენობა' : 'Quantity' }}</label>'
          +         '<input id="quick-review-quantity" type="number" name="quantity" min="1" max="' + maxQuantity + '" value="1" class="w-24 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200">'
          +       '</div>'
          +     '</div>'
          +     '<div class="grid gap-2 sm:grid-cols-2">'
          +       '<button type="submit" name="post_add_action" value="cart" class="inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-600">'
          +         '<i class="fa-solid fa-cart-shopping text-xs"></i>{{ app()->getLocale() === 'ka' ? 'კალათაში დამატება' : 'Add to Cart' }}'
          +       '</button>'
          +       '<button type="submit" name="post_add_action" value="checkout" class="inline-flex items-center justify-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-5 py-3 text-sm font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-100">'
          +         '<i class="fa-solid fa-bag-shopping text-xs"></i>{{ app()->getLocale() === 'ka' ? 'შეკვეთის გაფორმება' : 'Checkout' }}'
          +       '</button>'
          +     '</div>'
          +   '</form>'
          + '</div>';

        activeForm = body.querySelector('[data-quick-review-form]');
      }

      function setVariant(button) {
        var form = body.querySelector('[data-quick-review-form]');
        if (!form || !button) {
          return;
        }

        var variantIdInput = form.querySelector('[data-quick-review-variant-input]');
        var quantityInput = form.querySelector('input[name="quantity"]');
        var stock = Math.max(1, Math.min(10, parseInt(button.getAttribute('data-variant-stock') || '1', 10)));

        body.querySelectorAll('[data-quick-review-variant]').forEach(function (item) {
          var selected = item === button;
          item.classList.toggle('border-primary-500', selected);
          item.classList.toggle('bg-primary-50', selected);
          item.classList.toggle('text-slate-900', selected);
          item.classList.toggle('border-slate-200', !selected);
          item.classList.toggle('bg-white', !selected);
          item.setAttribute('aria-pressed', selected ? 'true' : 'false');
          var check = item.querySelector('.fa-check');
          if (check) {
            check.classList.toggle('text-primary-600', selected);
            check.classList.toggle('text-transparent', !selected);
          }
        });

        if (variantIdInput) {
          variantIdInput.value = button.getAttribute('data-variant-id') || '';
        }
        if (quantityInput) {
          quantityInput.max = String(stock);
          if (parseInt(quantityInput.value || '1', 10) > stock) {
            quantityInput.value = String(stock);
          }
        }

        var variantImageIndex = button.getAttribute('data-variant-image-index');
        if (variantImageIndex !== null && variantImageIndex !== '') {
          setGalleryImage(parseInt(variantImageIndex, 10));
        }
      }

      function showLoading() {
        body.innerHTML = '<div class="animate-pulse space-y-3"><div class="h-36 rounded-2xl bg-slate-100"></div><div class="h-4 w-2/3 rounded-full bg-slate-100"></div><div class="h-4 w-1/2 rounded-full bg-slate-100"></div><div class="grid grid-cols-2 gap-2"><div class="h-11 rounded-xl bg-slate-100"></div><div class="h-11 rounded-xl bg-slate-100"></div></div></div>';
      }

      document.addEventListener('click', function (event) {
        var openImage = event.target.closest('[data-quick-review-gallery-open]');
        if (openImage) {
          event.preventDefault();
          openLightbox(parseInt(openImage.getAttribute('data-gallery-index') || '0', 10));
          return;
        }

        if (lightboxOpen && lightboxRoot && (event.target === lightboxOverlay || event.target.closest('[data-site-lightbox-close]'))) {
          event.preventDefault();
          closeLightbox();
          return;
        }

        if (lightboxOpen && event.target.closest('[data-site-lightbox-prev]')) {
          event.preventDefault();
          navigateLightbox(-1);
          return;
        }

        if (lightboxOpen && event.target.closest('[data-site-lightbox-next]')) {
          event.preventDefault();
          navigateLightbox(1);
          return;
        }

        var galleryPrev = event.target.closest('[data-quick-review-gallery-prev]');
        if (galleryPrev) {
          event.preventDefault();
          setGalleryImage(getGalleryActiveIndex() - 1);
          return;
        }

        var galleryNext = event.target.closest('[data-quick-review-gallery-next]');
        if (galleryNext) {
          event.preventDefault();
          setGalleryImage(getGalleryActiveIndex() + 1);
          return;
        }

        var galleryThumb = event.target.closest('[data-quick-review-gallery-thumb]');
        if (galleryThumb) {
          event.preventDefault();
          setGalleryImage(parseInt(galleryThumb.getAttribute('data-gallery-index') || '0', 10));
          return;
        }

        var trigger = event.target.closest('[data-product-quick-review-trigger]');
        if (trigger) {
          event.preventDefault();
          var url = trigger.getAttribute('data-product-quick-review-url');
          if (!url) {
            return;
          }

          activeRequestToken += 1;
          var requestToken = activeRequestToken;

          setOpenState(true);
          showLoading();

          fetch(url, {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          })
          .then(function (response) {
            return response.json().then(function (data) {
              return { ok: response.ok, data: data };
            });
          })
          .then(function (result) {
            if (requestToken !== activeRequestToken) {
              return;
            }

            if (!result.ok) {
              throw new Error('Quick review failed');
            }

            renderPayload(result.data);
          })
          .catch(function () {
            if (requestToken !== activeRequestToken) {
              return;
            }

            body.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">{{ app()->getLocale() === 'ka' ? 'ამ პროდუქტის გახსნა ახლა ვერ მოხერხდა. სცადეთ თავიდან.' : 'We could not open this product right now. Please try again.' }}</div>';
          });

          return;
        }

        var variantButton = event.target.closest('[data-quick-review-variant]');
        if (variantButton) {
          event.preventDefault();
          setVariant(variantButton);
          return;
        }

        if (event.target === overlay || event.target.closest('[data-quick-review-close]')) {
          closeDrawer();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && root.getAttribute('aria-hidden') === 'false') {
          closeDrawer();
          return;
        }

        if (!lightboxOpen) {
          return;
        }

        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          navigateLightbox(-1);
        }

        if (event.key === 'ArrowRight') {
          event.preventDefault();
          navigateLightbox(1);
        }
      });

      if (lightboxRoot) {
        lightboxRoot.addEventListener('touchstart', function (event) {
          if (!lightboxOpen || !event.touches || event.touches.length !== 1) {
            return;
          }

          lightboxTouchStartX = event.touches[0].clientX;
          lightboxTouchStartY = event.touches[0].clientY;
        }, { passive: true });

        lightboxRoot.addEventListener('touchend', function (event) {
          if (!lightboxOpen || lightboxTouchStartX === null || !event.changedTouches || event.changedTouches.length !== 1) {
            return;
          }

          var deltaX = event.changedTouches[0].clientX - lightboxTouchStartX;
          var deltaY = event.changedTouches[0].clientY - lightboxTouchStartY;

          lightboxTouchStartX = null;
          lightboxTouchStartY = null;

          if (Math.abs(deltaX) < 40 || Math.abs(deltaX) < Math.abs(deltaY)) {
            return;
          }

          if (event.cancelable) {
            event.preventDefault();
          }
          navigateLightbox(deltaX < 0 ? 1 : -1);
        }, { passive: false });
      }

      document.addEventListener('cart:add-success', function (event) {
        if (!activeForm || !event.detail || event.detail.form !== activeForm) {
          return;
        }

        if (!(event.detail.data && event.detail.data.redirect_url)) {
          closeDrawer();
        }
      });
    }());
    </script>
    @stack('scripts')
</body>
</html>
