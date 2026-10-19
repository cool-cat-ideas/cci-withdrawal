<?php

declare(strict_types=1);

if (getenv('CCI_WITHDRAWAL_RUN_RUNTIME_TESTS') !== '1') {
    fwrite(STDERR, "Set CCI_WITHDRAWAL_RUN_RUNTIME_TESTS=1 to run this transactional test.\n");
    exit(2);
}

require dirname(__DIR__, 3) . '/config/config.inc.php';

$module = Module::getInstanceByName('cci_withdrawal');
if (!$module instanceof Cci_Withdrawal || !$module->ensureDatabase()) {
    fwrite(STDERR, "FAIL: the module or its schema is unavailable.\n");
    exit(1);
}

$database = Db::getInstance();
$table = _DB_PREFIX_ . 'cci_withdrawal_submission';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$insert = static function (array $payload, string $hash, int $shopId, string $scope) use ($database): bool {
    $date = date('Y-m-d H:i:s');

    return $database->insert('cci_withdrawal_submission', [
        'id_order' => 900001,
        'id_customer' => $scope === 'full' ? 700001 : 0,
        'id_shop' => $shopId,
        'id_lang' => 1,
        'submission_hash' => pSQL($hash),
        'public_token_hash' => pSQL(hash('sha256', 'receipt-' . $hash)),
        'buyer_status' => 'consumer',
        'non_professional_declaration' => 0,
        'company_detected' => 0,
        'customer_name' => $scope === 'full' ? 'Runtime customer' : 'Runtime guest',
        'customer_email' => $scope . '@example.test',
        'order_reference' => 'RUNTIME-' . strtoupper($scope),
        'statement_payload' => pSQL(json_encode($payload, JSON_THROW_ON_ERROR), true),
        'processing_status' => 'manual_review',
        'processing_message' => 'Runtime test',
        'confirmation_status' => 'pending',
        'confirmation_attempts' => 0,
        'confirmation_last_error' => '',
        'date_add' => $date,
        'date_upd' => $date,
    ], false, true, Db::INSERT_IGNORE);
};

$database->execute('START TRANSACTION');

try {
    $suffix = bin2hex(random_bytes(8));
    $fullHash = hash('sha256', 'full-' . $suffix);
    $partialHash = hash('sha256', 'partial-' . $suffix);
    $fullPayload = [
        'withdrawal_scope' => 'full',
        'products' => [
            ['id_product' => 1, 'quantity' => 1, 'native_return_supported' => true],
            ['id_product' => 2, 'quantity' => 1, 'native_return_supported' => false],
        ],
        'id_shop' => 1,
    ];
    $partialPayload = [
        'withdrawal_scope' => 'partial',
        'products' => [
            ['id_product' => 3, 'quantity' => 2, 'native_return_supported' => true],
        ],
        'id_shop' => 2,
    ];

    $assert($insert($fullPayload, $fullHash, 1, 'full'), 'full customer snapshot was not inserted');
    $fullId = (int) $database->Insert_ID();
    $full = $database->getRow(
        'SELECT * FROM `' . bqSQL($table) . '` WHERE `id_cci_withdrawal_submission` = ' . $fullId
    );
    $assert(is_array($full), 'full customer snapshot cannot be read');
    $assert($full['id_order_return'] === null, 'native RMA must remain nullable');
    $assert((int) $full['id_customer'] > 0, 'customer submission lost its customer identity');
    $assert((string) $full['processing_status'] === 'manual_review', 'digital item was not routed to manual review');
    $storedFullPayload = json_decode((string) $full['statement_payload'], true, 512, JSON_THROW_ON_ERROR);
    $assert($storedFullPayload === $fullPayload, 'full immutable snapshot changed during persistence');

    $insert($fullPayload, $fullHash, 1, 'full');
    $duplicateCount = (int) $database->getValue(
        'SELECT COUNT(*) FROM `' . bqSQL($table) . '` WHERE `submission_hash` = \'' . pSQL($fullHash) . '\''
    );
    $assert($duplicateCount === 1, 'the unique submission token did not prevent a duplicate/racing insert');

    $assert($insert($partialPayload, $partialHash, 2, 'partial'), 'partial guest snapshot was not inserted');
    $partialId = (int) $database->Insert_ID();
    $partial = $database->getRow(
        'SELECT * FROM `' . bqSQL($table) . '` WHERE `id_cci_withdrawal_submission` = ' . $partialId
    );
    $assert(is_array($partial), 'partial guest snapshot cannot be read');
    $assert((int) $partial['id_customer'] === 0, 'guest submission unexpectedly requires a customer account');
    $storedPartialPayload = json_decode((string) $partial['statement_payload'], true, 512, JSON_THROW_ON_ERROR);
    $assert($storedPartialPayload === $partialPayload, 'partial immutable snapshot changed during persistence');
    $assert((int) $full['id_shop'] !== (int) $partial['id_shop'], 'multistore snapshots were not isolated');

    $assert($database->update('cci_withdrawal_submission', [
        'confirmation_status' => 'failed',
        'confirmation_attempts' => 1,
        'confirmation_last_error' => 'Runtime transport failure',
    ], 'id_cci_withdrawal_submission = ' . $partialId), 'email failure result was not recorded');
    $failure = $database->getRow(
        'SELECT `confirmation_status`, `confirmation_attempts`, `confirmation_last_error`'
        . ' FROM `' . bqSQL($table) . '` WHERE `id_cci_withdrawal_submission` = ' . $partialId
    );
    $assert(
        is_array($failure)
        && $failure['confirmation_status'] === 'failed'
        && (int) $failure['confirmation_attempts'] === 1
        && $failure['confirmation_last_error'] === 'Runtime transport failure',
        'stored email failure data is incomplete'
    );

    $database->execute('ROLLBACK');
    echo "CCI Withdrawal transactional runtime test passed.\n";
} catch (Throwable $exception) {
    $database->execute('ROLLBACK');
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
