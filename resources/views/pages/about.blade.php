@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'MyTechnic-ზე — SIM სმარტ საათების ოფიციალური იმპორტიორი' : 'About MyTechnic — Official SIM Smartwatch Importer')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic არის SIM-იანი სმარტ საათების ოფიციალური იმპორტიორი საქართველოში. ზარი, GPS, შეტყობინებები, ვიდეო ზარი და მშობლებისთვის მშვიდი გამოცდილება.' : 'MyTechnic is the official SIM smartwatch importer in Georgia. Calls, GPS, messages, video calls, and a calmer experience for parents.')
@section('canonical', url('/about'))
@section('og_title', app()->getLocale() === 'ka' ? 'MyTechnic-ზე — SIM სმარტ საათების ოფიციალური იმპორტიორი' : 'About MyTechnic — Official SIM Smartwatch Importer')
@section('og_url', url('/about'))

@section('content')
<div class="bg-gradient-to-b from-primary-50 to-white">
  <!-- Breadcrumb -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <nav class="flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li class="inline-flex items-center">
          <a href="{{ route('home') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600">
            <i class="fas fa-home w-4 h-4 mr-2"></i>
            {{ __('ui.nav_home') }}
          </a>
        </li>
        <li>
          <div class="flex items-center">
            <i class="fas fa-chevron-right w-6 h-6 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-500">{{ __('ui.about_title') }}</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Hero Section -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
    <div class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white/85 px-4 py-2 text-sm font-medium text-primary-700 shadow-sm">
      <i class="fas fa-shield-heart"></i>
      <span>{{ __('ui.trust_shipping') }} · {{ __('ui.trust_warranty') }} · {{ __('ui.trust_support') }}</span>
    </div>
    <h1 class="mt-6 text-5xl font-bold text-gray-900 mb-4">{{ __('ui.about_title') }}</h1>
    <p class="text-xl text-gray-600 max-w-3xl mx-auto">{{ __('ui.about_intro') }}</p>
  </div>

  <!-- Content Section -->
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 space-y-16">
    <!-- Mission Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 md:p-10">
      <div class="flex items-start gap-4 mb-5">
        <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <i class="fas fa-bullseye text-primary-600 text-xl"></i>
        </div>
        <div>
          <h2 class="text-3xl font-bold text-gray-900">{{ __('ui.about_mission') }}</h2>
        </div>
      </div>
      <p class="text-gray-700 text-lg leading-relaxed max-w-4xl">
        {{ __('ui.about_mission_body') }}
      </p>
    </div>

    <!-- Why Choose Us Section -->
    <div>
      <h2 class="text-3xl font-bold text-gray-900 mb-8 flex items-center gap-3">
        <i class="fas fa-star text-yellow-500"></i>
        {{ __('ui.about_why') }}
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-md transition shadow-sm">
          <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-4">
            <i class="fas fa-hand-holding-heart text-emerald-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_curated_title') }}</h3>
          <p class="text-gray-600 leading-relaxed">{{ __('ui.about_curated_body') }}</p>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-md transition shadow-sm">
          <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
            <i class="fas fa-phone-alt text-primary-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_support_title') }}</h3>
          <p class="text-gray-600 leading-relaxed">{{ __('ui.about_support_body') }}</p>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-md transition shadow-sm">
          <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center mb-4">
            <i class="fas fa-info-circle text-violet-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_honest_title') }}</h3>
          <p class="text-gray-600 leading-relaxed">{{ __('ui.about_honest_body') }}</p>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-md transition shadow-sm">
          <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-4">
            <i class="fas fa-comments text-red-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_communication_title') }}</h3>
          <p class="text-gray-600 leading-relaxed">{{ __('ui.about_communication_body') }}</p>
        </div>
      </div>
    </div>

    <!-- Trust Details -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">01</div>
        <div class="text-lg font-bold text-gray-900">{{ __('ui.trust_shipping') }}</div>
        <div class="mt-2 text-sm text-gray-600">{{ __('ui.trust_shipping_text') }}</div>
      </div>
      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">02</div>
        <div class="text-lg font-bold text-gray-900">{{ __('ui.trust_warranty') }}</div>
        <div class="mt-2 text-sm text-gray-600">{{ __('ui.trust_warranty_text') }}</div>
      </div>
      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">03</div>
        <div class="text-lg font-bold text-gray-900">{{ __('ui.trust_support') }}</div>
        <div class="mt-2 text-sm text-gray-600">{{ __('ui.trust_support_text') }}</div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-2xl p-8 text-center shadow-lg">
      <h3 class="text-2xl font-bold mb-4">{{ __('ui.about_cta_title') }}</h3>
      <p class="text-primary-100 mb-6 max-w-2xl mx-auto">{{ __('ui.about_cta_subtitle') }}</p>
      <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 bg-white text-primary-600 font-semibold px-6 py-3 rounded-lg hover:bg-primary-50 transition">
        <i class="fas fa-envelope"></i>
        {{ __('ui.about_cta_button') }}
      </a>
    </div>
  </div>
</div>
@endsection
