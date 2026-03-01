@extends('layouts.app')

@php $locale = app()->getLocale(); $ka = $locale === 'ka'; @endphp

@section('title',            $ka ? 'SIM ბარათი ბავშვის სმარტ საათისთვის — Magti, Silknet, Cellfie | MyTechnic' : 'SIM Card for Kids Smartwatch — Magti, Silknet, Cellfie | MyTechnic')
@section('meta_description', $ka ? 'რომელი SIM ბარათი მუშაობს ბავშვის GPS სმარტ საათში? Magti, Silknet, Cellfie — თავსებადი ოპერატორები. PIN კოდის გათიშვა, SIM-ის ჩასმა, VoLTE — MyTechnic გაგიწევთ კონსულტაციას.' : 'Which SIM card works in a kids GPS smartwatch in Georgia? Magti, Silknet, Cellfie — compatible carriers. Disable PIN, insert SIM, VoLTE tips — MyTechnic will help.')
@section('robots',           'index, follow')
@section('canonical',        url('/sim-card-guide'))
@section('og_type',          'article')
@section('og_title',         $ka ? 'SIM ბარათი ბავშვის სმარტ საათისთვის' : 'SIM Card for Kids Smartwatch')
@section('og_description',   $ka ? 'Magti, Silknet, Cellfie — რომელი ოპერატორი მუშაობს ბავშვის GPS საათში. PIN კოდის გათიშვა, VoLTE და სხვა — MyTechnic გარჩევს.' : 'Magti, Silknet, Cellfie — which carrier works in a kids GPS watch. Disable PIN, VoLTE tips & more — MyTechnic answers.')
@section('og_url',           url('/sim-card-guide'))

@push('json_ld')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": {{ Js::from($ka ? 'SIM ბარათის ჩასმა ბავშვის სმარტ საათში' : 'How to insert a SIM card in a kids smartwatch') }},
  "description": {{ Js::from($ka ? 'ნაბიჯ-ნაბიჯ სახელმძღვანელო SIM ბარათის ჩასასმელად ბავშვის GPS სმარტ საათში.' : 'Step-by-step guide to inserting a SIM card in a kids GPS smartwatch.') }},
  "step": [
    { "@type": "HowToStep", "position": 1, "name": {{ Js::from($ka ? 'დაადასტურეთ SIM ტიპი' : 'Confirm the SIM type') }}, "text": {{ Js::from($ka ? 'შეამოწმეთ პროდუქტის სპეციფიკაცია — Nano-SIM ან Micro-SIM.' : 'Check your model specs — Nano-SIM or Micro-SIM.') }} },
    { "@type": "HowToStep", "position": 2, "name": {{ Js::from($ka ? 'შეიძინეთ SIM ბარათი' : 'Purchase a SIM card') }}, "text": {{ Js::from($ka ? 'შეიძინეთ სწორი ზომის SIM Magti-ს, Silknet-ის ან Cellfie-ს სალონში.' : 'Purchase the correct SIM size from a Magti, Silknet or Cellfie store.') }} },
    { "@type": "HowToStep", "position": 3, "name": {{ Js::from($ka ? 'გათიშეთ SIM-ის PIN კოდი' : 'Disable the SIM PIN code') }}, "text": {{ Js::from($ka ? 'ჩადეთ SIM ჩვეულებრივ ტელეფონში და გათიშეთ PIN კოდი პარამეტრებიდან. საათი PIN კოდს ვერ შეიყვანს.' : 'Insert the SIM into a regular phone and disable the PIN code in settings. The watch cannot enter a PIN.') }} },
    { "@type": "HowToStep", "position": 4, "name": {{ Js::from($ka ? 'გახსენით SIM სლოტი' : 'Open the SIM slot') }}, "text": {{ Js::from($ka ? 'კომპლექტში შემავალი ტოკნით ფრთხილად გახსენით SIM სლოტი საათის გვერდზე.' : 'Use the included pin to gently open the SIM slot on the side of the watch.') }} },
    { "@type": "HowToStep", "position": 5, "name": {{ Js::from($ka ? 'ჩასვით SIM ბარათი' : 'Insert the SIM') }}, "text": {{ Js::from($ka ? 'ოქროსფერი კონტაქტებით ქვემოთ ფრთხილად ჩადეთ SIM ბარათი სლოტში.' : 'Place the SIM gold-contacts-down and slide gently into the slot.') }} },
    { "@type": "HowToStep", "position": 6, "name": {{ Js::from($ka ? 'ჩართეთ და შეამოწმეთ' : 'Power on and verify') }}, "text": {{ Js::from($ka ? 'დახურეთ სლოტი და ჩართეთ საათი. ეკრანზე სიგნალის ხატულა უნდა გამოჩნდეს.' : 'Close the slot and power on the watch. A signal icon should appear on screen.') }} }
  ]
}
</script>
@endpush

@section('content')

{{-- ── Hero ──────────────────────────────────────────────────────── --}}
<section class="bg-gradient-to-br from-slate-900 to-slate-800 py-16 text-white">
    <div class="mx-auto max-w-3xl px-4 text-center">
        <p class="mb-2 text-xs font-medium uppercase tracking-widest text-primary-400">MyTechnic · სახელმძღვანელო</p>
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
            {{ $ka ? 'SIM ბარათი ბავშვის სმარტ საათისთვის' : 'SIM Card for Kids Smartwatch' }}
        </h1>
        <p class="mt-4 mx-auto max-w-xl text-base text-slate-300 leading-relaxed">
            {{ $ka
                ? 'რომელი ოპერატორი მუშაობს, რა ზომის SIM გჭირდება — ყველაფერი ერთ გვერდზე. გაუგებრობის შემთხვევაში ჩვენ დაგეხმარებით.'
                : 'Which carrier works, what SIM size you need — everything in one place. We\'re here if you have questions.' }}
        </p>
    </div>
</section>

<div class="mx-auto max-w-3xl px-4 py-14 space-y-14">

    {{-- ── SIM ზომა ─────────────────────────────────────────────────── --}}
    <section>
        <h2 class="mb-5 text-xl font-bold text-slate-900">
            {{ $ka ? 'რა ზომის SIM ბარათი სჭირდება?' : 'What SIM size does it need?' }}
        </h2>
        <div class="flex gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 flex-shrink-0 text-lg text-amber-500"></i>
            <div class="text-sm text-amber-900 leading-relaxed">
                <p class="mb-1 font-semibold">{{ $ka ? 'Nano-SIM ან Micro-SIM — დამოკიდებულია მოდელზე' : 'Nano-SIM or Micro-SIM — depends on the model' }}</p>
                <p>{{ $ka
                    ? 'სხვადასხვა მოდელი სხვადასხვა ზომის SIM-ს იყენებს. სწორი ზომისთვის — შეამოწმეთ კონკრეტული პროდუქტის სპეციფიკაციები, ან შეკვეთის დროს გვკითხეთ.'
                    : 'Different models use different SIM sizes. Check the product specs page or ask us at the time of purchase.' }}</p>
            </div>
        </div>
    </section>

    {{-- ── ოპერატორები ─────────────────────────────────────────────── --}}
    <section>
        <h2 class="mb-2 text-xl font-bold text-slate-900">
            {{ $ka ? 'რომელი ოპერატორი მუშაობს?' : 'Which carriers work?' }}
        </h2>
        <p class="mb-6 text-sm text-slate-500">
            {{ $ka
                ? 'ჩვენი საათები თავსებადია საქართველოში მოქმედი ოპერატორების SIM ბარათებთან. ქვემოთ მოცემულია დადასტურებული ოპერატორები.'
                : 'Our watches are compatible with Georgian carrier SIM cards. Below — confirmed compatible carriers.' }}
        </p>
        <div class="grid gap-4 sm:grid-cols-3">
            @php
            $carriers = [
                [
                    'name'     => 'Magti',
                    'logo'     => 'M',
                    'color'    => 'bg-red-600',
                    'style'    => null,
                    'url'      => 'https://www.magti.ge',
                    'note_ka'  => 'ფართო დაფარვის ზონა მთელ საქართველოში',
                    'note_en'  => 'Wide coverage across Georgia',
                ],
                [
                    'name'     => 'Silknet',
                    'logo'     => 'SN',
                    'color'    => 'bg-fuchsia-600',
                    'style'    => 'background-color:#c026d3;',
                    'url'      => 'https://www.silknet.com',
                    'note_ka'  => 'ერთ-ერთი წამყვანი ქსელი',
                    'note_en'  => 'One of the leading networks',
                ],
                [
                    'name'     => 'Cellfie',
                    'logo'     => 'C',
                    'color'    => 'bg-purple-600',
                    'style'    => null,
                    'url'      => 'https://cellfie.ge',
                    'note_ka'  => 'ხელმისაწვდომი ინტერნეტ პაკეტები',
                    'note_en'  => 'Great for affordable internet',
                ],
            ];
            @endphp
            @foreach($carriers as $c)
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $c['color'] }} text-sm font-extrabold text-white" @if(!empty($c['style'])) style="{{ $c['style'] }}" @endif>
                        {{ $c['logo'] }}
                    </div>
                    <div>
                        <p class="font-bold text-slate-900">{{ $c['name'] }}</p>
                        <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">
                            {{ $ka ? '✓ დადასტურებული' : '✓ Confirmed' }}
                        </span>
                    </div>
                </div>
                <p class="flex-grow text-xs text-slate-500">{{ $ka ? $c['note_ka'] : $c['note_en'] }}</p>
                <a href="{{ $c['url'] }}" target="_blank" rel="noopener noreferrer"
                   class="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-800">
                    {{ $ka ? 'ოფიციალური ტარიფები' : 'Official tariffs' }}
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                </a>
            </div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-slate-400">
            {{ $ka
                ? '* სხვა ოპერატორების SIM-ებიც შესაძლოა მუშაობდეს — დაგვიკავშირდით დეტალებისთვის.'
                : '* SIM cards from other carriers may also work — contact us for details.' }}
        </p>
    </section>

    {{-- ── მნიშვნელოვანი: PIN კოდი ──────────────────────────────────── --}}
    <section>
        <div class="flex gap-4 rounded-2xl border border-red-200 bg-red-50 p-5">
            <i class="fa-solid fa-shield-halved mt-0.5 flex-shrink-0 text-lg text-red-500"></i>
            <div class="text-sm text-red-900 leading-relaxed">
                <p class="mb-1 font-semibold">{{ $ka ? 'აუცილებელია: გათიშეთ SIM-ის PIN კოდი!' : 'Important: Disable the SIM PIN code!' }}</p>
                <p>{{ $ka
                    ? 'სანამ SIM ბარათს საათში ჩადებთ, აუცილებლად ჩადეთ ის ჩვეულებრივ სმარტფონში და გათიშეთ PIN კოდი (პარამეტრები → უსაფრთხოება → SIM ბარათის ჩაკეტვა). საათი PIN კოდს ვერ შეიყვანს და SIM ბარათი არ იმუშავებს.'
                    : 'Before inserting the SIM into the watch, insert it into a regular smartphone and disable the PIN code (Settings → Security → SIM card lock). The watch cannot enter a PIN and the SIM will not work.' }}</p>
            </div>
        </div>
    </section>

    {{-- ── VoLTE შენიშვნა ──────────────────────────────────────────── --}}
    <section>
        <div class="flex gap-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <i class="fa-solid fa-phone-volume mt-0.5 flex-shrink-0 text-lg text-indigo-500"></i>
            <div class="text-sm text-indigo-900 leading-relaxed">
                <p class="mb-1 font-semibold">{{ $ka ? '4G საათი და ზარები (VoLTE)' : '4G Watch & Calls (VoLTE)' }}</p>
                <p>{{ $ka
                    ? 'თუ თქვენი საათი 4G (LTE) მოდელია, ზარების განსახორციელებლად SIM ბარათს აქტიური VoLTE მხარდაჭერა უნდა ჰქონდეს. SIM-ის შეძენისას ოპერატორს მიუთითეთ, რომ VoLTE სერვისი გჭირდებათ.'
                    : 'If your watch is a 4G (LTE) model, the SIM card needs active VoLTE support to make calls. When purchasing the SIM, tell the carrier you need VoLTE service enabled.' }}</p>
            </div>
        </div>
    </section>

    {{-- ── SIM ჩასმა ───────────────────────────────────────────────── --}}
    <section>
        <h2 class="mb-6 text-xl font-bold text-slate-900">
            {{ $ka ? 'SIM-ის ჩასმა — ნაბიჯ-ნაბიჯ' : 'Inserting the SIM — step by step' }}
        </h2>
        <ol class="space-y-5">
            @foreach([
                [
                    'num'      => '1',
                    'title_ka' => 'შეამოწმეთ SIM ტიპი',
                    'text_ka'  => 'პროდუქტის გვერდზე ან შეფუთვაზე ნახეთ, Nano-SIM თუ Micro-SIM გჭირდებათ. დარწმუნებული არ ხართ? — დაგვიკავშირდით.',
                    'title_en' => 'Check the SIM type',
                    'text_en'  => 'Check the product page or packaging for Nano-SIM or Micro-SIM. Not sure? — contact us.',
                ],
                [
                    'num'      => '2',
                    'title_ka' => 'შეიძინეთ SIM ბარათი',
                    'text_ka'  => 'ნებისმიერ Magti-ს, Silknet-ის ან Cellfie-ს ოფისში შეიძინეთ შესაბამისი ზომის SIM. მოითხოვეთ მობილური ინტერნეტი (data) და, საჭიროების შემთხვევაში, VoLTE სერვისი.',
                    'title_en' => 'Purchase a SIM card',
                    'text_en'  => 'At any Magti, Silknet or Cellfie store, get the correct SIM size. Ask for mobile data and, if needed, VoLTE service.',
                ],
                [
                    'num'      => '3',
                    'title_ka' => 'გათიშეთ PIN კოდი',
                    'text_ka'  => 'ჩადეთ SIM ჩვეულებრივ სმარტფონში, შედით პარამეტრებში და გათიშეთ SIM-ის PIN კოდი. საათი PIN კოდის შეყვანას ვერ მოახერხებს.',
                    'title_en' => 'Disable the PIN code',
                    'text_en'  => 'Insert the SIM into a regular smartphone, go to Settings and disable the SIM PIN code. The watch cannot enter a PIN.',
                ],
                [
                    'num'      => '4',
                    'title_ka' => 'გახსენით SIM სლოტი',
                    'text_ka'  => 'კომპლექტში შემავალი ტოკნით ფრთხილად გახსენით SIM სლოტი. ის ჩვეულებრივ საათის გვერდზე ან ქვედა ნაწილშია.',
                    'title_en' => 'Open the SIM slot',
                    'text_en'  => 'Use the included ejection pin to gently open the SIM slot. It\'s usually on the side or bottom of the watch.',
                ],
                [
                    'num'      => '5',
                    'title_ka' => 'ჩადეთ SIM ბარათი',
                    'text_ka'  => 'ამოიღეთ SIM ბარათი ჩარჩოდან. ოქროსფერი კონტაქტებით ქვემოთ ფრთხილად ჩადეთ სლოტში.',
                    'title_en' => 'Insert the SIM',
                    'text_en'  => 'Remove the SIM from its frame. Place it gold-contacts-down and slide gently into the slot.',
                ],
                [
                    'num'      => '6',
                    'title_ka' => 'ჩართეთ და შეამოწმეთ',
                    'text_ka'  => 'დახურეთ სლოტი და ჩართეთ საათი. ეკრანზე სიგნალის ხატულა უნდა გამოჩნდეს. პრობლემის შემთხვევაში — დაგვიკავშირდით.',
                    'title_en' => 'Power on and verify',
                    'text_en'  => 'Close the slot and power on the watch. A signal icon should appear on screen. Having issues? — contact us.',
                ],
            ] as $step)
            <li class="flex gap-4">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">{{ $step['num'] }}</span>
                <div>
                    <h3 class="font-semibold text-slate-900">{{ $ka ? $step['title_ka'] : $step['title_en'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $ka ? $step['text_ka'] : $step['text_en'] }}</p>
                </div>
            </li>
            @endforeach
        </ol>
    </section>

    {{-- ── კონსულტაცია ──────────────────────────────────────────────── --}}
    <section class="flex gap-4 rounded-2xl border border-primary-100 bg-primary-50 p-6">
        <i class="fa-solid fa-circle-info mt-0.5 flex-shrink-0 text-xl text-primary-500"></i>
        <div>
            <p class="mb-1 font-semibold text-slate-900">
                {{ $ka ? 'პრობლემა ან კითხვა SIM-ის შესახებ?' : 'Problem or question about the SIM?' }}
            </p>
            <p class="mb-4 text-sm leading-relaxed text-slate-600">
                {{ $ka
                    ? 'SIM-ის კონფიგურაცია ზოგჯერ კონკრეტული მოდელის პარამეტრებზეა დამოკიდებული. ვიდეო ინსტრუქციებს ვამზადებთ — მანამდე კი პირდაპირ დაგვიკავშირდით, დეტალურად აგიხსნით.'
                    : 'SIM configuration sometimes depends on the specific model. We\'re preparing video instructions — until then, contact us directly and we\'ll explain everything.' }}
            </p>
            <a href="{{ route('contact') }}"
               class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-600">
                <i class="fa-solid fa-headset text-xs"></i>
                {{ $ka ? 'დაგვიკავშირდით' : 'Contact Us' }}
            </a>
        </div>
    </section>

    {{-- ── CTA ─────────────────────────────────────────────────────── --}}
    <section class="rounded-2xl bg-slate-900 px-8 py-10 text-center text-white">
        <h2 class="text-lg font-bold">{{ $ka ? 'SIM-იანი სმარტ საათი — ახლა შეუკვეთე' : 'SIM Smartwatch — Order Now' }}</h2>
        <p class="mt-2 text-sm text-slate-300">
            {{ $ka
                ? 'ყველა ჩვენი მოდელი SIM ბარათს მხარს უჭერს. სწრაფი და უფასო მიწოდება მთელ საქართველოში.'
                : 'All our models support SIM cards. Fast delivery across Georgia.' }}
        </p>
        <a href="{{ route('products.index') }}"
           class="mt-5 inline-block rounded-full bg-white px-7 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-primary-50">
            {{ $ka ? 'პროდუქტების კატალოგი →' : 'Browse Products →' }}
        </a>
    </section>

</div>
@endsection
