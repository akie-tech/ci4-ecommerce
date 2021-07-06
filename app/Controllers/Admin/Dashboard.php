<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function __construct()
    {
        $this->data['currentAdminMenu'] = 'dashboard';
        $this->data['currentAdminSubMenu'] = 'dashboard';
    }
    public function index()
    {
        return view("admin/dashboard/index", $this->data);
    }
}
