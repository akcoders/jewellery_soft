import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class AppSearchBar extends StatefulWidget {
  const AppSearchBar({
    super.key,
    required this.controller,
    required this.onChanged,
    this.hintText = 'Search',
  });

  final TextEditingController controller;
  final ValueChanged<String> onChanged;
  final String hintText;

  @override
  State<AppSearchBar> createState() => _AppSearchBarState();
}

class _AppSearchBarState extends State<AppSearchBar> {
  Timer? _debounce;
  bool _focused = false;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _handleChanged(String value) {
    _debounce?.cancel();
    setState(() {});
    _debounce = Timer(const Duration(milliseconds: 350), () {
      widget.onChanged(value);
    });
  }

  void _submit(String value) {
    _debounce?.cancel();
    widget.onChanged(value);
    FocusScope.of(context).unfocus();
  }

  @override
  Widget build(BuildContext context) {
    return Focus(
      onFocusChange: (focused) => setState(() => _focused = focused),
      child: AnimatedContainer(
        duration: MediaQuery.disableAnimationsOf(context)
            ? Duration.zero
            : const Duration(milliseconds: 180),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Colors.white, AppColors.background],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: _focused
                ? AppColors.brandRed.withValues(alpha: .65)
                : AppColors.border,
            width: _focused ? 1.5 : 1,
          ),
          boxShadow: AppShadows.soft,
        ),
        child: TextField(
          controller: widget.controller,
          onChanged: _handleChanged,
          onSubmitted: _submit,
          textInputAction: TextInputAction.search,
          autocorrect: false,
          decoration: InputDecoration(
            hintText: widget.hintText,
            filled: false,
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.lg,
              vertical: AppSpacing.md,
            ),
            prefixIcon: const Icon(
              LucideIcons.search,
              size: 19,
              color: AppColors.brandRed,
            ),
            suffixIcon: widget.controller.text.isEmpty
                ? null
                : IconButton(
                    tooltip: 'Clear search',
                    icon: const Icon(LucideIcons.x, size: 18),
                    onPressed: () {
                      _debounce?.cancel();
                      widget.controller.clear();
                      widget.onChanged('');
                      setState(() {});
                    },
                  ),
          ),
        ),
      ),
    );
  }
}
