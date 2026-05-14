<?php

it('exposes the health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('redirects unauthenticated visitors to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
