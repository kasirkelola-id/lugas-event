<?php

namespace App\Filters;

use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CredentialSafeToolbar extends DebugToolbar
{
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $path = ltrim($request->getUri()->getPath(), '/');
        if (str_starts_with($path, 'index.php/')) {
            $path = substr($path, 10);
        }
        // CI's toolbar persists POST, headers, session and view variables. Do
        // not collect browser sessions or any bearer API request/response.
        if ($path === '' || $path === 'superadmin' || str_starts_with($path, 'superadmin/')
            || $path === 'api' || str_starts_with($path, 'api/')) {
            return null;
        }
        return parent::after($request, $response, $arguments);
    }
}
