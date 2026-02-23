<footer class="bg-gray-900 text-gray-300 py-12">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Main Footer Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
      <!-- Brand & Description -->
      <div>
        <h3 class="text-white font-bold text-lg mb-4">KidSIM Watch</h3>
        <p class="text-sm leading-relaxed text-gray-400">
          {{ __('ui.footer_tagline') }}
        </p>
        <div class="mt-6 flex gap-3">
          <a href="{{ $contactSettings['whatsapp_url'] ?? 'https://wa.me/995555123456' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-10 h-10 bg-green-600 hover:bg-green-700 text-white rounded-full transition" title="WhatsApp">
            <i class="fab fa-whatsapp"></i>
          </a>
          <a href="{{ $contactSettings['facebook_url'] ?? 'https://www.facebook.com/kidsimwatch' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-10 h-10 bg-blue-600 hover:bg-blue-700 text-white rounded-full transition" title="Facebook">
            <i class="fab fa-facebook"></i>
          </a>
          <a href="{{ $contactSettings['instagram_url'] ?? 'https://www.instagram.com/kidsimwatch' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-10 h-10 bg-pink-600 hover:bg-pink-700 text-white rounded-full transition" title="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="{{ $contactSettings['telegram_url'] ?? 'https://t.me/kidsimwatch' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-10 h-10 bg-cyan-600 hover:bg-cyan-700 text-white rounded-full transition" title="Telegram">
            <i class="fab fa-telegram"></i>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <h4 class="text-white font-semibold mb-4">{{ __('ui.footer_quick_links') }}</h4>
        <ul class="space-y-2 text-sm">
          <li>
            <a href="{{ route('home') }}" class="hover:text-white transition">
              {{ __('ui.nav_home') }}
            </a>
          </li>
          <li>
            <a href="{{ route('products.index') }}" class="hover:text-white transition">
              {{ __('ui.nav_catalog') }}
            </a>
          </li>
          <li>
            <a href="{{ route('contact') }}" class="hover:text-white transition">
              {{ __('ui.nav_contact') }}
            </a>
          </li>
          <li>
            <a href="{{ route('about') }}" class="hover:text-white transition">
              {{ __('ui.footer_about') }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Contact Info -->
      <div>
        <h4 class="text-white font-semibold mb-4">{{ __('ui.footer_contact_info') }}</h4>
        <ul class="space-y-3 text-sm">
          <li class="flex items-start gap-2">
            <i class="fas fa-phone mt-1 text-green-500"></i>
            <div>
              <p class="text-gray-400">{{ __('ui.footer_phone') }}</p>
              <a href="tel:{{ $contactSettings['phone_link'] ?? '+995555123456' }}" class="hover:text-white transition font-medium">{{ $contactSettings['phone_display'] ?? '+995 555 123 456' }}</a>
            </div>
          </li>
          <li class="flex items-start gap-2">
            <i class="fas fa-envelope mt-1 text-blue-500"></i>
            <div>
              <p class="text-gray-400">{{ __('ui.footer_email') }}</p>
              <a href="mailto:{{ $contactSettings['email'] ?? 'info@kidsimwatch.ge' }}" class="hover:text-white transition font-medium">{{ $contactSettings['email'] ?? 'info@kidsimwatch.ge' }}</a>
            </div>
          </li>
          <li class="flex items-start gap-2">
            <i class="fas fa-map-marker-alt mt-1 text-red-500"></i>
            <div>
              <p class="text-gray-400">{{ __('ui.footer_location') }}</p>
              <p class="font-medium">{{ $contactSettings['location'] ?? 'Tbilisi, Georgia' }}</p>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Divider -->
    <div class="tech-hairline my-8"></div>

    <!-- Bottom Footer -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 text-sm text-gray-400">
      <p>{{ __('ui.footer_copyright') }}</p>
      <div class="flex gap-4">
        <a href="{{ route('privacy') }}" class="hover:text-white transition">{{ __('ui.footer_privacy') }}</a>
        <a href="{{ route('terms') }}" class="hover:text-white transition">{{ __('ui.footer_terms') }}</a>
      </div>
    </div>
  </div>
</footer>

