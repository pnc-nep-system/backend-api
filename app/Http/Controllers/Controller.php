<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "NEP Programme API",
    version: "1.0.0",
    description: "API documentation for authentication and programme entry management"
)]
#[OA\SecurityScheme(
    securityScheme: "sessionAuth",
    type: "apiKey",
    in: "cookie",
    name: "laravel_session"
)]
#[OA\Server(
    url: "/api",
    description: "Local development server"
)]
class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}