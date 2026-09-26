<?php

namespace App\Services\Chatbot\ConversationAudit;

use InvalidArgumentException;

/**
 * Keeps source text only for the duration of ingest(). Neither report nor
 * scenarios contain a transcript, a platform identifier, or a customer name.
 */
class ConversationAuditBuilder
{
    private const SUBJECTS = [
        'ბავშვის GPS საათი',
        'SIM-ბარათიანი საბავშვო საათი',
        'ვიდეოზარის მქონე საბავშვო საათი',
        'სკოლის ასაკის ბავშვის საათი',
        'საბავშვო სმარტსაათი',
        'GPS ფუნქციის მქონე საბავშვო საათი',
        'საბავშვო 4G საათი',
        'კამერიანი საბავშვო საათი',
        'SOS ღილაკიანი საბავშვო საათი',
        'ბავშვის ტელეფონ-საათი',
    ];

    private const QUESTION_PATTERNS = [
        'price' => [
            'რა ღირს %s?', 'რა ფასი აქვს %s?', '%s რა ფასად იყიდება?',
            '%s-ის ფასი მაინტერესებს.', 'რა თანხა დამჭირდება %s-ის შესაძენად?',
            'შეგიძლიათ მითხრათ %s-ის მიმდინარე ფასი?',
            '%s-ის ფასს სად ვნახავ?', '%s-ზე რა ფასი მოქმედებს ახლა?',
        ],
        'stock' => [
            'არის თუ არა მარაგში %s?', '%s ამჟამად ხელმისაწვდომია?',
            '%s შეგიძლიათ დღეს მომაწოდოთ?', '%s ისევ გაქვთ?',
            '%s-ის მარაგი როგორ შევამოწმო?', 'თუ შევუკვეთავ, %s ხელმისაწვდომია?',
            '%s ადგილზე არის?', '%s ხომ არ ამოწურულა?',
        ],
        'comparison' => [
            'რით განსხვავდება %s სხვა მოდელებისგან?', '%s როგორ შევადარო მსგავს საათებს?',
            '%s რომელი ფუნქციებით გამოირჩევა?', '%s და სხვა საბავშვო საათები როგორ შევადარო?',
            '%s-ის არჩევისას რა განსხვავებებს მივაქციო ყურადღება?',
            '%s უკეთესია თუ სხვა ტიპის საათი?', 'რა უპირატესობა აქვს %s-ს?',
            '%s-ის ალტერნატივებს როგორ შევადარო?',
        ],
        'recommendation' => [
            'მირჩიეთ %s-ის ტიპის მოდელი.', 'ვისთვის არის შესაფერისი %s?',
            '%s ღირს არჩევად?', '%s-ის მსგავსი რომელი მოდელი მირჩევთ?',
            'როგორ ავირჩიო %s?', 'რომელი %s იქნება პრაქტიკული?',
            '%s-ის არჩევაში დამეხმარებით?', 'რა შემთხვევაში მირჩევთ %s-ს?',
        ],
        'delivery' => [
            '%s-ის მიწოდება რამდენ ხანში ხდება?', '%s-ის შეკვეთას კურიერი მოიტანს?',
            '%s სად შეგიძლიათ მომაწოდოთ?', '%s-ის მიტანის პირობები რა არის?',
            '%s-ის შეკვეთას რეგიონშიც აწვდით?', '%s-ის მიტანის საფასური როგორ გავიგო?',
            '%s-ის შეკვეთის შემდეგ როდის მოდის კურიერი?', '%s-ის მიწოდების დრო როგორ დგინდება?',
        ],
        'warranty' => [
            'რა გარანტია აქვს %s-ს?', '%s-ის საგარანტიო პირობები როგორია?',
            '%s თუ არ გამომადგა, დაბრუნება როგორ ხდება?', '%s-ის გაცვლის პირობები რა არის?',
            '%s-ის შეკეთებას გარანტია ფარავს?', '%s-ის დაბრუნების ვადა როგორ შევამოწმო?',
            '%s-ის შეძენისას საგარანტიო დოკუმენტი მოყვება?', '%s-ის პრობლემა თუ გამოჩნდა, ვის მივმართო?',
        ],
        'payment' => [
            '%s-ის შეძენა განვადებით შეიძლება?', '%s-ისთვის ბარათით გადახდა შეიძლება?',
            '%s-ის გადახდის რა გზებია?', '%s-ის შეკვეთისას როდის უნდა გადავიხადო?',
            '%s-ის თანხის გადახდა ადგილზე შეიძლება?', '%s-ის განვადების პირობები სად ვნახო?',
            '%s-ის ონლაინ გადახდა ხელმისაწვდომია?', '%s-ის შეძენაზე გადახდის პირობები მაინტერესებს.',
        ],
        'location' => [
            '%s სად შემიძლია ადგილზე ვნახო?', '%s რომელ მაღაზიაშია წარმოდგენილი?',
            '%s-ის სანახავად სად მოვიდე?', '%s ადგილზე როგორ დავათვალიერო?',
            '%s-ის მაღაზიის მისამართი სად ვნახო?', '%s-ის საჩვენებელი ადგილი გაქვთ?',
            '%s სად შეიძლება ფიზიკურად შევამოწმო?', '%s რომ ვნახო, რომელ მისამართზე მოვიდე?',
        ],
        'connectivity' => [
            '%s რომელი SIM-ბარათით მუშაობს?', '%s-ს ინტერნეტი სჭირდება?',
            '%s Wi-Fi ქსელს უკავშირდება?', '%s რომელ მობილურ ქსელზე მუშაობს?',
            '%s-ს ცალკე ნომერი სჭირდება?', '%s 4G კავშირს უჭერს მხარს?',
            '%s-ისთვის რომელი ოპერატორია საჭირო?', '%s-ის SIM პარამეტრები როგორ შევამოწმო?',
        ],
        'tracking' => [
            '%s GPS მდებარეობას აჩვენებს?', '%s-ით ბავშვის მდებარეობა როგორ ვნახო?',
            '%s მდებარეობას რეალურ დროში აჩვენებს?', '%s-ის ლოკაციის სიზუსტე როგორია?',
            '%s-ს GPS ფუნქცია აქვს?', '%s-ის მდებარეობის ნახვა ტელეფონიდან შეიძლება?',
            '%s ლოკაციას როგორ აგზავნის?', '%s-ით უსაფრთხო ზონის დაყენება შეიძლება?',
        ],
        'calling' => [
            '%s-ით დარეკვა შეიძლება?', '%s ვიდეოზარს აკეთებს?',
            '%s-ს კამერა აქვს?', '%s-იდან ვისთან შეიძლება დარეკვა?',
            '%s-ზე შემომავალი ზარები მუშაობს?', '%s-ით ორმხრივი საუბარი შეიძლება?',
            '%s ვიდეოზარისთვის რა სჭირდება?', '%s-ის ზარის ფუნქციები როგორია?',
        ],
        'battery' => [
            '%s-ის ბატარეა რამდენ ხანს ძლებს?', '%s რამდენ ხანში იტენება?',
            '%s ყოველდღე დასატენია?', '%s-ის დატენვის წესი როგორია?',
            '%s-ის მუშაობის დრო რა არის?', '%s-ის ბატარეის მოცულობა ცნობილია?',
            '%s დატენვის გარეშე რამდენ ხანს მუშაობს?', '%s-ის დამტენი მოყვება?',
        ],
        'setup' => [
            '%s როგორ დავაყენო?', '%s რომელ აპლიკაციასთან მუშაობს?',
            '%s ტელეფონს როგორ დავუკავშირო?', '%s-ის რეგისტრაცია როგორ ხდება?',
            '%s-ის საწყისი გამართვა რთულია?', '%s-ის აპლიკაცია სად გადმოვწერო?',
            '%s-ის პარამეტრების შეცვლა როგორ ხდება?', '%s-ის დაყენებაში დახმარება შეიძლება?',
        ],
        'waterproof' => [
            '%s წყალგამძლეა?', '%s წვიმაში მუშაობს?',
            '%s-ით ცურვა შეიძლება?', '%s-ს რა წყალგამძლეობის დონე აქვს?',
            '%s წყალში თუ დასველდა, იმუშავებს?', '%s-ის IP რეიტინგი რა არის?',
            '%s-ის წყალთან გამოყენების პირობები როგორია?', '%s ხელების დაბანისას შეიძლება ეკეთოს ბავშვს?',
        ],
    ];

    private const INTENT_MAP = [
        'price' => 'price_query',
        'stock' => 'stock_query',
        'comparison' => 'comparison',
        'recommendation' => 'recommendation',
        'connectivity' => 'features',
        'tracking' => 'features',
        'calling' => 'features',
        'battery' => 'features',
        'setup' => 'features',
        'waterproof' => 'features',
    ];

    private array $seen = [];
    private array $messageCounts = [];
    private array $originCounts = [];
    private array $topicCounts = [];
    private array $replySignals = [];
    private array $botReplySignals = [];
    private array $dateSpans = [];
    private array $questionRefs = [];
    private int $duplicateCount = 0;
    private int $detectablePiiCount = 0;
    private int $customerQuestionLikeCount = 0;
    private int $unclassifiedQuestionLikeCount = 0;
    private int $customerShortMessageCount = 0;
    private int $botFallbackLikeCount = 0;

    public function __construct(
        private readonly string $hashKey,
        private readonly ConversationSignalClassifier $classifier = new ConversationSignalClassifier()
    ) {
        if ($hashKey === '') {
            throw new InvalidArgumentException('An application-local HMAC key is required.');
        }
    }

    public function ingest(
        string $channel,
        string $role,
        string $content,
        string $sourceKey,
        ?string $date,
        string $origin
    ): void {
        $channel = strtolower(trim($channel));
        $role = strtolower(trim($role));
        $content = trim($content);

        if (!in_array($role, ['customer', 'admin', 'page', 'bot', 'system'], true)
            || !in_array($channel, ['home', 'facebook', 'instagram', 'whatsapp'], true)) {
            return;
        }

        $sourceKey = trim($sourceKey) !== ''
            ? $sourceKey
            : hash('sha256', $role . '|' . $date . '|' . $content);
        $sourceHash = hash_hmac('sha256', $channel . '|' . $sourceKey, $this->hashKey);
        if (isset($this->seen[$sourceHash])) {
            $this->duplicateCount++;
            return;
        }
        $this->seen[$sourceHash] = true;

        $this->messageCounts[$channel][$role] = ($this->messageCounts[$channel][$role] ?? 0) + 1;
        $this->originCounts[$origin] = ($this->originCounts[$origin] ?? 0) + 1;

        $day = $date !== null ? substr($date, 0, 10) : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            $this->dateSpans[$channel]['first'] = min($day, $this->dateSpans[$channel]['first'] ?? $day);
            $this->dateSpans[$channel]['last'] = max($day, $this->dateSpans[$channel]['last'] ?? $day);
        }

        if ($content === '') {
            return;
        }

        if ($this->classifier->redact($content) !== $content) {
            $this->detectablePiiCount++;
        }

        if ($role === 'customer') {
            $topic = $this->classifier->topic($content);
            $this->topicCounts[$topic] = ($this->topicCounts[$topic] ?? 0) + 1;
            $questionLike = $this->classifier->questionLike($content);
            $this->customerQuestionLikeCount += (int) $questionLike;
            $this->unclassifiedQuestionLikeCount += (int) ($questionLike && $topic === 'unclassified');
            $this->customerShortMessageCount += (int) (mb_strlen($content) <= 20);
            if ($topic !== 'unclassified') {
                $this->questionRefs[] = [
                    'source_hash' => $sourceHash,
                    'source_channel' => $channel,
                    'topic' => $topic,
                ];
            }
        } elseif (in_array($role, ['admin', 'page'], true)) {
            foreach ($this->classifier->replySignals($content) as $signal) {
                $this->replySignals[$signal] = ($this->replySignals[$signal] ?? 0) + 1;
            }
        } elseif ($role === 'bot') {
            foreach ($this->classifier->replySignals($content) as $signal) {
                $this->botReplySignals[$signal] = ($this->botReplySignals[$signal] ?? 0) + 1;
            }
            $this->botFallbackLikeCount += (int) $this->classifier->fallbackLike($content);
        }
    }

    /** @return array{report: array<string,mixed>, scenarios: list<array<string,mixed>>} */
    public function finish(array $coverage, int $targetCases = 120): array
    {
        ksort($this->messageCounts);
        foreach ($this->messageCounts as &$roles) {
            ksort($roles);
        }
        unset($roles);
        ksort($this->originCounts);
        ksort($this->topicCounts);
        ksort($this->replySignals);
        ksort($this->botReplySignals);
        ksort($this->dateSpans);
        usort($this->questionRefs, fn (array $a, array $b): int => strcmp($a['source_hash'], $b['source_hash']));

        $scenarios = [];
        $topicOrdinals = [];
        $usedQuestions = [];
        foreach ($this->questionRefs as $ref) {
            $topic = $ref['topic'];
            $ordinal = $topicOrdinals[$topic] ?? 0;
            $topicOrdinals[$topic] = $ordinal + 1;
            $question = $this->templateQuestion($topic, $ordinal);
            if ($question === null || isset($usedQuestions[$question])) {
                continue;
            }
            $usedQuestions[$question] = true;
            $scenarios[] = [
                'id' => 'conv-' . str_pad((string) (count($scenarios) + 1), 3, '0', STR_PAD_LEFT),
                'category' => self::INTENT_MAP[$topic] ?? 'general',
                'question' => $question,
                'language' => 'ka',
                'expected' => [
                    'expected_intent' => self::INTENT_MAP[$topic] ?? 'general',
                    'guardrail_should_pass' => true,
                    'georgian_only' => true,
                    'answer_policy' => in_array($topic, ['price', 'stock', 'warranty', 'delivery'], true)
                        ? 'verify_current_business_source'
                        : 'answer_from_verified_context',
                ],
                'source_category' => $topic,
                'source_channel' => $ref['source_channel'],
                'review_status' => 'synthetic_question_from_observed_intent',
            ];
            if (count($scenarios) >= $targetCases) {
                break;
            }
        }

        return [
            'report' => [
                'schema_version' => 1,
                'purpose' => 'Aggregate conversation coverage and customer interests. No raw message text or identifiers.',
                'coverage' => $coverage,
                'message_counts' => $this->messageCounts,
                'source_counts' => $this->originCounts,
                'topic_counts' => $this->topicCounts,
                'business_reply_signal_counts' => $this->replySignals,
                'bot_reply_signal_counts' => $this->botReplySignals,
                'bot_fallback_like_count' => $this->botFallbackLikeCount,
                'customer_question_like_count' => $this->customerQuestionLikeCount,
                'unclassified_question_like_count' => $this->unclassifiedQuestionLikeCount,
                'customer_short_message_count' => $this->customerShortMessageCount,
                'date_spans' => $this->dateSpans,
                'duplicate_messages_skipped' => $this->duplicateCount,
                'messages_with_detectable_pii' => $this->detectablePiiCount,
                'frozen_scenario_count' => count($scenarios),
                'warnings' => [
                    'Business Page replies may have been sent by any team member and are not verified owner-approved answers.',
                    'No observed reply is automatically promoted to a policy, product claim, or expected answer.',
                    'Scenario wording is controlled synthetic text. Raw transcripts and source identifiers stay out of Git.',
                    'Current prices, stock, delivery, warranty, and returns require independent authoritative verification.',
                ],
            ],
            'scenarios' => $scenarios,
        ];
    }

    private function templateQuestion(string $topic, int $ordinal): ?string
    {
        $patterns = self::QUESTION_PATTERNS[$topic] ?? null;
        if ($patterns === null || $ordinal >= count($patterns) * count(self::SUBJECTS)) {
            return null;
        }

        $subject = self::SUBJECTS[$ordinal % count(self::SUBJECTS)];
        $pattern = $patterns[intdiv($ordinal, count(self::SUBJECTS))];

        return sprintf($pattern, $subject);
    }
}
