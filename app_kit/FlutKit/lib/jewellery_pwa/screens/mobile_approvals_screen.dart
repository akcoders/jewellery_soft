import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class MobileApprovalsScreen extends StatefulWidget {
  const MobileApprovalsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<MobileApprovalsScreen> createState() => _MobileApprovalsScreenState();
}

class _MobileApprovalsScreenState extends State<MobileApprovalsScreen> {
  bool _loading = true;
  bool _canReview = false;
  String _error = '';
  String _statusFilter = 'pending';
  String _pendingTypeFilter = 'all';
  List<dynamic> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = await widget.api.fetchApprovalRequests();
      if (!mounted) return;
      setState(() {
        _items = data['items'] is List ? data['items'] as List : [];
        _canReview = data['can_review'] == true;
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _newRequest(String type) async {
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => _MasterRequestDialog(api: widget.api, type: type),
    );
    if (created == true) await _load();
  }

  Future<void> _openStaffControls() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _ApprovalStaffSettingsScreen(api: widget.api),
      ),
    );
  }

  Future<void> _review(Map<String, dynamic> item, String decision) async {
    final note = TextEditingController();
    final isReapproval = decision == 'approve' && item['can_reapprove'] == true;
    final isFollowup = item['request_type'] == 'followup';
    final payload = _map(item['payload']);
    final chainPosition = _int(item['followup_chain_position']);
    final chainCount = _int(item['followup_chain_count']);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          decision == 'approve'
              ? LucideIcons.badge_check
              : LucideIcons.circle_x,
          color: decision == 'approve' ? Colors.green : Colors.red,
          size: 36,
        ),
        title: Text(
          isReapproval
              ? 'Re-approve issuement?'
              : isFollowup
              ? '${decision == 'approve' ? 'Approve' : 'Disapprove'} follow-up${chainPosition > 0 ? ' #$chainPosition' : ''}?'
              : decision == 'approve'
              ? 'Approve request?'
              : 'Reject request?',
        ),
        content: SizedBox(
          width: 520,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (isFollowup) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFAF7F9),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${item['followup_order_no'] ?? payload['order_no'] ?? '-'}${chainCount > 0 ? ' · Update $chainPosition of $chainCount' : ''}',
                          style: const TextStyle(
                            color: AppColors.plum,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          (payload['stage'] ?? '-').toString(),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          (payload['description'] ?? '-').toString(),
                          style: const TextStyle(height: 1.45),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Next reminder: ${(payload['next_followup_date'] ?? '').toString().isEmpty ? 'Not scheduled' : AppFormatters.dateTime(payload['next_followup_date'])}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: (decision == 'approve' ? Colors.green : Colors.red)
                          .withValues(alpha: .08),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      decision == 'approve'
                          ? 'Score impact: +2 for approval. Evidence and duplicate rules are calculated separately.'
                          : 'Score impact: −2 only. The update remains visible in the order chain.',
                      style: TextStyle(
                        color: decision == 'approve'
                            ? Colors.green.shade800
                            : Colors.red.shade800,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                TextField(
                  controller: note,
                  maxLength: 500,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: decision == 'approve'
                        ? 'Approval note (optional)'
                        : 'Reason for disapproval',
                    hintText: decision == 'approve'
                        ? 'Add any instruction for the follower'
                        : 'Explain what must be corrected',
                    border: const OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: decision == 'approve'
                  ? Colors.green.shade700
                  : Colors.red.shade700,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(
              isReapproval
                  ? 'Re-approve & Process'
                  : decision == 'approve'
                  ? (isFollowup ? 'Approve · +2' : 'Approve & Process')
                  : (isFollowup ? 'Disapprove · −2' : 'Reject'),
            ),
          ),
        ],
      ),
    );
    final noteValue = note.text;
    note.dispose();
    if (confirmed != true || !mounted) return;
    try {
      await widget.api.reviewApprovalRequest(
        _int(item['id']),
        decision: decision,
        note: noteValue,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isReapproval
                ? 'Issuement re-approved and processed.'
                : decision == 'approve'
                ? 'Request approved.'
                : item['request_type'] == 'followup'
                ? 'Followup disapproved.'
                : 'Request disapproved.',
          ),
        ),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _items.isEmpty) {
      return const FullScreenLoader(message: 'Loading approval requests...');
    }
    if (_error.isNotEmpty && _items.isEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final pendingItems = _items
        .where((raw) => _map(raw)['status'] == 'pending')
        .toList();
    final historyItems = _items
        .where((raw) => _map(raw)['status'] != 'pending')
        .toList();
    final pending = pendingItems.length;
    final approved = _items
        .where((raw) => _map(raw)['status'] == 'approved')
        .length;
    final rejected = _items
        .where((raw) => _map(raw)['status'] == 'rejected')
        .length;
    final visibleItems = _statusFilter == 'history'
        ? historyItems
        : pendingItems;
    final pendingFollowups = pendingItems
        .where((raw) => _map(raw)['request_type'] == 'followup')
        .toList();
    final pendingOther = pendingItems
        .where((raw) => _map(raw)['request_type'] != 'followup')
        .toList();
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [
                  Color(0xFF32142D),
                  Color(0xFF76224F),
                  Color(0xFFC19636),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(22),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x332E1028),
                  blurRadius: 26,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _canReview ? 'ADMIN APPROVAL DESK' : 'MASTER REQUESTS',
                        style: const TextStyle(
                          color: Color(0xFFF5D98A),
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.3,
                        ),
                      ),
                      const SizedBox(height: 7),
                      Text(
                        _canReview
                            ? '$pending pending approval${pending == 1 ? '' : 's'}'
                            : 'Create masters with admin approval',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  LucideIcons.shield_check,
                  color: Color(0xFFF5D98A),
                  size: 42,
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (_canReview) ...[
            OutlinedButton.icon(
              onPressed: _openStaffControls,
              icon: const Icon(LucideIcons.user_round_cog),
              label: const Text('Employee Approval & Camera Controls'),
            ),
            const SizedBox(height: 10),
          ],
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _deskFilter('pending', 'Pending $pending'),
              _deskFilter(
                'history',
                'History ${historyItems.length} ($approved approved, $rejected disapproved)',
              ),
            ],
          ),
          if (_statusFilter == 'pending' && pendingItems.isNotEmpty) ...[
            const SizedBox(height: 14),
            _queueSummary(pendingFollowups, pendingOther),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _typeFilter('all', 'All ${pendingItems.length}'),
                _typeFilter(
                  'followups',
                  'Follow-up chains ${pendingFollowups.length}',
                ),
                _typeFilter('other', 'Other ${pendingOther.length}'),
              ],
            ),
          ],
          const SizedBox(height: 10),
          LayoutBuilder(
            builder: (context, constraints) {
              final buttonWidth = constraints.maxWidth < 430
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 10) / 2;
              return Wrap(
                spacing: 10,
                runSpacing: 8,
                children: [
                  SizedBox(
                    width: buttonWidth,
                    child: FilledButton.icon(
                      onPressed: () => _newRequest('customer'),
                      icon: const Icon(LucideIcons.user_round_plus),
                      label: const Text('New Customer'),
                    ),
                  ),
                  SizedBox(
                    width: buttonWidth,
                    child: OutlinedButton.icon(
                      onPressed: () => _newRequest('karigar'),
                      icon: const Icon(LucideIcons.hammer),
                      label: const Text('New Karigar'),
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 18),
          if (visibleItems.isEmpty)
            AppEmptyState(
              title: _statusFilter == 'history'
                  ? 'No approval history'
                  : 'No pending requests',
              message: _statusFilter == 'history'
                  ? 'Approved and disapproved requests will appear here.'
                  : 'New requests waiting for approval will appear here.',
            )
          else if (_statusFilter == 'history')
            _historyTable(visibleItems)
          else
            ..._pendingQueue(pendingFollowups, pendingOther),
        ],
      ),
    );
  }

  Widget _queueSummary(List<dynamic> followups, List<dynamic> other) {
    final orderIds = followups
        .map((raw) {
          final item = _map(raw);
          return _int(
            item['followup_order_id'] ?? _map(item['payload'])['order_id'],
          );
        })
        .where((id) => id > 0)
        .toSet();
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: _queueMetric(
              '${orderIds.length}',
              'Orders',
              LucideIcons.shopping_bag,
              AppColors.plum,
            ),
          ),
          Container(width: 1, height: 44, color: AppColors.border),
          Expanded(
            child: _queueMetric(
              '${followups.length}',
              'Follow-ups',
              LucideIcons.git_branch,
              AppColors.gold,
            ),
          ),
          Container(width: 1, height: 44, color: AppColors.border),
          Expanded(
            child: _queueMetric(
              '${other.length}',
              'Other',
              LucideIcons.inbox,
              AppColors.diamond,
            ),
          ),
        ],
      ),
    );
  }

  Widget _queueMetric(String value, String label, IconData icon, Color color) =>
      Column(
        children: [
          Icon(icon, size: 18, color: color),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          Text(
            label,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      );

  List<Widget> _pendingQueue(List<dynamic> followups, List<dynamic> other) {
    final widgets = <Widget>[];
    if (_pendingTypeFilter != 'other') {
      final groups = <String, List<Map<String, dynamic>>>{};
      for (final raw in followups) {
        final item = _map(raw);
        final payload = _map(item['payload']);
        final orderId = _int(item['followup_order_id'] ?? payload['order_id']);
        final orderNo = (item['followup_order_no'] ?? payload['order_no'] ?? '')
            .toString();
        final key = orderId > 0 ? 'id:$orderId' : 'no:$orderNo';
        groups.putIfAbsent(key, () => []).add(item);
      }
      final grouped = groups.values.toList()
        ..sort(
          (a, b) => (a.first['created_at'] ?? '').toString().compareTo(
            (b.first['created_at'] ?? '').toString(),
          ),
        );
      if (grouped.isNotEmpty) {
        widgets.add(
          _sectionCaption(
            'FOLLOW-UP APPROVAL CHAINS',
            '${grouped.length} order${grouped.length == 1 ? '' : 's'} · oldest pending first',
          ),
        );
        widgets.addAll(grouped.map(_followupChainCard));
      }
    }
    if (_pendingTypeFilter != 'followups' && other.isNotEmpty) {
      widgets.add(
        _sectionCaption(
          'OTHER REQUESTS',
          '${other.length} request${other.length == 1 ? '' : 's'} waiting',
        ),
      );
      widgets.addAll(other.map((raw) => _requestCard(_map(raw))));
    }
    if (widgets.isEmpty) {
      widgets.add(
        const AppEmptyState(
          title: 'No requests in this filter',
          message: 'Choose another request type to view the approval queue.',
        ),
      );
    }
    return widgets;
  }

  Widget _sectionCaption(String title, String subtitle) => Padding(
    padding: const EdgeInsets.only(top: 8, bottom: 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: AppColors.plum,
            fontSize: 11,
            fontWeight: FontWeight.w900,
            letterSpacing: 1,
          ),
        ),
        Text(
          subtitle,
          style: const TextStyle(color: AppColors.textSecondary, fontSize: 11),
        ),
      ],
    ),
  );

  Widget _followupChainCard(List<Map<String, dynamic>> requests) {
    requests.sort(
      (a, b) => (a['created_at'] ?? '').toString().compareTo(
        (b['created_at'] ?? '').toString(),
      ),
    );
    final first = requests.first;
    final payload = _map(first['payload']);
    final orderNo = (first['followup_order_no'] ?? payload['order_no'] ?? '-')
        .toString();
    final rawChain = first['followup_chain'] is List
        ? first['followup_chain'] as List
        : <dynamic>[];
    final chain = rawChain
        .whereType<Map>()
        .map((row) => row.cast<String, dynamic>())
        .toList();
    final requestsByFollowup = <int, Map<String, dynamic>>{};
    for (final request in requests) {
      final followupId = _int(_map(request['payload'])['followup_id']);
      if (followupId > 0) requestsByFollowup[followupId] = request;
    }
    final requestsById = <int, Map<String, dynamic>>{
      for (final request in requests) _int(request['id']): request,
    };
    final timeline = <Map<String, dynamic>>[...chain];
    for (final request in requests) {
      final requestPayload = _map(request['payload']);
      final followupId = _int(requestPayload['followup_id']);
      if (followupId > 0 &&
          timeline.any((row) => _int(row['id']) == followupId)) {
        continue;
      }
      timeline.add({
        'id': followupId,
        'stage': requestPayload['stage'],
        'description': requestPayload['description'],
        'next_followup_date': requestPayload['next_followup_date'],
        'followup_taken_on': requestPayload['followup_taken_on'],
        'followup_taken_by_name': request['requested_by_name'],
        'approval_status': request['status'],
        'image_url': request['attachment_url'],
        '_request_id': request['id'],
      });
    }
    final recentStart = timeline.length > 8 ? timeline.length - 8 : 0;
    final visibleTimeline = <Map<String, dynamic>>[];
    for (var index = 0; index < timeline.length; index++) {
      final row = timeline[index];
      final hasPendingRequest =
          requestsByFollowup.containsKey(_int(row['id'])) ||
          requestsById.containsKey(_int(row['_request_id']));
      if (index >= recentStart || hasPendingRequest) {
        visibleTimeline.add(row);
      }
    }
    final hiddenCount = timeline.length - visibleTimeline.length;
    final reviewed = timeline
        .where((row) => (row['approval_status'] ?? 'approved') != 'pending')
        .length;
    final progress = timeline.isEmpty ? 0.0 : reviewed / timeline.length;
    final latestNext = timeline.isEmpty
        ? ''
        : (timeline.last['next_followup_date'] ?? '').toString();

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: AppColors.plum.withValues(alpha: .18)),
      ),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFF3C1737), Color(0xFF70224F)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        LucideIcons.git_branch,
                        color: Color(0xFFF5D98A),
                      ),
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            orderNo,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          Text(
                            '${timeline.length} update${timeline.length == 1 ? '' : 's'} in chain · ${requests.length} pending',
                            style: const TextStyle(
                              color: Color(0xFFDCC9D7),
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5D98A),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        '${requests.length} PENDING',
                        style: const TextStyle(
                          color: Color(0xFF4A3210),
                          fontSize: 9,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 13),
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 5,
                    color: const Color(0xFFF5D98A),
                    backgroundColor: Colors.white.withValues(alpha: .15),
                  ),
                ),
                const SizedBox(height: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Review top to bottom to preserve the stage story.',
                      style: TextStyle(color: Colors.white70, fontSize: 10),
                    ),
                    if (latestNext.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        'Next ${AppFormatters.dateTime(latestNext)}',
                        style: const TextStyle(
                          color: Color(0xFFF5D98A),
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          if (hiddenCount > 0)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
              color: const Color(0xFFF8F3E7),
              child: Text(
                '$hiddenCount earlier reviewed update${hiddenCount == 1 ? '' : 's'} hidden · latest context shown',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 16, 14, 4),
            child: Column(
              children: [
                for (var i = 0; i < visibleTimeline.length; i++)
                  _followupTimelineNode(
                    visibleTimeline[i],
                    requestsByFollowup[_int(visibleTimeline[i]['id'])] ??
                        requestsById[_int(visibleTimeline[i]['_request_id'])],
                    timeline.length - visibleTimeline.length + i + 1,
                    timeline.length,
                    i == visibleTimeline.length - 1,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _followupTimelineNode(
    Map<String, dynamic> row,
    Map<String, dynamic>? request,
    int position,
    int total,
    bool isLast,
  ) {
    final status = (row['approval_status'] ?? request?['status'] ?? 'approved')
        .toString()
        .toLowerCase();
    final MaterialColor color = switch (status) {
      'approved' => Colors.green,
      'rejected' => Colors.red,
      _ => Colors.orange,
    };
    final statusLabel = switch (status) {
      'rejected' => 'DISAPPROVED',
      'pending' => 'PENDING REVIEW',
      _ => 'APPROVED',
    };
    final imageUrl = (row['image_url'] ?? request?['attachment_url'] ?? '')
        .toString();
    final takenOn = (row['followup_taken_on'] ?? request?['created_at'] ?? '')
        .toString();
    final next = (row['next_followup_date'] ?? '').toString();
    final isOldestPending = request?['followup_is_oldest_pending'] == true;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 38,
            child: Column(
              children: [
                Container(
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .12),
                    shape: BoxShape.circle,
                    border: Border.all(color: color, width: 2),
                  ),
                  child: status == 'approved'
                      ? Icon(LucideIcons.check, size: 15, color: color)
                      : status == 'rejected'
                      ? Icon(LucideIcons.x, size: 15, color: color)
                      : Text(
                          '$position',
                          style: TextStyle(
                            color: color.shade800,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: AppColors.border,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Container(
              margin: const EdgeInsets.only(bottom: 14),
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: status == 'pending'
                    ? const Color(0xFFFFFBEE)
                    : const Color(0xFFFAF7F9),
                borderRadius: BorderRadius.circular(15),
                border: Border.all(
                  color: status == 'pending'
                      ? Colors.orange.withValues(alpha: .42)
                      : AppColors.border,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${row['stage'] ?? '-'}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: color.withValues(alpha: .11),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          statusLabel,
                          style: TextStyle(
                            color: color.shade800,
                            fontSize: 8,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Update $position of $total · ${row['followup_taken_by_name'] ?? request?['requested_by_name'] ?? '-'}${takenOn.isEmpty ? '' : ' · ${AppFormatters.dateTime(takenOn)}'}',
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                  const SizedBox(height: 9),
                  Text(
                    (row['description'] ?? '-').toString(),
                    style: const TextStyle(height: 1.45),
                  ),
                  const SizedBox(height: 9),
                  Wrap(
                    spacing: 7,
                    runSpacing: 7,
                    children: [
                      _miniFact(
                        LucideIcons.calendar_clock,
                        next.isEmpty
                            ? 'No next reminder'
                            : 'Next ${AppFormatters.dateTime(next)}',
                      ),
                      _miniFact(
                        imageUrl.isEmpty
                            ? LucideIcons.image_off
                            : LucideIcons.image,
                        imageUrl.isEmpty ? 'No evidence' : 'Evidence attached',
                      ),
                      if (isOldestPending)
                        _miniFact(LucideIcons.list_start, 'REVIEW FIRST'),
                    ],
                  ),
                  if ((row['review_note'] ?? '').toString().isNotEmpty) ...[
                    const SizedBox(height: 9),
                    Text(
                      'Admin: ${row['review_note']}',
                      style: TextStyle(
                        color: color.shade800,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  if (imageUrl.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.network(
                        imageUrl,
                        width: double.infinity,
                        height: 120,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => const SizedBox.shrink(),
                      ),
                    ),
                  ],
                  if (_canReview && status == 'pending' && request != null) ...[
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () => _review(request, 'reject'),
                            icon: const Icon(LucideIcons.x, size: 17),
                            label: const Text('Disapprove'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: () => _review(request, 'approve'),
                            icon: const Icon(LucideIcons.check, size: 17),
                            label: const Text('Approve +2'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _miniFact(IconData icon, String text) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: AppColors.border),
    ),
    child: Text.rich(
      TextSpan(
        children: [
          WidgetSpan(
            alignment: PlaceholderAlignment.middle,
            child: Icon(icon, size: 12, color: AppColors.textSecondary),
          ),
          TextSpan(text: '  $text'),
        ],
      ),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700),
    ),
  );

  Widget _historyTable(List<dynamic> items) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          headingRowColor: WidgetStateProperty.all(const Color(0xFFF8F3E7)),
          columns: const [
            DataColumn(label: Text('Request')),
            DataColumn(label: Text('Type')),
            DataColumn(label: Text('Status')),
            DataColumn(label: Text('Reviewed')),
            DataColumn(label: Text('Action')),
          ],
          rows: items.map((raw) {
            final item = _map(raw);
            final status = (item['status'] ?? '').toString();
            final reviewedAt = (item['reviewed_at'] ?? '').toString();
            return DataRow(
              cells: [
                DataCell(Text('#${item['id']}')),
                DataCell(
                  Text(_typeLabel((item['request_type'] ?? '').toString())),
                ),
                DataCell(
                  Text(
                    status == 'rejected' ? 'Disapproved' : 'Approved',
                    style: TextStyle(
                      color: status == 'approved'
                          ? Colors.green.shade700
                          : Colors.red.shade700,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                DataCell(
                  Text(
                    reviewedAt.isEmpty
                        ? '-'
                        : AppFormatters.dateTime(reviewedAt),
                  ),
                ),
                DataCell(
                  OutlinedButton.icon(
                    onPressed: () => _showRequestDetails(item),
                    icon: const Icon(LucideIcons.eye, size: 16),
                    label: const Text('View'),
                  ),
                ),
              ],
            );
          }).toList(),
        ),
      ),
    );
  }

  Future<void> _showRequestDetails(Map<String, dynamic> item) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => FractionallySizedBox(
        heightFactor: .92,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Approval request #${item['id']}',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(LucideIcons.x),
                  tooltip: 'Close',
                ),
              ],
            ),
            const SizedBox(height: 8),
            _requestCard(item),
          ],
        ),
      ),
    );
  }

  Widget _requestCard(Map<String, dynamic> item) {
    final type = (item['request_type'] ?? '').toString();
    final status = (item['status'] ?? 'pending').toString();
    final payload = _map(item['payload']);
    final isIssue = type == 'issuement';
    final isFollowup = type == 'followup';
    final isChallan = type == 'delivery_challan';
    final canReapprove = item['can_reapprove'] == true;
    final statusLabel = status == 'rejected'
        ? 'DISAPPROVED'
        : status.toUpperCase();
    final statusColor = switch (status) {
      'approved' => Colors.green,
      'rejected' => Colors.red,
      _ => Colors.orange,
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: const Color(0xFFF6EBCB),
                  foregroundColor: AppColors.plum,
                  child: Icon(
                    isIssue
                        ? LucideIcons.send
                        : isFollowup
                        ? LucideIcons.clipboard_check
                        : isChallan
                        ? LucideIcons.scroll_text
                        : type == 'customer_create'
                        ? LucideIcons.users
                        : LucideIcons.hammer,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _typeLabel(type),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        '#${item['id']} · ${item['requested_by_name'] ?? '-'}',
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: statusColor.shade700,
                      fontSize: 10,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const Divider(height: 26),
            Text(
              (item['summary'] ?? '').toString(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (isIssue) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.hammer, payload['karigar_name'] ?? '-'),
                  _chip(LucideIcons.warehouse, payload['location_name'] ?? '-'),
                  _chip(LucideIcons.calendar, payload['issue_date'] ?? '-'),
                ],
              ),
              const SizedBox(height: 12),
              for (final material in ['gold', 'diamond', 'stone'])
                if (payload['${material}_lines'] is List &&
                    (payload['${material}_lines'] as List).isNotEmpty)
                  _materialLines(
                    material,
                    payload['${material}_lines'] as List,
                  ),
              if ((item['attachment_url'] ?? '').toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    item['attachment_url'].toString(),
                    width: double.infinity,
                    height: 230,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const SizedBox(
                      height: 70,
                      child: Center(
                        child: Text('Attachment preview unavailable'),
                      ),
                    ),
                  ),
                ),
              ],
            ] else if (isFollowup) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.shopping_bag, payload['order_no'] ?? '-'),
                  _chip(LucideIcons.activity, payload['stage'] ?? '-'),
                ],
              ),
              const SizedBox(height: 10),
              _followupContext(payload),
              const SizedBox(height: 10),
              Text(
                'New follow-up',
                style: TextStyle(
                  color: AppColors.plum,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                (payload['description'] ?? '').toString(),
                style: const TextStyle(height: 1.5),
              ),
              if ((item['attachment_url'] ?? '').toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    item['attachment_url'].toString(),
                    width: double.infinity,
                    height: 230,
                    fit: BoxFit.contain,
                  ),
                ),
              ],
            ] else if (isChallan) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.users, payload['customer_name'] ?? '-'),
                  _chip(LucideIcons.map_pin, payload['dispatch_from'] ?? '-'),
                  _chip(LucideIcons.calendar, payload['challan_date'] ?? '-'),
                  _chip(
                    LucideIcons.package,
                    '${(payload['items'] as List?)?.length ?? 0} item(s)',
                  ),
                ],
              ),
              const SizedBox(height: 10),
              for (
                var i = 0;
                i < ((payload['items'] as List?)?.length ?? 0);
                i++
              )
                Builder(
                  builder: (context) {
                    final line = _map((payload['items'] as List)[i]);
                    return Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(11),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFAF7F9),
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${i + 1}. ${line['description'] ?? '-'} · ${line['item_type'] ?? '-'} · ${line['purity'] ?? '-'} · ${line['pcs'] ?? 0} PCS · Rs. ${line['value'] ?? 0}',
                        style: const TextStyle(
                          fontSize: 12,
                          height: 1.45,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    );
                  },
                ),
            ] else ...[
              const SizedBox(height: 8),
              Text(
                _masterDetails(payload),
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.5,
                ),
              ),
            ],
            if ((item['review_note'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                'Admin note: ${item['review_note']}',
                style: const TextStyle(fontStyle: FontStyle.italic),
              ),
            ],
            if (_canReview && status == 'pending') ...[
              const SizedBox(height: 15),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _review(item, 'reject'),
                      icon: const Icon(LucideIcons.x),
                      label: const Text('Reject'),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () => _review(item, 'approve'),
                      icon: const Icon(LucideIcons.check),
                      label: const Text('Approve'),
                    ),
                  ),
                ],
              ),
            ] else if (_canReview && canReapprove) ...[
              const SizedBox(height: 15),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => _review(item, 'approve'),
                  icon: const Icon(LucideIcons.refresh_cw),
                  label: const Text('Re-approve & Process'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _deskFilter(String value, String label) => FilterChip(
    selected: _statusFilter == value,
    label: Text(label),
    onSelected: (_) => setState(() => _statusFilter = value),
  );

  Widget _typeFilter(String value, String label) => ChoiceChip(
    selected: _pendingTypeFilter == value,
    label: Text(label),
    avatar: Icon(
      value == 'followups'
          ? LucideIcons.git_branch
          : value == 'other'
          ? LucideIcons.inbox
          : LucideIcons.layout_list,
      size: 15,
    ),
    onSelected: (_) => setState(() => _pendingTypeFilter = value),
  );

  Widget _followupContext(Map<String, dynamic> payload) {
    final previous = _map(payload['previous_followup']);
    final previousDescription = (previous['description'] ?? '').toString();
    final previousStage = (previous['stage'] ?? '').toString();
    final previousTakenOn = (previous['followup_taken_on'] ?? '').toString();
    final nextFollowup = (payload['next_followup_date'] ?? '').toString();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFAF7F9),
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Previous follow-up',
            style: TextStyle(
              color: AppColors.plum,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            previousDescription.isEmpty
                ? 'No previous follow-up recorded.'
                : '${previousStage.isEmpty ? '' : '$previousStage · '}$previousDescription',
            style: const TextStyle(height: 1.45),
          ),
          if (previousTakenOn.isNotEmpty)
            Text(
              '${AppFormatters.dateTime(previousTakenOn)}${(previous['followup_taken_by_name'] ?? '').toString().isEmpty ? '' : ' · ${previous['followup_taken_by_name']}'}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 11,
              ),
            ),
          const Divider(height: 22),
          const Text(
            'Next follow-up',
            style: TextStyle(
              color: AppColors.plum,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            nextFollowup.isEmpty
                ? 'Not scheduled (terminal stage)'
                : AppFormatters.dateTime(nextFollowup),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  Widget _materialLines(String material, List lines) {
    final unitKey = material == 'gold'
        ? 'weight_gm'
        : material == 'diamond'
        ? 'carat'
        : 'qty';
    final unit = material == 'gold'
        ? 'gm'
        : material == 'diamond'
        ? 'ct'
        : 'qty';
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFAF7F9),
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${material[0].toUpperCase()}${material.substring(1)} · ${lines.length} line(s)',
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.plum,
            ),
          ),
          for (var i = 0; i < lines.length; i++)
            Text(
              'Line ${i + 1} · ${_map(lines[i])['display_name'] ?? 'Item ${_map(lines[i])['item_id'] ?? '-'}'} · ${_map(lines[i])[unitKey] ?? 0} $unit${_map(lines[i])['pcs'] == null ? '' : ' · ${_map(lines[i])['pcs']} pcs'}',
              style: const TextStyle(fontSize: 12, height: 1.55),
            ),
        ],
      ),
    );
  }

  Widget _chip(IconData icon, dynamic value) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: const Color(0xFFF8F3E7),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14),
        const SizedBox(width: 6),
        Text(
          '$value',
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );

  String _masterDetails(Map<String, dynamic> payload) =>
      [
            payload['phone'],
            payload['email'],
            payload['department'],
            payload['city'],
            payload['state'],
            payload['gstin'],
          ]
          .map((e) => (e ?? '').toString().trim())
          .where((e) => e.isNotEmpty)
          .join(' · ');

  String _typeLabel(String type) => switch (type) {
    'customer_create' => 'Customer Creation',
    'karigar_create' => 'Karigar Creation',
    'followup' => 'Order Follow-up',
    'delivery_challan' => 'Delivery Challan',
    _ => 'Material Issuement',
  };
}

class _ApprovalStaffSettingsScreen extends StatefulWidget {
  const _ApprovalStaffSettingsScreen({required this.api});

  final MobileApiService api;

  @override
  State<_ApprovalStaffSettingsScreen> createState() =>
      _ApprovalStaffSettingsScreenState();
}

class _ApprovalStaffSettingsScreenState
    extends State<_ApprovalStaffSettingsScreen> {
  bool _loading = true;
  String _error = '';
  List<Map<String, dynamic>> _rows = [];
  final Set<int> _saving = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final rows = await widget.api.fetchApprovalStaffSettings();
      if (!mounted) return;
      setState(() {
        _rows = rows
            .whereType<Map>()
            .map((row) => row.cast<String, dynamic>())
            .toList();
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(Map<String, dynamic> row) async {
    final id = _int(row['id']);
    if (id <= 0 || _saving.contains(id)) return;
    setState(() => _saving.add(id));
    try {
      await widget.api.updateApprovalStaffSettings(id, {
        'followup_requires_approval': _enabled(
          row['followup_requires_approval'],
        ),
        'issuement_requires_approval': _enabled(
          row['issuement_requires_approval'],
        ),
        'delivery_challan_requires_approval': _enabled(
          row['delivery_challan_requires_approval'],
        ),
        'followup_gallery_enabled': _enabled(row['followup_gallery_enabled']),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${row['name']} settings saved.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _saving.remove(id));
    }
  }

  void _toggle(Map<String, dynamic> row, String field, bool value) {
    setState(() => row[field] = value ? 1 : 0);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Employee PWA Controls')),
    body: _loading
        ? const FullScreenLoader(message: 'Loading employee controls...')
        : _error.isNotEmpty
        ? AppErrorState(message: _error, onRetry: _load)
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.paleGold.withValues(alpha: .45),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Text(
                    'Approval is ON by default for follow-ups, issuements and delivery challans. Gallery is OFF by default, so follow-up photos open the camera only.',
                    style: TextStyle(height: 1.5, fontWeight: FontWeight.w600),
                  ),
                ),
                const SizedBox(height: 16),
                if (_rows.isEmpty)
                  const AppEmptyState(
                    title: 'No staff accounts',
                    message:
                        'Active non-admin employee logins will appear here.',
                  ),
                ..._rows.map(_card),
              ],
            ),
          ),
  );

  Widget _card(Map<String, dynamic> row) {
    final id = _int(row['id']);
    final busy = _saving.contains(id);
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              (row['name'] ?? 'Employee').toString(),
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
            ),
            Text(
              '${row['email'] ?? ''} · ${row['role_label'] ?? 'Staff'}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const Divider(height: 24),
            _switch(
              row,
              'followup_requires_approval',
              'Follow-up approval required',
            ),
            _switch(
              row,
              'issuement_requires_approval',
              'Issuement approval required',
            ),
            _switch(
              row,
              'delivery_challan_requires_approval',
              'Delivery challan approval required',
            ),
            _switch(
              row,
              'followup_gallery_enabled',
              'Allow gallery for follow-up image',
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: busy ? null : () => _save(row),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(LucideIcons.save),
                label: Text(busy ? 'Saving...' : 'Save Controls'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _switch(Map<String, dynamic> row, String field, String label) =>
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        value: _enabled(row[field]),
        onChanged: (value) => _toggle(row, field, value),
      );

  bool _enabled(dynamic value) =>
      value == true || value == 1 || value.toString() == '1';
}

class _MasterRequestDialog extends StatefulWidget {
  const _MasterRequestDialog({required this.api, required this.type});
  final MobileApiService api;
  final String type;

  @override
  State<_MasterRequestDialog> createState() => _MasterRequestDialogState();
}

class _MasterRequestDialogState extends State<_MasterRequestDialog> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields = {};
  bool _saving = false;

  TextEditingController _c(String key) =>
      _fields.putIfAbsent(key, TextEditingController.new);

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    final payload = {
      for (final entry in _fields.entries) entry.key: entry.value.text.trim(),
    };
    try {
      if (widget.type == 'customer') {
        await widget.api.createCustomerApprovalRequest(payload);
      } else {
        await widget.api.createKarigarApprovalRequest(payload);
      }
      if (mounted) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final customer = widget.type == 'customer';
    return AlertDialog(
      title: Text(customer ? 'Request New Customer' : 'Request New Karigar'),
      content: SizedBox(
        width: 580,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              children: [
                _field(
                  'name',
                  customer ? 'Customer name *' : 'Karigar name *',
                  required: true,
                ),
                _field('phone', 'Phone'),
                _field('email', 'Email', type: TextInputType.emailAddress),
                if (customer) ...[
                  _field('gstin', 'GSTIN'),
                  _field('line1', 'Address line 1'),
                  _field('line2', 'Address line 2'),
                ] else ...[
                  _field('department', 'Department'),
                  _field('skills_text', 'Skills'),
                  _field('address', 'Address'),
                ],
                _field('city', 'City'),
                _field('state', 'State'),
                _field('pincode', 'Pincode'),
                if (!customer) ...[
                  _field(
                    'rate_per_gm',
                    'Rate per gram',
                    type: const TextInputType.numberWithOptions(decimal: true),
                  ),
                  _field(
                    'wastage_percentage',
                    'Wastage %',
                    type: const TextInputType.numberWithOptions(decimal: true),
                  ),
                ],
                _field('notes', 'Notes', lines: 2),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _submit,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(LucideIcons.send, size: 18),
          label: const Text('Send for Approval'),
        ),
      ],
    );
  }

  Widget _field(
    String key,
    String label, {
    bool required = false,
    TextInputType? type,
    int lines = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 11),
    child: TextFormField(
      controller: _c(key),
      keyboardType: type,
      maxLines: lines,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator: required
          ? (value) => (value ?? '').trim().length < 2 ? 'Required' : null
          : null,
    ),
  );
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
