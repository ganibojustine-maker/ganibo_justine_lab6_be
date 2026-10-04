<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'controllers/ApiController.php';

/**
 * ProductController - protected CRUD
 * Every action requires a valid Bearer access token (JWT).
 */
class ProductController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->authenticate();              // <-- blocks every unauthenticated request
        $this->api->rate_limit();
        $this->call->model('ProductModel');
    }

    /** GET /api/products?search=&page=&per_page= */
    public function index()
    {
        $this->api->require_method('GET');
        $q = $this->api->get_query_params();

        $result = $this->ProductModel->paginate_products(
            (string) ($q['search'] ?? ''),
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 10)
        );

        $this->api->respond([
            'success' => true,
            'message' => 'Products retrieved',
            'data'    => $result['items'],
            'meta'    => $result['meta'],
            'stats'   => $this->ProductModel->stats(),
        ]);
    }

    /** GET /api/products/{id} */
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->ok($this->find_or_404($id));
    }

    /** POST /api/products */
    public function store()
    {
        $this->api->require_method('POST');
        $data = $this->validate($this->input());
        $id   = $this->ProductModel->create_product($data);
        $this->ok($this->ProductModel->find_product($id), 'Product created', 201);
    }

    /** PUT|PATCH /api/products/{id} */
    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->fail('Method Not Allowed', 405);
        }
        $existing = $this->find_or_404($id);

        // PATCH sends only changed fields: merge with the current record, then validate.
        $merged = array_merge($existing, array_intersect_key(
            $this->input(),
            array_flip(['product_name', 'description', 'price', 'quantity'])
        ));
        $data = $this->validate($merged);

        $this->ProductModel->update_product((int) $id, $data);
        $this->ok($this->ProductModel->find_product((int) $id), 'Product updated');
    }

    /** DELETE /api/products/{id} */
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->find_or_404($id);
        $this->ProductModel->delete_product((int) $id);
        $this->ok(['id' => (int) $id], 'Product deleted');
    }

    // ------------------------------------------------------------------

    /** UTF-8 safe length that works even when the mbstring extension is missing. */
    private function len(string $v): int
    {
        return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : (int) preg_match_all('/./us', $v);
    }

    private function find_or_404($id): array
    {
        if (!ctype_digit((string) $id)) {
            $this->fail('Product not found', 404);
        }
        $product = $this->ProductModel->find_product((int) $id);
        if (!$product) {
            $this->fail('Product not found', 404);
        }
        return $product;
    }

    private function validate(array $in): array
    {
        $name  = trim((string) ($in['product_name'] ?? ''));
        $desc  = isset($in['description']) ? trim((string) $in['description']) : '';
        $price = $in['price'] ?? null;
        $qty   = $in['quantity'] ?? null;
        $errors = [];

        if ($name === '' || $this->len($name) > 100) {
            $errors['product_name'] = 'Product name is required (max 100 characters).';
        }
        if ($this->len($desc) > 5000) {
            $errors['description'] = 'Description is too long (max 5000 characters).';
        }
        if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
            $errors['price'] = 'Price must be a number between 0 and 99,999,999.99.';
        }
        if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0 || (int) $qty > 2147483647) {
            $errors['quantity'] = 'Quantity must be a whole number (0 or more).';
        }
        if ($errors) {
            $this->fail('Validation failed', 422, $errors);
        }

        return [
            'product_name' => $name,
            'description'  => $desc === '' ? null : $desc,
            'price'        => round((float) $price, 2),
            'quantity'     => (int) $qty,
        ];
    }
}
