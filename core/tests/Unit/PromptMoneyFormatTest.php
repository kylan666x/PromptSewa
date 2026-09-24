<?php

use App\Models\Prompt;

test('price labels render integer NPR without float math', function () {
    $prompt = new Prompt(['price_cents' => 29950, 'user_id' => 1]);

    expect($prompt->priceLabel())->toBe('Rs. 299');
});

test('zero price renders the free label', function () {
    $prompt = new Prompt(['price_cents' => 0, 'user_id' => 1]);

    expect($prompt->priceLabel())->toBe('Free');
});

test('large prices group thousands without decimals', function () {
    $prompt = new Prompt(['price_cents' => 1234567, 'user_id' => 1]);

    expect($prompt->priceLabel())->toBe('Rs. 12,345');
});
