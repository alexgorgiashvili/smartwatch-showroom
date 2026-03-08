<?php
$data = json_decode(file_get_contents(__DIR__ . '/../../database/data/chatbot_golden_dataset.json'), true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON OK - " . count($data) . " cases\n";
    // Check last 2 entries
    $last2 = array_slice($data, -2);
    foreach ($last2 as $c) {
        echo "  {$c['id']}: {$c['question']}\n";
    }
} else {
    echo "JSON ERROR: " . json_last_error_msg() . "\n";
}
