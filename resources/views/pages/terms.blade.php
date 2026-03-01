@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'გამოყენების პირობები — MyTechnic' : 'Terms of Service — MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic-ის გამოყენების პირობები — შეკვეთით გაიცანით ამ საიტის გამოყენება.' : 'MyTechnic Terms of Service — review the terms that govern your use of this site.')
@section('robots', 'noindex, follow')
@section('canonical', url('/terms'))

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
      <i class="fas fa-file-contract text-primary-600"></i>
      <span>ბოლო განახლება: 2026-02-15</span>
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
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">1</div>
          <h2 class="text-2xl font-bold text-gray-900">ზოგადი პირობები</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic-ის გამოყენებით ეთანხმებით წინამდებარე პირობებს და ყველა მოქმედ კანონს.
          თქვენ პასუხისმგებელი ხართ ადგილობრივი კანონებისა და წესების დაცვაზე.
        </p>
      </div>

      <!-- Section 2 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">2</div>
          <h2 class="text-2xl font-bold text-gray-900">გამოყენების ლიცენზია</h2>
        </div>
        <p class="text-gray-700 mb-4">
          დაშვებადია (პირადი არა კომერციული) მიზანით განხილვა ვებსაიტის მასალების განხილვა.
        </p>
        <div class="bg-gray-50 rounded p-4 space-y-2">
          <p class="font-semibold text-gray-900">აკრძალურია:</p>
          <ul class="space-y-2">
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">მასალათა კოპირება ან რეპროდუქცირება</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">კომერციული ან საჯარო მიზნით გამოყენება</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">პროგრამული კოდის ხელახელა ანალიზის მცდელობა</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">ავტორის უფლების შესახებ აღნიშვნა</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">მასალათა მესამე ადამისათვის გადაცემა ან ვებსაიტის ასლიდანვი პროექსია</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">3</div>
          <h2 class="text-2xl font-bold text-gray-900">უარყოფანობა</h2>
        </div>
        <p class="text-gray-700">
          ვებსაიტის მასალა მოწოდებულია "როგორცაარისაან" ფორმატით. ჩვენ არ ვიცემთ არცერთის გარანტიას, გამოხატული თუ გაუნგსაარი მიზნით.
        </p>
      </div>

      <!-- Section 4 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">4</div>
          <h2 class="text-2xl font-bold text-gray-900">პასუხისმგებლობის შეზღუდვა</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic და მისი მოწოდების მიმწოდებლები არ არის პასუხისმგებელი არცერთი ლარისა, მონაცემების დაკარგუნების ან უწყვეტობის შეპარებისათვის.
        </p>
      </div>

      <!-- Section 5 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">5</div>
          <h2 class="text-2xl font-bold text-gray-900">ინფორმაციის სიზუსტე</h2>
        </div>
        <p class="text-gray-700">
          ვებსაიტის მასალა შეიძლება ტექნიკურ ან საიმდროიან შეცდომებებს. ჩვენ არ ვკისრთ შესაბამის სიზუსტის გარანტიას და სათაკმიდ შეგვიძლია ღილადვის გარეშე.
        </p>
      </div>

      <!-- Section 6 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">6</div>
          <h2 class="text-2xl font-bold text-gray-900">გარე ბმულები</h2>
        </div>
        <p class="text-gray-700">
          ჩვენ არ გადაგვარებიათ ვებსაიტის ჩართთან ცვეერთი რესურსების შესახებ და არ აგებთ ერთიანი ათვის გარე დაკავშირებული გვერდებისათვის არის.
        </p>
      </div>

      <!-- Section 7 -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">7</div>
          <h2 class="text-2xl font-bold text-gray-900">ცვლილება</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic-ს შეუძლია წინამდებარე ჩიის ცვლილება შეიტანოს შეიტყობინების გარეშე. ვებსაიტით სარგებლობით იგულისხავთ მოქმედი აკტუალური ვერსიის აღიარებს.
        </p>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-indigo-900 to-indigo-800 text-white rounded-xl p-8 text-center mt-12">
      <h3 class="text-2xl font-bold mb-2">პირობები კითხვებზე?</h3>
      <p class="text-indigo-100 mb-6">MyTechnic-ი გიპასუხებთ გადადასტუროთ წეს შეკითხვები არის გაუგე.</p>
      <a href="mailto:info@mytechnic.ge" class="inline-flex items-center gap-2 bg-white text-indigo-900 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition">
        <i class="fas fa-envelope"></i>
        info@mytechnic.ge
      </a>
    </div>
  </div>
</div>
@endsection
