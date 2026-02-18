<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KidSIM Watch')</title>
    <meta name="description" content="@yield('meta_description', '')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Font Awesome Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
</head>
<body class="bg-white text-gray-900">
    <!-- HyperUI Header -->
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Logo -->
          <div class="md:flex md:items-center md:gap-12">
            <a class="block text-blue-600 dark:text-blue-400 font-bold text-lg" href="{{ route('home') }}">
              <span class="sr-only">KidSIM Watch</span>
              KidSIM Watch
            </a>
          </div>

          <!-- Desktop Navigation -->
          <div class="hidden md:block">
            <nav aria-label="Global">
              <ul class="flex items-center gap-6 text-base">
                <li>
                  <a class="text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white" href="{{ route('home') }}">
                    მთავარი
                  </a>
                </li>
                <li>
                  <a class="text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white" href="{{ route('products.index') }}">
                    კატალოგი
                  </a>
                </li>
                <li>
                  <a class="text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white" href="{{ route('contact') }}">
                    კონტაქტი
                  </a>
                </li>
              </ul>
            </nav>
          </div>

          <!-- Mobile Navigation (Slides from right) -->
          <nav id="mobile-menu" class="fixed top-0 right-0 h-screen w-64 bg-white dark:bg-gray-900 shadow-lg transform translate-x-full transition-transform duration-300 ease-in-out z-50 md:hidden flex flex-col">
            <!-- Mobile Menu Header with Logo -->
            <div class="border-b border-gray-200 dark:border-gray-800 p-6">
              <a class="text-blue-600 dark:text-blue-400 font-bold text-lg" href="{{ route('home') }}">
                KidSIM Watch
              </a>
            </div>

            <!-- Navigation Links -->
            <ul class="flex flex-col items-start gap-0 text-base flex-grow">
              <li class="w-full border-b border-gray-200 dark:border-gray-800">
                <a class="block w-full py-4 px-6 text-gray-500 transition hover:text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800" href="{{ route('home') }}">
                  მთავარი
                </a>
              </li>
              <li class="w-full border-b border-gray-200 dark:border-gray-800">
                <a class="block w-full py-4 px-6 text-gray-500 transition hover:text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800" href="{{ route('products.index') }}">
                  კატალოგი
                </a>
              </li>
              <li class="w-full border-b border-gray-200 dark:border-gray-800">
                <a class="block w-full py-4 px-6 text-gray-500 transition hover:text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800" href="{{ route('contact') }}">
                  კონტაქტი
                </a>
              </li>
            </ul>

            <!-- Mobile Menu Footer - Social Icons -->
            <div class="border-t border-gray-200 dark:border-gray-800 p-6">
              <div class="flex gap-6 justify-center">
                <!-- WhatsApp -->
                <a href="https://wa.me/995555000000" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-green-500 dark:text-gray-500 dark:hover:text-green-400 transition duration-300" title="WhatsApp">
                  <i class="fab fa-whatsapp text-3xl"></i>
                </a>

                <!-- Instagram -->
                <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-pink-500 dark:text-gray-500 dark:hover:text-pink-400 transition duration-300" title="Instagram">
                  <i class="fab fa-instagram text-3xl"></i>
                </a>

                <!-- Facebook Messenger -->
                <a href="https://m.me/yourpage" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400 transition duration-300" title="Messenger">
                  <i class="fab fa-facebook-messenger text-3xl"></i>
                </a>
              </div>
            </div>
          </nav>

          <!-- Mobile menu overlay -->
          <div id="mobile-menu-overlay" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden"></div>

          <!-- Right side: Social Icons & Language Switcher -->
          <div class="flex items-center gap-4">
            <!-- Social Media Icons -->
            <div class="hidden sm:flex sm:gap-4 items-center">
              <!-- WhatsApp -->
              <a href="https://wa.me/995555000000" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-green-500 dark:text-gray-500 dark:hover:text-green-400 transition duration-300" title="WhatsApp">
                <i class="fab fa-whatsapp text-xl"></i>
              </a>

              <!-- Instagram -->
              <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-pink-500 dark:text-gray-500 dark:hover:text-pink-400 transition duration-300" title="Instagram">
                <i class="fab fa-instagram text-xl"></i>
              </a>

              <!-- Facebook Messenger -->
              <a href="https://m.me/yourpage" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400 transition duration-300" title="Messenger">
                <i class="fab fa-facebook-messenger text-xl"></i>
              </a>
            </div>



            <!-- Mobile menu toggle -->
            <div class="block md:hidden">
              <button id="mobile-menu-btn" class="rounded-sm bg-gray-100 p-2 text-gray-600 transition hover:text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-white">
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
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer>
        @include('components.footer')
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
    <script>
        // Mobile menu toggle with slide animation
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');

        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('translate-x-full');
            mobileMenuOverlay.classList.toggle('hidden');
        });

        // Close menu when clicking on overlay
        mobileMenuOverlay.addEventListener('click', function() {
            mobileMenu.classList.add('translate-x-full');
            mobileMenuOverlay.classList.add('hidden');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.add('translate-x-full');
                mobileMenuOverlay.classList.add('hidden');
            });
        });

        const popularSplide = document.getElementById('popular-splide');
        if (popularSplide && window.Splide) {
          new Splide('#popular-splide', {
            type: 'slide',
            gap: '1rem',
            autoWidth: true,
            padding: { right: '15%' },
            arrows: false,
            pagination: true,
            snap: true,
            drag: 'free',
            breakpoints: {
              1024: { padding: { right: '6%' } },
              768: { padding: { right: '10%' } },
              640: { padding: { right: '15%' } },
            },
          }).mount();
        }
    </script>
    @stack('scripts')
</body>
</html>
