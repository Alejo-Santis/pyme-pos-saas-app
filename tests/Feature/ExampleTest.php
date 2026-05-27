<?php

test('tenant login page returns a successful response', function () {
    $response = $this->tenantGet('/login');

    $response->assertStatus(200);
});
