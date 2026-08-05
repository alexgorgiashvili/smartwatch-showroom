<div class="bg-gradient-to-b from-primary-50 to-white">
  <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
    <nav class="flex text-sm text-gray-600" aria-label="Breadcrumb">
      <a href="{{ route('home') }}" class="hover:text-primary-600"><i class="fas fa-home mr-2"></i>Home</a>
      <i class="fas fa-chevron-right mx-3 text-gray-400"></i><span>Terms of Service</span>
    </nav>
  </div>

  <header class="border-b border-gray-200 px-4 py-14 text-center">
    <h1 class="text-4xl font-bold text-gray-900 sm:text-5xl">Terms of Service</h1>
    <p class="mt-4 text-gray-600"><i class="fas fa-file-contract mr-2 text-primary-600"></i>{{ __('ui.terms_updated') }}</p>
  </header>

  <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-6 text-gray-700">
      Please read these terms carefully. By accessing mytechnic.ge or placing an order, you agree to these Terms of Service, our Privacy Policy, and applicable Georgian law.
    </div>

    <div class="space-y-6">
      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">1. General Terms</h2>
        <p class="mt-3 text-gray-700">These terms apply to visitors, customers, and anyone using the website. If you do not agree with them, please stop using the service.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">2. Permitted Use</h2>
        <p class="mt-3 text-gray-700">You may use website materials for personal, non-commercial product browsing and purchasing. You may not copy or distribute materials, use them commercially without written permission, reverse engineer site software, remove legal notices, interfere with security, or place copies on another server.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">3. Orders, Prices, and Payment</h2>
        <p class="mt-3 text-gray-700">Prices are shown in Georgian lari (GEL) and may change before an order is confirmed. Availability depends on stock. Online card payments are securely processed by Bank of Georgia; MyTechnic does not store full card details. Cash on delivery is available only for eligible orders in Tbilisi.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">4. Delivery</h2>
        <p class="mt-3 text-gray-700">Delivery is free across Georgia. Estimated delivery times are one business day in Tbilisi, one to three business days in regional cities, and two to five business days for villages or remote addresses. Same-day delivery is available only after confirmation. Times are estimates and may change because of address, schedule, courier, weather, or logistics conditions.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">5. Warranty, Returns, and Exchanges</h2>
        <p class="mt-3 text-gray-700">A one-month warranty applies to 2G models and a three-month warranty applies to 4G models from the purchase date. It covers manufacturing defects and technical faults not caused by the user. It does not cover mechanical damage, normal wear, water damage or misuse, or unauthorized repair or modification.</p>
        <p class="mt-3 text-gray-700">A model exchange may be requested within 14 calendar days of delivery if the product is unused, has its complete original packaging, and is accompanied by proof of purchase. Contact <a class="font-semibold text-primary-600" href="mailto:{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}">{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}</a> to start a warranty, return, or exchange request.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">6. Website Information and Disclaimer</h2>
        <p class="mt-3 text-gray-700">Website materials are provided on an “as is” basis. Product specifications, images, descriptions, prices, and availability may contain errors or differ from the delivered product. To the extent permitted by law, use of the website is at your own risk.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">7. Limitation of Liability</h2>
        <p class="mt-3 text-gray-700">To the extent permitted by applicable law, MyTechnic and its partners are not liable for indirect, incidental, or consequential loss resulting from use of, or inability to use, the website. Nothing in these terms excludes rights or liability that cannot legally be excluded.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">8. External Links</h2>
        <p class="mt-3 text-gray-700">The website may link to third-party services. MyTechnic does not control their content, security, privacy policies, or practices. You use external services at your own risk and under their terms.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">9. AI Chatbot</h2>
        <p class="mt-3 text-gray-700">The AI chatbot provides automated product and support information. Its responses may not always be complete or accurate and do not constitute legal or medical advice. Confirm important information with our team before relying on it.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">10. Changes and Contact</h2>
        <p class="mt-3 text-gray-700">We may update these terms by publishing a revised version on this page. Continued use after publication means you accept the current version. Questions can be sent to <a class="font-semibold text-primary-600" href="mailto:{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}">{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}</a>.</p>
      </section>
    </div>
  </div>
</div>
