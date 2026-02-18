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
            <span class="text-sm font-medium text-gray-500">{{ __('ui.terms_title') }}</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Hero Section -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center border-b border-gray-200">
    <h1 class="text-5xl font-bold text-gray-900 mb-4">{{ __('ui.terms_title') }}</h1>
    <p class="text-gray-600 flex items-center justify-center gap-2">
      <i class="fas fa-file-contract text-blue-600"></i>
      <span>Last Updated: 2026-02-15</span>
    </p>
  </div>

  <!-- Content Section -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Introduction -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
      <div class="flex gap-3">
        <i class="fas fa-exclamation-triangle text-amber-600 text-xl flex-shrink-0 mt-1"></i>
        <div>
          <h3 class="font-semibold text-gray-900 mb-1">{{ __('ui.terms_intro') }}</h3>
          <p class="text-gray-700">{{ __('ui.terms_intro') }}</p>
        </div>
      </div>
    </div>

    <!-- Terms Sections -->
    <div class="space-y-8">
      <!-- Section 1 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">1</div>
          <h2 class="text-2xl font-bold text-gray-900">General Terms</h2>
        </div>
        <p class="text-gray-700">
          By accessing KidSIM Watch, you agree to be bound by these terms of service and all applicable laws and regulations.
          You are responsible for compliance with any applicable local laws.
        </p>
      </div>

      <!-- Section 2 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">2</div>
          <h2 class="text-2xl font-bold text-gray-900">Use License</h2>
        </div>
        <p class="text-gray-700 mb-4">
          Permission is granted to temporarily download materials from our website for personal, non-commercial viewing only.
        </p>
        <div class="bg-gray-50 rounded p-4 space-y-2">
          <p class="font-semibold text-gray-900">You may NOT:</p>
          <ul class="space-y-2">
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">Modify, copy, or reproduce the materials</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">Use materials for commercial purposes or public display</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">Attempt to reverse engineer or decompile software</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">Remove copyright or proprietary notices</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">Transfer materials to another person or mirror the website</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">3</div>
          <h2 class="text-2xl font-bold text-gray-900">Disclaimer</h2>
        </div>
        <p class="text-gray-700">
          Materials on our website are provided on an "as is" basis. We make no warranties, expressed or implied, and disclaim all warranties including fitness for a particular purpose or non-infringement of rights.
        </p>
      </div>

      <!-- Section 4 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">4</div>
          <h2 class="text-2xl font-bold text-gray-900">Limitations</h2>
        </div>
        <p class="text-gray-700">
          KidSIM Watch and its suppliers shall not be liable for any damages including loss of profit, data loss, or business interruption arising from the use of our materials or website.
        </p>
      </div>

      <!-- Section 5 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">5</div>
          <h2 class="text-2xl font-bold text-gray-900">Material Accuracy</h2>
        </div>
        <p class="text-gray-700">
          Materials on our website may contain technical or typographical errors. We do not warrant accuracy or completeness of materials and may change them at any time without notice.
        </p>
      </div>

      <!-- Section 6 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">6</div>
          <h2 class="text-2xl font-bold text-gray-900">External Links</h2>
        </div>
        <p class="text-gray-700">
          We have not reviewed all linked sites and are not responsible for their contents. Links to external sites do not imply endorsement. Use of linked websites is at the user's own risk.
        </p>
      </div>

      <!-- Section 7 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 text-blue-600 font-bold">7</div>
          <h2 class="text-2xl font-bold text-gray-900">Modifications</h2>
        </div>
        <p class="text-gray-700">
          We may revise these terms of service at any time without notice. By using this website, you agree to be bound by the current version of these terms.
        </p>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-indigo-900 to-indigo-800 text-white rounded-xl p-8 text-center mt-12">
      <h3 class="text-2xl font-bold mb-2">Questions About Our Terms?</h3>
      <p class="text-indigo-100 mb-6">Get in touch with us if you need any clarification.</p>
      <a href="mailto:info@kidsimwatch.ge" class="inline-flex items-center gap-2 bg-white text-indigo-900 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition">
        <i class="fas fa-envelope"></i>
        info@kidsimwatch.ge
      </a>
    </div>
  </div>
</div>
@endsection
