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
            <span class="text-sm font-medium text-gray-500">{{ __('ui.about_title') }}</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Hero Section -->
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
    <h1 class="text-5xl font-bold text-gray-900 mb-4">{{ __('ui.about_title') }}</h1>
    <p class="text-xl text-gray-600 max-w-2xl mx-auto">{{ __('ui.about_intro') }}</p>
  </div>

  <!-- Content Section -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-16">
    <!-- Mission Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
      <div class="flex items-start gap-4 mb-4">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <i class="fas fa-target text-blue-600 text-xl"></i>
        </div>
        <div>
          <h2 class="text-3xl font-bold text-gray-900">{{ __('ui.about_mission') }}</h2>
        </div>
      </div>
      <p class="text-gray-700 text-lg leading-relaxed">
        {{ __('ui.about_mission') }}: ჩვენი მიზანი არის ქართული ოჯახებისთვის მოწოდება სანდო, აკრიფებული სმარტ საათების გადაწყვეტილებებისა, რომელთა საშუალებით მშობლებმა შეძლეს დარჩენა დაკავშირებული თავიანთი ბავშვებთან მთელი დღის განმავლობაში.
      </p>
    </div>

    <!-- Why Choose Us Section -->
    <div>
      <h2 class="text-3xl font-bold text-gray-900 mb-8 flex items-center gap-3">
        <i class="fas fa-star text-yellow-500"></i>
        {{ __('ui.about_why') }}
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Feature 1 -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition">
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
            <i class="fas fa-hand-holding text-green-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_curated') }}</h3>
          <p class="text-gray-600">ჩვენ არ ყიდით ყველაფერს. ხელსაყრელი წინამჯდოვანი განვთავსეთ მხოლოდ მოდელები რომელიც ჩვენ დაიჯდებოდა ჩვენი საკუთარი ბავშვებისთვის.</p>
        </div>

        <!-- Feature 2 -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition">
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
            <i class="fas fa-phone-alt text-blue-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_support') }}</h3>
          <p class="text-gray-600">თბილიშში დაფუძნებული, სწრაფი პასუხი და მხარდაჭერა ყველა დაკითხვისთვის.</p>
        </div>

        <!-- Feature 3 -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition">
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
            <i class="fas fa-info text-purple-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_honest') }}</h3>
          <p class="text-gray-600">თითოეული პროდუქტი მოიცავს დეტალურ მახასიათებლებს და გამხვირვალე რევიუებს.</p>
        </div>

        <!-- Feature 4 -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition">
          <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
            <i class="fas fa-comments text-red-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('ui.about_communication') }}</h3>
          <p class="text-gray-600">მიიღეთ სწრაფი პასუხი WhatsApp-ით, Telegram-ით ან პირდაპირი ტელეფონით.</p>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl p-8 text-center">
      <h3 class="text-2xl font-bold mb-4">{{ __('ui.footer_contact_info') }}</h3>
      <p class="text-blue-100 mb-6">Have questions? We're here to help!</p>
      <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 bg-white text-blue-600 font-semibold px-6 py-3 rounded-lg hover:bg-blue-50 transition">
        <i class="fas fa-envelope"></i>
        Contact Us
      </a>
@endsection
