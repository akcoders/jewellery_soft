import 'package:flutkit/jewellery_mobile/screens/inventory_tab.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';

class InventoryScreen extends StatelessWidget {
  const InventoryScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  Widget build(BuildContext context) {
    return InventoryTab(api: api);
  }
}
