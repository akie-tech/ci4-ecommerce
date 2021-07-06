<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\Category;
use App\Models\CategoryModel;

class Categories extends BaseController
{
    protected $categoryModel;
    protected $perPage = 10;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
        $this->data['currentAdminMenu'] = 'catalogue';
        $this->data['currentAdminSubMenu'] = 'category';
    }
    public function index($categoryId = null)
    {
        $this->getCategories();
        $this->getParentOptions($categoryId);
        if ($categoryId) {
            $category = $this->categoryModel->find($categoryId);
            if (!$category) {
                $this->session->setFlashdata('errors', 'Invalid Category');
                return redirect()->to('/admin/categories');
            }
            $this->data['category'] = $category;
        }

        return view('admin/categories/index', $this->data);
    }

    public function update($id)
    {
        $params = [
            'id' => $id,
            'name' => $this->request->getVar('name'),
            'parent_id' => $this->request->getVar('parent_id'),
        ];
        $this->data['selectedParentId'] = $params['parent_id'];
        if ($this->categoryModel->save($params)) {
            $this->session->setFlashdata('success', 'Category has been saved');
            return redirect()->to('/admin/categories');
        } else {
            $this->getCategories();
            $this->getParentOptions();
            $this->data['errors'] = $this->categoryModel->errors();
            return view('admin/categories/index', $this->data);
        }
    }
    public function store()
    {
        $params = [
            'name' => $this->request->getVar('name'),
            'parent_id' => $this->request->getVar('parent_id'),
        ];
        $this->data['selectedParentId'] = $params['parent_id'];
        if ($this->categoryModel->save($params)) {
            $this->session->setFlashdata('success', 'Category has been saved');
            return redirect()->to('/admin/categories');
        } else {
            $this->getCategories();
            $this->getParentOptions();
            $this->data['errors'] = $this->categoryModel->errors();
            return view('admin/categories/index', $this->data);
        }
    }

    public function destroy($id)
    {
        // $this->getCategories ();
        $category = $this->categoryModel->find($id);
        if (!$category) {
            $this->session->setFlashdata('errors', 'Invalid Category');
            return redirect()->to('/admin/categories');
        } elseif ($this->categoryModel->delete($category->id)) {
            $this->session->setFlashdata('success', 'Category has been removed');
            return redirect()->to('/admin/categories');
        } else {
            $this->session->setFlashdata('errors', "Couldn`t delete the category");
            return redirect()->to('/admin/categories');
        }
    }




    private function getParentOptions($exceptCategoryId = null)
    {
        $this->data['parentOptions'] = $this->categoryModel->getParentOptions($exceptCategoryId);
    }
    private function getCategories()
    {
        $this->data['categories'] = $this->categoryModel->paginate($this->perPage, 'bootstrap');
        $this->data['pager'] = $this->categoryModel->pager;
    }
}
