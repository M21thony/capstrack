<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * Laravel's form-forgery check, without the XSRF-TOKEN cookie it hands out
 * for JavaScript to read. No CapsTrack script reads it - every form and
 * fetch() sends the page's own token - and a cookie a script can read is one
 * a security scan rightly asks about.
 */
class PreventRequestForgery extends Middleware
{
    protected $addHttpCookie = false;
}
