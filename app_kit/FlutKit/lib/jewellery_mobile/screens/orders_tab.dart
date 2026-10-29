import 'package:flutkit/jewellery_mobile/screens/orders_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';

/// The compact home entry uses the same accessible order workspace as the shell.
class OrdersTab extends StatelessWidget {
  const OrdersTab({super.key, required this.api});

  final MobileApiService api;

  @override
  Widget build(BuildContext context) => OrdersScreen(api: api);
}
