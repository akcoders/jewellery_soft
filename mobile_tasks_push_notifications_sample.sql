-- Sample test data for ERP mobile task + push notifications
-- Change admin_user_id and external_user_id before running on production.

INSERT INTO `mobile_tasks` (
  `admin_user_id`, `title`, `note`, `scheduled_at`, `status`, `is_done`, `created_by`, `created_at`, `updated_at`
) VALUES
(1, 'Call customer for diamond approval', 'Order followup task from mobile sample data', DATE_ADD(NOW(), INTERVAL 2 HOUR), 'pending', 0, 1, NOW(), NOW()),
(1, 'Review gold purchase invoice', 'Purchase verification reminder', DATE_ADD(NOW(), INTERVAL 4 HOUR), 'pending', 0, 1, NOW(), NOW());

INSERT INTO `mobile_push_notifications` (
  `admin_user_id`, `external_user_id`, `type`, `reference_table`, `reference_id`, `title`, `message`, `payload_json`, `scheduled_at`, `status`, `done_flag`, `created_at`, `updated_at`
) VALUES
(1, 'demo@aabhushan.local', 'task', 'mobile_tasks', 1, 'Task Reminder', 'Call customer for diamond approval', '{"type":"task","task_id":1}', DATE_ADD(NOW(), INTERVAL 2 HOUR), 'pending', 0, NOW(), NOW()),
(1, 'demo@aabhushan.local', 'followup', 'order_followups', 1, 'Order Followup Reminder', 'Follow up pending for sample order', '{"type":"followup","order_id":1}', DATE_ADD(NOW(), INTERVAL 3 HOUR), 'pending', 0, NOW(), NOW());
