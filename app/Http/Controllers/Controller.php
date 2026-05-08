<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Kitob Project API",
    version: "1.0.0",
    description: "API Documentation for Kitob Project Phone-based OTP Authentication"
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Local Development Server"
)]
abstract class Controller
{
    //
}
