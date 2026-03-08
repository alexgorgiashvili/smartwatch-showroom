@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'მომსახურების პირობები — MyTechnic' : 'Terms of Service — MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic-ის მომსახურების პირობები — გაეცანით ვებსაიტის გამოყენების წესებსა და პირობებს.' : 'MyTechnic Terms of Service — review the terms that govern your use of this site.')
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
      <span>ბოლო განახლება: 2026-03-08</span>
    </p>
  </div>

  <!-- Content Section -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Introduction -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
      <div class="flex gap-3">
        <i class="fas fa-exclamation-triangle text-amber-600 text-xl flex-shrink-0 mt-1"></i>
        <div>
          <h3 class="font-semibold text-gray-900 mb-1">გთხოვთ, ყურადღებით გაეცნოთ</h3>
          <p class="text-gray-700">{{ __('ui.terms_intro') }}</p>
        </div>
      </div>
    </div>

    <!-- Terms Sections -->
    <div class="space-y-8">
      <!-- Section 1: ზოგადი პირობები -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">1</div>
          <h2 class="text-2xl font-bold text-gray-900">ზოგადი პირობები</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic-ის ვებსაიტზე (mytechnic.ge) შესვლით და მისი გამოყენებით თქვენ ეთანხმებით წინამდებარე მომსახურების პირობებს, კონფიდენციალობის პოლიტიკას და საქართველოს მოქმედ კანონმდებლობას. თუ არ ეთანხმებით რომელიმე პირობას, გთხოვთ, შეწყვიტოთ ვებსაიტით სარგებლობა. ეს პირობები ვრცელდება ვებსაიტის ყველა მომხმარებელზე — მათ შორის ვიზიტორებზე, მყიდველებსა და სხვა პირებზე.
        </p>
      </div>

      <!-- Section 2: გამოყენების ლიცენზია -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">2</div>
          <h2 class="text-2xl font-bold text-gray-900">გამოყენების ლიცენზია</h2>
        </div>
        <p class="text-gray-700 mb-4">
          თქვენ გეძლევათ შეზღუდული, არაექსკლუზიური უფლება, გამოიყენოთ ვებსაიტის მასალები მხოლოდ პირადი, არაკომერციული მიზნით — პროდუქტების დათვალიერებისა და შეკვეთის გაფორმების ფარგლებში.
        </p>
        <div class="bg-gray-50 rounded p-4 space-y-2">
          <p class="font-semibold text-gray-900">აკრძალულია:</p>
          <ul class="space-y-2">
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">ვებსაიტის მასალების კოპირება, რეპროდუქცია ან გავრცელება</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">კომერციული ან საჯარო მიზნით გამოყენება MyTechnic-ის წერილობითი თანხმობის გარეშე</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">პროგრამული კოდის რევერსული ინჟინერია ან დეკომპილაცია</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">საავტორო უფლებებისა და სხვა სამართლებრივი აღნიშვნების წაშლა ან შეცვლა</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-ban text-red-500 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">მასალების მესამე პირისთვის გადაცემა ან სხვა სერვერზე ასლის განთავსება</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 3: შეკვეთა და გადახდა -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">3</div>
          <h2 class="text-2xl font-bold text-gray-900">შეკვეთა და გადახდა</h2>
        </div>
        <p class="text-gray-700 mb-4">
          MyTechnic-ზე შეკვეთის გაფორმებისას მოქმედებს შემდეგი პირობები:
        </p>
        <div class="space-y-4">
          <div class="flex gap-3">
            <i class="fas fa-credit-card text-primary-600 flex-shrink-0 mt-1"></i>
            <div>
              <h4 class="font-semibold text-gray-900">ონლაინ გადახდა (საქართველოს ბანკი / BOG)</h4>
              <p class="text-gray-600 text-sm">გადახდა ხორციელდება საქართველოს ბანკის უსაფრთხო გადახდის სისტემის მეშვეობით, ლარში (GEL). გადახდისას თქვენი საბანკო მონაცემები მუშავდება მხოლოდ ბანკის მხარეს — MyTechnic არ ინახავს ბარათის მონაცემებს.</p>
            </div>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-truck text-primary-600 flex-shrink-0 mt-1"></i>
            <div>
              <h4 class="font-semibold text-gray-900">კურიერთან ნაღდი ანგარიშსწორება</h4>
              <p class="text-gray-600 text-sm">პროდუქტის მიწოდებისას კურიერთან ნაღდი ანგარიშსწორებით გადახდა. გადახდა ხორციელდება ლარში (GEL), პროდუქტის მიღების მომენტში.</p>
            </div>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-info-circle text-primary-600 flex-shrink-0 mt-1"></i>
            <div>
              <h4 class="font-semibold text-gray-900">ფასები და ხელმისაწვდომობა</h4>
              <p class="text-gray-600 text-sm">ვებსაიტზე მითითებული ფასები მოცემულია ლარში და შეიძლება შეიცვალოს წინასწარი შეტყობინების გარეშე. პროდუქტის ხელმისაწვდომობა დამოკიდებულია მარაგზე.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: გარანტია და დაბრუნება -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">4</div>
          <h2 class="text-2xl font-bold text-gray-900">გარანტია და დაბრუნება</h2>
        </div>
        <div class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex gap-3">
              <i class="fas fa-shield-alt text-green-600 flex-shrink-0 mt-1"></i>
              <div>
                <h4 class="font-semibold text-gray-900">1 თვის გარანტია</h4>
                <p class="text-gray-600 text-sm">MyTechnic-ში შეძენილ ყველა პროდუქტზე მოქმედებს 1 (ერთი) თვის გარანტია, შეძენის დღიდან. გარანტია ფარავს ქარხნულ დეფექტებს და ტექნიკურ გაუმართაობას, რომელიც არ არის გამოწვეული მომხმარებლის მიერ.</p>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 rounded p-4 space-y-2">
            <p class="font-semibold text-gray-900">გარანტია არ ვრცელდება:</p>
            <ul class="space-y-2">
              <li class="flex gap-2">
                <i class="fas fa-times-circle text-red-500 flex-shrink-0 mt-1"></i>
                <span class="text-gray-700">მექანიკური დაზიანებებისა და ფიზიკური ცვეთის შემთხვევაში</span>
              </li>
              <li class="flex gap-2">
                <i class="fas fa-times-circle text-red-500 flex-shrink-0 mt-1"></i>
                <span class="text-gray-700">წყალში ჩაძირვის ან არასწორი ექსპლუატაციის დროს</span>
              </li>
              <li class="flex gap-2">
                <i class="fas fa-times-circle text-red-500 flex-shrink-0 mt-1"></i>
                <span class="text-gray-700">არაავტორიზებული შეკეთების ან მოდიფიკაციის შემთხვევაში</span>
              </li>
            </ul>
          </div>
          <div class="flex gap-3">
            <i class="fas fa-undo text-amber-600 flex-shrink-0 mt-1"></i>
            <div>
              <h4 class="font-semibold text-gray-900">დაბრუნების პოლიტიკა</h4>
              <p class="text-gray-600 text-sm">პროდუქტის დაბრუნება შესაძლებელია მიღებიდან 3 (სამი) კალენდარული დღის განმავლობაში, თუ პროდუქტი არ არის გამოყენებული, აქვს ორიგინალი შეფუთვა და თან ახლავს ყიდვის დამადასტურებელი დოკუმენტი. დაბრუნების მოთხოვნისთვის დაგვიკავშირდით ელფოსტაზე: info@mytechnic.ge.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 5: პასუხისმგებლობის უარყოფა -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">5</div>
          <h2 class="text-2xl font-bold text-gray-900">პასუხისმგებლობის უარყოფა</h2>
        </div>
        <p class="text-gray-700">
          ვებსაიტზე განთავსებული მასალები მოწოდებულია „როგორც არის" პრინციპით. MyTechnic არ იძლევა რაიმე სახის გარანტიას — გამოხატულს თუ ნაგულისხმევს — ვებსაიტის მასალების სიზუსტეზე, სანდოობაზე ან სრულყოფილებაზე. ვებსაიტით სარგებლობა ხორციელდება მომხმარებლის საკუთარი რისკით.
        </p>
      </div>

      <!-- Section 6: პასუხისმგებლობის შეზღუდვა -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">6</div>
          <h2 class="text-2xl font-bold text-gray-900">პასუხისმგებლობის შეზღუდვა</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic და მისი პარტნიორები არ არიან პასუხისმგებელნი რაიმე პირდაპირ, არაპირდაპირ, შემთხვევით ან თანმდევ ზარალზე, რომელიც შეიძლება წარმოიშვას ვებსაიტის გამოყენების ან გამოყენების შეუძლებლობის შედეგად — მათ შორის მონაცემების დაკარგვა ან მომსახურების შეფერხება.
        </p>
      </div>

      <!-- Section 7: ინფორმაციის სიზუსტე -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">7</div>
          <h2 class="text-2xl font-bold text-gray-900">ინფორმაციის სიზუსტე</h2>
        </div>
        <p class="text-gray-700">
          ვებსაიტის მასალები შეიძლება შეიცავდეს ტექნიკურ უზუსტობებს ან ტიპოგრაფიულ შეცდომებს. MyTechnic არ იძლევა ინფორმაციის სიზუსტისა და სრულყოფილების გარანტიას. პროდუქტის ტექნიკური მახასიათებლები, ფოტოები და აღწერილობები შეიძლება განსხვავდებოდეს რეალური პროდუქტისგან.
        </p>
      </div>

      <!-- Section 8: გარე ბმულები -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">8</div>
          <h2 class="text-2xl font-bold text-gray-900">გარე ბმულები</h2>
        </div>
        <p class="text-gray-700">
          ვებსაიტი შეიძლება შეიცავდეს ბმულებს მესამე მხარის ვებგვერდებზე. MyTechnic არ აკონტროლებს და არ არის პასუხისმგებელი ამ გარე რესურსების შინაარსზე, კონფიდენციალობის პოლიტიკაზე ან პრაქტიკაზე. გარე ბმულზე გადასვლა ხორციელდება მომხმარებლის საკუთარი რისკით.
        </p>
      </div>

      <!-- Section 9: AI ჩატბოტი -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">9</div>
          <h2 class="text-2xl font-bold text-gray-900">AI ჩატბოტი</h2>
        </div>
        <p class="text-gray-700 mb-4">
          ვებსაიტზე მოქმედებს ხელოვნური ინტელექტის (AI) ჩატბოტი, რომელიც მომხმარებელს ეხმარება პროდუქტების შესახებ ინფორმაციის მიღებაში, კითხვებზე პასუხის გაცემასა და შეკვეთის პროცესში.
        </p>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-2">
          <p class="font-semibold text-gray-900">გთხოვთ, გაითვალისწინოთ:</p>
          <ul class="space-y-2">
            <li class="flex gap-2">
              <i class="fas fa-robot text-blue-600 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">ჩატბოტის პასუხები გენერირდება ავტომატურად და შეიძლება არ იყოს ყოველთვის ზუსტი</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-robot text-blue-600 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">ჩატბოტის მიერ მოწოდებული ინფორმაცია არ წარმოადგენს იურიდიულ ან სამედიცინო რჩევას</span>
            </li>
            <li class="flex gap-2">
              <i class="fas fa-robot text-blue-600 flex-shrink-0 mt-1"></i>
              <span class="text-gray-700">ზუსტი ინფორმაციისთვის მიმართეთ ჩვენს გუნდს: info@mytechnic.ge</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 10: პირობების ცვლილება -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex gap-3 mb-4">
          <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 text-primary-600 font-bold">10</div>
          <h2 class="text-2xl font-bold text-gray-900">პირობების ცვლილება</h2>
        </div>
        <p class="text-gray-700">
          MyTechnic იტოვებს უფლებას, ნებისმიერ დროს შეცვალოს წინამდებარე პირობები წინასწარი შეტყობინების გარეშე. განახლებული პირობები ძალაში შედის ვებსაიტზე გამოქვეყნების მომენტიდან. ვებსაიტით სარგებლობის გაგრძელებით თქვენ ეთანხმებით პირობების აქტუალურ ვერსიას.
        </p>
      </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-gradient-to-r from-indigo-900 to-indigo-800 text-white rounded-xl p-8 text-center mt-12">
      <h3 class="text-2xl font-bold mb-2">გაქვთ კითხვები პირობებთან დაკავშირებით?</h3>
      <p class="text-indigo-100 mb-6">MyTechnic-ის გუნდი მზადაა უპასუხოს თქვენს ნებისმიერ შეკითხვას.</p>
      <a href="mailto:info@mytechnic.ge" class="inline-flex items-center gap-2 bg-white text-indigo-900 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition">
        <i class="fas fa-envelope"></i>
        info@mytechnic.ge
      </a>
    </div>
  </div>
</div>
@endsection
