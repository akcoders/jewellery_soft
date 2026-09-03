<?php

namespace App\Services;

use App\Models\OrderCategoryModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class OrderCategoryService
{
    private BaseConnection $db;
    private OrderCategoryModel $model;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->model = new OrderCategoryModel($this->db);
    }

    /** @return list<array<string,mixed>> */
    public function options(bool $includeInactiveId = false, int $inactiveId = 0): array
    {
        $builder = $this->db->table('order_categories')->select('id, name, code, is_active');
        if (! $includeInactiveId || $inactiveId <= 0) {
            $builder->where('is_active', 1);
        } else {
            $builder->groupStart()->where('is_active', 1)->orWhere('id', $inactiveId)->groupEnd();
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /** @return array{id:int,name:string,code:string} */
    public function resolve(int $categoryId, string $newCategoryName = ''): array
    {
        if ($categoryId > 0) {
            $category = $this->db->table('order_categories')
                ->select('id, name, code')
                ->where('id', $categoryId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();
            if (! $category) {
                throw new RuntimeException('Selected jewellery category is not available.');
            }

            return $this->normalise($category);
        }

        $name = preg_replace('/\s+/', ' ', trim($newCategoryName)) ?: '';
        if ($name === '') {
            throw new RuntimeException('Please select a jewellery category or add a new one.');
        }
        if (mb_strlen($name) > 100) {
            throw new RuntimeException('Jewellery category must not exceed 100 characters.');
        }

        $existing = $this->db->table('order_categories')
            ->select('id, name, code')
            ->where('LOWER(name)', mb_strtolower($name))
            ->get()
            ->getRowArray();
        if ($existing) {
            $this->db->table('order_categories')->where('id', (int) $existing['id'])->update(['is_active' => 1]);
            return $this->normalise($existing);
        }

        $baseCode = (new OrderNumberService($this->db))->categoryCode($name);
        $code = $baseCode;
        $suffix = 2;
        while ($this->db->table('order_categories')->where('code', $code)->countAllResults() > 0) {
            $code = substr($baseCode, 0, max(1, 12 - strlen((string) $suffix))) . $suffix;
            $suffix++;
        }

        $this->model->insert(['name' => $name, 'code' => $code, 'is_active' => 1]);
        return ['id' => (int) $this->model->getInsertID(), 'name' => $name, 'code' => $code];
    }

    /** @param array<string,mixed> $row @return array{id:int,name:string,code:string} */
    private function normalise(array $row): array
    {
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'code' => (string) $row['code']];
    }
}
