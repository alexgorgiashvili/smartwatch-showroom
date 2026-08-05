<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('question_en', 255)->nullable()->after('question');
            $table->text('answer_en')->nullable()->after('answer');
            $table->string('category_en', 120)->nullable()->after('category');
        });

        Schema::table('chatbot_documents', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('content_en')->nullable()->after('content_ka');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('region_en')->nullable()->after('region');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('name_en', 160)->nullable()->after('name');
            $table->string('color_name_en', 50)->nullable()->after('color_name');
        });

        foreach ($this->variantColorTranslations() as $name => $nameEn) {
            DB::table('product_variants')->where('name', $name)->update(['name_en' => $nameEn]);
            DB::table('product_variants')->where('color_name', $name)->update(['color_name_en' => $nameEn]);
        }

        foreach ($this->cityTranslations() as $name => [$nameEn, $regionEn]) {
            DB::table('cities')->where('name', $name)->update([
                'name_en' => $nameEn,
                'region_en' => $regionEn,
            ]);
        }

        $translations = $this->defaultFaqTranslations();

        DB::table('faqs')->orderBy('id')->get()->each(function (object $faq) use ($translations): void {
            $translation = $translations[trim((string) $faq->question)] ?? null;
            if ($translation === null) {
                return;
            }

            DB::table('faqs')->where('id', $faq->id)->update([
                'question_en' => $translation['question'],
                'answer_en' => $translation['answer'],
                'category_en' => $translation['category'],
            ]);
        });

        $contactDefaults = [
            'location_en' => 'Tbilisi, Georgia',
            'hours_en' => 'Every day, 10:00–20:00',
            'faq_support_title_en' => 'Need quick help?',
            'faq_support_description_en' => 'Message us on Live Chat, WhatsApp, or Messenger and we’ll reply as quickly as possible.',
        ];

        foreach ($contactDefaults as $key => $value) {
            DB::table('contact_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        foreach (['location_en', 'hours_en', 'faq_support_title_en', 'faq_support_description_en'] as $key) {
            DB::table('contact_settings')->where('key', $key)->delete();
        }

        Schema::table('chatbot_documents', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'content_en']);
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['question_en', 'answer_en', 'category_en']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'region_en']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'color_name_en']);
        });
    }

    /** @return array<string, array{question: string, answer: string, category: string}> */
    private function defaultFaqTranslations(): array
    {
        $delivery = "Delivery is free across Georgia.\n\nEstimated delivery times:\n- Tbilisi: 1 business day\n- Regional cities: 1–3 business days\n- Villages or remote addresses: 2–5 business days\n\nSame-day delivery is available only in exceptional cases and after confirmation.\n\nWhenever possible, the courier will contact you in advance to confirm the address and delivery time. The estimate may change if the address, logistics, or working-day schedule requires additional clarification.";
        $warranty = "All our smartwatches include an official warranty covering manufacturing defects and device malfunctions.\n\nThe warranty period and detailed terms are listed in each product description. If a watch develops a fault during normal use, contact our support team and we’ll help find the best solution.";

        return [
            'მიწოდება თბილისში' => ['question' => 'Delivery in Tbilisi', 'answer' => $delivery, 'category' => 'Delivery'],
            'უფასო მიწოდება მთელი ქვეყნის მასშტაბით' => ['question' => 'Free Delivery Across Georgia', 'answer' => $delivery, 'category' => 'Delivery'],
            'გარანტიაა' => ['question' => 'Warranty', 'answer' => $warranty, 'category' => 'Warranty and Returns'],
            'გარანტია' => ['question' => 'Warranty', 'answer' => $warranty, 'category' => 'Warranty and Returns'],
            'დაბრუნება და შეცვლა' => [
                'question' => 'Returns and Exchanges',
                'answer' => "You may return an item within 7 calendar days of delivery if it remains unused, in resalable condition, and in its complete original packaging.\n\nTo start a return, contact us through any support channel and an operator will explain the next steps.",
                'category' => 'Warranty and Returns',
            ],
            'GPS ფუნქციები' => [
                'question' => 'GPS Features',
                'answer' => "GPS lets you see your child’s location in real time through the mobile app.\n\nThe app can show the current location and movement history. Some models also support safe zones (geofences); you receive a notification when the child leaves a configured zone.",
                'category' => 'Child Safety',
            ],
            'SIM ბარათის მხარდაჭერა' => [
                'question' => 'SIM Card Support',
                'answer' => "A SIM card enables calls, messages, and mobile data on the watch. We recommend an active SIM with a data package so GPS and online features work reliably.\n\nSIM installation instructions are included with the watch, and our team can help you online if needed.",
                'category' => 'Product Use',
            ],
            'ბატარეის დრო' => [
                'question' => 'Battery Life',
                'answer' => "Battery life depends on usage. With normal use, a watch typically lasts 1–3 days on one charge.\n\nContinuous GPS use and frequent calls consume more power; in that case, daily charging is recommended.",
                'category' => 'Product Use',
            ],
            'წყალგამძლეობა' => [
                'question' => 'Water Resistance',
                'answer' => "Some models are water-resistant for everyday use, such as handwashing or light rain.\n\nThe product page lists the exact IP rating. Do not use the watch while swimming or in the sea or pool unless the model specifications explicitly allow it.",
                'category' => 'Product Use',
            ],
            'აპლიკაციის ინსტალაცია' => [
                'question' => 'Installing the App',
                'answer' => "Full watch functionality requires installing the companion mobile app on a parent’s phone.\n\nYour order includes download instructions. Pairing the watch in the app is straightforward, and step-by-step support is available in English.",
                'category' => 'App and Controls',
            ],
            'გადახდის მეთოდები' => [
                'question' => 'Payment Methods',
                'answer' => "You can pay online by bank card. Cash on delivery is also available for eligible orders in Tbilisi.\n\nThe available payment methods and final amount are shown during checkout.",
                'category' => 'Orders and Payment',
            ],
            'დახმარება და კონტაქტი' => [
                'question' => 'Support and Contact',
                'answer' => "You can contact us on Facebook, WhatsApp, Instagram, by email, or by phone.\n\nThe Contact page lists every support channel and our working hours. We aim to reply as quickly as possible.",
                'category' => 'Contact',
            ],
        ];
    }

    /** @return array<string, array{string, string}> */
    private function cityTranslations(): array
    {
        return [
            'თბილისი' => ['Tbilisi', 'Tbilisi'],
            'ბათუმი' => ['Batumi', 'Adjara'], 'ქობულეთი' => ['Kobuleti', 'Adjara'], 'ხელვაჩაური' => ['Khelvachauri', 'Adjara'], 'ხულო' => ['Khulo', 'Adjara'], 'ქედა' => ['Keda', 'Adjara'], 'შუახევი' => ['Shuakhevi', 'Adjara'],
            'ქუთაისი' => ['Kutaisi', 'Imereti'], 'ზესტაფონი' => ['Zestafoni', 'Imereti'], 'სამტრედია' => ['Samtredia', 'Imereti'], 'წყალტუბო' => ['Tskaltubo', 'Imereti'], 'ბაღდათი' => ['Baghdati', 'Imereti'], 'ვანი' => ['Vani', 'Imereti'], 'ხონი' => ['Khoni', 'Imereti'], 'თერჯოლა' => ['Terjola', 'Imereti'], 'საჩხერე' => ['Sachkhere', 'Imereti'], 'ტყიბული' => ['Tkibuli', 'Imereti'], 'ჭიათურა' => ['Chiatura', 'Imereti'],
            'ზუგდიდი' => ['Zugdidi', 'Samegrelo'], 'ფოთი' => ['Poti', 'Samegrelo'], 'სენაკი' => ['Senaki', 'Samegrelo'], 'წალენჯიხა' => ['Tsalenjikha', 'Samegrelo'], 'მარტვილი' => ['Martvili', 'Samegrelo'], 'ჩხოროწყუ' => ['Chkhorotsku', 'Samegrelo'], 'ხობი' => ['Khobi', 'Samegrelo'], 'აბაშა' => ['Abasha', 'Samegrelo'],
            'გორი' => ['Gori', 'Shida Kartli'], 'კასპი' => ['Kaspi', 'Shida Kartli'], 'ხაშური' => ['Khashuri', 'Shida Kartli'], 'ქარელი' => ['Kareli', 'Shida Kartli'], 'ცხინვალი' => ['Tskhinvali', 'Shida Kartli'],
            'რუსთავი' => ['Rustavi', 'Kvemo Kartli'], 'მარნეული' => ['Marneuli', 'Kvemo Kartli'], 'გარდაბანი' => ['Gardabani', 'Kvemo Kartli'], 'ბოლნისი' => ['Bolnisi', 'Kvemo Kartli'], 'წალკა' => ['Tsalka', 'Kvemo Kartli'], 'დმანისი' => ['Dmanisi', 'Kvemo Kartli'], 'თეთრიწყარო' => ['Tetritskaro', 'Kvemo Kartli'],
            'თელავი' => ['Telavi', 'Kakheti'], 'სიღნაღი' => ['Sighnaghi', 'Kakheti'], 'გურჯაანი' => ['Gurjaani', 'Kakheti'], 'საგარეჯო' => ['Sagarejo', 'Kakheti'], 'ყვარელი' => ['Kvareli', 'Kakheti'], 'ლაგოდეხი' => ['Lagodekhi', 'Kakheti'], 'დედოფლისწყარო' => ['Dedoplistskaro', 'Kakheti'], 'ახმეტა' => ['Akhmeta', 'Kakheti'],
            'მცხეთა' => ['Mtskheta', 'Mtskheta-Mtianeti'], 'დუშეთი' => ['Dusheti', 'Mtskheta-Mtianeti'], 'ყაზბეგი' => ['Kazbegi', 'Mtskheta-Mtianeti'], 'თიანეთი' => ['Tianeti', 'Mtskheta-Mtianeti'],
            'ახალციხე' => ['Akhaltsikhe', 'Samtskhe-Javakheti'], 'ბორჯომი' => ['Borjomi', 'Samtskhe-Javakheti'], 'ახალქალაქი' => ['Akhalkalaki', 'Samtskhe-Javakheti'], 'ნინოწმინდა' => ['Ninotsminda', 'Samtskhe-Javakheti'], 'ადიგენი' => ['Adigeni', 'Samtskhe-Javakheti'], 'ასპინძა' => ['Aspindza', 'Samtskhe-Javakheti'], 'ვალე' => ['Vale', 'Samtskhe-Javakheti'],
            'ოზურგეთი' => ['Ozurgeti', 'Guria'], 'ლანჩხუთი' => ['Lanchkhuti', 'Guria'], 'ჩოხატაური' => ['Chokhatauri', 'Guria'],
            'ამბროლაური' => ['Ambrolauri', 'Racha-Lechkhumi'], 'ცაგერი' => ['Tsageri', 'Racha-Lechkhumi'], 'ონი' => ['Oni', 'Racha-Lechkhumi'], 'ლენტეხი' => ['Lentekhi', 'Racha-Lechkhumi'],
            'მესტია' => ['Mestia', 'Svaneti'], 'ლალი' => ['Lali', 'Svaneti'],
        ];
    }

    /** @return array<string, string> */
    private function variantColorTranslations(): array
    {
        return [
            'შავი' => 'Black',
            'ლურჯი' => 'Blue',
            'ვარდისფერი' => 'Pink',
            'თეთრი' => 'White',
            'იასამნისფერი' => 'Purple',
            'მწვანე' => 'Green',
            'წითელი' => 'Red',
        ];
    }
};
