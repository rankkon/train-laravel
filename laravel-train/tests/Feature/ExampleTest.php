<?php

test('the home page redirects guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirectToRoute('login');
});
