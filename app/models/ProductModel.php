<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primary_key = 'id';

    /** Columns returned to the client */
    private $columns = 'id, product_name, description, price, quantity, created_at';

    public function paginate_products(string $search = '', int $page = 1, int $per_page = 10): array
    {
        $page     = max(1, $page);
        $per_page = min(100, max(1, $per_page));
        $offset   = ($page - 1) * $per_page;

        $count_q = $this->db->table($this->table);
        if ($search !== '') {
            $count_q->like('product_name', "%{$search}%");
        }
        $total = (int) $count_q->select_count('id', 'total')->get()['total'];

        $list_q = $this->db->table($this->table)->select($this->columns);
        if ($search !== '') {
            $list_q->like('product_name', "%{$search}%");
        }
        $rows = $list_q->order_by('id', 'DESC')->limit($per_page, $offset)->get_all();

        return [
            'items' => array_map([$this, 'cast'], $rows ?: []),
            'meta'  => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $per_page,
                'last_page' => max(1, (int) ceil($total / $per_page)),
            ],
        ];
    }

    public function find_product(int $id)
    {
        $row = $this->db->table($this->table)->select($this->columns)->where('id', $id)->get();
        return $row ? $this->cast($row) : null;
    }

    public function create_product(array $d): int
    {
        $this->db->table($this->table)->insert([
            'product_name' => $d['product_name'],
            'description'  => $d['description'],
            'price'        => $d['price'],
            'quantity'     => $d['quantity'],
        ]);
        return (int) $this->db->last_id();
    }

    public function update_product(int $id, array $d): bool
    {
        $this->db->table($this->table)->where('id', $id)->update([
            'product_name' => $d['product_name'],
            'description'  => $d['description'],
            'price'        => $d['price'],
            'quantity'     => $d['quantity'],
        ]);
        return true;
    }

    public function delete_product(int $id): bool
    {
        $this->db->table($this->table)->where('id', $id)->delete();
        return true;
    }

    public function stats(): array
    {
        $r = $this->db->raw(
            "SELECT COUNT(*) AS total, COALESCE(SUM(quantity),0) AS units,
                    COALESCE(SUM(price*quantity),0) AS value,
                    COALESCE(SUM(quantity <= 5),0) AS low_stock
             FROM {$this->table}"
        )->fetch(PDO::FETCH_ASSOC);
        return [
            'total_products' => (int) $r['total'],
            'total_units'    => (int) $r['units'],
            'inventory_value'=> round((float) $r['value'], 2),
            'low_stock'      => (int) $r['low_stock'],
        ];
    }

    private function cast(array $row): array
    {
        $row['id']       = (int) $row['id'];
        $row['price']    = (float) $row['price'];
        $row['quantity'] = (int) $row['quantity'];
        return $row;
    }
}
