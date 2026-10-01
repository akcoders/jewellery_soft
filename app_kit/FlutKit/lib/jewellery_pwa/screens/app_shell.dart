import 'dart:async';

import 'package:flutkit/jewellery_pwa/screens/admin_tasks_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/dashboard_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/diamond_bags_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/diamond_requirements_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/followups_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/inventory_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/issuements_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/notification_center_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_work_requests_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/orders_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/performance_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/purchase_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/task_scheduler_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/transactions_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/transaction_create_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_update_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/pwa_install_prompt.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.session, required this.onLogout});

  final MobileSession session;
  final Future<void> Function() onLogout;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  late final MobileApiService _api;
  String _section = 'dashboard';
  final List<String> _sectionHistory = <String>[];
  bool _drawerOpen = false;
  int _refreshTick = 0;
  int _notificationCount = 0;
  Timer? _appUpdateTimer;
  bool _checkingAppUpdate = false;
  String _shownAppUpdateVersion = '';

  @override
  void initState() {
    super.initState();
    _api = MobileApiService(
      baseUrl: widget.session.baseUrl,
      token: widget.session.token,
    );
    TaskRefreshBus.tick.addListener(_loadNotificationCount);
    OneSignalService.openedNotification.addListener(_handleOpenedNotification);
    _loadNotificationCount();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _handleOpenedNotification();
      _checkAppUpdate();
    });
    _appUpdateTimer = Timer.periodic(
      const Duration(minutes: 1),
      (_) => _checkAppUpdate(),
    );
  }

  @override
  void dispose() {
    TaskRefreshBus.tick.removeListener(_loadNotificationCount);
    OneSignalService.openedNotification.removeListener(
      _handleOpenedNotification,
    );
    _appUpdateTimer?.cancel();
    super.dispose();
  }

  Future<void> _checkAppUpdate() async {
    if (!PwaUpdateService.supported || _checkingAppUpdate || !mounted) return;
    _checkingAppUpdate = true;
    try {
      final data = await _api.fetchAppUpdateStatus();
      final update = (data['update'] as Map?)?.cast<String, dynamic>();
      final version = (update?['version'] ?? '').toString().trim();
      if (!mounted ||
          version.isEmpty ||
          version == PwaUpdateService.appliedVersion ||
          version == _shownAppUpdateVersion) {
        return;
      }
      _shownAppUpdateVersion = version;
      final message = (update?['message'] ?? '').toString().trim();
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => PopScope<void>(
          canPop: false,
          child: AlertDialog(
            icon: const Icon(Icons.system_update_alt, size: 42),
            title: const Text('App updated — please relaunch'),
            content: Text(
              message.isEmpty
                  ? 'A new Aabhushan ERP version is available. Relaunch now to clear the old PWA cache and load the update.'
                  : message,
            ),
            actions: [
              FilledButton.icon(
                onPressed: () =>
                    PwaUpdateService.clearCacheAndRelaunch(version),
                icon: const Icon(Icons.refresh),
                label: const Text('Clear Cache & Relaunch'),
              ),
            ],
          ),
        ),
      );
    } catch (_) {
      // The app remains usable while offline; the next timer cycle retries.
    } finally {
      _checkingAppUpdate = false;
    }
  }

  void _select(String key) {
    _scaffoldKey.currentState?.closeDrawer();
    _switchSection(key);
  }

  void _switchSection(String key) {
    if (!mounted) return;
    if (key == _section) {
      _loadNotificationCount();
      return;
    }
    setState(() {
      _sectionHistory.add(_section);
      _section = key;
    });
    _loadNotificationCount();
  }

  void _handleBackNavigation(bool didPop, Object? result) {
    if (didPop || !mounted) return;
    if (_drawerOpen) {
      _scaffoldKey.currentState?.closeDrawer();
      return;
    }
    if (_sectionHistory.isEmpty) {
      if (_section != 'dashboard') {
        setState(() => _section = 'dashboard');
        _loadNotificationCount();
      }
      return;
    }

    final previousSection = _sectionHistory.removeLast();
    setState(() => _section = previousSection);
    _loadNotificationCount();
  }

  void _handleOpenedNotification() {
    final payload = OneSignalService.consumeOpenedNotification();
    if (!mounted || payload == null) return;

    final screen = (payload['screen'] ?? '').toString().toLowerCase();
    if (screen == 'diamond_requirements') {
      _switchSection('diamond_requirements');
      final requirementId = _asInt(payload['requirement_id']);
      if (requirementId > 0) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => DiamondRequirementDetailScreen(
                api: _api,
                requirementId: requirementId,
              ),
            ),
          );
        });
      }
      return;
    }

    final orderId = _asInt(payload['order_id']);
    if (orderId > 0) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => OrderDetailScreen(api: _api, orderId: orderId),
          ),
        );
      });
      return;
    }

    final taskId = _asInt(payload['task_id']);
    if ((taskId > 0 || screen == 'tasks') && widget.session.canUsePerformance) {
      _switchSection('tasks');
      return;
    }

    final type = (payload['type'] ?? '').toString().toLowerCase();
    if (type.contains('followup')) {
      _switchSection('followups');
    } else {
      _switchSection('dashboard');
    }
  }

  int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  void _openOrdersByStatus(String status) {
    setState(() {
      if (_section != 'orders') {
        _sectionHistory.add(_section);
      }
      _section = 'orders';
      _refreshTick++;
      _ordersInitialStatus = status;
    });
  }

  String _ordersInitialStatus = 'All';

  Future<void> _loadNotificationCount() async {
    try {
      final notifications = await _api.fetchNotifications();

      if (!mounted) {
        return;
      }
      setState(() => _notificationCount = notifications.length);
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() => _notificationCount = 0);
    }
  }

  Widget _body() {
    switch (_section) {
      case 'orders':
        return OrdersScreen(
          key: ValueKey('orders_${_ordersInitialStatus}_$_refreshTick'),
          api: _api,
          initialStatus: _ordersInitialStatus,
        );
      case 'followups':
        return FollowupsScreen(api: _api);
      case 'order_requests':
        return OrderWorkRequestsScreen(api: _api);
      case 'diamond_requirements':
        return DiamondBagsScreen(
          key: ValueKey('diamond_requirements_$_refreshTick'),
          api: _api,
        );
      case 'issuements':
        return IssuementsScreen(
          key: ValueKey('issuements_$_refreshTick'),
          api: _api,
        );
      case 'diamond_returns':
        return TransactionsScreen(
          key: ValueKey('diamond_returns_$_refreshTick'),
          title: 'Diamond Returns',
          loader: _api.fetchDiamondReturns,
          icon: Icons.diamond_outlined,
          accentColor: AppColors.diamond,
          transactionKey: 'diamond_return',
          api: _api,
        );
      case 'diamond_purchases':
        return TransactionsScreen(
          key: ValueKey('diamond_purchases_$_refreshTick'),
          title: 'Diamond Purchases',
          loader: _api.fetchDiamondPurchases,
          icon: Icons.shopping_bag_outlined,
          accentColor: AppColors.diamond,
          transactionKey: 'diamond_purchase',
          api: _api,
        );
      case 'gold_returns':
        return TransactionsScreen(
          key: ValueKey('gold_returns_$_refreshTick'),
          title: 'Gold Returns',
          loader: _api.fetchGoldReturns,
          icon: Icons.workspace_premium_outlined,
          accentColor: AppColors.gold,
          transactionKey: 'gold_return',
          api: _api,
        );
      case 'gold_purchases':
        return TransactionsScreen(
          key: ValueKey('gold_purchases_$_refreshTick'),
          title: 'Gold Purchases',
          loader: _api.fetchGoldPurchases,
          icon: Icons.shopping_bag_outlined,
          accentColor: AppColors.gold,
          transactionKey: 'gold_purchase',
          api: _api,
        );
      case 'stone_returns':
        return TransactionsScreen(
          key: ValueKey('stone_returns_$_refreshTick'),
          title: 'Stone Returns',
          loader: _api.fetchStoneReturns,
          icon: Icons.scatter_plot_outlined,
          accentColor: AppColors.stone,
          transactionKey: 'stone_return',
          api: _api,
        );
      case 'stone_purchases':
        return TransactionsScreen(
          key: ValueKey('stone_purchases_$_refreshTick'),
          title: 'Stone Purchases',
          loader: _api.fetchStonePurchases,
          icon: Icons.shopping_bag_outlined,
          accentColor: AppColors.stone,
          transactionKey: 'stone_purchase',
          api: _api,
        );
      case 'inventory':
        return InventoryScreen(api: _api);
      case 'tasks':
        return TaskSchedulerScreen(api: _api);
      case 'admin_tasks':
        return AdminTasksScreen(api: _api);
      case 'performance':
        return PerformanceScreen(api: _api);
      default:
        return DashboardScreen(
          api: _api,
          onOpenOrdersByStatus: _openOrdersByStatus,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 1100;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final page = AnimatedSwitcher(
      duration: reduceMotion
          ? Duration.zero
          : const Duration(milliseconds: 240),
      switchInCurve: Curves.easeOutCubic,
      child: KeyedSubtree(key: ValueKey(_section), child: _body()),
    );
    return PopScope<Object?>(
      canPop: false,
      onPopInvokedWithResult: _handleBackNavigation,
      child: Scaffold(
        key: _scaffoldKey,
        onDrawerChanged: (isOpened) => _drawerOpen = isOpened,
        appBar: AppBar(
          toolbarHeight: 74,
          automaticallyImplyLeading: false,
          leading: wide
              ? null
              : IconButton(
                  tooltip: 'Open menu',
                  onPressed: () => _scaffoldKey.currentState?.openDrawer(),
                  icon: const Icon(LucideIcons.menu, size: 22),
                ),
          titleSpacing: wide ? 28 : 4,
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'AABHUSHAN WORKSPACE',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                  color: AppColors.gold,
                  letterSpacing: 1.6,
                ),
              ),
              const SizedBox(height: 3),
              Text(_title(), maxLines: 1, overflow: TextOverflow.ellipsis),
            ],
          ),
          actions: [
            ValueListenableBuilder<bool>(
              valueListenable: PwaInstallService.available,
              builder: (context, available, _) {
                if (!available) return const SizedBox.shrink();
                return IconButton(
                  tooltip: 'Install Aabhushan ERP',
                  onPressed: () => showPwaInstallPrompt(context),
                  icon: const Icon(LucideIcons.download, size: 20),
                );
              },
            ),
            IconButton(
              tooltip: 'Notifications',
              onPressed: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => NotificationCenterScreen(api: _api),
                  ),
                );
                if (mounted) _loadNotificationCount();
              },
              icon: _NotificationBell(count: _notificationCount),
            ),
            _accountMenu(),
            const SizedBox(width: 10),
          ],
        ),
        drawer: wide
            ? null
            : Drawer(
                width: MediaQuery.sizeOf(context).width < 390
                    ? MediaQuery.sizeOf(context).width * .9
                    : 340,
                backgroundColor: Colors.white,
                child: _navigationPanel(),
              ),
        body: SafeArea(
          top: false,
          bottom: false,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (wide)
                Container(
                  width: 260,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(right: BorderSide(color: AppColors.border)),
                  ),
                  child: _navigationPanel(),
                ),
              Expanded(
                child: Align(
                  alignment: Alignment.topCenter,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 1240),
                    child: SizedBox(width: double.infinity, child: page),
                  ),
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: wide ? null : _bottomNavigation(),
        floatingActionButton: _fab(context),
      ),
    );
  }

  Widget _accountMenu() {
    final name = widget.session.userName.trim();
    return PopupMenuButton<String>(
      tooltip: 'Account',
      onSelected: (value) async {
        if (value == 'logout') await widget.onLogout();
      },
      itemBuilder: (context) => [
        PopupMenuItem(
          enabled: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(name, style: const TextStyle(fontWeight: FontWeight.w700)),
              Text(
                widget.session.userEmail,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
        const PopupMenuDivider(),
        const PopupMenuItem(
          value: 'logout',
          child: Row(
            children: [
              Icon(LucideIcons.log_out, size: 18),
              SizedBox(width: 10),
              Text('Sign out'),
            ],
          ),
        ),
      ],
      icon: CircleAvatar(
        radius: 17,
        backgroundColor: AppColors.paleGold,
        foregroundColor: AppColors.plum,
        child: Text(
          name.isEmpty ? 'A' : name.characters.first.toUpperCase(),
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
        ),
      ),
    );
  }

  String get _workSection => widget.session.isAdmin
      ? 'admin_tasks'
      : widget.session.canUsePerformance
      ? 'tasks'
      : 'followups';

  Widget _bottomNavigation() {
    final keys = ['dashboard', 'orders', _workSection];
    final current = keys.indexOf(_section);
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: NavigationBar(
        selectedIndex: current < 0 ? 3 : current,
        onDestinationSelected: (index) {
          if (index == 3) {
            _scaffoldKey.currentState?.openDrawer();
          } else {
            _select(keys[index]);
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(LucideIcons.house), label: 'Home'),
          NavigationDestination(
            icon: Icon(LucideIcons.clipboard_list),
            label: 'Orders',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.list_todo),
            label: 'Work',
          ),
          NavigationDestination(
            icon: Icon(LucideIcons.layout_grid),
            label: 'More',
          ),
        ],
      ),
    );
  }

  Widget _navigationPanel() => Material(
    color: Colors.white,
    child: SafeArea(
      top: false,
      child: Column(
        children: [
          _DrawerHeader(session: widget.session),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: [
                _drawerSection('Workspace'),
                _drawerItem('dashboard', 'Dashboard', LucideIcons.house),
                _drawerItem('orders', 'Orders', LucideIcons.clipboard_list),
                _drawerItem(
                  'followups',
                  'Followups',
                  LucideIcons.calendar_clock,
                ),
                if (widget.session.isAdmin) ...[
                  _drawerItem(
                    'order_requests',
                    'Order Requests',
                    LucideIcons.badge_check,
                  ),
                  _drawerItem(
                    'admin_tasks',
                    'Staff Tasks',
                    LucideIcons.list_todo,
                  ),
                ],
                _drawerItem(
                  'issuements',
                  'Issuements',
                  LucideIcons.send,
                  leading: const _IssuementMenuIcon(),
                ),
                _drawerSection('Diamond'),
                _drawerItem(
                  'diamond_requirements',
                  'Diamond Bags',
                  LucideIcons.package,
                ),
                _drawerItem(
                  'diamond_returns',
                  'Diamond Return',
                  LucideIcons.diamond,
                ),
                _drawerItem(
                  'diamond_purchases',
                  'Diamond Purchase',
                  LucideIcons.shopping_bag,
                ),
                _drawerSection('Gold & stone'),
                _drawerItem('gold_returns', 'Gold Return', LucideIcons.gem),
                _drawerItem(
                  'gold_purchases',
                  'Gold Purchase',
                  LucideIcons.shopping_bag,
                ),
                _drawerItem(
                  'stone_returns',
                  'Stone Return',
                  LucideIcons.sparkles,
                ),
                _drawerItem(
                  'stone_purchases',
                  'Stone Purchase',
                  LucideIcons.shopping_bag,
                ),
                _drawerSection('Manage'),
                _drawerItem('inventory', 'Inventory', LucideIcons.boxes),
                if (widget.session.canUsePerformance) ...[
                  _drawerItem('tasks', 'My Tasks', LucideIcons.circle_check),
                  _drawerItem(
                    'performance',
                    'My Performance',
                    LucideIcons.chart_no_axes_combined,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );

  Widget? _fab(BuildContext context) {
    if (_section == 'orders') {
      return FloatingActionButton.extended(
        onPressed: () async {
          final created = await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => OrderCreateScreen(api: _api)),
          );
          if (created != null && mounted) setState(() => _refreshTick++);
        },
        icon: const Icon(Icons.add),
        label: const Text('Create'),
      );
    }
    if (_section == 'issuements') {
      return FloatingActionButton.extended(
        onPressed: () async {
          final created = await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => IssuementCreateScreen(api: _api)),
          );
          if (created != null && mounted) setState(() => _refreshTick++);
        },
        icon: const Icon(Icons.add),
        label: const Text('Create'),
      );
    }
    final config = _transactionConfig();
    if (config == null) return null;
    return FloatingActionButton.extended(
      onPressed: () async {
        final created = await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => config['action'] == 'purchase'
                ? PurchaseCreateScreen(api: _api, material: config['material']!)
                : TransactionCreateScreen(
                    api: _api,
                    title: 'Create ${_title()}',
                    material: config['material']!,
                    action: config['action']!,
                    accentColor: _accentForSection(),
                  ),
          ),
        );
        if (created == true && mounted) {
          setState(() => _refreshTick++);
        }
      },
      icon: const Icon(Icons.add),
      label: const Text('Create'),
    );
  }

  Map<String, String>? _transactionConfig() {
    switch (_section) {
      case 'diamond_returns':
        return {'material': 'diamond', 'action': 'return'};
      case 'diamond_purchases':
        return {'material': 'diamond', 'action': 'purchase'};
      case 'gold_returns':
        return {'material': 'gold', 'action': 'return'};
      case 'gold_purchases':
        return {'material': 'gold', 'action': 'purchase'};
      case 'stone_returns':
        return {'material': 'stone', 'action': 'return'};
      case 'stone_purchases':
        return {'material': 'stone', 'action': 'purchase'};
      default:
        return null;
    }
  }

  Color _accentForSection() {
    switch (_section) {
      case 'diamond_requirements':
      case 'diamond_returns':
      case 'diamond_purchases':
        return AppColors.diamond;
      case 'gold_returns':
      case 'gold_purchases':
        return AppColors.gold;
      case 'stone_returns':
      case 'stone_purchases':
        return AppColors.stone;
      default:
        return AppColors.brandRed;
    }
  }

  String _title() {
    switch (_section) {
      case 'orders':
        return 'Orders';
      case 'followups':
        return 'Order Followups';
      case 'order_requests':
        return 'Order Requests';
      case 'admin_tasks':
        return 'Staff Tasks';
      case 'issuements':
        return 'Issuements';
      case 'diamond_requirements':
        return 'Diamond Bags';
      case 'diamond_returns':
        return 'Diamond Returns';
      case 'diamond_purchases':
        return 'Diamond Purchases';
      case 'gold_returns':
        return 'Gold Returns';
      case 'gold_purchases':
        return 'Gold Purchases';
      case 'stone_returns':
        return 'Stone Returns';
      case 'stone_purchases':
        return 'Stone Purchases';
      case 'inventory':
        return 'Inventory';
      case 'tasks':
        return 'My Tasks';
      case 'performance':
        return 'My Performance';
      default:
        return 'Dashboard';
    }
  }

  Widget _drawerItem(
    String key,
    String label,
    IconData icon, {
    Widget? leading,
  }) {
    final selected = _section == key;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      child: ListTile(
        dense: true,
        minTileHeight: 52,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        selected: selected,
        selectedColor: Colors.white,
        selectedTileColor: AppColors.plum,
        leading: IconTheme(
          data: IconThemeData(
            color: selected ? AppColors.brandGold : AppColors.textSecondary,
            size: 20,
          ),
          child: leading ?? Icon(icon, size: 20),
        ),
        title: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
          ),
        ),
        trailing: selected
            ? const Icon(
                LucideIcons.chevron_right,
                size: 17,
                color: AppColors.brandGold,
              )
            : null,
        onTap: () => _select(key),
      ),
    );
  }

  Widget _drawerSection(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(22, 20, 16, 7),
      child: Text(
        title.toUpperCase(),
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: AppColors.textSecondary,
          letterSpacing: 1.3,
        ),
      ),
    );
  }
}

/// A code-drawn icon keeps the Issuements entry visible even when a device has
/// retained an older, tree-shaken Material Icons font from a previous PWA build.
class _IssuementMenuIcon extends StatelessWidget {
  const _IssuementMenuIcon();

  @override
  Widget build(BuildContext context) {
    final theme = IconTheme.of(context);
    return SizedBox.square(
      dimension: theme.size ?? 24,
      child: CustomPaint(
        painter: _IssuementMenuIconPainter(
          color: theme.color ?? AppColors.textSecondary,
        ),
      ),
    );
  }
}

class _IssuementMenuIconPainter extends CustomPainter {
  const _IssuementMenuIconPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final scale = size.shortestSide / 24;
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.9 * scale
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(3 * scale, 10 * scale, 18 * scale, 11 * scale),
        Radius.circular(2 * scale),
      ),
      paint,
    );
    canvas.drawLine(
      Offset(12 * scale, 15 * scale),
      Offset(12 * scale, 3 * scale),
      paint,
    );
    final arrow = Path()
      ..moveTo(7.5 * scale, 7.5 * scale)
      ..lineTo(12 * scale, 3 * scale)
      ..lineTo(16.5 * scale, 7.5 * scale);
    canvas.drawPath(arrow, paint);
  }

  @override
  bool shouldRepaint(covariant _IssuementMenuIconPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _NotificationBell extends StatelessWidget {
  const _NotificationBell({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final label = count > 99 ? '99+' : '$count';
    return Stack(
      clipBehavior: Clip.none,
      children: [
        const Icon(LucideIcons.bell, size: 21),
        if (count > 0)
          Positioned(
            right: -6,
            top: -6,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.brandRed,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: Colors.white, width: 1.5),
              ),
              constraints: const BoxConstraints(minWidth: 20),
              child: Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _DrawerHeader extends StatelessWidget {
  const _DrawerHeader({required this.session});

  final MobileSession session;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 22, 20, 24),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF351727), AppColors.plum, Color(0xFF702A37)],
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
                width: 48,
                height: 48,
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: const Color(0xFFFFFBF4),
                  border: Border.all(color: AppColors.brandGold),
                ),
                child: Image.asset(
                  'assets/images/brand/aabhushan_mark.png',
                  fit: BoxFit.contain,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'AABHUSHAN',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        letterSpacing: 1.3,
                      ),
                    ),
                    Text(
                      'JEWELLERY WORKSPACE',
                      style: TextStyle(
                        color: AppColors.brandGold,
                        fontSize: 8,
                        letterSpacing: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(color: Colors.white.withValues(alpha: 0.13)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.userName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  session.userEmail,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white70, fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
