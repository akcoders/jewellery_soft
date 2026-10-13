<?php

namespace App\Database\Migrations;

use App\Services\FinishedJewelleryService;
use CodeIgniter\Database\Migration;
use Throwable;

class BackfillCompletedOrderDesigns extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'order_items', 'order_receive_summaries', 'fg_items', 'design_masters'] as $table) {
            if (! $this->db->tableExists($table)) {
                return;
            }
        }

        $orders = $this->db->table('orders o')
            ->select('o.id')
            ->join('order_receive_summaries rs', 'rs.order_id = o.id', 'inner')
            ->join('order_items oi', 'oi.order_id = o.id', 'inner')
            ->where('o.status', 'Completed')
            ->groupStart()->where('oi.design_id', null)->orWhere('oi.design_id', 0)->groupEnd()
            ->groupBy('o.id')
            ->orderBy('o.id', 'ASC')
            ->get()->getResultArray();

        $service = new FinishedJewelleryService($this->db);
        foreach ($orders as $order) {
            try {
                $service->createForCompletedOrder((int) $order['id']);
            } catch (Throwable $e) {
                log_message('error', 'Completed design backfill failed for order {order}: {message}', [
                    'order' => (int) $order['id'],
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /** Generated finished-goods and design records are business history and are not deleted on rollback. */
    public function down(): void
    {
    }
}
