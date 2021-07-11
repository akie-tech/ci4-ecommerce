<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

use App\Models\ProductModel;
use App\Models\CategoryModel;
use App\Models\BrandModel;
use App\Models\AttributeModel;
use App\Models\AttributeOptionModel;
use App\Models\ProductAttributeValueModel;
use App\Models\ProductInventoryModel;

class Products extends BaseController
{
    protected $productModel, $categoryModel, $brandModel, $attributeModel, $attributeOptionModel, $productAttributeValueModel, $productInventoryModel;

    protected $perPage = 10;

    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->categoryModel = new CategoryModel();
        $this->brandModel = new BrandModel();
        $this->attributeModel = new AttributeModel();
        $this->attributeOptionModel = new AttributeOptionModel();
        $this->productAttributeValueModel = new ProductAttributeValueModel();
        $this->productInventoryModel = new ProductInventoryModel();


        $this->data['currentAdminMenu'] = 'catalogue';
        $this->data['currentAdminSubMenu'] = 'product';

        $categories = $this->getCategories();
        $brands = $this->getBrands();

        $this->data['categories'] = $categories;
        $this->data['brands'] = $brands;
        $this->data['types'] = $this->productModel::getProductTypesDropdown();
        $this->data['statuses'] = $this->productModel->getStatuses();
    }

    private function getCategories()
    {
        $categories = [];
        foreach ($this->categoryModel->findAll() as $category) {
            $categories[] = $category->toArray();
        }
        return $categories;
    }

    private function getBrands()
    {
        $brands = [
            '' => '-- Set Brand --'
        ];

        foreach ($this->brandModel->findAll() as $brand) {
            $brands[$brand->id] = $brand->name;
        }
        return $brands;
    }

    private function getProducts()
    {
        $this->data['products'] = $this->productModel
            ->select('products.*', 'product_inventories.qty')
            ->join('product_inventories', 'products.id = product_inventories.product_id', 'left')
            ->paginate($this->perPage, 'bootstrap');

        $this->data['pager'] = $this->productModel->pager;
    }

    private function getConfigurableAttributes()
    {
        $configurableAttributes = $this->attributeModel->where('is_configurable', true)->findAll();
        return $configurableAttributes;
    }

    public function index()
    {
        $this->getProducts();
        return view('admin/products/index', $this->data);
    }

    public function create()
    {
        $this->data['configurableAttributes'] = $this->getConfigurableAttributes();
        return view('admin/products/form', $this->data);
    }

    public function edit($id)
    {
        $product = $this->productModel->find($id);

        if (!$product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $this->data['product'] = $product;
        $this->data['configurableAttributes'] = $this->getConfigurableAttributes();
        $this->data['categoryIds'] = array_column($product->categories, 'id');
        return view('admin/products/form', $this->data);
    }

    public function store()
    {
        $params = [
            'name' => $this->request->getVar('name'),
            'sku' => $this->request->getVar('sku'),
            'type' => $this->request->getVar('type'),
            'categories' => $this->request->getVar('categories'),
            'brand_id' => $this->request->getVar('brand_id'),
            'user_id' => $this->request->getVar('user_id'),
            'price' => $this->request->getVar('price'),
            'stock' => $this->request->getVar('stock'),
            'weight' => $this->request->getVar('weight'),
            'length' => $this->request->getVar('length'),
            'width' => $this->request->getVar('width'),
            'height' => $this->request->getVar('height'),
            'short_description' => $this->request->getVar('short_description'),
            'description' => $this->request->getVar('description'),
            'status' => $this->request->getVar('status')
        ];
        if ($params['type'] == $this->productModel::CONFIGURABLE) {
            $params['status'] = $this->productModel::DRAFT;
            $params['price'] = 0;
            $params['configurable'] = $this->request->getVar('configurable');
        }


        $this->db->transStart();
        $this->productModel->save($params);
        $product = $this->productModel->find($this->db->insertID());



        if ($product && $product->type == $this->productModel::SIMPLE) {
            $productInventoryTable = $this->db->table('product_inventories');
            $productInventoryTable->insert([
                'product_id' => $product->id,
                'qty' => $params['stock'],
            ]);

            $productCategoryTable = $this->db->table('product_categories');
            if (!empty($params['categories'])) {
                foreach ($params['categories'] as $key => $categoryId) {
                    $productCategoryTable->insert([
                        'product_id' => $product->id,
                        'category_id' => $categoryId
                    ]);
                }
            }
        }
        if ($product->type == $this->productModel::CONFIGURABLE) {
            $this->generateProductVariants($product, $params);
        }

        $this->db->transComplete();

        if ($product) {
            if ($product->type == $this->productModel::CONFIGURABLE) {
                return redirect()->to('/admin/products/' . $product->id . '/edit');
            }
            $this->session->setFlashdata('succes', 'Product has been saved.');
            return redirect()->to('/admin/products');
        } else {
            $this->data['configurableAttributes'] = $this->getConfigurableAttributes();
            $this->data['categoryIds'] = $params['categories'];
            $this->data['errors'] = $this->productModel->errors();
            return view('admin/products/create', $this->data);
        }
    }
    public function update($id)
    {
        $product = $this->productModel->find($id);

        if (!$product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $params = [
            'id' => $id,
            'name' => $this->request->getVar('name'),
            'sku' => $this->request->getVar('sku'),
            'type' => $this->request->getVar('type'),
            'categories' => $this->request->getVar('categories'),
            'brand_id' => $this->request->getVar('brand_id'),
            'user_id' => $this->currentUser->id,
            'price' => $this->request->getVar('price'),
            'stock' => $this->request->getVar('stock'),
            'weight' => $this->request->getVar('weight'),
            'length' => $this->request->getVar('length'),
            'width' => $this->request->getVar('width'),
            'height' => $this->request->getVar('height'),
            'short_description' => $this->request->getVar('short_description'),
            'description' => $this->request->getVar('description'),
            'status' => $this->request->getVar('status'),
        ];

        if ($product->type == $this->productModel::CONFIGURABLE) {
            $params['variants'] = $this->request->getVar('variants');
        }

        $this->db->transStart();
        $this->productModel->save($params);

        if ($product && $product->type == $this->productModel::SIMPLE) {
            $productInventoryTable = $this->db->table('product_inventories');
            $productInventoryTable->insert([
                'product_id' => $product->id,
                'qty' => $params['stock'],
            ]);

            $productCategoryTable = $this->db->table('product_categories');
            if (!empty($params['categories'])) {
                foreach ($params['categories'] as $key => $categoryId) {
                    $productCategoryTable->insert([
                        'product_id' => $product->id,
                        'category_id' => $categoryId,
                    ]);
                }
            }
        }

        if ($product && $product->type == $this->productModel::CONFIGURABLE) {
            $this->updateProductVariants($params);
        }

        $this->db->transComplete();

        if ($this->productModel->errors()) {
            $this->data['categoryIds'] = $params['categories'];
            $this->data['errors'] = $this->productModel->errors();
            return view('admin/products/form', $this->data);
        } else {
            $this->session->setFlashdata('success', 'Product has been saved.');
            return redirect()->to('/admin/products');
        }
    }

    private function updateProductVariants($params)
    {
        if ($params['variants']) {
            foreach ($params['variants'] as $variantParams) {
                $variantParams['status'] = $params['status'];
                $this->productModel->save($variantParams);

                $inventoryParams = [
                    'product_id' => $variantParams['id'],
                    'qty' => $variantParams['stock'],
                ];

                $this->updateOrCreateInventory($inventoryParams);
            }
        }
    }

    private function updateOrCreateInventory($params)
    {
        $existInventory = $this->productInventoryModel
            ->where('product_id', $params['product_id'])
            ->first();

        if ($existInventory) {
            $params['id'] = $existInventory->id;
        }

        $this->productInventoryModel->save($params);
    }



    private function generateProductVariants($product, $params)
    {
        $variantAttributes = !(empty($params['configurable'])) ? $params['configurable'] : [];
        $configurableAttributes = array_column($this->getConfigurableAttributes(), 'code');

        $variantAttributes = array_filter($variantAttributes, function ($value, $key) use ($configurableAttributes) {
            return in_array($key, $configurableAttributes);
        }, ARRAY_FILTER_USE_BOTH);

        $variants = $this->generateVariantsWithAttributeCombinations($variantAttributes);

        if ($variants) {
            foreach ($variants as $variant) {
                $variantParams = [
                    'parent_id' => $product->id,
                    'user_id' => $product->user_id,
                    'sku' => $product->sku . '-' . implode('-', array_values($variant)),
                    'type' => $this->productModel::SIMPLE,
                    'name' => $product->name . $this->convertVariantAttributesAsName($variant),
                    'price' => 0,
                    'status' => $this->productModel::DRAFT
                ];

                $this->productModel->save($variantParams);
                $newProductVariant = $this->productModel->find($this->db->insertID());

                $productCategoryTable = $this->db->table('product_categories');
                if (!empty($params['categories'])) {
                    foreach ($params['categories'] as $key => $categoryId) {
                        $productCategoryTable->insert([
                            'product_id' => $newProductVariant->id,
                            'category_id' => $categoryId
                        ]);
                    }
                }
                $this->saveProductAttributeValues($newProductVariant, $variant, $product->id);
            }
        }
    }
    private function saveProductAttributeValues($product, $variant, $parentProductID)
    {
        foreach (array_values($variant) as $attributeOptionID) {
            $attributeOption = $this->attributeOptionModel->find($attributeOptionID);
            $attributeValueParams = [
                'parent_product_id' => $parentProductID,
                'product_id' => $product->id,
                'attribute_id' => $attributeOption->attribute_id,
                'attribute_option_id' => $attributeOptionID,
                'text_value' => $attributeOption->name
            ];
            $this->productAttributeValueModel->save($attributeValueParams);
        }
    }
    private function convertVariantAttributesAsName($variant)
    {
        $variantName = '';

        foreach (array_keys($variant) as $key => $code) {
            $attributeOptionID = $variant[$code];
            $attributeOption = $this->attributeOptionModel->find($attributeOptionID);

            if ($attributeOption) {
                $variantName .= ' - ' . $attributeOption->name;
            }
        }

        return $variantName;
    }

    private function generateVariantsWithAttributeCombinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, array($property => $property_value));
                }
            }
            $result = $tmp;
        }
        return $result;
    }
}
