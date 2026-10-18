<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$label = static fn(string $type): string => match ($type) {
    'customer_create' => 'New Customer',
    'karigar_create' => 'New Karigar',
    'order_create' => 'New Order',
    'followup' => 'Order Follow-up',
    'delivery_challan' => 'Delivery Challan',
    default => 'Material Issuement',
};
$icon = static fn(string $type): string => match ($type) {
    'customer_create' => 'fe-users',
    'karigar_create' => 'fe-user-check',
    'order_create' => 'fe-shopping-bag',
    'followup' => 'fe-message-circle',
    'delivery_challan' => 'fe-file-text',
    default => 'fe-share-2',
};
$filterKey = static fn(string $type): string => in_array($type, ['customer_create', 'karigar_create', 'order_create', 'followup', 'delivery_challan'], true) ? $type : 'issuement';
$typeCounts = [];
foreach (($items ?? []) as $pendingItem) {
    $key = $filterKey((string) ($pendingItem['request_type'] ?? ''));
    $typeCounts[$key] = ($typeCounts[$key] ?? 0) + 1;
}
$chips = [
    'followup' => ['Follow-ups', 'fe-message-circle'],
    'issuement' => ['Issuements', 'fe-share-2'],
    'delivery_challan' => ['Challans', 'fe-file-text'],
    'customer_create' => ['Customers', 'fe-users'],
    'karigar_create' => ['Karigars', 'fe-user-check'],
    'order_create' => ['Orders', 'fe-shopping-bag'],
];
?>
<style>
.approval-desk{--ad-radius:18px;display:grid;gap:20px}
.approval-desk>.tab-content{padding-top:0}
.approval-desk .ad-eyebrow{color:var(--au-gold,#a87f17);font-size:10.5px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
/* Hero */
.ad-hero{background:radial-gradient(520px 240px at 95% -10%,rgba(243,217,139,.26),transparent 70%),radial-gradient(420px 260px at 0% 130%,rgba(239,93,106,.16),transparent 70%),linear-gradient(135deg,#1a150b 0%,#14121a 55%,#0d0f14 100%);border:1px solid rgba(217,180,80,.32);border-radius:22px;box-shadow:0 30px 60px -32px rgba(40,30,10,.6);color:#f4efe2;overflow:hidden;padding:28px 30px 0;position:relative}
.ad-hero:before{background:linear-gradient(90deg,transparent,rgba(243,217,139,.7),transparent);content:"";height:1px;left:10%;position:absolute;right:10%;top:0}
.ad-hero__top{align-items:flex-start;display:flex;flex-wrap:wrap;gap:20px;justify-content:space-between;position:relative;z-index:1}
.ad-hero .ad-eyebrow{color:#e9c96f}
.ad-hero h2{color:#f7f1e1!important;font-family:"Cormorant Garamond",Georgia,serif;font-size:clamp(28px,3vw,40px);font-weight:600;line-height:1.1;margin:10px 0 8px}
.ad-hero p{color:rgba(244,239,226,.68);font-size:14px;margin:0;max-width:560px}
.ad-hero__badge{align-items:center;background:rgba(255,255,255,.05);border:1px solid rgba(243,217,139,.35);border-radius:18px;display:flex;flex-direction:column;justify-content:center;min-width:120px;padding:14px 20px}
.ad-hero__badge strong{color:#f3d98b;font-family:"Cormorant Garamond",Georgia,serif;font-size:44px;font-variant-numeric:lining-nums;font-weight:600;line-height:1}
.ad-hero__badge span{color:rgba(244,239,226,.65);font-size:11px;font-weight:700;letter-spacing:.14em;margin-top:4px;text-transform:uppercase}
.ad-hero__facets{fill:none;height:260px;opacity:.13;pointer-events:none;position:absolute;right:120px;stroke:#f3d98b;stroke-width:.8;top:-30px;width:420px}
.ad-stats{border-top:1px solid rgba(255,255,255,.08);display:grid;grid-template-columns:repeat(5,minmax(0,1fr));margin:24px -30px 0;position:relative;z-index:1}
.ad-stat{padding:16px 22px}
.ad-stat+.ad-stat{border-left:1px solid rgba(255,255,255,.08)}
.ad-stat small{color:rgba(244,239,226,.58);display:block;font-size:11.5px}
.ad-stat strong{color:#f7f1e1;display:block;font-size:22px;font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:-.02em;margin-top:2px}
.ad-stat.is-ok strong{color:#5fe0a5}.ad-stat.is-bad strong{color:#ff8a94}
/* Tabs + toolbar */
.ad-bar{align-items:center;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between}
.ad-tabs{background:var(--au-surface,#fff);border:1px solid var(--au-border,#e6e1d6);border-radius:14px;box-shadow:var(--au-shadow);display:inline-flex;gap:4px;margin:0;padding:4px}
.ad-tabs .nav-link{white-space:nowrap;align-items:center;background:transparent!important;border:0!important;border-radius:10px!important;color:var(--au-muted,#6c6f79)!important;display:inline-flex;font-size:13.5px;font-weight:600;gap:8px;padding:9px 16px}
.ad-tabs .nav-link:hover{color:var(--au-text,#17171b)!important}
.ad-tabs .nav-link.active{background:var(--au-gold-soft,rgba(168,127,23,.1))!important;box-shadow:inset 0 0 0 1px var(--au-gold-line,rgba(168,127,23,.35));color:var(--au-gold,#a87f17)!important}
.ad-tabs .ad-count{background:var(--au-surface-3,#f2efe7);border-radius:99px;color:var(--au-text-2,#3a3c44);font-size:11px;font-weight:700;min-width:22px;padding:2px 7px;text-align:center}
.ad-tabs .nav-link.active .ad-count{background:var(--au-gold,#a87f17);color:var(--au-on-gold,#fff)}
.ad-search{align-items:center;background:var(--au-input,#fff);border:1px solid var(--au-border-strong,#ddd6c8);border-radius:12px;color:var(--au-muted,#6c6f79);display:flex;gap:8px;min-width:260px;padding:0 12px;transition:border-color .16s,box-shadow .16s}
.ad-search:focus-within{border-color:var(--au-gold,#a87f17);box-shadow:0 0 0 3px var(--au-gold-soft,rgba(168,127,23,.1))}
.ad-search input{background:transparent;border:0;color:var(--au-text,#17171b);flex:1;font-size:13px;min-height:40px;outline:0}
.ad-chips{display:flex;flex-wrap:wrap;gap:8px}
.ad-chip{align-items:center;background:var(--au-surface,#fff);border:1px solid var(--au-border-strong,#ddd6c8);border-radius:99px;color:var(--au-text-2,#3a3c44);cursor:pointer;display:inline-flex;font-size:12.5px;font-weight:600;gap:6px;padding:7px 13px;transition:all .16s}
.ad-chip:hover{border-color:var(--au-gold-line,rgba(168,127,23,.35));color:var(--au-text,#17171b)}
.ad-chip.is-on{background:var(--au-gold-soft,rgba(168,127,23,.1));border-color:var(--au-gold-line,rgba(168,127,23,.35));color:var(--au-gold,#a87f17)}
.ad-chip b{background:var(--au-surface-3,#f2efe7);border-radius:99px;color:var(--au-text-2,#3a3c44);font-size:10.5px;padding:1px 7px}
.ad-chip.is-on b{background:var(--au-gold,#a87f17);color:var(--au-on-gold,#fff)}
/* Status pills */
.status-pill{align-items:center;border-radius:999px;display:inline-flex;font-size:11px;font-weight:700;gap:5px;letter-spacing:.04em;padding:6px 11px;text-transform:uppercase;white-space:nowrap}
.status-pending{background:var(--au-amber-soft,#fff2c8);color:var(--au-amber,#b7790b);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--au-amber,#b7790b) 28%,transparent)}
.status-approved{background:var(--au-emerald-soft,#dcf7e8);color:var(--au-emerald,#12865a);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--au-emerald,#12865a) 28%,transparent)}
.status-rejected{background:var(--au-ruby-soft,#fde5e7);color:var(--au-ruby,#c4283a);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--au-ruby,#c4283a) 28%,transparent)}
/* Cards */
.ad-card{background:var(--au-surface,#fff);border:1px solid var(--au-border,#e6e1d6);border-radius:var(--ad-radius);box-shadow:var(--au-shadow);overflow:hidden}
.ad-card__head{align-items:center;border-bottom:1px solid var(--au-border,#e6e1d6);display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;padding:18px 22px;position:relative}
.ad-card__head:before{background:linear-gradient(180deg,var(--au-gold-hi,#c99a24),var(--au-gold-lo,#8a6810));border-radius:0 3px 3px 0;bottom:16px;content:"";left:0;position:absolute;top:16px;width:3px}
.ad-card__head h4{color:var(--au-text,#17171b);font-size:18px;font-weight:700;letter-spacing:-.015em;margin:4px 0 2px}
.ad-card__meta{color:var(--au-muted,#6c6f79);font-size:12.5px}
.ad-mark{align-items:center;background:linear-gradient(145deg,var(--au-gold-soft,rgba(168,127,23,.1)),transparent);border:1px solid var(--au-gold-line,rgba(168,127,23,.35));border-radius:14px;color:var(--au-gold,#a87f17);display:inline-flex;flex:0 0 46px;font-size:19px;height:46px;justify-content:center}
.ad-progress{align-items:center;color:var(--au-muted,#6c6f79);display:flex;font-size:12px;gap:10px}
.ad-progress__bar{background:var(--au-surface-3,#f2efe7);border-radius:99px;height:6px;overflow:hidden;width:120px}
.ad-progress__bar span{background:linear-gradient(90deg,var(--au-emerald,#12865a),#8fd16a);border-radius:inherit;display:block;height:100%}
/* Timeline */
.followup-timeline{padding:20px 22px 22px;position:relative}
.followup-timeline:before{background:linear-gradient(180deg,var(--au-border-strong,#ddd6c8),var(--au-border,#e6e1d6));bottom:34px;content:"";left:37px;position:absolute;top:34px;width:2px}
.followup-step{margin-bottom:14px;padding-left:46px;position:relative}
.followup-step:last-child{margin-bottom:0}
.followup-dot{align-items:center;background:var(--au-surface,#fff);border:2px solid var(--au-border-strong,#ddd6c8);border-radius:50%;color:var(--au-muted,#6c6f79);display:inline-flex;font-size:11px;height:32px;justify-content:center;left:0;position:absolute;top:14px;width:32px;z-index:1}
.followup-dot.approved{background:var(--au-emerald-soft,#dcf7e8);border-color:var(--au-emerald,#12865a);color:var(--au-emerald,#12865a)}
.followup-dot.pending{background:var(--au-amber-soft,#fff2c8);border-color:var(--au-amber,#b7790b);color:var(--au-amber,#b7790b);box-shadow:0 0 0 5px color-mix(in srgb,var(--au-amber,#b7790b) 12%,transparent)}
.followup-dot.rejected{background:var(--au-ruby-soft,#fde5e7);border-color:var(--au-ruby,#c4283a);color:var(--au-ruby,#c4283a)}
.followup-step-card{background:var(--au-surface,#fff);border:1px solid var(--au-border,#e6e1d6);border-radius:16px;padding:16px 18px;transition:border-color .16s,box-shadow .16s}
.followup-step.approved .followup-step-card,.followup-step.rejected .followup-step-card{background:var(--au-surface-2,#faf8f3)}
.followup-step.pending .followup-step-card{border-color:color-mix(in srgb,var(--au-amber,#b7790b) 40%,transparent);box-shadow:0 14px 30px -22px color-mix(in srgb,var(--au-amber,#b7790b) 70%,transparent)}
.followup-step.is-first .followup-step-card{border-color:var(--au-gold,#a87f17);box-shadow:0 0 0 3px var(--au-gold-soft,rgba(168,127,23,.1)),0 18px 34px -22px rgba(168,127,23,.7)}
.followup-step-top{align-items:flex-start;display:flex;gap:14px;justify-content:space-between}
.approval-section-label{color:var(--au-muted,#6c6f79);font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
.followup-stage{color:var(--au-text,#17171b);font-size:14.5px;font-weight:700}
.followup-description{color:var(--au-text-2,#3a3c44);font-size:14px;line-height:1.55;margin-top:4px;white-space:pre-wrap}
.followup-meta{align-items:center;color:var(--au-muted,#6c6f79);display:flex;flex-wrap:wrap;font-size:12px;gap:6px 12px;margin-top:10px}
.followup-meta i{margin-right:4px}
.followup-image{border:1px solid var(--au-border,#e6e1d6);border-radius:12px;height:76px;margin-top:10px;object-fit:cover;transition:transform .16s;width:92px}
.followup-image:hover{transform:scale(1.03)}
.review-first{align-items:center;background:linear-gradient(180deg,var(--au-gold-hi,#c99a24),var(--au-gold,#a87f17));border-radius:999px;color:var(--au-on-gold,#fff);display:inline-flex;font-size:10px;font-weight:800;gap:3px;letter-spacing:.06em;padding:4px 9px;text-transform:uppercase}
.followup-context{background:var(--au-surface,#fff);border:1px dashed var(--au-border-strong,#ddd6c8);border-radius:12px;color:var(--au-text-2,#3a3c44);margin-top:12px;padding:10px 13px}
.followup-context strong{color:var(--au-text,#17171b)}
/* Decision dock */
.ad-decision{align-items:flex-end;background:var(--au-surface-2,#faf8f3);border:1px solid var(--au-border,#e6e1d6);border-radius:14px;display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;padding:12px}
.ad-decision__note{flex:1 1 260px;min-width:0}
.ad-decision__note .form-label{color:var(--au-muted,#6c6f79)!important;font-size:10.5px!important;font-weight:700!important;letter-spacing:.1em;margin-bottom:5px;text-transform:uppercase}
.ad-decision__note .form-control{min-height:42px}
.ad-decision__actions{display:flex;flex-wrap:wrap;gap:8px}
.ad-decision__actions .btn{align-items:center;display:inline-flex;font-weight:650;gap:4px;min-height:42px;padding:8px 14px;white-space:nowrap}
.ad-decision__actions .btn-success{box-shadow:0 10px 22px -12px var(--au-emerald,#12865a)}
/* Request details */
.approval-material{background:var(--au-surface-2,#faf8f3);border:1px solid var(--au-border,#e6e1d6);border-radius:13px;height:100%;padding:12px 14px}
.approval-material small{color:var(--au-muted,#6c6f79)!important;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.approval-material strong{color:var(--au-text,#17171b);font-size:14px;margin-top:2px}
.approval-material .border-top{border-color:var(--au-border,#e6e1d6)!important}
.approval-photo{background:#16121a;border-radius:13px;max-height:300px;object-fit:contain;width:100%}
.ad-section-title{align-items:center;display:flex;gap:10px;margin:6px 0 -6px}
.ad-section-title:after{background:var(--au-border,#e6e1d6);content:"";flex:1;height:1px}
/* Empty */
.ad-empty{align-items:center;display:flex;flex-direction:column;gap:6px;padding:56px 20px;text-align:center}
.ad-empty__icon{align-items:center;background:var(--au-emerald-soft,#dcf7e8);border:1px solid color-mix(in srgb,var(--au-emerald,#12865a) 35%,transparent);border-radius:18px;color:var(--au-emerald,#12865a);display:inline-flex;font-size:24px;height:58px;justify-content:center;margin-bottom:8px;width:58px}
.ad-empty strong{color:var(--au-text,#17171b);font-size:16px}
.ad-empty span{color:var(--au-muted,#6c6f79);font-size:13px}
.ad-no-match{display:none}
/* History */
.approval-history-card .ad-card__head{border-bottom:0}
.approval-history-card .ad-card__body{padding:0 0 4px}
.approval-history-card .table-responsive{border-top:1px solid var(--au-border,#e6e1d6)}
.approval-view-modal .modal-content{border:0;border-radius:20px;overflow:hidden}
.approval-view-modal .modal-header{background:radial-gradient(420px 160px at 100% 0%,rgba(243,217,139,.22),transparent 70%),linear-gradient(135deg,#1a150b,#11141b);border-bottom:1px solid rgba(217,180,80,.3)!important;color:#f4efe2}
.approval-view-modal .modal-header small{color:#e9c96f;font-size:10.5px;font-weight:700;letter-spacing:.14em}
.approval-view-modal .modal-title{color:#f7f1e1!important;font-weight:700}
.approval-view-modal .btn-close{filter:invert(1) grayscale(1) brightness(1.8)}
.ad-reapprove{background:var(--au-surface-2,#faf8f3);border:1px solid var(--au-border,#e6e1d6);border-radius:14px;margin-top:14px;padding:12px}
@media(max-width:1199.98px){.ad-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.ad-stat:nth-child(4){border-left:0}.ad-stat:nth-child(n+4){border-top:1px solid rgba(255,255,255,.08)}}
@media(max-width:767.98px){.ad-hero{padding:22px 20px 0}.ad-hero__badge{min-width:0;padding:10px 16px}.ad-hero__badge strong{font-size:34px}.ad-stats{grid-template-columns:repeat(2,minmax(0,1fr));margin:20px -20px 0}.ad-stat{padding:13px 18px}.ad-stat:nth-child(odd){border-left:0}.ad-stat:nth-child(even){border-left:1px solid rgba(255,255,255,.08)}.ad-stat:nth-child(n+3){border-top:1px solid rgba(255,255,255,.08)}.ad-bar{align-items:stretch;flex-direction:column}.ad-tabs{display:flex}.ad-tabs .nav-item{flex:1}.ad-tabs .nav-link{justify-content:center;width:100%}.ad-search{min-width:0}.ad-card__head,.followup-timeline{padding:16px}.followup-timeline:before{left:31px}.followup-step{padding-left:40px}.followup-step-top{display:block}.followup-step-top>.text-end{align-items:center;display:flex;gap:10px;justify-content:space-between;margin-top:10px;text-align:left!important}.followup-image{height:150px;margin-top:0;width:100%}.ad-decision__actions{width:100%}.ad-decision__actions .btn{flex:1 1 100%;justify-content:center}}
</style>
<div class="approval-desk">
    <section class="ad-hero">
        <svg class="ad-hero__facets" viewBox="0 0 400 240" aria-hidden="true"><path d="M120 20h160l80 70-200 140L-40 90z"/><path d="M120 20l-30 70 110 140 110-140-30-70M-40 90h400"/><path d="M90 90l110-70 110 70"/></svg>
        <div class="ad-hero__top">
            <div>
                <span class="ad-eyebrow"><i class="fe fe-shield me-1"></i>Admin control desk</span>
                <h2>Mobile Approvals</h2>
                <p>Review follow-up, delivery challan, issuement and master requests with complete details.</p>
            </div>
            <div class="ad-hero__badge"><strong><?= (int) $pendingCount ?></strong><span>Pending</span></div>
        </div>
        <div class="ad-stats">
            <div class="ad-stat"><small>Orders awaiting follow-up review</small><strong><?= count($followupGroups ?? []) ?></strong></div>
            <div class="ad-stat"><small>Pending follow-up updates</small><strong><?= (int) ($pendingFollowupCount ?? 0) ?></strong></div>
            <div class="ad-stat"><small>Other mobile requests</small><strong><?= count($otherItems ?? []) ?></strong></div>
            <div class="ad-stat is-ok"><small>Approved (history)</small><strong><?= (int) ($approvedCount ?? 0) ?></strong></div>
            <div class="ad-stat is-bad"><small>Disapproved (history)</small><strong><?= (int) ($rejectedCount ?? 0) ?></strong></div>
        </div>
    </section>

    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success mb-0"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger mb-0"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

    <div class="ad-bar">
        <ul class="nav ad-tabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="pending-approvals-tab" data-bs-toggle="pill" data-bs-target="#pending-approvals-pane" type="button" role="tab" aria-controls="pending-approvals-pane" aria-selected="true"><i class="fe fe-clock"></i>Pending <span class="ad-count"><?= (int) $pendingCount ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="approval-history-tab" data-bs-toggle="pill" data-bs-target="#approval-history-pane" type="button" role="tab" aria-controls="approval-history-pane" aria-selected="false"><i class="fe fe-list"></i>Approval History <span class="ad-count"><?= count($historyItems ?? []) ?></span></button></li>
        </ul>
        <?php if (($items ?? []) !== []): ?>
            <label class="ad-search mb-0" data-ad-pending-only><i class="fe fe-search"></i><input type="search" placeholder="Search order, request, staff…" data-ad-search aria-label="Search pending approvals"></label>
        <?php endif; ?>
    </div>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="pending-approvals-pane" role="tabpanel" aria-labelledby="pending-approvals-tab" tabindex="0">
        <?php if ($items === []): ?>
            <div class="ad-card"><div class="ad-empty"><span class="ad-empty__icon"><i class="fe fe-check-circle"></i></span><strong>No pending approval requests</strong><span>Reviewed requests are available in Approval History.</span></div></div>
        <?php else: ?>
            <div class="ad-chips mb-3" role="group" aria-label="Filter by request type">
                <button type="button" class="ad-chip is-on" data-ad-filter="all">All <b><?= (int) $pendingCount ?></b></button>
                <?php foreach ($chips as $chipKey => $chip): if (empty($typeCounts[$chipKey])) continue; ?>
                    <button type="button" class="ad-chip" data-ad-filter="<?= esc($chipKey, 'attr') ?>"><i class="fe <?= esc($chip[1]) ?>"></i><?= esc($chip[0]) ?> <b><?= (int) $typeCounts[$chipKey] ?></b></button>
                <?php endforeach; ?>
            </div>
            <div class="ad-card ad-no-match mb-3" data-ad-no-match><div class="ad-empty"><span class="ad-empty__icon" style="background:var(--au-surface-2);color:var(--au-muted);border-color:var(--au-border)"><i class="fe fe-search"></i></span><strong>No requests match</strong><span>Try a different search or filter.</span></div></div>
        <?php endif; ?>

        <div class="d-grid gap-4">
        <?php foreach (($followupGroups ?? []) as $group): ?>
            <?php
            $pendingByFollowup = [];
            foreach (($group['requests'] ?? []) as $pendingRequest) {
                $pendingPayload = is_array($pendingRequest['payload'] ?? null) ? $pendingRequest['payload'] : [];
                $followupId = (string) ($pendingRequest['subject_table'] ?? '') === 'order_followups'
                    ? (int) ($pendingRequest['subject_id'] ?? 0)
                    : (int) ($pendingPayload['followup_id'] ?? 0);
                if ($followupId > 0) $pendingByFollowup[$followupId] = $pendingRequest;
            }
            $chain = is_array($group['chain'] ?? null) ? $group['chain'] : [];
            $reviewedInChain = count(array_filter($chain, static fn($f): bool => is_array($f) && in_array(strtolower((string) ($f['approval_status'] ?? 'approved')), ['approved', 'rejected'], true)));
            $searchText = strtolower(implode(' ', array_merge(
                [(string) ($group['order_no'] ?? '')],
                array_map(static fn($f): string => is_array($f) ? ($f['stage'] ?? '') . ' ' . ($f['description'] ?? '') . ' ' . ($f['followup_taken_by_name'] ?? '') : '', $chain)
            )));
            ?>
            <section class="ad-card followup-group" data-ad-item data-ad-type="followup" data-ad-text="<?= esc($searchText, 'attr') ?>">
                <div class="ad-card__head">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ad-mark"><i class="fe fe-message-circle"></i></span>
                        <div>
                            <span class="ad-eyebrow">Follow-up approval chain</span>
                            <h4><?= esc((string) ($group['order_no'] ?? 'Order')) ?></h4>
                            <div class="ad-card__meta"><?= count($chain) ?> total update<?= count($chain) === 1 ? '' : 's' ?> · <?= count($group['requests'] ?? []) ?> waiting for review</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <?php if (count($chain) > 0): ?><div class="ad-progress" title="Reviewed updates in this chain"><div class="ad-progress__bar"><span style="width:<?= (int) round($reviewedInChain / max(1, count($chain)) * 100) ?>%"></span></div><?= (int) $reviewedInChain ?>/<?= count($chain) ?> reviewed</div><?php endif; ?>
                        <?php if ((int) ($group['order_id'] ?? 0) > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/orders/' . (int) $group['order_id']) ?>"><i class="fe fe-external-link me-1"></i>Open order</a><?php endif; ?>
                    </div>
                </div>
                <div class="followup-timeline">
                    <?php foreach ($chain as $position => $followup): ?>
                        <?php
                        $followupStatus = strtolower((string) ($followup['approval_status'] ?? 'approved'));
                        $followupStatus = in_array($followupStatus, ['pending', 'approved', 'rejected'], true) ? $followupStatus : 'approved';
                        $pendingRequest = $pendingByFollowup[(int) ($followup['id'] ?? 0)] ?? null;
                        $isOldestPending = is_array($pendingRequest) && ! empty($pendingRequest['followup_is_oldest_pending']);
                        $imageUrl = (string) (($followup['image_url'] ?? '') ?: (is_array($pendingRequest) ? ($pendingRequest['attachment_url'] ?? '') : ''));
                        $dotIcon = $followupStatus === 'approved' ? 'fe-check' : ($followupStatus === 'rejected' ? 'fe-x' : 'fe-clock');
                        ?>
                        <article class="followup-step <?= esc($followupStatus) ?><?= $isOldestPending ? ' is-first' : '' ?>">
                            <span class="followup-dot <?= esc($followupStatus) ?>" aria-hidden="true"><i class="fe <?= $dotIcon ?>"></i></span>
                            <div class="followup-step-card">
                                <div class="followup-step-top">
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><span class="approval-section-label">Update <?= (int) $position + 1 ?></span><?php if ($isOldestPending): ?><span class="review-first"><i class="fe fe-arrow-right"></i>Review first</span><?php endif; ?></div>
                                        <div class="followup-stage"><?= esc((string) (($followup['stage'] ?? '') ?: 'Follow-up')) ?></div>
                                        <div class="followup-description"><?= esc((string) (($followup['description'] ?? '') ?: 'No update text recorded.')) ?></div>
                                        <div class="followup-meta"><span><i class="fe fe-calendar"></i><?= ! empty($followup['followup_taken_on']) ? esc(date('d M Y, h:i A', strtotime((string) $followup['followup_taken_on']))) : '-' ?></span><span><i class="fe fe-user"></i><?= esc((string) (($followup['followup_taken_by_name'] ?? '') ?: 'Staff')) ?></span><?php if (! empty($followup['next_followup_date'])): ?><span><i class="fe fe-corner-down-right"></i>Next: <?= esc(date('d M Y, h:i A', strtotime((string) $followup['next_followup_date']))) ?></span><?php endif; ?></div>
                                    </div>
                                    <div class="text-end"><span class="status-pill status-<?= esc($followupStatus) ?>"><i class="fe <?= $dotIcon ?>"></i><?= esc($followupStatus === 'rejected' ? 'disapproved' : $followupStatus) ?></span><?php if ($imageUrl !== ''): ?><a href="<?= esc($imageUrl, 'attr') ?>" target="_blank" rel="noopener" class="d-block"><img class="followup-image" src="<?= esc($imageUrl, 'attr') ?>" alt="Evidence for update <?= (int) $position + 1 ?>" loading="lazy"></a><?php endif; ?></div>
                                </div>
                                <?php if ($followupStatus !== 'pending' && (! empty($followup['reviewed_by_name']) || ! empty($followup['review_note']))): ?><div class="followup-context small"><strong><?= $followupStatus === 'rejected' ? 'Disapproved' : 'Approved' ?><?= ! empty($followup['reviewed_by_name']) ? ' by ' . esc((string) $followup['reviewed_by_name']) : '' ?></strong><?= ! empty($followup['review_note']) ? '<div class="mt-1">' . esc((string) $followup['review_note']) . '</div>' : '' ?></div><?php endif; ?>
                                <?php if (is_array($pendingRequest)): ?>
                                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $pendingRequest['id'] . '/review') ?>" class="ad-decision">
                                        <?= csrf_field() ?>
                                        <div class="ad-decision__note"><label class="form-label">Decision note</label><input class="form-control" name="note" maxlength="500" placeholder="Optional approval or disapproval note"></div>
                                        <div class="ad-decision__actions"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Disapprove this follow-up? It will lose 2 points.')"><i class="fe fe-x me-1"></i>Disapprove · −2</button><button class="btn btn-outline-secondary" name="decision" value="approve_no_point" onclick="return confirm('Approve this follow-up without performance points?')"><i class="fe fe-minus-circle me-1"></i>No point · 0</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve this follow-up? It will earn 2 points.')"><i class="fe fe-check me-1"></i>Approve · +2</button></div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (($otherItems ?? []) !== []): ?><div class="ad-section-title" data-ad-other-title><span class="approval-section-label">Other mobile requests</span></div><?php endif; ?>
        <?php foreach (($otherItems ?? []) as $row): $payload = is_array($row['payload'] ?? null) ? $row['payload'] : []; $type = (string) ($row['request_type'] ?? ''); $status = (string) ($row['status'] ?? 'pending'); ?>
            <?php $searchText = strtolower('#' . (int) $row['id'] . ' ' . $label($type) . ' ' . ($row['summary'] ?? '') . ' ' . ($row['requested_by_name'] ?? '')); ?>
            <div class="ad-card approval-card <?= esc($status) ?>" data-ad-item data-ad-type="<?= esc($filterKey($type), 'attr') ?>" data-ad-text="<?= esc($searchText, 'attr') ?>">
                <div class="ad-card__head">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <span class="ad-mark"><i class="fe <?= esc($icon($type)) ?>"></i></span>
                        <div><span class="ad-eyebrow">Request #<?= (int) $row['id'] ?> · <?= esc(date('d M Y, h:i A', strtotime((string) $row['created_at']))) ?></span><h4><?= esc($label($type)) ?></h4><div class="ad-card__meta"><?= esc($row['summary'] ?? '') ?></div></div>
                    </div>
                    <div class="text-end"><span class="status-pill status-<?= esc($status) ?>"><?= esc($status === 'rejected' ? 'disapproved' : $status) ?></span><small class="d-block text-muted mt-2"><i class="fe fe-user me-1"></i>by <?= esc($row['requested_by_name'] ?? '-') ?></small></div>
                </div>
                <div class="card-body p-4">
                    <?= view('admin/mobile_approvals/_request_details', ['row' => $row]) ?>
                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $row['id'] . '/review') ?>" class="ad-decision">
                        <?= csrf_field() ?>
                        <div class="ad-decision__note"><label class="form-label">Admin note</label><input class="form-control" name="note" maxlength="500" placeholder="Optional approval / rejection note"></div>
                        <div class="ad-decision__actions"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Reject this request?')"><i class="fe fe-x me-1"></i>Reject</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve and process this request?')"><i class="fe fe-check me-1"></i>Approve & Process</button></div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="approval-history-pane" role="tabpanel" aria-labelledby="approval-history-tab" tabindex="0">
        <div class="ad-card approval-history-card">
            <div class="ad-card__head">
                <div><span class="ad-eyebrow">Audit trail</span><h4>Approval History</h4><div class="ad-card__meta">Search reviewed requests and open View for complete details.</div></div>
                <div class="d-flex gap-2"><span class="status-pill status-approved"><i class="fe fe-check"></i><?= (int) ($approvedCount ?? 0) ?> approved</span><span class="status-pill status-rejected"><i class="fe fe-x"></i><?= (int) ($rejectedCount ?? 0) ?> disapproved</span></div>
            </div>
            <div class="ad-card__body">
                <div class="table-responsive">
                    <table class="table datatable table-bordered table-hover align-middle mb-0" data-dt-page-length="10">
                        <thead><tr><th>Request</th><th>Type</th><th>Summary</th><th>Requested By</th><th>Status</th><th>Reviewed By</th><th>Reviewed At</th><th>Reference</th><th data-dt-ordering="false">Action</th></tr></thead>
                        <tbody>
                        <?php foreach (($historyItems ?? []) as $history): $historyType = (string) ($history['request_type'] ?? ''); $historyStatus = (string) ($history['status'] ?? ''); ?>
                            <tr>
                                <td data-order="<?= (int) $history['id'] ?>"><strong>#<?= (int) $history['id'] ?></strong></td>
                                <td><span class="d-inline-flex align-items-center gap-2"><i class="fe <?= esc($icon($historyType)) ?> text-primary"></i><?= esc($label($historyType)) ?></span></td>
                                <td><?= esc((string) ($history['summary'] ?? '-')) ?></td>
                                <td><?= esc((string) ($history['requested_by_name'] ?? '-')) ?></td>
                                <td><span class="status-pill status-<?= esc($historyStatus) ?>"><?= esc($historyStatus === 'rejected' ? 'disapproved' : $historyStatus) ?></span></td>
                                <td><?= esc((string) ($history['reviewed_by_name'] ?? '-')) ?></td>
                                <td data-order="<?= esc((string) ($history['reviewed_at'] ?? '')) ?>"><?= ! empty($history['reviewed_at']) ? esc(date('d M Y, h:i A', strtotime((string) $history['reviewed_at']))) : '-' ?></td>
                                <td><?= esc((string) (($history['result_reference'] ?? '') ?: '-')) ?></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#approvalHistoryModal<?= (int) $history['id'] ?>"><i class="fe fe-eye me-1"></i>View</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<?php foreach (($historyItems ?? []) as $history): $historyType = (string) ($history['request_type'] ?? ''); $historyStatus = (string) ($history['status'] ?? ''); ?>
<div class="modal fade approval-view-modal" id="approvalHistoryModal<?= (int) $history['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><small>REQUEST #<?= (int) $history['id'] ?></small><h5 class="modal-title"><?= esc($label($historyType)) ?></h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><strong><?= esc((string) ($history['summary'] ?? '-')) ?></strong><div class="small text-muted">Requested by <?= esc((string) ($history['requested_by_name'] ?? '-')) ?> · <?= ! empty($history['created_at']) ? esc(date('d M Y, h:i A', strtotime((string) $history['created_at']))) : '-' ?></div></div><span class="status-pill status-<?= esc($historyStatus) ?>"><?= esc($historyStatus === 'rejected' ? 'disapproved' : $historyStatus) ?></span></div>
                <?= view('admin/mobile_approvals/_request_details', ['row' => $history]) ?>
                <?php if (! empty($history['can_reapprove'])): ?>
                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $history['id'] . '/review') ?>" class="ad-reapprove">
                        <?= csrf_field() ?><div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">Re-approval note</label><input class="form-control" name="note" maxlength="500" placeholder="Why this rejected issuement is being approved"></div><div class="col-md-auto"><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Re-approve and process this rejected issuement?')"><i class="fe fe-refresh-cw me-1"></i>Re-approve &amp; Process</button></div></div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.getElementById('approval-history-tab')?.addEventListener('shown.bs.tab', function () {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') return;
    jQuery('#approval-history-pane table.datatable').each(function () {
        if (jQuery.fn.DataTable.isDataTable(this)) jQuery(this).DataTable().columns.adjust();
    });
});

// Display-only filtering of pending requests (no data or form changes).
(function () {
    var search = document.querySelector('[data-ad-search]');
    var chips = document.querySelectorAll('[data-ad-filter]');
    var items = document.querySelectorAll('[data-ad-item]');
    var noMatch = document.querySelector('[data-ad-no-match]');
    var otherTitle = document.querySelector('[data-ad-other-title]');
    if (!items.length) return;
    var activeType = 'all';

    function apply() {
        var term = (search && search.value || '').trim().toLowerCase();
        var shown = 0, otherShown = 0;
        items.forEach(function (item) {
            var type = item.getAttribute('data-ad-type');
            var visible = (activeType === 'all' || type === activeType) && (!term || (item.getAttribute('data-ad-text') || '').indexOf(term) !== -1);
            item.style.display = visible ? '' : 'none';
            if (visible) { shown++; if (type !== 'followup') otherShown++; }
        });
        if (noMatch) noMatch.style.display = shown ? 'none' : 'block';
        if (otherTitle) otherTitle.style.display = otherShown ? '' : 'none';
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            activeType = chip.getAttribute('data-ad-filter');
            chips.forEach(function (c) { c.classList.toggle('is-on', c === chip); });
            apply();
        });
    });
    if (search) search.addEventListener('input', apply);

    // The search box only applies to the pending tab.
    var pendingOnly = document.querySelector('[data-ad-pending-only]');
    document.getElementById('approval-history-tab')?.addEventListener('shown.bs.tab', function () { if (pendingOnly) pendingOnly.style.visibility = 'hidden'; });
    document.getElementById('pending-approvals-tab')?.addEventListener('shown.bs.tab', function () { if (pendingOnly) pendingOnly.style.visibility = ''; });
})();
</script>
<?= $this->endSection() ?>
