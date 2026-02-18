@extends('layouts.app')

@section('content')
<div class="bg-white">
  <!-- Breadcrumb -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <nav class="flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li class="inline-flex items-center">
          <a href="{{ route('home') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
            <i class="fas fa-home w-4 h-4 mr-2"></i>
            {{ __('ui.nav_home') }}
          </a>
        </li>
        <li>
          <div class="flex items-center">
            <i class="fas fa-chevron-right w-6 h-6 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-500">{{ __('ui.nav_contact') }}</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Page Header -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <div class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">{{ __('ui.contact_title') }}</h1>
      <p class="text-lg text-gray-600">{{ __('ui.contact_sub') }}</p>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
      <!-- Left Column: Contact Info + Social -->
      <div class="space-y-6">
        <!-- Contact Information Card -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 sm:p-5">
          <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('ui.contact_info_title') }}</h2>

          <div class="space-y-3">
            <!-- Phone -->
            <div class="flex items-center">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                <i class="fas fa-phone text-white text-sm"></i>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">{{ __('ui.contact_phone') }}</p>
                <p class="text-base font-semibold text-gray-900">+995 555 123 456</p>
              </div>
            </div>

            <!-- Email -->
            <div class="flex items-center">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                <i class="fas fa-envelope text-white text-sm"></i>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">{{ __('ui.contact_email') }}</p>
                <a href="mailto:info@kidsimwatch.ge" class="text-base font-semibold text-blue-600 hover:text-blue-700">info@kidsimwatch.ge</a>
              </div>
            </div>

            <!-- Address -->
            <div class="flex items-center">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                <i class="fas fa-map-marker-alt text-white text-sm"></i>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">{{ __('ui.contact_address') }}</p>
                <p class="text-base font-semibold text-gray-900">Tbilisi, Georgia</p>
              </div>
            </div>

            <!-- Hours -->
            <div class="flex items-center">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                <i class="fas fa-clock text-white text-sm"></i>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">{{ __('ui.contact_hours') }}</p>
                <p class="text-base font-semibold text-gray-900">{{ __('ui.contact_hours_text') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Social Media Buttons -->
        <div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">{{ __('ui.social_message') }}</h3>

          <div class="space-y-2">
            <!-- WhatsApp Button -->
            <a href="https://wa.me/995555123456" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition">
              <i class="fab fa-whatsapp"></i>
              WhatsApp
            </a>

            <!-- Facebook Button -->
            <a href="https://www.facebook.com/kidsimwatch" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
              <i class="fab fa-facebook"></i>
              Facebook
            </a>

            <!-- Instagram Button -->
            <a href="https://www.instagram.com/kidsimwatch" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-pink-700 transition">
              <i class="fab fa-instagram"></i>
              Instagram
            </a>


          </div>
        </div>
      </div>

      <!-- Right Column: Inquiry Form -->
      <div class="bg-gray-50 rounded-lg p-6 sm:p-8 sticky top-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ __('ui.contact_form_title') }}</h2>

        @if ($errors->any())
          <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="text-red-700 font-medium flex items-center">
              <i class="fas fa-exclamation-circle mr-2"></i>
              Please fix the following errors:
            </div>
            <ul class="list-disc list-inside text-red-600 text-sm mt-2">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @if (session('success'))
          <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-700 font-medium flex items-center">
              <i class="fas fa-check-circle mr-2"></i>
              {{ __('ui.inquiry_success') }}
            </p>
          </div>
        @endif

        <form action="{{ route('inquiries.store') }}" method="POST" class="space-y-4">
          @csrf

          <!-- Name Field -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              {{ __('ui.form_name') }} <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
              placeholder="Your name">
            @error('name')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Phone Field -->
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
              {{ __('ui.form_phone') }} <span class="text-red-500">*</span>
            </label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('phone') border-red-500 @enderror"
              placeholder="+995 55X XXX XXX">
            @error('phone')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Email Field -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
              {{ __('ui.form_email') }}
            </label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('email') border-red-500 @enderror"
              placeholder="your@email.com">
            @error('email')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Message Field -->
          <div>
            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
              {{ __('ui.form_message') }} <span class="text-red-500">*</span>
            </label>
            <textarea id="message" name="message" rows="5" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('message') border-red-500 @enderror"
              placeholder="How can we help?">{{ old('message') }}</textarea>
            @error('message')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Submit Button -->
          <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 mt-6 flex items-center justify-center text-sm">
            <i class="fas fa-paper-plane mr-2"></i>
            {{ __('ui.form_submit') }}
          </button>
        </form>

        <!-- Alternative Contact Note -->
        <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-sm text-gray-700">
            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
            <span class="font-medium">Tip:</span> For faster response, use WhatsApp or call directly!
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
