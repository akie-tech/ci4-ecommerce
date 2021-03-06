<?php

namespace App\Entities;

use CodeIgniter\Entity;

class ProductImage extends Entity\Entity
{
    protected $dates = ['created_at', 'updated_at'];

    public function getSmall()
    {
        return base_url() . '/uploads/' . $this->attributes['small'];
    }

    public function getMedium()
    {
        return base_url() . '/uploads/' . $this->attributes['medium'];
    }

    public function getLarge()
    {
        return base_url() . '/uploads/' . $this->attributes['large'];
    }
}
