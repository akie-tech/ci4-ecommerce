<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        return $this->renderView("admin/dashboard/index", $this->data);
    }
}
