<?php

namespace App\Services\Chatbot\ConversationAudit;

/**
 * Deliberately rule based: customer text is inspected in memory and never
 * copied into the audit report or its frozen evaluation scenarios.
 */
class ConversationSignalClassifier
{
    private const TOPIC_PATTERNS = [
        'price' => '/ფას|ღირს|ლარ|₾|price|cost|რამდენი/iu',
        'stock' => '/მარაგ|გაქვთ|ხელმისაწვდომ|არსებობს|stock|available|დარჩა/iu',
        'comparison' => '/შედარ|განსხვავ|ჯობია|უკეთეს|compare|versus|\bvs\b/iu',
        'recommendation' => '/მირჩი|რომელი.*(საათი|მოდელი)|შესაფერის|ბავშვისთვის.*(საათი|მოდელი)|recommend/iu',
        'delivery' => '/მიწოდ|მიტანა|კურიერ|როდის მოვა|როდის ჩამოვა|shipping|delivery/iu',
        'warranty' => '/გარანტ|საგარანტ|დაბრუნ|გაცვლ|შეცვლ|warranty|return|exchange/iu',
        'payment' => '/გადახდ|განვად|ბარათით|ნაღდ|ფულის|payment|installment/iu',
        'location' => '/სად ხართ|მისამართ|მაღაზია სად|ადგილმდებარეობ|ლოკაცი|location|address/iu',
        'connectivity' => '/სიმ|sim|esim|ინტერნეტ|wi.?fi|ქსელ|ოპერატორ|4g|5g/iu',
        'tracking' => '/gps|მდებარეობ|თვალთვალ|ლოკაცი|გეოლოკაცი|tracking|location/iu',
        'calling' => '/ზარ|დარეკ|ვიდეო|კამერ|საუბარ|call|camera/iu',
        'battery' => '/ბატარე|დატენ|რამდენ ხანს ძლებს|battery|charge/iu',
        'setup' => '/აპლიკაცი|დაყენ|დაკავშირ|რეგისტრაცი|პაროლ|configure|setup|app\b/iu',
        'waterproof' => '/წყალ|ცურვ|წვიმ|ip67|ip68|waterproof/iu',
    ];

    private const REPLY_PATTERNS = [
        'mentions_price' => '/\d+\s*(?:₾|ლარ)|ფას/iu',
        'mentions_availability' => '/მარაგ|გაქვთ|ხელმისაწვდომ|ამოიწურა/iu',
        'mentions_delivery' => '/მიწოდ|მიტანა|კურიერ|shipping/iu',
        'mentions_warranty_or_return' => '/გარანტ|დაბრუნ|გაცვლ/iu',
        'mentions_payment' => '/გადახდ|განვად|ბარათით/iu',
        'includes_link' => '~https?://|www\.~iu',
        'asks_for_contact' => '/ნომერი|ტელეფონი|მოგვწერეთ|დაგვიკავშირდით|contact/iu',
    ];

    public function topic(string $text): string
    {
        foreach (self::TOPIC_PATTERNS as $topic => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $topic;
            }
        }

        return 'unclassified';
    }

    /** @return list<string> */
    public function replySignals(string $text): array
    {
        $signals = [];
        foreach (self::REPLY_PATTERNS as $signal => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    public function questionLike(string $text): bool
    {
        return preg_match('/\?|\b(?:what|how|when|where|which|can|do you)\b|რა |როგორ |როდის|სად |რამდენ|შეიძლება|გაქვთ|მაინტერესებს/iu', $text) === 1;
    }

    public function fallbackLike(string $text): bool
    {
        return preg_match('/ბოდიში|დროებით მიუწვდომ|დროებით პრობლემა|პასუხი ვერ მივიღე|სცადეთ მოგვიანებით/iu', $text) === 1;
    }

    /**
     * Defense in depth for any future private, non-versioned review output.
     * Public frozen scenarios are generated entirely from controlled templates.
     */
    public function redact(string $text): string
    {
        $patterns = [
            '~https?://\S+|www\.\S+~iu' => '[URL]',
            '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[A-Z]{2,}/iu' => '[EMAIL]',
            '/(?<!\w)@[\p{L}\p{N}._]{2,}/u' => '[HANDLE]',
            '/(?:\+?995[\s.\-]?)?5\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}(?!\d)/u' => '[PHONE]',
            '/(?<!\d)(?:\d[\s\-]?){13,19}(?!\d)/u' => '[LONG_NUMBER]',
            '/(?<!\d)\d{11}(?!\d)/u' => '[PERSONAL_NUMBER]',
            '/(?:ქუჩა|ქ\.|გამზირი|ჩიხი|street|avenue)\s+[\p{L}\p{N}\s,.-]{4,}/iu' => '[ADDRESS]',
        ];

        return trim((string) preg_replace(array_keys($patterns), array_values($patterns), $text));
    }
}
