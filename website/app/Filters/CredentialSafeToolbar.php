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
        // not collect browser sessions or the credential delivery/change flows.
        if ($path === '' || $path === 'superadmin' || str_starts_with($path, 'superadmin/')
            || preg_match('#^api/(?:login|register|me|logout|profile/password|users(?:/[0-9]+/reset-password)?)$#', $path)) {
            return null;
        }
        return parent::after($request, $response, $arguments);
    }
}
