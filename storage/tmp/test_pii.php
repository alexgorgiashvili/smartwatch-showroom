<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$guard = new \App\Services\Chatbot\InputGuardService();

// Use reflection to test redactPii directly
$ref = new ReflectionMethod($guard, 'redactPii');
$ref->setAccessible(true);

$tests = [
    ['input' => '010-02785-01 მოდელი რას მოიცავს?', 'should_contain' => '010-02785-01'],
    ['input' => 'KT34-GPS-SOS საათი', 'should_contain' => 'KT34-GPS-SOS'],
    ['input' => '+995555123456 ნომერზე დამირეკეთ', 'should_contain' => '[REDACTED_PHONE]'],
    ['input' => '+1 (555) 123-4567', 'should_contain' => '[REDACTED_PHONE]'],
    ['input' => 'test@example.com', 'should_contain' => '[REDACTED_EMAIL]'],
];

echo "=== PII Redaction Tests ===\n\n";
$passed = 0;
foreach ($tests as $t) {
    $result = $ref->invoke($guard, $t['input']);
    $ok = str_contains($result, $t['should_contain']);
    $status = $ok ? 'PASS' : 'FAIL';
    $passed += $ok ? 1 : 0;
    echo "{$status}: \"{$t['input']}\" => \"{$result}\"\n";
    echo "  should_contain: \"{$t['should_contain']}\"\n\n";
}
echo "Result: {$passed}/" . count($tests) . "\n";
