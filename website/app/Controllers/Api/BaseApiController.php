<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class BaseApiController extends ResourceController
{
    use ResponseTrait;

    protected function sendSuccess(string $message, $data = null, int $statusCode = 200)
    {
        $response = [
            'status'  => true,
            'message' => $message,
        ];
        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->respond($response, $statusCode);
    }

    protected function sendError(string $message, $errors = null, int $statusCode = 400)
    {
        $response = [
            'status'  => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->respond($response, $statusCode);
    }
}
