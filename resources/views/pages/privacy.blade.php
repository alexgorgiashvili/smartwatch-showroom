@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'კონფიდენციალობის პოლიტიკა — MyTechnic' : 'Privacy Policy — MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic-ის კონფიდენციალობის პოლიტიკა — როგორ ვაგროვებთ, ვიყენებთ და ვიცავთ თქვენს პირად მონაცემებს.' : 'MyTechnic Privacy Policy — how we collect, use, and protect your personal data.')
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
      <span>ბოლო განახლება: 2026-03-08</span>
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

    <!-- Section 1: Information We Collect -->
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
            <p class="text-gray-600 text-sm">სახელი, გვარი, ელექტრონული ფოსტა, ტელეფონის ნომერი, მიწოდების მისამართი</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-primary-600 font-bold">2</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">ნავიგაციის მონაცემები</h3>
            <p class="text-gray-600 text-sm">მონახულებული გვერდები, ბრაუზერის ტიპი, მოწყობილობა, IP მისამართი</p>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <span class="text-primary-600 font-bold">3</span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">შეკვეთისა და კომუნიკაციის მონაცემები</h3>
            <p class="text-gray-600 text-sm">შეკვეთის დეტალები, დაინტერესებული პროდუქტები, ჩატბოტთან მიმოწერა, კომუნიკაციის არხი</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 2: How We Use Your Information -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-cog text-green-600"></i>
        {{ __('ui.privacy_use') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-3">
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">შეკვეთების დამუშავება, მიწოდების ორგანიზება და მომხმარებელთან კომუნიკაცია</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">თქვენი კითხვებისა და მოთხოვნების დროულად და ზუსტად დამუშავება</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">ვებსაიტისა და მომსახურების ხარისხის გაუმჯობესება</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">ვებსაიტის ტრაფიკისა და მომხმარებლის ქცევის ანალიზი (ანონიმური სტატისტიკით)</p>
        </div>
        <div class="flex gap-3">
          <i class="fas fa-check-circle text-green-600 flex-shrink-0 mt-1"></i>
          <p class="text-gray-700">არასანქცირებული წვდომისა და თაღლითობის პრევენცია</p>
        </div>
      </div>
    </div>

    <!-- Section 3: AI Chatbot Data -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-robot text-blue-600"></i>
        AI ჩატბოტის მონაცემები
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <p class="text-gray-700 mb-4">
          ჩვენს ვებსაიტზე მოქმედებს AI ჩატბოტი, რომელიც გეხმარებათ პროდუქტების შესახებ ინფორმაციის მიღებაში. ჩატბოტთან ურთიერთობისას შეიძლება შეგროვდეს შემდეგი მონაცემები:
        </p>
        <div class="space-y-2">
          <div class="flex gap-3">
            <i class="fas fa-comment-dots text-blue-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">შეტყობინებების შინაარსი და მიმოწერის ისტორია</p>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-clock text-blue-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">სესიის დრო და ხანგრძლივობა</p>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-search text-blue-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">მოთხოვნილი პროდუქტებისა და თემების ინფორმაცია</p>
          </div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
          <p class="text-gray-700 text-sm">ჩატბოტის მონაცემები გამოიყენება მხოლოდ მომსახურების ხარისხის გაუმჯობესებისა და თქვენი მოთხოვნის დამუშავების მიზნით. ეს მონაცემები არ გადაეცემა მესამე მხარეს.</p>
        </div>
      </div>
    </div>

    <!-- Section 4: Cookies & Analytics -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-cookie-bite text-amber-600"></i>
        Cookie და ანალიტიკა
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <p class="text-gray-700 mb-4">
          ვებსაიტი იყენებს Cookie ფაილებსა და ანალიტიკის ინსტრუმენტებს მომხმარებლის გამოცდილების გასაუმჯობესებლად. Cookie ფაილები გვეხმარება:
        </p>
        <div class="space-y-2">
          <div class="flex gap-3">
            <i class="fas fa-check text-amber-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">თქვენი სესიისა და პრეფერენციების დამახსოვრებაში</p>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-check text-amber-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">ვებსაიტის მუშაობის ანალიზსა და გაუმჯობესებაში</p>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-check text-amber-600 flex-shrink-0 mt-1"></i>
            <p class="text-gray-700">ტრაფიკის სტატისტიკის შეგროვებაში (ანონიმურად)</p>
          </div>
        </div>
        <p class="text-gray-600 text-sm mt-4">თქვენ შეგიძლიათ ბრაუზერის პარამეტრებიდან გამორთოთ Cookie ფაილები, თუმცა ამ შემთხვევაში ვებსაიტის ზოგიერთი ფუნქცია შეიძლება არ იმუშაოს სრულყოფილად.</p>
      </div>
    </div>

    <!-- Section 5: Data Security -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-lock text-purple-600"></i>
        {{ __('ui.privacy_security') }}
      </h2>
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <p class="text-gray-700 mb-4">
          ჩვენ ვიყენებთ შესაბამის ტექნიკურ და ორგანიზაციულ ზომებს თქვენი პირადი მონაცემების დასაცავად არასანქცირებული წვდომის, დაკარგვის ან გამჟღავნებისგან. თქვენი მონაცემები არ გადაეცემა მესამე მხარეს თქვენი თანხმობის გარეშე.
        </p>
        <h3 class="font-semibold text-gray-900 mb-3">დაცვის ზომები:</h3>
        <ul class="space-y-2 text-gray-700">
          <li class="flex gap-2">
            <i class="fas fa-shield-alt text-purple-600 flex-shrink-0 mt-0.5"></i>
            SSL/TLS დაშიფვრა მონაცემების გადაცემისას
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield-alt text-purple-600 flex-shrink-0 mt-0.5"></i>
            უსაფრთხო სერვერული ინფრასტრუქტურა და Firewall
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield-alt text-purple-600 flex-shrink-0 mt-0.5"></i>
            წვდომის კონტროლი და რეგულარული უსაფრთხოების მონიტორინგი
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield-alt text-purple-600 flex-shrink-0 mt-0.5"></i>
            საბანკო გადახდის მონაცემები მუშავდება მხოლოდ საქართველოს ბანკის მხარეს (BOG)
          </li>
          <li class="flex gap-2">
            <i class="fas fa-shield-alt text-purple-600 flex-shrink-0 mt-0.5"></i>
            პირადი ინფორმაცია არ გადაეცემა მესამე მხარეს თქვენი წინასწარი თანხმობის გარეშე
          </li>
        </ul>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white rounded-xl p-8 text-center">
      <h3 class="text-2xl font-bold mb-2">გაქვთ კითხვები კონფიდენციალობასთან დაკავშირებით?</h3>
      <p class="text-gray-300 mb-6">MyTechnic-ის გუნდი მზადაა უპასუხოს თქვენს ნებისმიერ შეკითხვას.</p>
      <a href="mailto:info@mytechnic.ge" class="inline-flex items-center gap-2 bg-primary-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-primary-700 transition">
        <i class="fas fa-envelope"></i>
        info@mytechnic.ge
      </a>
    </div>
  </div>
</div>
@endsection
