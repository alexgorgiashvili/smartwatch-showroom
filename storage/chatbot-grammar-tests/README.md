# ჩატბოტის გრამატიკული შეცდომების ავტომატური დეტექცია

ეს სისტემა ავტომატურად ატესტებს ჩატბოტს, აღმოაჩენს ქართული ენის გრამატიკულ შეცდომებს და ამატებს გამოსწორებებს prompt-ში.

## 📋 სტრუქტურა

```
storage/chatbot-grammar-tests/
├── README.md                    # ეს ფაილი
├── test-questions.json          # 10 ტესტ კითხვა
├── responses/                   # ჩატბოტის პასუხები
│   └── 2026-03-14_02-15-00.json
└── analysis/                    # AI ანალიზის შედეგები
    └── 2026-03-14_02-16-00.json
```

## 🚀 გამოყენება

### სწრაფი გზა (რეკომენდებული)

ერთი ბრძანება რომელიც ყველაფერს გააკეთებს:

```bash
php artisan chatbot:grammar-workflow
```

ეს გაუშვებს:
1. ✅ ჩატბოტის ტესტირება 10 კითხვით
2. ✅ GPT-4o-ს მიერ ანალიზი
3. ✅ შეცდომების ჩვენება
4. ⏸️ თქვენი დასტური
5. ✅ Prompt-ის განახლება

### ნაბიჯ-ნაბიჯ გზა

თუ გსურთ თითოეული ნაბიჯის კონტროლი:

#### 1. პასუხების შეგროვება

```bash
php artisan chatbot:collect-responses
```

Options:
- `--no-cache` - cache-ის გვერდის ავლით (ახალი პასუხებისთვის)

#### 2. გრამატიკის ანალიზი

```bash
php artisan chatbot:analyze-grammar
```

Options:
- `--model=gpt-4o` - რომელი AI მოდელი გამოიყენოს (default: gpt-4o)
- `{file}` - კონკრეტული response ფაილი (default: უახლესი)

#### 3. Prompt-ის განახლება

```bash
php artisan chatbot:update-prompt
```

Options:
- `--force` - დასტურის გარეშე განახლება
- `{analysis-file}` - კონკრეტული ანალიზის ფაილი (default: უახლესი)

## 📝 ტესტ კითხვების რედაქტირება

შეცვალეთ `test-questions.json`:

```json
{
  "questions": [
    {
      "id": 1,
      "question": "თქვენი კითხვა აქ",
      "category": "price_query",
      "expected_themes": ["price", "budget"]
    }
  ]
}
```

## 🔍 რას აკეთებს ანალიზი?

AI მოდელი (GPT-4o) ეძებს:

- ✅ ბრუნვების შეცდომებს (სახელობითი, ნათესაობითი, მიცემითი)
- ✅ ნაცილობელების არასწორ გამოყენებას ("ამ მოდელი" vs "ეს მოდელი")
- ✅ ზმნის ფორმების შეცდომებს
- ✅ კალკირებას (ინგლისურიდან პირდაპირი თარგმანი)
- ✅ სიტყვათა წყობის პრობლემებს

## 📊 Output მაგალითი

```json
{
  "errors_found": 2,
  "findings": [
    {
      "question_id": 1,
      "incorrect": "ამ მოდელი",
      "correct": "ეს მოდელი",
      "error_type": "demonstrative_pronoun_case",
      "explanation": "ნაჩვენები ნაცილობელი + სახელობითი ბრუნვა",
      "prompt_rule": "| „ამ მოდელი" | „ეს მოდელი" | ნაჩვენები ნაცილობელი |"
    }
  ]
}
```

## 🔐 უსაფრთხოება

- ✅ ავტომატური backup იქმნება prompt-ის ყოველი განახლებისას
- ✅ Manual confirmation საჭიროა (თუ არ გამოიყენება `--force`)
- ✅ ყველა ანალიზი და response ინახება audit trail-ისთვის

## 📁 Backup-ების მართვა

Backup-ები ინახება:
```
config/chatbot-prompt.backup.2026-03-14_02-30-00.php
```

Backup-ის აღდგენა:
```bash
cp config/chatbot-prompt.backup.2026-03-14_02-30-00.php config/chatbot-prompt.php
php artisan config:cache
```

## 🎯 მაგალითი Workflow

```bash
# 1. გაუშვი სრული workflow
php artisan chatbot:grammar-workflow

# Output:
# 🚀 Starting Complete Grammar Workflow
# =====================================
# 
# 📝 Step 1/3: Collecting chatbot responses...
# 🤖 Starting chatbot response collection...
# 📋 Found 10 test questions
# ██████████ 10/10
# ✅ Responses collected successfully!
#
# 🔍 Step 2/3: Analyzing grammar...
# 🤖 Calling gpt-4o for analysis...
# ✅ Analysis completed!
# 📋 Detailed Findings:
#   Finding #1
#   Question: 10 ლარში ჩავეტევი?
#   ❌ Incorrect: ამ მოდელი
#   ✅ Correct: ეს მოდელი
#
# 📝 Step 3/3: Updating prompt...
# Do you want to add these corrections to the chatbot prompt? (yes/no) [yes]:
# > yes
# 💾 Backup created: chatbot-prompt.backup.2026-03-14_02-30-00.php
# ✅ Prompt updated successfully!
# 🎉 Grammar Workflow Completed Successfully!
```

## 🔧 Troubleshooting

### "No response files found"
გაუშვით: `php artisan chatbot:collect-responses`

### "OpenAI API error"
შეამოწმეთ `.env` ფაილში `OPENAI_API_KEY`

### "Could not find insertion point"
Prompt ფაილი შეიძლება შეცვლილი იყოს. გამოიყენეთ backup.

## 💡 Tips

1. **პირველი გაშვება:** გამოიყენეთ `--no-cache` რომ მიიღოთ ახალი პასუხები
2. **სწრაფი ტესტირება:** `--force` flag-ით გამოტოვებთ confirmation-ს
3. **სხვა AI მოდელი:** `--model=claude-3-5-sonnet` (თუ configured)
4. **ტესტების დამატება:** შეცვალეთ `test-questions.json` და დაამატეთ მეტი კითხვა

## 📚 დამატებითი ინფორმაცია

- Prompt ფაილი: `config/chatbot-prompt.php`
- Section 3.6: გრამატიკული შეცდომები
- Adaptive Learning: `app/Services/Chatbot/AdaptiveLearningService.php`
