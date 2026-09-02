import 'package:flutkit/jewellery_mobile/screens/dashboard_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/followups_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/inventory_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/notification_center_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/orders_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/performance_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/task_scheduler_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/transactions_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/transaction_create_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter/material.dart';

class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.session, required this.onLogout});

  final MobileSession session;
  final Future<void> Function() onLogout;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  late final MobileApiService _api;
  String _section = 'dashboard';
  int _refreshTick = 0;
  int _notificationCount = 0;

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
    });
  }

  @override
  void dispose() {
    TaskRefreshBus.tick.removeListener(_loadNotificationCount);
    OneSignalService.openedNotification.removeListener(
      _handleOpenedNotification,
    );
    super.dispose();
  }

  void _select(String key) {
    Navigator.of(context).pop();
    _switchSection(key);
  }

  void _switchSection(String key) {
    if (!mounted) return;
    setState(() => _section = key);
    _loadNotificationCount();
  }

  void _handleOpenedNotification() {
    final payload = OneSignalService.consumeOpenedNotification();
    if (!mounted || payload == null) return;

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
    final screen = (payload['screen'] ?? '').toString().toLowerCase();
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
      case 'diamond_issues':
        return TransactionsScreen(
          key: ValueKey('diamond_issues_$_refreshTick'),
          title: 'Diamond Issues',
          loader: _api.fetchDiamondIssues,
          icon: Icons.diamond_outlined,
          accentColor: AppColors.diamond,
          transactionKey: 'diamond_issue',
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
      case 'gold_issues':
        return TransactionsScreen(
          key: ValueKey('gold_issues_$_refreshTick'),
          title: 'Gold Issues',
          loader: _api.fetchGoldIssues,
          icon: Icons.workspace_premium_outlined,
          accentColor: AppColors.gold,
          transactionKey: 'gold_issue',
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
      case 'stone_issues':
        return TransactionsScreen(
          key: ValueKey('stone_issues_$_refreshTick'),
          title: 'Stone Issues',
          loader: _api.fetchStoneIssues,
          icon: Icons.scatter_plot_outlined,
          accentColor: AppColors.stone,
          transactionKey: 'stone_issue',
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
    return Scaffold(
      appBar: AppBar(
        title: Text(_title()),
        actions: [
          ValueListenableBuilder<bool>(
            valueListenable: PwaInstallService.available,
            builder: (context, available, _) {
              if (!available) return const SizedBox.shrink();
              return IconButton(
                tooltip: 'Install Aabhushan ERP',
                onPressed: () async {
                  final installed = await PwaInstallService.promptInstall();
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        installed
                            ? 'Aabhushan ERP installed successfully.'
                            : 'Installation was not completed.',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.install_mobile_outlined),
              );
            },
          ),
          IconButton(
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => NotificationCenterScreen(api: _api),
                ),
              );
              if (mounted) {
                _loadNotificationCount();
              }
            },
            icon: _NotificationBell(count: _notificationCount),
          ),
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'logout') {
                await widget.onLogout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                enabled: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.session.userName,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
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
                    Icon(Icons.logout, size: 18),
                    SizedBox(width: 8),
                    Text('Logout'),
                  ],
                ),
              ),
            ],
            icon: const Icon(Icons.account_circle_outlined),
          ),
        ],
      ),
      drawer: Drawer(
        child: SafeArea(
          child: Column(
            children: [
              _DrawerHeader(session: widget.session),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    _drawerSection('Main'),
                    _drawerItem('dashboard', 'Dashboard', Icons.home_outlined),
                    _drawerItem('orders', 'Orders', Icons.assignment_outlined),
                    _drawerItem(
                      'followups',
                      'Followups',
                      Icons.event_note_outlined,
                    ),
                    _drawerSection('Diamond'),
                    _drawerItem(
                      'diamond_issues',
                      'Diamond Issue',
                      Icons.diamond_outlined,
                    ),
                    _drawerItem(
                      'diamond_returns',
                      'Diamond Return',
                      Icons.diamond_outlined,
                    ),
                    _drawerItem(
                      'diamond_purchases',
                      'Diamond Purchase',
                      Icons.shopping_bag_outlined,
                    ),
                    _drawerSection('Gold'),
                    _drawerItem(
                      'gold_issues',
                      'Gold Issue',
                      Icons.workspace_premium_outlined,
                    ),
                    _drawerItem(
                      'gold_returns',
                      'Gold Return',
                      Icons.workspace_premium_outlined,
                    ),
                    _drawerItem(
                      'gold_purchases',
                      'Gold Purchase',
                      Icons.shopping_bag_outlined,
                    ),
                    _drawerSection('Stone'),
                    _drawerItem(
                      'stone_issues',
                      'Stone Issue',
                      Icons.scatter_plot_outlined,
                    ),
                    _drawerItem(
                      'stone_returns',
                      'Stone Return',
                      Icons.scatter_plot_outlined,
                    ),
                    _drawerItem(
                      'stone_purchases',
                      'Stone Purchase',
                      Icons.shopping_bag_outlined,
                    ),
                    _drawerSection('Utility'),
                    _drawerItem(
                      'inventory',
                      'Inventory',
                      Icons.inventory_2_outlined,
                    ),
                    if (widget.session.canUsePerformance) ...[
                      _drawerItem('tasks', 'My Tasks', Icons.task_alt_outlined),
                      _drawerItem(
                        'performance',
                        'My Performance',
                        Icons.insights_outlined,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 220),
        child: KeyedSubtree(key: ValueKey(_section), child: _body()),
      ),
      floatingActionButton: _fab(context),
    );
  }

  Widget? _fab(BuildContext context) {
    final config = _transactionConfig();
    if (config == null) return null;
    return FloatingActionButton.extended(
      onPressed: () async {
        final created = await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => TransactionCreateScreen(
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
      case 'diamond_issues':
        return {'material': 'diamond', 'action': 'issue'};
      case 'diamond_returns':
        return {'material': 'diamond', 'action': 'return'};
      case 'diamond_purchases':
        return {'material': 'diamond', 'action': 'purchase'};
      case 'gold_issues':
        return {'material': 'gold', 'action': 'issue'};
      case 'gold_returns':
        return {'material': 'gold', 'action': 'return'};
      case 'gold_purchases':
        return {'material': 'gold', 'action': 'purchase'};
      case 'stone_issues':
        return {'material': 'stone', 'action': 'issue'};
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
      case 'diamond_issues':
      case 'diamond_returns':
      case 'diamond_purchases':
        return AppColors.diamond;
      case 'gold_issues':
      case 'gold_returns':
      case 'gold_purchases':
        return AppColors.gold;
      case 'stone_issues':
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
      case 'diamond_issues':
        return 'Diamond Issues';
      case 'diamond_returns':
        return 'Diamond Returns';
      case 'diamond_purchases':
        return 'Diamond Purchases';
      case 'gold_issues':
        return 'Gold Issues';
      case 'gold_returns':
        return 'Gold Returns';
      case 'gold_purchases':
        return 'Gold Purchases';
      case 'stone_issues':
        return 'Stone Issues';
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

  Widget _drawerItem(String key, String label, IconData icon) {
    final selected = _section == key;
    return ListTile(
      selected: selected,
      selectedTileColor: AppColors.brandRed.withValues(alpha: 0.12),
      leading: Icon(icon),
      title: Text(label),
      onTap: () => _select(key),
    );
  }

  Widget _drawerSection(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 6),
      child: Text(
        title.toUpperCase(),
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: AppColors.textSecondary,
          letterSpacing: 0.6,
        ),
      ),
    );
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
        const Icon(Icons.notifications_none_rounded),
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
      padding: const EdgeInsets.all(16),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFB81D24), Color(0xFFD4AF37)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const CircleAvatar(
            radius: 26,
            backgroundColor: Colors.white,
            child: Icon(
              Icons.account_circle_outlined,
              size: 34,
              color: Color(0xFFB81D24),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            session.userName,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            session.userEmail,
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
