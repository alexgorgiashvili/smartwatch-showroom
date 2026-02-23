<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KidSIM Watch')</title>
    <meta name="description" content="@yield('meta_description', '')">
    @stack('head_meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-hidden bg-white text-gray-900">
  @php
    $cartCount = collect(session('cart', []))->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
  @endphp
    <!-- HyperUI Header -->
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-gray-950/95 backdrop-blur-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Logo -->
          <div class="md:flex md:items-center md:gap-12">
            <a class="flex items-center gap-1" href="{{ route('home') }}" aria-label="KidSIM Watch">
              <span class="rounded-full bg-gray-900 px-3 py-1 text-sm font-extrabold tracking-tight">
                <span class="text-primary-400">KID</span><span class="text-white">SIM</span>
              </span>
              <span class="hidden text-sm font-semibold text-gray-100 sm:inline">Watch</span>
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
                <li>
                  <a class="rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('faq') ? 'text-primary-300 font-semibold bg-primary-600/20' : 'text-gray-300 hover:text-white hover:bg-white/10' }}" href="{{ route('faq') }}">კითხვები</a>
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
              <a class="flex items-center gap-1" href="{{ route('home') }}">
                <span class="rounded-full bg-gray-900 px-2.5 py-0.5 text-xs font-extrabold"><span class="text-primary-400">KID</span><span class="text-white">SIM</span></span>
                <span class="text-sm font-semibold text-gray-100">Watch</span>
              </a>
              <button id="mobile-menu-close" aria-label="Close menu" class="flex size-8 items-center justify-center rounded-full text-gray-300 transition hover:bg-white/10 hover:text-white">
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
                  <i class="fa-solid fa-watch w-4 text-center text-xs opacity-60"></i>კატალოგი
                </a>
              </li>
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
                <a class="flex items-center gap-3 px-5 py-4 text-sm font-medium transition-colors {{ request()->routeIs('cart.*') ? 'bg-primary-600/20 text-primary-300' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}" href="{{ route('cart.index') }}">
                  <i class="fa-solid fa-cart-shopping w-4 text-center text-xs opacity-60"></i>კალათა
                  <span data-cart-badge class="{{ $cartCount > 0 ? '' : 'hidden' }} inline-flex min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[10px] font-bold text-white">{{ $cartCount }}</span>
                </a>
              </li>
            </ul>

            <!-- Mobile Menu Footer - Social Icons -->
            <div class="border-t border-white/10 p-6">
              <div class="flex gap-6 justify-center">
                <!-- WhatsApp -->
                <a href="{{ $contactSettings['whatsapp_url'] ?? 'https://wa.me/995555123456' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-green-400" title="WhatsApp">
                  <i class="fab fa-whatsapp text-3xl"></i>
                </a>

                <!-- Instagram -->
                <a href="{{ $contactSettings['instagram_url'] ?? 'https://www.instagram.com/kidsimwatch' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-pink-400" title="Instagram">
                  <i class="fab fa-instagram text-3xl"></i>
                </a>

                <!-- Facebook Messenger -->
                <a href="{{ $contactSettings['messenger_url'] ?? 'https://m.me/yourpage' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-blue-400" title="Messenger">
                  <i class="fab fa-facebook-messenger text-3xl"></i>
                </a>
              </div>
            </div>
          </nav>

          <!-- Mobile menu overlay -->
          <div id="mobile-menu-overlay" class="fixed inset-0 z-40 hidden bg-black/60 md:hidden"></div>

          <!-- Right side: Social Icons + Language Switcher -->
          <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <!-- Social Media Icons -->
            <div class="hidden lg:flex lg:gap-3 items-center">
              <!-- WhatsApp -->
              <a href="{{ $contactSettings['whatsapp_url'] ?? 'https://wa.me/995555123456' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-green-400" title="WhatsApp">
                <i class="fab fa-whatsapp text-xl"></i>
              </a>

              <!-- Instagram -->
              <a href="{{ $contactSettings['instagram_url'] ?? 'https://www.instagram.com/kidsimwatch' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-pink-400" title="Instagram">
                <i class="fab fa-instagram text-xl"></i>
              </a>

              <!-- Facebook Messenger -->
              <a href="{{ $contactSettings['messenger_url'] ?? 'https://m.me/yourpage' }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 transition duration-300 hover:text-blue-400" title="Messenger">
                <i class="fab fa-facebook-messenger text-xl"></i>
              </a>
            </div>



            <!-- Mobile menu toggle -->
            <a href="{{ route('cart.index') }}" class="relative inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border border-white/15 text-gray-200 transition hover:border-white/30 hover:text-white" aria-label="Cart">
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

    <div id="chatbot-widget" data-endpoint="{{ route('chatbot.respond') }}">
      <button type="button" class="chatbot-fab" data-chatbot-toggle aria-expanded="false" aria-controls="chatbot-panel">
        <span class="chatbot-fab-icon">🤖</span>
        <span class="chatbot-fab-text">დახმარება</span>
      </button>

      <section id="chatbot-panel" class="chatbot-panel" aria-live="polite" aria-hidden="true">
        <header class="chatbot-header">
          <div>
            <p class="chatbot-title">KidSIM Assistant</p>
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

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');

        function openMobileMenu() {
            mobileMenu.classList.remove('translate-x-full');
            mobileMenuOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeMobileMenu() {
            mobileMenu.classList.add('translate-x-full');
            mobileMenuOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        mobileMenuClose.addEventListener('click', closeMobileMenu);
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
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

            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; }

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            })
            .then(function (res) {
              return res.json().then(function (data) {
                return { ok: res.ok, data: data };
              }).catch(function () {
                return { ok: false, data: { message: 'სერვერმა არასწორი პასუხი დააბრუნა.' } };
              });
            })
            .then(function (result) {
              if (result.ok && result.data.success) {
                updateCartBadges(result.data.cart_count || 0);
                showCartToast(result.data.message || 'პროდუქტი დაემატა კალათაში.', false);
                var qty = form.querySelector('input[name="quantity"][type="number"]');
                if (qty) { qty.value = 1; }
              } else {
                showCartToast((result.data && result.data.message) || 'შეცდომა მოხდა.', true);
              }
            })
            .catch(function () {
                showCartToast('შეცდომა მოხდა. სცადეთ თავიდან.', true);
            })
            .finally(function () {
                if (btn) { btn.disabled = false; }
            });
        });
    }());
    </script>
    @stack('scripts')
</body>
</html>
