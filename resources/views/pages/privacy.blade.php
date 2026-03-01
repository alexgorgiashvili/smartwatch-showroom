@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'კონფიდენციალობის პოლიტიკა — MyTechnic' : 'Privacy Policy — MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic-ის კონფიდენციალობის პოლიტიკა — როგორ ვაიგებთ თქვენი მონაცემები.' : 'MyTechnic Privacy Policy — how we handle your personal data.')
@section('robots', 'noindex, follow')
@section('canonical', url('/privacy'))

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
      <i class="fas fa-calendar-alt text-primary-600"></i>
      <span>{{ __('ui.privacy_updated') }}: 2026-02-15</span>
    </p>
  </div>

  <!-- Content Section -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Introduction Box -->
    <div class="bg-primary-50 border border-primary-200 rounded-xl p-6 mb-8">
      <div class="flex gap-3">
        <i class="fas fa-shield-alt text-primary-600 text-xl flex-shrink-0 mt-1"></i>
        <p class="text-gray-700">{{ __('ui.privacy_intro') }}</p>
      </div>
    </div>

    <!-- Information We Collect -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-database text-primary-600"></i>
        {{ __('ui.privacy_types') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-primary-600 font-bold">1</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">პირადი მონაცემები</h3>
            <p class="text-gray-600 text-sm">სახელი, ელ. ფოსტა, ტელეფონი, მდებარეობა</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-primary-600 font-bold">2</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">ნავიგაციის მონაცემები</h3>
            <p class="text-gray-600 text-sm">მონახული გვერდები, ბრაუზერი, მოწყობილობა, მოწყობილობის ტიპი</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-primary-600 font-bold">3</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">მოთხოვნის მონაცემები</h3>
            <p class="text-gray-600 text-sm">დაინტერესებული პროდუქტები, ფასის კითხვები, კომუნიკაციის ყანალი</p>
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
          <p class="text-gray-700">თქვენი მოთხოვნაზე რეაგირება და ეფექტური გადახება</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">დაგვიკავშიროთ კითხვის აღდგომაზე და უზრუნველი ინფორმაციის მოწოდება</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">ვებსაიტის გაკვეთილება და ანალიტიკა</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">ისტატისტიკა და ტრაფიკის ანალიზი</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">უკანონო გამოყენებისა და ტუნდასწრაფის აღკვეთის თავიდაცვა</p>
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
          განვხორციელიანვაებთ შესაბამისი ტექნიკური და ორგანიზაციული ზომები თქვენი მონაცემების დასაცვავის და არააურისეტან წვდომისაკენ. თქვენი თანხმობა მსოფლიოს არ გადაეცემა მესამე მხარეს თქვენი თანხმობის გარეშე.
        </p>
        <h3 class="font-semibold text-gray-900 mb-3">დაცვის ზომები:</h3>
        <ul class="space-y-2 text-gray-700">
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            SSL/TLS დაშიფრვა მონაცემების გადაცემისათვის
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            დაცვის სერვერული ინფრასტრუქტურა და Firewall
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            წვდომისაკების კონტროლი და რეგულარული მონიტორინგი
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield text-purple-600 flex-shrink-0 mt-0.5"></i>
            პირადი ინფორმაცია არ გადაეცემა მესამე მხარეს თქვენი საკითხვის გარეშე
          </li>
        </ul>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white rounded-xl p-8 text-center">
      <h3 class="text-2xl font-bold mb-2">კონფიდენციალობას თაობაზე კითხვები გაქვს?</h3>
      <p class="text-gray-300 mb-6">MyTechnic-ი გიპასუხებთ გულწრფელად და გადაწყვეთ თქვენს შეკითხვებზე.</p>
      <a href="mailto:info@mytechnic.ge" class="inline-flex items-center gap-2 bg-primary-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-primary-700 transition">
        <i class="fas fa-envelope"></i>
        info@mytechnic.ge
      </a>
    </div>
  </div>
</div>
@endsection
