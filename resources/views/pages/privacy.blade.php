@extends('layouts.app')

@section('content')
<div class="bg-gradient-to-b from-blue-50 to-white">
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
            <span class="text-sm font-medium text-gray-500">{{ __('ui.privacy_title') }}</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Hero Section -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center border-b border-gray-200">
    <h1 class="text-5xl font-bold text-gray-900 mb-4">{{ __('ui.privacy_title') }}</h1>
    <p class="text-gray-600 flex items-center justify-center gap-2">
      <i class="fas fa-calendar-alt text-blue-600"></i>
      <span>{{ __('ui.privacy_updated') }}: 2026-02-15</span>
    </p>
  </div>

  <!-- Content Section -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Introduction Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-8">
      <div class="flex gap-3">
        <i class="fas fa-shield-alt text-blue-600 text-xl flex-shrink-0 mt-1"></i>
        <p class="text-gray-700">{{ __('ui.privacy_intro') }}</p>
      </div>
    </div>

    <!-- Information We Collect -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-database text-blue-600"></i>
        {{ __('ui.privacy_types') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-blue-600 font-bold">1</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Personal Data</h3>
            <p class="text-gray-600 text-sm">Name, email address, phone number, location information</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-blue-600 font-bold">2</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Navigation Data</h3>
            <p class="text-gray-600 text-sm">Pages visited, referrer, browser type, device information</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-blue-600 font-bold">3</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Inquiry Data</h3>
            <p class="text-gray-600 text-sm">Products interested in, pricing queries, communication methods</p>
          </div>
        </div>
      </div>
    </div>

    <!-- How We Use Your Information -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-cog text-green-600"></i>
        {{ __('ui.privacy_use') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-3">
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">Process and respond to your inquiries</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">Contact you regarding your inquiry and provide updates</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">Improve and optimize the website functionality</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">Analyze website usage and performance metrics</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">Prevent fraudulent transactions and misuse</p>
        </div>
      </div>
    </div>

    <!-- Data Security -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-lock text-purple-600"></i>
        {{ __('ui.privacy_security') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <p class="text-gray-700 mb-4">
          We have implemented appropriate technical and organizational measures designed to secure your information against accidental loss and unauthorized access. Your personal data is not shared with third parties without your explicit consent.
        </p>
        <h3 class="font-semibold text-gray-900 mb-3">Our Security Measures:</h3>
        <ul class="space-y-2 text-gray-700">
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            SSL/TLS encryption for all data transmission
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            Secure server infrastructure with firewall protection
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            Access controls and regular security monitoring
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            No sharing of personal data with third parties without consent
          </li>
        </ul>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white rounded-xl p-8 text-center">
      <h3 class="text-2xl font-bold mb-2">Questions About Privacy?</h3>
      <p class="text-gray-300 mb-6">We're here to help and answer any questions about our privacy practices.</p>
      <a href="mailto:info@kidsimwatch.ge" class="inline-flex items-center gap-2 bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-envelope"></i>
        info@kidsimwatch.ge
      </a>
    </div>
  </div>
</div>
@endsection
