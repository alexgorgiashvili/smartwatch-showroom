<div class="bg-gradient-to-b from-primary-50 to-white">
  <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
    <nav class="flex text-sm text-gray-600" aria-label="Breadcrumb">
      <a href="{{ route('home') }}" class="hover:text-primary-600"><i class="fas fa-home mr-2"></i>Home</a>
      <i class="fas fa-chevron-right mx-3 text-gray-400"></i><span>Privacy Policy</span>
    </nav>
  </div>

  <header class="border-b border-gray-200 px-4 py-14 text-center">
    <h1 class="text-4xl font-bold text-gray-900 sm:text-5xl">Privacy Policy</h1>
    <p class="mt-4 text-gray-600"><i class="fas fa-calendar-alt mr-2 text-primary-600"></i>{{ __('ui.privacy_updated') }}</p>
  </header>

  <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="mb-8 rounded-xl border border-blue-200 bg-blue-50 p-6 text-gray-700">
      MyTechnic respects your privacy. This policy explains what information we collect, why we use it, how we protect it, and what choices you have when using mytechnic.ge.
    </div>

    <div class="space-y-6">
      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">1. Information We Collect</h2>
        <p class="mt-3 text-gray-700">When you place an order or contact us, we may collect your name, phone number, email address, delivery address, personal number where required for payment or delivery, order details, and the contents of your support request.</p>
        <p class="mt-3 text-gray-700">We may also process technical information such as device and browser data, IP address, pages viewed, cookies, and chatbot interactions to operate, secure, and improve the website.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">2. How We Use Information</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-gray-700">
          <li>To process orders, payments, delivery, returns, and warranty requests.</li>
          <li>To answer inquiries and provide product or technical support.</li>
          <li>To prevent fraud, keep the service secure, and meet legal obligations.</li>
          <li>To understand website performance and improve our products and customer experience.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">3. Payments and Service Providers</h2>
        <p class="mt-3 text-gray-700">Online card payments are processed by Bank of Georgia. MyTechnic does not store your full card details. We may share only the information necessary to complete payment, delivery, hosting, analytics, security, or customer support with trusted providers acting for those purposes.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">4. Cookies and Analytics</h2>
        <p class="mt-3 text-gray-700">We use essential cookies for sessions, language preferences, carts, and security. Where enabled, analytics and advertising technologies help us measure traffic and campaign performance. Browser settings can be used to control non-essential cookies, although some site functions may be affected.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">5. AI Chatbot</h2>
        <p class="mt-3 text-gray-700">Messages sent to the AI chatbot may be stored and processed to answer your questions, provide product recommendations, maintain conversation history, and improve support quality. Do not submit passwords, card details, or other highly sensitive information in chat. Automated answers may contain errors and are not legal or medical advice.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">6. Retention and Security</h2>
        <p class="mt-3 text-gray-700">We keep information only as long as reasonably necessary for the purposes described above and any applicable legal, accounting, warranty, or dispute requirements. We use measures including TLS encryption, access controls, secure infrastructure, and monitoring. No online system can guarantee absolute security.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">7. Your Choices</h2>
        <p class="mt-3 text-gray-700">Subject to applicable Georgian law, you may ask to access, correct, or delete your personal data, object to certain processing, or request more information about how it is used. Some records may need to be retained to comply with law or complete existing obligations.</p>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="text-2xl font-bold text-gray-900">8. Updates and Contact</h2>
        <p class="mt-3 text-gray-700">We may update this policy when our services or legal obligations change. The current version is published on this page. For privacy questions or requests, contact us at <a class="font-semibold text-primary-600" href="mailto:{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}">{{ $contactSettings['email'] ?? 'info@mytechnic.ge' }}</a>.</p>
      </section>
    </div>
  </div>
</div>
