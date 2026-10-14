-- Read-only all-time employee point report.
-- Requires MySQL 8.0+ and the application's latest migrations.
-- Rates follow the requested rules; rating and duplicate-follow-up modifiers
-- are excluded. Missing image_path is scored as missing image evidence.
WITH
params AS (
    SELECT UTC_TIMESTAMP() + INTERVAL 330 MINUTE AS as_of
),
staff AS (
    SELECT au.id, au.name
    FROM admin_users au
    WHERE au.is_active = 1
      AND NOT EXISTS (
          SELECT 1
          FROM user_roles ur
          INNER JOIN roles r ON r.id = ur.role_id
          WHERE ur.user_id = au.id
            AND UPPER(COALESCE(NULLIF(r.role_code, ''), r.name))
                IN ('SUPER_ADMIN', 'ADMIN', 'OWNER')
      )
),
approval_requests AS (
    SELECT
        mar.requested_by,
        mar.status,
        mar.result_id,
        CAST(
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(mar.payload_json, '$.order_id')), ''),
                CAST(mar.subject_id AS CHAR)
            ) AS UNSIGNED
        ) AS order_id,
        CAST(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(mar.payload_json, '$.followup_taken_on')), '')
            AS DATETIME
        ) AS payload_submitted_at,
        mar.created_at,
        mar.reviewed_at
    FROM mobile_approval_requests mar
    WHERE mar.request_type = 'followup'
      AND mar.status IN ('pending', 'approved', 'rejected')
),
normalized_approval_requests AS (
    SELECT
        requested_by,
        status,
        result_id,
        order_id,
        COALESCE(payload_submitted_at, created_at) AS submitted_at,
        reviewed_at
    FROM approval_requests
),
pending_schedule_approvals AS (
    SELECT s.id AS schedule_id, MAX(ar.submitted_at) AS submitted_at
    FROM order_followup_schedules s
    INNER JOIN normalized_approval_requests ar
        ON ar.order_id = s.order_id
       AND ar.requested_by = s.assigned_to
       AND ar.status = 'pending'
       AND ar.submitted_at >= COALESCE(s.created_at, s.due_at)
    GROUP BY s.id
),
followup_schedule_base AS (
    SELECT
        s.id,
        s.assigned_to AS user_id,
        s.order_id,
        s.completed_followup_id,
        s.due_at,
        GREATEST(s.due_at, COALESCE(s.created_at, s.due_at)) AS effective_due_at,
        COALESCE(s.created_at, s.due_at) AS cycle_start_at,
        s.status,
        s.completed_at,
        COALESCE(cf.approval_status, 'approved') AS approval_status,
        COALESCE(cf.points_enabled, 1) AS points_enabled,
        CASE
            WHEN s.status = 'pending'
                THEN LEAST(p.as_of, COALESCE(psa.submitted_at, p.as_of))
            WHEN s.status LIKE 'completed%'
                 AND COALESCE(cf.approval_status, 'approved') NOT IN ('pending', 'rejected')
                THEN COALESCE(s.completed_at, s.updated_at)
            ELSE NULL
        END AS performance_end_at
    FROM order_followup_schedules s
    CROSS JOIN params p
    LEFT JOIN order_followups cf ON cf.id = s.completed_followup_id
    LEFT JOIN pending_schedule_approvals psa ON psa.schedule_id = s.id
    WHERE s.status = 'pending'
       OR s.status LIKE 'completed%'
),
followup_pause_seconds AS (
    SELECT
        sb.id AS schedule_id,
        SUM(
            GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    GREATEST(sb.effective_due_at, ar.submitted_at),
                    LEAST(sb.performance_end_at, ar.reviewed_at)
                )
            )
        ) AS paused_seconds
    FROM followup_schedule_base sb
    INNER JOIN normalized_approval_requests ar
        ON ar.order_id = sb.order_id
       AND ar.requested_by = sb.user_id
       AND ar.status = 'rejected'
       AND ar.reviewed_at IS NOT NULL
       AND ar.submitted_at >= sb.cycle_start_at
    WHERE sb.performance_end_at IS NOT NULL
    GROUP BY sb.id
),
followup_approval_credit AS (
    SELECT
        sb.id AS schedule_id,
        SUM(
            GREATEST(
                0,
                TIMESTAMPDIFF(SECOND, ar.submitted_at, ar.reviewed_at) DIV 86400
            )
        ) AS credited_days
    FROM followup_schedule_base sb
    INNER JOIN normalized_approval_requests ar
        ON ar.order_id = sb.order_id
       AND ar.requested_by = sb.user_id
       AND ar.status = 'approved'
       AND ar.result_id = sb.completed_followup_id
       AND ar.reviewed_at IS NOT NULL
       AND sb.due_at >= ar.submitted_at
    WHERE sb.status LIKE 'completed%'
      AND sb.approval_status = 'approved'
      AND sb.points_enabled <> 0
    GROUP BY sb.id
),
followup_schedule_points AS (
    SELECT
        delay.user_id,
        delay.status,
        delay.effective_due_at,
        delay.performance_end_at,
        delay.active_late_seconds,
        delay.late_days,
        CASE
            WHEN delay.late_days > 0
             AND (delay.status = 'pending' OR delay.active_late_seconds >= 86400)
                THEN 1
            ELSE 0
        END AS pending_or_24h_count,
        CASE
            WHEN delay.status <> 'pending'
             AND delay.late_days > 0
             AND delay.active_late_seconds < 86400
                THEN 1
            ELSE 0
        END AS under_24h_count,
        delay.approval_credit_days
    FROM (
        SELECT
            sb.user_id,
            sb.status,
            sb.effective_due_at,
            sb.performance_end_at,
            CASE
                WHEN sb.performance_end_at IS NULL
                     OR sb.performance_end_at <= sb.effective_due_at
                     OR (
                         sb.status LIKE 'completed%'
                         AND sb.approval_status IN ('pending', 'rejected')
                     )
                    THEN 0
                ELSE GREATEST(
                    0,
                    TIMESTAMPDIFF(SECOND, sb.effective_due_at, sb.performance_end_at)
                    - COALESCE(fps.paused_seconds, 0)
                )
            END AS active_late_seconds,
            CASE
                WHEN sb.performance_end_at IS NULL OR sb.performance_end_at <= sb.effective_due_at
                    THEN 0
                WHEN sb.status LIKE 'completed%'
                     AND sb.approval_status IN ('pending', 'rejected')
                    THEN 0
                ELSE GREATEST(
                    1,
                    FLOOR(
                        GREATEST(
                            0,
                            TIMESTAMPDIFF(SECOND, sb.effective_due_at, sb.performance_end_at)
                            - COALESCE(fps.paused_seconds, 0)
                        ) / 86400
                    )
                )
            END AS late_days,
            COALESCE(fac.credited_days, 0) AS approval_credit_days
        FROM followup_schedule_base sb
        LEFT JOIN followup_pause_seconds fps ON fps.schedule_id = sb.id
        LEFT JOIN followup_approval_credit fac ON fac.schedule_id = sb.id
    ) delay
),
issue_rows AS (
    SELECT
        h.created_by AS user_id,
        COALESCE(NULLIF(TRIM(h.voucher_no), ''), CONCAT('gold:', h.id)) AS dedupe_key
    FROM gold_inventory_issue_headers h
    WHERE h.created_by IS NOT NULL

    UNION ALL

    SELECT
        h.created_by,
        COALESCE(NULLIF(TRIM(h.voucher_no), ''), CONCAT('diamond:', h.id))
    FROM issue_headers h
    WHERE h.created_by IS NOT NULL

    UNION ALL

    SELECT
        h.created_by,
        COALESCE(NULLIF(TRIM(h.voucher_no), ''), CONCAT('stone:', h.id))
    FROM stone_inventory_issue_headers h
    WHERE h.created_by IS NOT NULL
      AND h.receive_movement_id IS NULL
),
deduplicated_issues AS (
    SELECT user_id, dedupe_key
    FROM issue_rows
    GROUP BY user_id, dedupe_key
),
task_calculations AS (
    SELECT
        t.admin_user_id AS user_id,
        t.scheduled_at,
        CASE
            WHEN t.is_done = 1 OR LOWER(t.status) LIKE 'completed%' THEN 1
            ELSE 0
        END AS is_completed,
        CASE
            WHEN t.is_done = 1 OR LOWER(t.status) LIKE 'completed%'
                THEN COALESCE(t.completed_at, t.updated_at, t.scheduled_at)
            ELSE p.as_of
        END AS evaluated_at
    FROM mobile_tasks t
    CROSS JOIN params p
    WHERE t.counts_for_performance = 1
      AND LOWER(t.status) <> 'cancelled'
      AND (
          t.reference_type IS NULL
          OR (
              LEFT(t.reference_type, 10) <> 'inventory_'
              AND t.reference_type <> 'order_followup'
          )
      )
),
event_points AS (
    SELECT o.created_by AS user_id, 'orders' AS category, 0.25 AS points, 1 AS event_count
    FROM orders o
    WHERE o.created_by IS NOT NULL

    UNION ALL

    SELECT f.followup_taken_by, 'approved_followups', 2.00, 1
    FROM order_followups f
    WHERE f.followup_taken_by IS NOT NULL
      AND COALESCE(f.approval_status, 'approved') = 'approved'
      AND COALESCE(f.points_enabled, 1) <> 0

    UNION ALL

    SELECT f.followup_taken_by, 'missing_followup_images', -0.50, 1
    FROM order_followups f
    WHERE f.followup_taken_by IS NOT NULL
      AND COALESCE(f.approval_status, 'approved') = 'approved'
      AND COALESCE(f.points_enabled, 1) <> 0
      AND NULLIF(TRIM(f.image_path), '') IS NULL

    UNION ALL

    SELECT di.user_id, 'issuements', 0.50, 1
    FROM deduplicated_issues di

    UNION ALL

    SELECT h.created_by, 'purchases', 0.50, 1
    FROM gold_inventory_purchase_headers h
    WHERE h.created_by IS NOT NULL

    UNION ALL

    SELECT h.created_by, 'purchases', 0.50, 1
    FROM purchase_headers h
    WHERE h.created_by IS NOT NULL

    UNION ALL

    SELECT h.created_by, 'purchases', 0.50, 1
    FROM stone_inventory_purchase_headers h
    WHERE h.created_by IS NOT NULL

    UNION ALL

    SELECT
        tc.user_id,
        'tasks',
        CASE
            WHEN tc.is_completed = 1 AND tc.evaluated_at <= tc.scheduled_at THEN 2.00
            WHEN tc.evaluated_at > tc.scheduled_at THEN
                -2.00 * GREATEST(
                    1,
                    CEILING(TIMESTAMPDIFF(SECOND, tc.scheduled_at, tc.evaluated_at) / 86400.0)
                )
            ELSE 0.00
        END AS points,
        CASE
            WHEN tc.is_completed = 1 AND tc.evaluated_at <= tc.scheduled_at THEN 1
            WHEN tc.evaluated_at > tc.scheduled_at THEN
                GREATEST(
                    1,
                    CEILING(TIMESTAMPDIFF(SECOND, tc.scheduled_at, tc.evaluated_at) / 86400.0)
                )
            ELSE 0
        END AS event_count
    FROM task_calculations tc

    UNION ALL

    SELECT fsp.user_id, 'followup_delay', -2.00 * fsp.late_days, fsp.late_days
    FROM followup_schedule_points fsp
    WHERE fsp.late_days > 0

    UNION ALL

    SELECT fsp.user_id, 'followup_pending_or_24h', 0.00, fsp.pending_or_24h_count
    FROM followup_schedule_points fsp
    WHERE fsp.pending_or_24h_count > 0

    UNION ALL

    SELECT fsp.user_id, 'followup_under_24h', 0.00, fsp.under_24h_count
    FROM followup_schedule_points fsp
    WHERE fsp.under_24h_count > 0

    UNION ALL

    SELECT fsp.user_id, 'approval_delay_credit', 2.00 * fsp.approval_credit_days, fsp.approval_credit_days
    FROM followup_schedule_points fsp
    WHERE fsp.approval_credit_days > 0
),
totals AS (
    SELECT
        user_id,
        SUM(CASE WHEN category = 'orders' THEN event_count ELSE 0 END) AS order_count,
        SUM(CASE WHEN category = 'orders' THEN points ELSE 0 END) AS order_points,
        SUM(CASE WHEN category = 'approved_followups' THEN event_count ELSE 0 END) AS approved_followup_count,
        SUM(CASE WHEN category = 'approved_followups' THEN points ELSE 0 END) AS approved_followup_points,
        SUM(CASE WHEN category = 'missing_followup_images' THEN event_count ELSE 0 END) AS missing_followup_image_count,
        SUM(CASE WHEN category = 'missing_followup_images' THEN points ELSE 0 END) AS missing_followup_image_points,
        SUM(CASE WHEN category = 'followup_delay' THEN event_count ELSE 0 END) AS followup_penalty_day_units,
        SUM(CASE WHEN category = 'followup_delay' THEN points ELSE 0 END) AS followup_delay_points,
        SUM(CASE WHEN category = 'followup_pending_or_24h' THEN event_count ELSE 0 END) AS followup_pending_or_24h_count,
        SUM(CASE WHEN category = 'followup_under_24h' THEN event_count ELSE 0 END) AS followup_under_24h_count,
        SUM(CASE WHEN category = 'approval_delay_credit' THEN event_count ELSE 0 END) AS approval_credit_days,
        SUM(CASE WHEN category = 'approval_delay_credit' THEN points ELSE 0 END) AS approval_credit_points,
        SUM(CASE WHEN category = 'issuements' THEN event_count ELSE 0 END) AS issuement_count,
        SUM(CASE WHEN category = 'issuements' THEN points ELSE 0 END) AS issuement_points,
        SUM(CASE WHEN category = 'purchases' THEN event_count ELSE 0 END) AS purchase_count,
        SUM(CASE WHEN category = 'purchases' THEN points ELSE 0 END) AS purchase_points,
        SUM(CASE WHEN category = 'tasks' AND points > 0 THEN event_count ELSE 0 END) AS on_time_task_count,
        SUM(CASE WHEN category = 'tasks' AND points < 0 THEN event_count ELSE 0 END) AS task_late_days,
        SUM(CASE WHEN category = 'tasks' THEN points ELSE 0 END) AS task_points
    FROM event_points
    GROUP BY user_id
)
SELECT
    s.id AS employee_id,
    s.name AS employee_name,
    COALESCE(t.order_count, 0) AS order_count,
    COALESCE(t.order_points, 0.00) AS order_points,
    COALESCE(t.approved_followup_count, 0) AS approved_followup_count,
    COALESCE(t.approved_followup_points, 0.00) AS approved_followup_points,
    COALESCE(t.missing_followup_image_count, 0) AS missing_followup_image_count,
    COALESCE(t.missing_followup_image_points, 0.00) AS missing_followup_image_points,
    COALESCE(t.followup_penalty_day_units, 0) AS followup_penalty_day_units,
    COALESCE(t.followup_delay_points, 0.00) AS followup_delay_points,
    COALESCE(t.followup_pending_or_24h_count, 0) AS followup_pending_or_24h_count,
    COALESCE(t.followup_under_24h_count, 0) AS followup_under_24h_count,
    COALESCE(t.approval_credit_days, 0) AS approval_credit_days,
    COALESCE(t.approval_credit_points, 0.00) AS approval_credit_points,
    COALESCE(t.issuement_count, 0) AS issuement_count,
    COALESCE(t.issuement_points, 0.00) AS issuement_points,
    COALESCE(t.purchase_count, 0) AS purchase_count,
    COALESCE(t.purchase_points, 0.00) AS purchase_points,
    COALESCE(t.on_time_task_count, 0) AS on_time_task_count,
    COALESCE(t.task_late_days, 0) AS task_late_days,
    COALESCE(t.task_points, 0.00) AS task_points,
    ROUND(
        COALESCE(t.order_points, 0)
        + COALESCE(t.approved_followup_points, 0)
        + COALESCE(t.missing_followup_image_points, 0)
        + COALESCE(t.followup_delay_points, 0)
        + COALESCE(t.approval_credit_points, 0)
        + COALESCE(t.issuement_points, 0)
        + COALESCE(t.purchase_points, 0)
        + COALESCE(t.task_points, 0),
        2
    ) AS net_points,
    GREATEST(
        0,
        ROUND(
            100
            + COALESCE(t.order_points, 0)
            + COALESCE(t.approved_followup_points, 0)
            + COALESCE(t.missing_followup_image_points, 0)
            + COALESCE(t.followup_delay_points, 0)
            + COALESCE(t.approval_credit_points, 0)
            + COALESCE(t.issuement_points, 0)
            + COALESCE(t.purchase_points, 0)
            + COALESCE(t.task_points, 0),
            2
        )
    ) AS score_with_100_baseline
FROM staff s
LEFT JOIN totals t ON t.user_id = s.id
ORDER BY net_points DESC, s.name ASC;
