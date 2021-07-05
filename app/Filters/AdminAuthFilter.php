<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Session\Session;
use Prophecy\Exception\Doubler\ReturnByReferenceException;
use IonAuth\Libraries\IonAuth;


class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();
        $auth = new IonAuth();
        if (!$auth->loggedIn()) {
            return redirect()->to('/auth/login');
        }

        $currentUser = $auth->user()->row();
        if (!$auth->inGroup($arguments, $currentUser->id)) {
            $session->setFlashdata('errors', 'Sorry, you didn`t have any permission to acces this page');
            return redirect()->to('/');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }
}
